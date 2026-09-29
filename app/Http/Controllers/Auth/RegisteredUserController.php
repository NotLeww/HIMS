<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserConsent;
use App\Rules\PasswordStandard;
use App\Services\PasswordHistoryService;
use App\Services\Privacy\ConsentService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(
        Request $request,
        PasswordHistoryService $passwords,
        ConsentService $consentService,
    ): RedirectResponse {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', new PasswordStandard],
            'privacy_consent' => ['required', 'accepted'],
        ], [
            'password.confirmed' => PasswordStandard::CONFIRMATION_MESSAGE,
            'privacy_consent.required' => 'You must read and agree to the Privacy Policy to create an account.',
            'privacy_consent.accepted' => 'You must read and agree to the Privacy Policy to create an account.',
        ]);

        $user = $passwords->usePassword(
            null,
            $request->string('password')->toString(),
            fn (string $passwordHash): User => User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $passwordHash,
            ]),
        );

        $consentService->recordConsent(
            user: $user,
            type: UserConsent::TYPE_PRIVACY_POLICY,
            version: config('privacy.policy_version', 'v1.0'),
            isMandatory: true,
            source: 'registration',
            request: $request,
        );

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
