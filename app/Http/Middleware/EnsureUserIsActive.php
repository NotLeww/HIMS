<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account that has been deactivated.
 *
 * Without this, deactivating a user only takes effect the next time they log
 * in — someone already signed in keeps working until their session expires,
 * which defeats the point of the switch. Checking per request means the block
 * lands on their very next click.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'This account has been deactivated. Contact an administrator.']);
        }

        return $next($request);
    }
}
