<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginApprovalRequest;
use App\Models\User;
use App\Services\DeviceSecurity\DeviceSecurityService;
use App\Support\AuthenticationContext;
use App\Support\AuthenticationPanel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class DeviceApprovalController extends Controller
{
    public function __construct(
        private readonly DeviceSecurityService $deviceSecurity,
    ) {}

    /**
     * Display the waiting screen on Device B while awaiting approval from Device A.
     */
    public function waiting(Request $request, LoginApprovalRequest $approvalRequest): View|RedirectResponse
    {
        $challengeHash = $this->deviceSecurity->approvalChallengeHash($request, $approvalRequest);
        $panel = AuthenticationPanel::forGuard($approvalRequest->guard);

        if ($challengeHash === null || ! hash_equals($approvalRequest->challenge_token_hash, $challengeHash)) {
            return redirect()->route($panel->loginRoute())->withErrors([
                'email' => 'Invalid or expired sign-in approval request.',
            ]);
        }

        $remainingSeconds = max(0, $approvalRequest->expires_at->getTimestamp() - now()->getTimestamp());

        return view('auth.device-approval.waiting', [
            'approvalRequest' => $approvalRequest,
            'panel' => $panel,
            'remainingSeconds' => $remainingSeconds,
            'loginUrl' => route($panel->loginRoute()),
            'statusUrl' => route('auth.device-approval.status', $approvalRequest),
            'claimUrl' => route('auth.device-approval.claim', $approvalRequest),
            'cancelUrl' => route('auth.device-approval.cancel', $approvalRequest),
            'resendUrl' => route('auth.device-approval.resend-email', $approvalRequest),
            'pollIntervalMilliseconds' => max(1000, (int) config('auth.device_security.approval_poll_interval_seconds', 3) * 1000),
        ]);
    }

    /**
     * Poll endpoint for Device B waiting screen.
     */
    public function status(Request $request, LoginApprovalRequest $approvalRequest): JsonResponse
    {
        $challengeHash = $this->deviceSecurity->approvalChallengeHash($request, $approvalRequest);

        if ($challengeHash === null || ! hash_equals($approvalRequest->challenge_token_hash, $challengeHash)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $this->deviceSecurity->expireApprovalRequestIfNeeded($approvalRequest);
        $approvalRequest->refresh();

        $remainingSeconds = max(0, $approvalRequest->expires_at->getTimestamp() - now()->getTimestamp());

        return response()->json([
            'status' => $approvalRequest->status,
            'remaining_seconds' => $remainingSeconds,
            'device_name' => $approvalRequest->device_name,
            'message' => match ($approvalRequest->status) {
                LoginApprovalRequest::STATUS_APPROVED => 'Sign-in approved! Transferring session...',
                LoginApprovalRequest::STATUS_REJECTED => 'This sign-in request was rejected by the account owner.',
                LoginApprovalRequest::STATUS_EXPIRED => 'This sign-in request has expired.',
                LoginApprovalRequest::STATUS_CANCELLED => 'This sign-in request was cancelled.',
                LoginApprovalRequest::STATUS_COMPLETED => 'Session established.',
                default => 'Waiting for approval on your active device...',
            },
        ]);
    }

    /**
     * Finalize the approved request and activate Device B's session.
     */
    public function claim(Request $request, LoginApprovalRequest $approvalRequest): JsonResponse|RedirectResponse
    {
        $challengeHash = $this->deviceSecurity->approvalChallengeHash($request, $approvalRequest);
        if ($challengeHash === null) {
            return response()->json([
                'success' => false,
                'message' => 'This sign-in approval session is no longer valid.',
            ], 401);
        }

        try {
            $user = $this->deviceSecurity->claimApprovedRequest(
                $approvalRequest,
                $challengeHash,
                $request,
            );
            $this->deviceSecurity->forgetApprovalChallenge($request, $approvalRequest);

            $panel = AuthenticationPanel::forRole($user->role);
            $redirectUrl = route($panel->dashboardRoute(), absolute: false);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $redirectUrl,
                ]);
            }

            return redirect()->intended($redirectUrl);
        } catch (ConflictHttpException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            $panel = AuthenticationPanel::forGuard($approvalRequest->guard);

            return redirect()->route($panel->loginRoute())->withErrors([
                'email' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Auth::guard($approvalRequest->guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            report($e);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The approved session could not be established. Please sign in again.',
                ], 500);
            }

            $panel = AuthenticationPanel::forGuard($approvalRequest->guard);

            return redirect()->route($panel->loginRoute())->withErrors([
                'email' => 'The approved session could not be established. Please sign in again.',
            ]);
        }
    }

    /**
     * Safely handle old or cached cancellation links without mutating state on GET.
     */
    public function confirmCancellation(Request $request, LoginApprovalRequest $approvalRequest): View|RedirectResponse
    {
        $challengeHash = $this->deviceSecurity->approvalChallengeHash($request, $approvalRequest);
        $panel = AuthenticationPanel::forGuard($approvalRequest->guard);

        if ($challengeHash === null
            || ! hash_equals($approvalRequest->challenge_token_hash, $challengeHash)
            || $approvalRequest->status !== LoginApprovalRequest::STATUS_PENDING
            || $approvalRequest->isExpired()) {
            return redirect()->route($panel->loginRoute());
        }

        return view('auth.device-approval.cancel-confirmation', [
            'approvalRequest' => $approvalRequest,
            'panel' => $panel,
            'cancelUrl' => route('auth.device-approval.cancel', $approvalRequest),
            'waitingUrl' => route('auth.device-approval.waiting', $approvalRequest),
        ]);
    }

    /**
     * Device B cancels its pending request.
     */
    public function cancel(Request $request, LoginApprovalRequest $approvalRequest): RedirectResponse
    {
        $challengeHash = $this->deviceSecurity->approvalChallengeHash($request, $approvalRequest);

        if ($challengeHash !== null && hash_equals($approvalRequest->challenge_token_hash, $challengeHash)) {
            if ($approvalRequest->status === LoginApprovalRequest::STATUS_PENDING) {
                $approvalRequest->update(['status' => LoginApprovalRequest::STATUS_CANCELLED]);
            }

            $this->deviceSecurity->forgetApprovalChallenge($request, $approvalRequest);
        }

        $panel = AuthenticationPanel::forGuard($approvalRequest->guard);

        return redirect()->route($panel->loginRoute());
    }

    /**
     * Render Scenario 2 Email OTP confirmation screen.
     */
    public function verifyEmail(Request $request, LoginApprovalRequest $approvalRequest): View|RedirectResponse
    {
        $challengeHash = $this->deviceSecurity->approvalChallengeHash($request, $approvalRequest);
        $panel = AuthenticationPanel::forGuard($approvalRequest->guard);

        if ($challengeHash === null || ! hash_equals($approvalRequest->challenge_token_hash, $challengeHash)) {
            return redirect()->route($panel->loginRoute())->withErrors([
                'email' => 'Invalid or expired sign-in verification request.',
            ]);
        }

        $user = $approvalRequest->user;
        $maskedEmail = $this->maskEmail($user->email);
        $remainingSeconds = max(0, ($approvalRequest->email_otp_expires_at?->getTimestamp() ?? now()->getTimestamp()) - now()->getTimestamp());

        return view('auth.device-approval.verify-email', [
            'approvalRequest' => $approvalRequest,
            'panel' => $panel,
            'maskedEmail' => $maskedEmail,
            'remainingSeconds' => $remainingSeconds,
            'loginUrl' => route($panel->loginRoute()),
            'verifyUrl' => route('auth.device-approval.verify-email.post', $approvalRequest),
            'resendUrl' => route('auth.device-approval.resend-email', $approvalRequest),
        ]);
    }

    /**
     * Submit Scenario 2 Email OTP.
     */
    public function submitEmailOtp(Request $request, LoginApprovalRequest $approvalRequest): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
            'trust_device' => ['nullable', 'boolean'],
        ], [
            'otp.required' => 'Enter the 6-digit verification code sent to your email.',
            'otp.digits' => 'Enter the complete 6-digit code.',
        ]);

        $throttleKey = 'device-otp:'.$approvalRequest->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'otp' => "Too many incorrect attempts. Please wait {$seconds} seconds.",
            ]);
        }

        $challengeHash = $this->deviceSecurity->approvalChallengeHash($request, $approvalRequest);
        if ($challengeHash === null) {
            throw ValidationException::withMessages([
                'otp' => 'This verification session is no longer valid. Please sign in again.',
            ]);
        }

        try {
            $user = $this->deviceSecurity->verifyEmailConfirmation(
                $approvalRequest,
                $challengeHash,
                $validated['otp'],
                $request,
                $request->boolean('trust_device', true),
            );
            $this->deviceSecurity->forgetApprovalChallenge($request, $approvalRequest);

            RateLimiter::clear($throttleKey);

            $panel = AuthenticationPanel::forRole($user->role);
            $redirectUrl = route($panel->dashboardRoute(), absolute: false);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'redirect_url' => $redirectUrl,
                ]);
            }

            return redirect()->intended($redirectUrl);
        } catch (ValidationException $e) {
            RateLimiter::hit($throttleKey, 300);
            throw $e;
        } catch (Throwable $e) {
            Auth::guard($approvalRequest->guard)->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            report($e);

            return redirect()->route(AuthenticationPanel::forGuard($approvalRequest->guard)->loginRoute())
                ->withErrors(['email' => 'Device verification could not be completed. Please sign in again.']);
        }
    }

    /**
     * Resend Scenario 2 Email OTP.
     */
    public function resendEmailOtp(Request $request, LoginApprovalRequest $approvalRequest): RedirectResponse
    {
        $challengeHash = $this->deviceSecurity->approvalChallengeHash($request, $approvalRequest);

        if ($challengeHash === null) {
            return redirect()->route(AuthenticationPanel::forGuard($approvalRequest->guard)->loginRoute())
                ->withErrors(['email' => 'This verification session is no longer valid. Please sign in again.']);
        }

        $throttleKey = 'resend-device-approval:'.$approvalRequest->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()->withErrors([
                'email' => "Please wait {$seconds} seconds before resending the approval email.",
            ]);
        }

        $this->deviceSecurity->resendApprovalEmail($approvalRequest, $challengeHash);
        RateLimiter::hit($throttleKey, (int) config('auth.device_security.approval_resend_cooldown_seconds', 60));

        return back()->with('status', 'The sign-in approval email was resent.');
    }

    /**
     * Check if the authenticated user has any pending login approval requests (polled by active Device A).
     */
    public function checkPending(Request $request): JsonResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? 'web';
        $user = $request->user($guard);

        if (! $user instanceof User) {
            return response()->json(['has_pending' => false]);
        }

        $pending = LoginApprovalRequest::query()
            ->where('user_id', $user->id)
            ->where('status', LoginApprovalRequest::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->latest('requested_at')
            ->first();

        if (! $pending) {
            return response()->json(['has_pending' => false]);
        }

        return response()->json([
            'has_pending' => true,
            'request' => [
                'id' => $pending->id,
                'device_name' => $pending->device_name ?: 'Unknown Device',
                'browser' => $pending->browser ?: 'Web Browser',
                'platform' => $pending->platform ?: 'Unknown Platform',
                'ip_address' => $pending->ip_address ?: 'Unknown IP',
                'requested_at' => $pending->requested_at->timezone(config('app.timezone', 'UTC'))->format('h:i:s A'),
                'time_ago' => $pending->requested_at->diffForHumans(),
                'expires_in_seconds' => max(0, $pending->expires_at->getTimestamp() - now()->getTimestamp()),
                'approve_url' => route('auth.device-approvals.approve', $pending),
                'reject_url' => route('auth.device-approvals.reject', $pending),
            ],
        ]);
    }

    /**
     * Device A approves pending Device B request.
     */
    public function approve(Request $request, LoginApprovalRequest $approvalRequest): JsonResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? 'web';
        $user = $request->user($guard);
        abort_unless($user instanceof User, 401);

        try {
            $this->deviceSecurity->approveRequest(
                $approvalRequest,
                $user,
                $request->boolean('trust_device'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Sign-in request approved. Your session on this device has ended.',
                'redirect_url' => route(AuthenticationContext::loginRoute($guard)),
            ]);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'The sign-in request could not be approved. Please try again.',
            ], 500);
        }
    }

    public function reviewEmailDecision(LoginApprovalRequest $approvalRequest, string $decision): View
    {
        $available = $approvalRequest->status === LoginApprovalRequest::STATUS_PENDING
            && ! $approvalRequest->isExpired();

        $confirmUrl = URL::temporarySignedRoute(
            'auth.device-approval.email.confirm',
            $approvalRequest->expires_at,
            ['approvalRequest' => $approvalRequest->id, 'decision' => $decision],
        );

        return view('auth.device-approval.email-decision', compact(
            'approvalRequest',
            'decision',
            'available',
            'confirmUrl',
        ) + [
            'panel' => AuthenticationPanel::forGuard($approvalRequest->guard),
        ]);
    }

    public function confirmEmailDecision(LoginApprovalRequest $approvalRequest, string $decision): View
    {
        try {
            if ($decision === 'deny') {
                $this->deviceSecurity->rejectRequest($approvalRequest, $approvalRequest->user);
                $message = 'The sign-in request was denied.';
            } else {
                $trustDevice = $decision === 'approve-trust';
                $this->deviceSecurity->approveRequest($approvalRequest, $approvalRequest->user, $trustDevice);
                $message = $trustDevice
                    ? 'The sign-in was approved and the requesting browser may become trusted.'
                    : 'The sign-in was approved for this attempt only.';
            }

            return view('auth.device-approval.email-result', [
                'success' => true,
                'message' => $message,
            ]);
        } catch (ConflictHttpException $e) {
            return view('auth.device-approval.email-result', [
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Device A rejects pending Device B request.
     */
    public function reject(Request $request, LoginApprovalRequest $approvalRequest): JsonResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? 'web';
        $user = $request->user($guard);
        abort_unless($user instanceof User, 401);

        try {
            $this->deviceSecurity->rejectRequest($approvalRequest, $user);

            return response()->json([
                'success' => true,
                'message' => 'Sign-in request was rejected. The suspicious device has been temporarily blocked for '
                    .config('auth.device_security.device_rejection_cooldown_minutes', 15).' minutes.',
                'security_notice' => 'This request was blocked. If you did not attempt to sign in, consider changing your password.',
            ]);
        } catch (ConflictHttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'The sign-in request could not be rejected. Please try again.',
            ], 500);
        }
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, 1);

        return $visible.str_repeat('*', max(3, mb_strlen($local) - 1)).'@'.$domain;
    }
}
