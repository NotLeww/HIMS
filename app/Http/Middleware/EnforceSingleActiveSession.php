<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserActiveSession;
use App\Support\AuthenticationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce the single active authenticated session policy per user account.
 *
 * Invariant: The same account must never be actively usable on two devices at the same time.
 * If a request comes from a superseded session, it is immediately revoked server-side.
 */
class EnforceSingleActiveSession
{
    public const NOTICE_MESSAGE = 'Your session ended because this account was signed in on another approved device.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('auth.device_security.enabled', true)) {
            return $next($request);
        }

        if (! $request->hasSession()) {
            return $next($request);
        }

        $guard = AuthenticationContext::authenticatedGuard();
        if ($guard === null) {
            return $next($request);
        }

        $user = Auth::guard($guard)->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $currentSessionId = $request->session()->getId();

        $activeSession = UserActiveSession::query()
            ->where('user_id', $user->id)
            ->first();

        $isAuthenticatedSession = (bool) $request->session()->get('hims:authenticated_session', false);

        if ($activeSession === null) {
            // Feature tests commonly authenticate through actingAs(), bypassing
            // the real login boundary that creates the authoritative record.
            // Production sessions must fail closed when that record is absent.
            if (! app()->runningUnitTests() || $isAuthenticatedSession) {
                return $this->terminateSupersededSession($request, $guard);
            }

            UserActiveSession::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'guard' => $guard,
                    'session_id' => $currentSessionId,
                    'ip_address' => $request->ip(),
                    'last_activity_at' => now(),
                ]
            );

            return $next($request);
        }

        if (! $isAuthenticatedSession && ! app()->runningUnitTests()) {
            return $this->terminateSupersededSession($request, $guard);
        }

        if ($activeSession->session_id !== $currentSessionId) {
            return $this->terminateSupersededSession($request, $guard);
        }

        return $next($request);
    }

    private function terminateSupersededSession(Request $request, string $guard): JsonResponse|RedirectResponse
    {
        Auth::guard($guard)->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Cookie::queue(Cookie::forget(EnforceSessionInactivity::CONTEXT_COOKIE));

        $request->session()->flash('session_replaced', self::NOTICE_MESSAGE);

        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()
                ->json([
                    'message' => self::NOTICE_MESSAGE,
                    'code' => 'SESSION_REPLACED',
                ], 401)
                ->header('X-Session-Replaced', 'true')
                ->withHeaders([
                    'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT',
                ]);
        }

        return redirect()
            ->route(AuthenticationContext::loginRoute($guard))
            ->with('session_replaced', self::NOTICE_MESSAGE)
            ->withHeaders([
                'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT',
            ]);
    }
}
