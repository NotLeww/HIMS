<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuthenticationPanel;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Mark the account named by the signed link as verified.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = User::query()->findOrFail($request->route('id'));

        abort_unless(hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $panel = AuthenticationPanel::forRole($user->role);
        $message = $user->isActive()
            ? 'Email verified. Your HIMS account is now activated. You may sign in.'
            : 'Email verified. An administrator must reactivate your account before you can sign in.';

        return redirect()->route($panel->loginRoute())->with('status', $message);
    }
}
