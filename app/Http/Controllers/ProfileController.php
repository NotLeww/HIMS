<?php

namespace App\Http\Controllers;

use App\Enums\AuthenticatorSecretStatus;
use App\Enums\Permission;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\AuthenticatorSecretService;
use App\Services\AuthenticatorSetupService;
use App\Services\DeviceSecurity\DeviceSecurityService;
use App\Services\FileContentValidator;
use App\Services\Privacy\ConsentService;
use App\Services\Sms\SmsOtpDelivery;
use App\Services\UserAccountService;
use App\Support\AuditBrowserLocation;
use App\Support\AuthenticationContext;
use App\Support\MfaSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProfileController extends Controller
{
    /** @var list<string> */
    private const AVATAR_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/x-ms-bmp',
    ];

    /**
     * Display the user's profile form.
     */
    public function edit(
        Request $request,
        AuthenticatorSetupService $setup,
        AuthenticatorSecretService $authenticatorSecrets,
        ConsentService $consentService,
    ): View {
        $user = $request->user();
        $authenticatorStatus = $user instanceof User
            ? $authenticatorSecrets->status($user)
            : AuthenticatorSecretStatus::Missing;
        $recoveryRequired = $authenticatorStatus === AuthenticatorSecretStatus::Invalid;

        return view('profile.edit', [
            'user' => $user,
            'authenticatorRecoveryRequired' => $recoveryRequired,
            'authenticatorSetup' => $user instanceof User
                && (! $user->authenticatorMfaEnabled() || $recoveryRequired)
                ? $setup->details($request, $user)
                : null,
            'activeSession' => $user instanceof User ? $user->activeSession : null,
            'trustedDevices' => $user instanceof User
                ? $user->trustedDevices()
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->orderByDesc('last_used_at')
                    ->get()
                : collect(),
            'consentSummary' => $user instanceof User
                ? $consentService->getUserConsentSummary($user)
                : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->safe()->only([
            'surname',
            'first_name',
            'middle_name',
            'email',
        ]));

        $emailChanged = $request->user()->isDirty('email');

        if ($emailChanged) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        if ($emailChanged) {
            $request->user()->sendEmailVerificationNotification();
        }

        $request->session()->put(
            'profile_success',
            $emailChanged
                ? 'Email updated. Check your new address to verify it.'
                : 'Profile updated successfully.'
        );

        return Redirect::route('profile.edit');
    }

    public function updateMfa(Request $request): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);

        abort_unless($user instanceof User && $user->isAdministrator(), 403);

        $validated = $request->validate([
            'mfa_enabled' => ['required', 'boolean'],
            'current_password' => ['required', 'current_password:'.$guard],
        ]);

        $enabled = (bool) $validated['mfa_enabled'];
        if ($enabled === (bool) $user->mfa_enabled) {
            return Redirect::route('profile.edit');
        }

        $user->forceFill(['mfa_enabled' => $enabled])->save();
        if ($enabled) {
            $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
            MfaSession::mark($request, $user, $guard);
        }
        $request->session()->put(
            'mfa_success',
            $enabled
                ? 'Multi-factor authentication is now ON. A code will be required on your next login.'
                : 'Multi-factor authentication is now OFF. Future logins will use your password only.',
        );

        return Redirect::route('profile.edit');
    }

    public function updateSmsMfa(Request $request, SmsOtpDelivery $sms): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);
        abort_unless($user instanceof User, 401);

        $validated = $request->validateWithBag('smsMfa', [
            'sms_mfa_enabled' => ['required', 'boolean'],
            'current_password' => ['required', 'current_password:'.$guard],
        ]);

        $enabled = (bool) $validated['sms_mfa_enabled'];
        if ($enabled === (bool) $user->sms_mfa_enabled) {
            return Redirect::route('profile.edit');
        }

        if ($enabled && preg_match('/^09[0-9]{9}$/D', (string) $user->phone) !== 1) {
            return Redirect::route('profile.edit')->withErrors([
                'sms_mfa_enabled' => 'A valid registered 11-digit mobile number is required. Ask an administrator to update your contact number.',
            ], 'smsMfa');
        }

        if ($enabled && ! $sms->available()) {
            return Redirect::route('profile.edit')->withErrors([
                'sms_mfa_enabled' => 'SMS verification is currently unavailable. Please contact an administrator.',
            ], 'smsMfa');
        }

        $user->forceFill([
            'sms_mfa_enabled' => $enabled,
            'sms_mfa_phone' => $enabled ? $user->phone : null,
        ])->save();

        if ($enabled) {
            MfaSession::mark($request, $user, $guard);
        }

        return Redirect::route('profile.edit')->with('sms_mfa_success', $enabled
            ? 'SMS authentication is on. Future sign-ins will require a code sent to your registered mobile number.'
            : 'SMS authentication is off.');
    }

    public function updateSessionTimeoutReminder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'session_timeout_reminder_enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['session_timeout_reminder_enabled'];
        $request->user()->forceFill([
            'session_timeout_reminder_enabled' => $enabled,
        ])->save();

        $request->session()->put(
            'session_reminder_success',
            $enabled
                ? 'Session timeout reminders are now ON.'
                : 'Session timeout reminders are now OFF. Automatic logout remains active.',
        );

        return Redirect::route('profile.edit');
    }

    public function storeAuditLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);

        AuditBrowserLocation::store($request, $validated);

        return response()->json(['stored' => true]);
    }

    /**
     * Update the user's profile picture.
     */
    public function updateAvatar(Request $request, FileContentValidator $fileContentValidator): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'avatar' => [
                'required',
                'file',
                'max:3072', // 3 MB max
            ],
        ], [
            'avatar.required' => 'Please select an image file to upload.',
            'avatar.file' => 'The uploaded file is not valid.',
            'avatar.max' => 'The profile picture must not exceed 3 MB.',
        ]);

        $validator->after(function ($validator) use ($request, $fileContentValidator) {
            $file = $request->file('avatar');
            if (! $file || ! $file->isValid()) {
                return;
            }

            try {
                $fileContentValidator->validate($file, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
            } catch (\InvalidArgumentException $exception) {
                $validator->errors()->add('avatar', $exception->getMessage());

                return;
            }

            // Image integrity check: verify decodable image headers and dimensions
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
                $validator->errors()->add('avatar', 'The uploaded file is corrupted or not a valid image.');

                return;
            }

            if (! in_array($imageInfo['mime'], self::AVATAR_MIME_TYPES, true)) {
                $validator->errors()->add('avatar', 'The uploaded image must be a valid JPG, PNG, GIF, WebP, or BMP format.');

                return;
            }

            if ($imageInfo[0] > 4096 || $imageInfo[1] > 4096) {
                $validator->errors()->add('avatar', 'The image dimensions cannot exceed 4096x4096 pixels.');

                return;
            }
        });

        if ($validator->fails()) {
            return Redirect::route('profile.edit')
                ->withErrors($validator)
                ->withInput();
        }

        $user = $request->user();
        $file = $request->file('avatar');
        $oldPath = $user->avatar_path;
        $mime = getimagesize($file->getRealPath())['mime'];
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp', 'image/x-ms-bmp' => 'bmp',
        };

        // Store the replacement before deleting the old file so a storage
        // failure cannot leave the account pointing at a missing avatar.
        $path = $file->storeAs('avatars', Str::uuid().'.'.$extension, 'public');

        if (! is_string($path)) {
            return Redirect::route('profile.edit')
                ->withErrors(['avatar' => 'The profile picture could not be saved. Please try again.']);
        }

        try {
            $user->forceFill(['avatar_path' => $path])->save();
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $request->session()->put('avatar_success', 'Profile picture updated successfully.');

        return Redirect::route('profile.edit');
    }

    /**
     * Remove the user's profile picture.
     */
    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => null])->save();

        $request->session()->put('avatar_success', 'Profile picture removed. Your initials avatar is now active.');

        return Redirect::route('profile.edit');
    }

    /**
     * Safely stream the user's profile picture.
     */
    public function showAvatar(Request $request, User $user): BinaryFileResponse
    {
        $actor = $request->user();
        abort_unless(
            $actor instanceof User
                && (
                    $actor->is($user)
                    || (
                        $actor->hasPermission(Permission::ManageUsers)
                        && app(UserAccountService::class)->canManage($actor, $user)
                    )
                ),
            403,
        );

        abort_unless($user->avatar_path && Storage::disk('public')->exists($user->avatar_path), 404);

        $path = Storage::disk('public')->path($user->avatar_path);
        $mime = Storage::disk('public')->mimeType($user->avatar_path) ?? 'image/jpeg';

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Revoke a recognized trusted device.
     */
    public function destroyTrustedDevice(
        Request $request,
        TrustedDevice $trustedDevice,
        DeviceSecurityService $deviceSecurity,
    ): RedirectResponse {
        $guard = AuthenticationContext::authenticatedGuard() ?? AuthenticationContext::WEB_GUARD;
        $user = $request->user($guard);
        abort_unless($user instanceof User && $trustedDevice->user_id === $user->id, 403);

        $deviceSecurity->revokeTrustedDevice($trustedDevice, $user);

        return Redirect::route('profile.edit')->with('device_success', "Trusted device '{$trustedDevice->display_name}' was revoked.");
    }
}
