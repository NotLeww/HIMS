<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnforceSessionInactivity;
use App\Http\Requests\Auth\LoginRequest;
use App\Notifications\LoginMfaOtp;
use App\Services\DeviceSecurity\DeviceSecurityService;
use App\Services\LoginLockoutService;
use App\Services\LoginMfaService;
use App\Services\PasswordExpirationService;
use App\Services\Sms\SmsMfaChallengeService;
use App\Services\Sms\SmsOtpDelivery;
use App\Support\AuthenticationContext;
use App\Support\AuthenticationPanel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request, LoginLockoutService $lockouts, LoginMfaService $mfa): View
    {
        if ($mfa->isExpired($request, AuthenticationContext::WEB_GUARD)) {
            $mfa->clear($request);
        }

        return view('auth.login', [
            'loginRestriction' => $lockouts->sessionRestriction($request, AuthenticationContext::WEB_GUARD),
            'supplierPortal' => $request->routeIs('supplier.login'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(
        LoginRequest $request,
        LoginMfaService $mfa,
        PasswordExpirationService $expiration,
        SmsMfaChallengeService $sms,
        DeviceSecurityService $deviceSecurity,
    ): RedirectResponse {
        $user = $request->validateCredentials();
        $panel = AuthenticationPanel::forRole($user->role);

        $deviceResult = $deviceSecurity->handleLoginAttempt(
            $request,
            $user,
            AuthenticationContext::WEB_GUARD,
            $request->boolean('remember'),
        );

        if ($deviceResult->isWaitingApproval()) {
            $deviceSecurity->rememberApprovalChallenge($request, $deviceResult->approvalRequest, $deviceResult->token);

            return redirect()->route('auth.device-approval.waiting', [
                'approvalRequest' => $deviceResult->approvalRequest->id,
            ]);
        }

        if ($deviceResult->requiresEmailConfirmation()) {
            $deviceSecurity->rememberApprovalChallenge($request, $deviceResult->approvalRequest, $deviceResult->token);

            return redirect()->route('auth.device-approval.verify-email', [
                'approvalRequest' => $deviceResult->approvalRequest->id,
            ]);
        }

        if ($user->authenticatorMfaEnabled()) {
            $pendingUser = $mfa->pendingUser($request, AuthenticationContext::WEB_GUARD);

            if ($pendingUser?->is($user)
                && $mfa->challengeUsesAuthenticator($request, AuthenticationContext::WEB_GUARD)
                && ! $mfa->isExpired($request, AuthenticationContext::WEB_GUARD)) {
                return redirect()->route($panel->loginMfaRoute());
            }

            $request->session()->regenerate();
            $mfa->issueAuthenticator(
                $request,
                $user,
                AuthenticationContext::WEB_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            return redirect()->route($panel->loginMfaRoute());
        }

        if ($user->mfa_enabled) {
            $pendingUser = $mfa->pendingUser($request, AuthenticationContext::WEB_GUARD);

            if ($pendingUser?->is($user) && $mfa->resendAvailableIn($request, AuthenticationContext::WEB_GUARD) > 0) {
                return redirect()->route($panel->loginMfaRoute())
                    ->with('status', 'A verification code was recently sent.');
            }

            $request->session()->regenerate();
            $otp = $mfa->issue(
                $request,
                $user,
                AuthenticationContext::WEB_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            try {
                $user->notify(new LoginMfaOtp($otp, $mfa->expiresInMinutes()));
            } catch (Throwable $exception) {
                $mfa->clear($request);
                Log::error('Login MFA email could not be sent.', [
                    'user_id' => $user->getKey(),
                    'guard' => AuthenticationContext::WEB_GUARD,
                    'exception' => $exception::class,
                ]);

                return back()->withErrors([
                    'email' => 'We could not send a verification code. Please try again.',
                ])->onlyInput('email');
            }

            return redirect()->route($panel->loginMfaRoute());
        }

        if ($user->sms_mfa_enabled) {
            $pendingUser = $mfa->pendingUser($request, AuthenticationContext::WEB_GUARD);
            if ($pendingUser?->is($user) && $mfa->challengeMethod($request, AuthenticationContext::WEB_GUARD) === LoginMfaService::METHOD_SMS) {
                return redirect()->route($panel->loginMfaRoute());
            }

            $request->session()->regenerate();
            $status = $sms->begin($request, $user, AuthenticationContext::WEB_GUARD, $request->boolean('remember'), $request->progressiveThrottleKey());

            return $status === SmsOtpDelivery::SENT
                ? redirect()->route($panel->loginMfaRoute())
                : back()->withErrors(['email' => $status === SmsOtpDelivery::RATE_LIMITED
                    ? 'Too many SMS code requests. Please wait before trying again.'
                    : 'We could not send a verification code. Please try again.'])->onlyInput('email');
        }

        if ($user->passwordHasExpired()) {
            $request->session()->regenerate();
            $expiration->begin(
                $request,
                $user,
                AuthenticationContext::WEB_GUARD,
                $request->boolean('remember'),
                $request->progressiveThrottleKey(),
            );

            return redirect()->route($panel->expiredPasswordRoute());
        }

        $request->login($user);

        $request->session()->regenerate();
        $deviceSecurity->activateSession(
            $user,
            AuthenticationContext::WEB_GUARD,
            $request,
            $deviceSecurity->getValidTrustedDevice($user, $request),
        );
        $request->session()->put(
            EnforceSessionInactivity::LAST_ACTIVITY_AT,
            now()->getTimestamp(),
        );

        $destination = AuthenticationPanel::forRole($user->role)->dashboardRoute();

        return redirect()->intended(route($destination, absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = Auth::guard($guard)->user();

        if ($user) {
            app(DeviceSecurityService::class)->clearActiveSession($user);
        }

        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(EnforceSessionInactivity::CONTEXT_COOKIE));

        $loginRoute = $user?->role?->isSupplier() ? 'supplier.login' : 'login';

        return redirect()
            ->route($loginRoute)
            ->withHeaders([
                'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT',
            ]);
    }

    /**
     * End a verified idle browser session and carry a one-time login notice.
     *
     * The route is signed because it is reached by the browser's inactivity
     * timer with a top-level navigation rather than a form submission. A stale
     * timer after manual logout cannot create a timeout notice.
     */
    public function expired(Request $request): RedirectResponse
    {
        if (! Auth::guard(AuthenticationContext::WEB_GUARD)->check()
            || ! EnforceSessionInactivity::hasExceededInactivityLimit(
                $request,
                AuthenticationContext::WEB_GUARD,
            )) {
            return redirect()
                ->route('login')
                ->withHeaders([
                    'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT',
                ]);
        }

        $user = Auth::guard(AuthenticationContext::WEB_GUARD)->user();
        if ($user) {
            app(DeviceSecurityService::class)->clearActiveSession($user);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(EnforceSessionInactivity::CONTEXT_COOKIE));

        return redirect()
            ->route('login')
            ->with('session_timeout', true)
            ->withHeaders([
                'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT',
            ]);
    }
}
