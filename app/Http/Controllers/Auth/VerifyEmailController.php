<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountActivationService;
use App\Support\AuthenticationPanel;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    /**
     * Mark the account named by the signed link as verified.
     */
    public function __invoke(Request $request, AccountActivationService $activation): RedirectResponse
    {
        $user = User::query()->findOrFail($request->route('id'));

        abort_unless(hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification())), 403);

        if ($user->status === UserStatus::Cancelled) {
            return redirect()->route(AuthenticationPanel::forRole($user->role)->loginRoute())->with(
                'status',
                AccountActivationService::CANCELLED_MESSAGE,
            );
        }

        if ($user->isPendingActivation() && blank($user->getAuthPassword())) {
            $verified = $activation->verifyEmailLink($user);

            if ($verified !== null) {
                $request->session()->regenerate();
                $request->session()->forget('account_activation');
                $request->session()->put('account_activation.verified_user_id', $verified->getKey());

                return redirect()->route('activation.password')
                    ->with('status', 'Email verified. Create your password to finish activating your HIMS account.');
            }
        }

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
