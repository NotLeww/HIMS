<?php

namespace App\Services\DeviceSecurity;

use App\Enums\AuditAction;
use App\Models\DeviceLoginCooldown;
use App\Models\LoginApprovalRequest;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserActiveSession;
use App\Notifications\NewDeviceApprovedNotification;
use App\Notifications\NewDeviceLoginAttemptNotification;
use App\Notifications\SessionTakeoverNotification;
use App\Notifications\SuspiciousLoginBlockedNotification;
use App\Services\AuditDeviceContextResolver;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class DeviceSecurityService
{
    private const CHALLENGE_SESSION_PREFIX = 'auth.device_approval.challenge.';

    public function __construct(
        private readonly AuditDeviceContextResolver $deviceResolver,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Resolve a formatted device name and summary from request user-agent.
     *
     * @return array{device_name: string, browser: string, operating_system: string, device_type: string}
     */
    public function resolveDeviceContext(Request $request): array
    {
        $context = $this->deviceResolver->resolve($request);

        $browser = $context['browser'] ?: 'Unknown Browser';
        $os = $context['operating_system'] ?: 'Unknown OS';
        $type = $context['device_type'] ?: 'Desktop';

        $deviceName = "{$browser} on {$os}";

        return [
            'device_name' => $deviceName,
            'browser' => $browser,
            'operating_system' => $os,
            'device_type' => $type,
        ];
    }

    /**
     * Calculate stable client device fingerprint from IP and User Agent.
     */
    public function resolveDeviceFingerprint(Request $request): string
    {
        return hash('sha256', ($request->ip() ?? '0.0.0.0').'|'.($request->userAgent() ?? ''));
    }

    /**
     * Check if a device is currently under a temporary rejection cooldown.
     *
     * @throws ValidationException
     */
    public function ensureDeviceIsNotBlocked(User $user, Request $request): void
    {
        $fingerprint = $this->resolveDeviceFingerprint($request);
        $activeCooldown = DeviceLoginCooldown::query()
            ->where('user_id', $user->id)
            ->where('blocked_until', '>', now())
            ->where('device_fingerprint', $fingerprint)
            ->orderByDesc('blocked_until')
            ->first();

        if ($activeCooldown) {
            $minutes = max(1, (int) ceil(now()->diffInSeconds($activeCooldown->blocked_until) / 60));

            throw ValidationException::withMessages([
                'email' => "Sign-in from this device is temporarily blocked due to a rejected request. Please try again in {$minutes} ".Str::plural('minute', $minutes).'.',
            ]);
        }
    }

    /**
     * Check if request carries a recognized, unexpired, unrevoked trusted-device cookie.
     */
    public function getValidTrustedDevice(User $user, Request $request): ?TrustedDevice
    {
        $cookieName = (string) config('auth.device_security.cookie_name', 'hims_trusted_device');
        $rawCookie = $request->cookie($cookieName);

        if (! is_string($rawCookie) || trim($rawCookie) === '') {
            return null;
        }

        $tokenHash = hash('sha256', trim($rawCookie));

        $trusted = TrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($trusted) {
            $trusted->forceFill([
                'last_used_at' => now(),
                'ip_address' => $request->ip(),
            ])->saveQuietly();

            return $trusted;
        }

        return null;
    }

    /**
     * Determine security action for an incoming valid-credential attempt.
     *
     * @throws ValidationException
     */
    public function handleLoginAttempt(
        Request $request,
        User $user,
        string $guard,
        bool $remember,
    ): DeviceLoginResult {
        if (! (bool) config('auth.device_security.enabled', true)) {
            return DeviceLoginResult::trusted();
        }

        // 1. Enforce cooldown on suspicious/rejected devices (does NOT lock the account)
        $this->ensureDeviceIsNotBlocked($user, $request);

        // 2. Check if this is a previously trusted recognized device
        $trustedDevice = $this->getValidTrustedDevice($user, $request);

        if ($trustedDevice !== null) {
            return DeviceLoginResult::trusted();
        }

        // 3. Every untrusted device requires an explicit owner decision.
        $deviceContext = $this->resolveDeviceContext($request);
        $fingerprint = $this->resolveDeviceFingerprint($request);
        $lifetimeMinutes = (int) config('auth.device_security.approval_request_lifetime_minutes', 5);

        $requestLimitKey = 'device-approval-create:'.$user->id.':'.$fingerprint;
        $maxRequests = (int) config('auth.device_security.approval_creation_max_attempts', 3);
        $decaySeconds = (int) config('auth.device_security.approval_creation_decay_seconds', 60);

        if (RateLimiter::tooManyAttempts($requestLimitKey, $maxRequests)) {
            $seconds = RateLimiter::availableIn($requestLimitKey);

            throw ValidationException::withMessages([
                'email' => "Too many sign-in verification requests from this device. Please wait {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($requestLimitKey, $decaySeconds);

        $challengeToken = Str::random(64);
        $approvalRequest = $this->createPendingApprovalRequest($user, [
            'user_id' => $user->id,
            'guard' => $guard,
            'remember' => $remember,
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'ip_address' => $request->ip(),
            'device_fingerprint' => $fingerprint,
            'device_name' => $deviceContext['device_name'],
            'platform' => $deviceContext['operating_system'],
            'browser' => $deviceContext['browser'],
            'requested_at' => now(),
            'expires_at' => now()->addMinutes($lifetimeMinutes),
        ]);

        $this->auditLogger->log(
            AuditAction::LoginApprovalRequested,
            null,
            "Sign-in approval requested for an unrecognized device: {$deviceContext['device_name']}.",
            $user,
            'Account',
            source: 'system',
        );

        if ((bool) config('auth.device_security.approval_emails_enabled', false)) {
            try {
                $user->notify(new NewDeviceLoginAttemptNotification(
                    $approvalRequest,
                ));

                $this->auditLogger->log(
                    AuditAction::LoginApprovalEmailSent,
                    null,
                    'Sent sign-in approval email.',
                    $user,
                    'Account',
                    source: 'system',
                );
            } catch (Throwable $e) {
                Log::warning('Login approval email could not be sent.', [
                    'user_id' => $user->getKey(),
                    'exception' => $e::class,
                ]);

                $approvalRequest->update(['status' => LoginApprovalRequest::STATUS_CANCELLED]);

                throw ValidationException::withMessages([
                    'email' => 'We could not send the sign-in approval email. Please try again.',
                ]);
            }
        }

        return DeviceLoginResult::waitingApproval($approvalRequest, $challengeToken);
    }

    /**
     * Device A approves Device B login request.
     *
     * @throws ConflictHttpException
     */
    public function approveRequest(LoginApprovalRequest $request, User $actor, bool $trustDevice = false): void
    {
        if ($this->expireApprovalRequestIfNeeded($request)) {
            throw new ConflictHttpException('This sign-in request has expired.');
        }

        DB::transaction(function () use ($request, $actor, $trustDevice) {
            User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();

            /** @var LoginApprovalRequest|null $locked */
            $locked = LoginApprovalRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->user_id !== $actor->id) {
                throw new ConflictHttpException('Login approval request not found or not owned by user.');
            }

            if ($locked->isExpired()) {
                throw new ConflictHttpException('This sign-in request has expired.');
            }

            if ($locked->status !== LoginApprovalRequest::STATUS_PENDING) {
                throw new ConflictHttpException("This request has already been {$locked->status}.");
            }

            $locked->update([
                'status' => LoginApprovalRequest::STATUS_APPROVED,
                'approved_at' => now(),
                'responded_by_user_id' => $actor->id,
                'trust_device_on_approval' => $trustDevice,
            ]);

            LoginApprovalRequest::query()
                ->where('user_id', $actor->id)
                ->where('id', '!=', $locked->getKey())
                ->where('status', LoginApprovalRequest::STATUS_PENDING)
                ->update(['status' => LoginApprovalRequest::STATUS_CANCELLED]);

            // Revoke Device A's active session immediately server-side
            UserActiveSession::query()
                ->where('user_id', $actor->id)
                ->update(['session_id' => 'superseded_'.Str::random(24)]);

            $this->auditLogger->log(
                $trustDevice ? AuditAction::LoginApprovalApprovedAndTrusted : AuditAction::LoginApprovalApprovedOnce,
                $actor,
                ($trustDevice ? 'Approved and trusted' : 'Approved once')." sign-in for {$locked->device_name}. Previous active session revoked.",
                $locked->user,
                'Account',
            );

        });
    }

    public function rememberApprovalChallenge(Request $request, LoginApprovalRequest $approvalRequest, string $token): void
    {
        $request->session()->put(self::approvalChallengeSessionKey($approvalRequest), hash('sha256', $token));
    }

    public function approvalChallengeHash(Request $request, LoginApprovalRequest $approvalRequest): ?string
    {
        $hash = $request->session()->get(self::approvalChallengeSessionKey($approvalRequest));

        return is_string($hash) && strlen($hash) === 64 ? $hash : null;
    }

    public function forgetApprovalChallenge(Request $request, LoginApprovalRequest $approvalRequest): void
    {
        $request->session()->forget(self::approvalChallengeSessionKey($approvalRequest));
    }

    public static function approvalChallengeSessionKey(LoginApprovalRequest|string $approvalRequest): string
    {
        $id = $approvalRequest instanceof LoginApprovalRequest ? $approvalRequest->getKey() : $approvalRequest;

        return self::CHALLENGE_SESSION_PREFIX.$id;
    }

    /**
     * Device A rejects Device B login request.
     *
     * @throws ConflictHttpException
     */
    public function rejectRequest(LoginApprovalRequest $request, User $actor): void
    {
        if ($this->expireApprovalRequestIfNeeded($request)) {
            throw new ConflictHttpException('This sign-in request has expired.');
        }

        DB::transaction(function () use ($request, $actor) {
            /** @var LoginApprovalRequest|null $locked */
            $locked = LoginApprovalRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->user_id !== $actor->id) {
                throw new ConflictHttpException('Login approval request not found or not owned by user.');
            }

            if ($locked->isExpired()) {
                throw new ConflictHttpException('This sign-in request has expired.');
            }

            if ($locked->status !== LoginApprovalRequest::STATUS_PENDING) {
                throw new ConflictHttpException("This request has already been {$locked->status}.");
            }

            $locked->update([
                'status' => LoginApprovalRequest::STATUS_REJECTED,
                'rejected_at' => now(),
                'responded_by_user_id' => $actor->id,
            ]);

            $cooldownMinutes = (int) config('auth.device_security.device_rejection_cooldown_minutes', 15);

            DeviceLoginCooldown::create([
                'user_id' => $actor->id,
                'ip_address' => $locked->ip_address ?? '0.0.0.0',
                'device_fingerprint' => $locked->device_fingerprint ?? 'unknown',
                'login_approval_request_id' => $locked->id,
                'blocked_until' => now()->addMinutes($cooldownMinutes),
                'reason' => 'Sign-in rejected by account owner.',
            ]);

            $this->auditLogger->log(
                AuditAction::LoginApprovalRejected,
                $actor,
                "Rejected sign-in request for {$locked->device_name}.",
                $locked->user,
                'Account',
            );

            $this->auditLogger->log(
                AuditAction::SuspiciousLoginBlocked,
                $actor,
                "Temporarily blocked suspicious device ({$locked->device_name}) for {$cooldownMinutes} minutes.",
                $locked->user,
                'Account',
            );

            if ((bool) config('auth.device_security.approval_emails_enabled', false)) {
                try {
                    $locked->user->notify(new SuspiciousLoginBlockedNotification(
                        $locked->device_name ?: 'Unknown Device',
                        $locked->ip_address,
                        $cooldownMinutes,
                    ));
                } catch (Throwable $e) {
                    Log::warning('Rejection alert email could not be sent.', [
                        'user_id' => $locked->user_id,
                        'exception' => $e::class,
                    ]);
                }
            }
        });
    }

    /**
     * Device B exchanges approved token into an active authenticated session.
     *
     * @throws ConflictHttpException
     */
    public function claimApprovedRequest(
        LoginApprovalRequest $request,
        string $challengeHash,
        Request $httpRequest,
    ): User {
        if ($this->expireApprovalRequestIfNeeded($request)) {
            throw new ConflictHttpException('This approval request has expired.');
        }

        $user = DB::transaction(function () use ($request, $challengeHash, $httpRequest) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user_id);

            if ($user->isTemporarilyLocked()) {
                throw new ConflictHttpException('This account is currently locked. Please contact the system administrator.');
            }

            /** @var LoginApprovalRequest|null $locked */
            $locked = LoginApprovalRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals($locked->challenge_token_hash, $challengeHash)) {
                throw new ConflictHttpException('Invalid request token.');
            }

            if ($locked->isExpired()) {
                throw new ConflictHttpException('This approval request has expired.');
            }

            if ($locked->status !== LoginApprovalRequest::STATUS_APPROVED) {
                throw new ConflictHttpException("Cannot claim a request with status '{$locked->status}'.");
            }

            $guard = $locked->guard;

            // Authenticate Device B
            Auth::guard($guard)->login($user, $locked->remember);
            $httpRequest->session()->regenerate();

            $trusted = null;
            if ($locked->trust_device_on_approval) {
                $trusted = $this->registerTrustedDevice($user, $httpRequest);
            }

            // Establish single active session in database
            $this->activateSession($user, $guard, $httpRequest, $trusted);

            $locked->update([
                'status' => LoginApprovalRequest::STATUS_COMPLETED,
            ]);

            return $user;
        });

        if ((bool) config('auth.device_security.approval_emails_enabled', false)) {
            try {
                $user->notify(new NewDeviceApprovedNotification(
                    $request->device_name ?: 'Unknown Device',
                    $request->ip_address,
                    now()->timezone(config('app.timezone', 'UTC'))->format('M d, Y h:i A'),
                ));
            } catch (Throwable $e) {
                Log::warning('Approval notice email could not be sent.', [
                    'user_id' => $user->getKey(),
                    'exception' => $e::class,
                ]);
            }
        }

        return $user;
    }

    /**
     * Complete Scenario 2 email OTP verification and establish active session.
     *
     * @throws ValidationException
     */
    public function verifyEmailConfirmation(
        LoginApprovalRequest $request,
        string $challengeHash,
        string $otp,
        Request $httpRequest,
        bool $trustDevice = false,
    ): User {
        if ($this->expireApprovalRequestIfNeeded($request)) {
            throw ValidationException::withMessages(['otp' => 'This verification code has expired. Please sign in again.']);
        }

        return DB::transaction(function () use ($request, $challengeHash, $otp, $httpRequest, $trustDevice) {
            $user = User::query()->lockForUpdate()->findOrFail($request->user_id);

            if ($user->isTemporarilyLocked()) {
                throw ValidationException::withMessages([
                    'otp' => 'This account is currently locked. Please contact the system administrator.',
                ]);
            }

            /** @var LoginApprovalRequest|null $locked */
            $locked = LoginApprovalRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! hash_equals($locked->challenge_token_hash, $challengeHash)) {
                throw ValidationException::withMessages(['otp' => 'Invalid session verification token.']);
            }

            if ($locked->isExpired() || ($locked->email_otp_expires_at && $locked->email_otp_expires_at->isPast())) {
                throw ValidationException::withMessages(['otp' => 'This verification code has expired. Please sign in again.']);
            }

            if ($locked->status !== LoginApprovalRequest::STATUS_PENDING) {
                throw ValidationException::withMessages(['otp' => "This request is no longer valid ({$locked->status})."]);
            }

            if (! $locked->email_otp_hash || ! hash_equals($locked->email_otp_hash, hash('sha256', $otp))) {
                throw ValidationException::withMessages(['otp' => 'The verification code is incorrect.']);
            }

            $guard = $locked->guard;

            // Authenticate the verified device
            Auth::guard($guard)->login($user, $locked->remember);
            $httpRequest->session()->regenerate();

            $trusted = null;
            if ($trustDevice) {
                $trusted = $this->registerTrustedDevice($user, $httpRequest);
            }

            $this->activateSession($user, $guard, $httpRequest, $trusted);

            $locked->update([
                'status' => LoginApprovalRequest::STATUS_COMPLETED,
                'approved_at' => now(),
                'email_otp_hash' => null,
                'email_otp_expires_at' => null,
            ]);

            $this->auditLogger->log(
                AuditAction::LoginApprovalApproved,
                $user,
                "Verified and approved new device ({$locked->device_name}) via email verification code.",
                $user,
                'Account',
            );

            return $user;
        });
    }

    /** @throws ValidationException */
    public function resendApprovalEmail(LoginApprovalRequest $request, string $challengeHash): void
    {
        if (! (bool) config('auth.device_security.approval_emails_enabled', false)) {
            throw ValidationException::withMessages([
                'email' => 'Sign-in approval emails are currently disabled.',
            ]);
        }

        if ($this->expireApprovalRequestIfNeeded($request)) {
            throw ValidationException::withMessages(['email' => 'This approval request is no longer pending.']);
        }

        $locked = LoginApprovalRequest::query()->where('id', $request->id)->firstOrFail();

        if (! hash_equals($locked->challenge_token_hash, $challengeHash)) {
            throw ValidationException::withMessages(['email' => 'Invalid approval request.']);
        }

        if ($locked->status !== LoginApprovalRequest::STATUS_PENDING || $locked->isExpired()) {
            throw ValidationException::withMessages(['email' => 'This approval request is no longer pending.']);
        }

        try {
            $locked->user->notify(new NewDeviceLoginAttemptNotification($locked));
        } catch (Throwable $e) {
            Log::warning('Resent login approval email failed.', [
                'user_id' => $locked->user_id,
                'exception' => $e::class,
            ]);

            throw ValidationException::withMessages([
                'email' => 'We could not resend the approval email. Please try again.',
            ]);
        }

        $this->auditLogger->log(
            AuditAction::LoginApprovalEmailResent,
            null,
            'Resent sign-in approval email.',
            $locked->user,
            'Account',
            source: 'system',
        );
    }

    /**
     * Atomically register or update the single active authenticated session for a user.
     *
     * Invariant: At most one active session per account.
     */
    public function activateSession(
        User $user,
        string $guard,
        Request $request,
        ?TrustedDevice $trustedDevice = null,
    ): UserActiveSession {
        $isTrustedTakeover = false;

        $activeSession = DB::transaction(function () use ($user, $guard, $request, $trustedDevice, &$isTrustedTakeover) {
            $request->session()->put('hims:authenticated_session', true);
            $deviceContext = $this->resolveDeviceContext($request);
            $newSessionId = $request->session()->getId();

            /** @var UserActiveSession|null $existing */
            $existing = UserActiveSession::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($trustedDevice !== null) {
                $this->cancelPendingRequestsForUser($user);
            }

            $isTrustedTakeover = $trustedDevice !== null
                && $existing !== null
                && $existing->session_id !== $newSessionId
                && ! Str::startsWith($existing->session_id, 'superseded_');

            if ($isTrustedTakeover) {
                $this->auditLogger->log(
                    AuditAction::SessionReplaced,
                    $user,
                    "Session taken over by recognized trusted device: {$trustedDevice->display_name}.",
                    $user,
                    'Account',
                );
            }

            if ($existing && $existing->session_id !== $newSessionId) {
                // Invalidate the old session row from database storage if driver is database
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))
                        ->where('id', $existing->session_id)
                        ->delete();
                }
            }

            return UserActiveSession::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'session_id' => $newSessionId,
                    'guard' => $guard,
                    'device_name' => $deviceContext['device_name'],
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit($request->userAgent() ?? '', 1000),
                    'trusted_device_id' => $trustedDevice?->id,
                    'last_activity_at' => now(),
                ]
            );
        });

        if (
            $isTrustedTakeover
            && $trustedDevice !== null
            && (bool) config('auth.device_security.approval_emails_enabled', false)
        ) {
            try {
                $user->notify(new SessionTakeoverNotification(
                    $trustedDevice->display_name,
                    $request->ip(),
                    now()->timezone(config('app.timezone', 'UTC'))->format('M d, Y h:i A'),
                ));
            } catch (Throwable $e) {
                Log::warning('Session takeover notification could not be sent.', [
                    'user_id' => $user->getKey(),
                    'exception' => $e::class,
                ]);
            }
        }

        return $activeSession;
    }

    /**
     * Remove the active session record on manual logout or inactivity expiry.
     */
    public function clearActiveSession(User $user, ?string $sessionId = null): void
    {
        UserActiveSession::query()
            ->where('user_id', $user->id)
            ->when($sessionId !== null, fn ($q) => $q->where('session_id', $sessionId))
            ->delete();

    }

    /**
     * Create and store a new trusted device record and return plain token and model.
     *
     * @return array{token: string, trustedDevice: TrustedDevice}
     */
    public function issueTrustedDevice(User $user, Request $request, ?string $deviceName = null): array
    {
        $deviceContext = $this->resolveDeviceContext($request);
        if ($deviceName !== null) {
            $deviceContext['device_name'] = $deviceName;
        }

        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);
        $lifetimeDays = (int) config('auth.device_security.trusted_device_lifetime_days', 30);

        $cookieName = (string) config('auth.device_security.cookie_name', 'hims_trusted_device');
        $existingToken = $request->cookie($cookieName);

        $trusted = DB::transaction(function () use (
            $user,
            $tokenHash,
            $existingToken,
            $deviceContext,
            $request,
            $lifetimeDays,
        ): TrustedDevice {
            $existing = null;

            if (is_string($existingToken) && trim($existingToken) !== '') {
                $existing = TrustedDevice::query()
                    ->where('user_id', $user->id)
                    ->where('token_hash', hash('sha256', trim($existingToken)))
                    ->lockForUpdate()
                    ->first();
            }

            $attributes = [
                'token_hash' => $tokenHash,
                'display_name' => $deviceContext['device_name'],
                'user_agent_summary' => "{$deviceContext['browser']} on {$deviceContext['operating_system']}",
                'ip_address' => $request->ip(),
                'last_used_at' => now(),
                'expires_at' => now()->addDays($lifetimeDays),
                'revoked_at' => null,
            ];

            if ($existing !== null) {
                $existing->forceFill($attributes)->save();

                return $existing;
            }

            return TrustedDevice::create([
                'user_id' => $user->id,
                'device_uuid' => (string) Str::uuid(),
                'first_trusted_at' => now(),
                ...$attributes,
            ]);
        });

        $this->auditLogger->log(
            AuditAction::TrustedDeviceAdded,
            $user,
            "Added trusted device: {$deviceContext['device_name']}.",
            $user,
            'Account',
        );

        return [
            'token' => $plainToken,
            'trustedDevice' => $trusted,
        ];
    }

    /**
     * Create and store a new trusted device record and queue the secure cookie.
     */
    public function registerTrustedDevice(User $user, Request $request): TrustedDevice
    {
        $data = $this->issueTrustedDevice($user, $request);
        $lifetimeDays = (int) config('auth.device_security.trusted_device_lifetime_days', 30);
        $cookieName = (string) config('auth.device_security.cookie_name', 'hims_trusted_device');
        $cookieSecure = (bool) config('auth.device_security.cookie_secure', false) || $request->isSecure();
        $minutes = $lifetimeDays * 24 * 60;

        Cookie::queue(Cookie::make(
            $cookieName,
            $data['token'],
            $minutes,
            '/',
            config('session.domain'),
            $cookieSecure,
            true, // HttpOnly
            false,
            'lax'
        ));

        return $data['trustedDevice'];
    }

    /**
     * Revoke a trusted device and invalidate its trust token.
     */
    public function revokeTrustedDevice(TrustedDevice $trustedDevice, ?User $actor = null): void
    {
        DB::transaction(function () use ($trustedDevice, $actor) {
            $trustedDevice->update(['revoked_at' => now()]);

            $this->auditLogger->log(
                AuditAction::TrustedDeviceRevoked,
                $actor ?: $trustedDevice->user,
                "Revoked trusted device: {$trustedDevice->display_name}.",
                $trustedDevice->user,
                'Account',
            );
        });
    }

    /**
     * Invalidate pending login approval requests for an account.
     */
    public function cancelPendingRequestsForUser(User $user): int
    {
        return LoginApprovalRequest::query()
            ->where('user_id', $user->id)
            ->where('status', LoginApprovalRequest::STATUS_PENDING)
            ->update(['status' => LoginApprovalRequest::STATUS_CANCELLED]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createPendingApprovalRequest(User $user, array $attributes): LoginApprovalRequest
    {
        return DB::transaction(function () use ($user, $attributes): LoginApprovalRequest {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            LoginApprovalRequest::query()
                ->where('user_id', $user->id)
                ->where('device_fingerprint', $attributes['device_fingerprint'])
                ->where('status', LoginApprovalRequest::STATUS_PENDING)
                ->update(['status' => LoginApprovalRequest::STATUS_CANCELLED]);

            return LoginApprovalRequest::create($attributes);
        });
    }

    public function expireApprovalRequestIfNeeded(LoginApprovalRequest $request): bool
    {
        return DB::transaction(function () use ($request): bool {
            $locked = LoginApprovalRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null
                || ! in_array($locked->status, [LoginApprovalRequest::STATUS_PENDING, LoginApprovalRequest::STATUS_APPROVED], true)
                || ! $locked->isExpired()) {
                return false;
            }

            $locked->update(['status' => LoginApprovalRequest::STATUS_EXPIRED]);
            $this->auditLogger->log(
                AuditAction::LoginApprovalExpired,
                null,
                'Login approval request expired.',
                $locked->user,
                'Account',
                source: 'system',
            );

            return true;
        });
    }

    /**
     * Handle security invalidations on password change or reset.
     */
    public function handlePasswordChanged(
        User $user,
        bool $revokeTrustedDevices = true,
        ?string $keepSessionId = null,
    ): void {
        $this->cancelPendingRequestsForUser($user);

        // Terminate other active sessions
        UserActiveSession::query()
            ->where('user_id', $user->id)
            ->when($keepSessionId !== null, fn ($q) => $q->where('session_id', '!=', $keepSessionId))
            ->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
                ->delete();
        }

        if ($revokeTrustedDevices) {
            TrustedDevice::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }
    }
}
