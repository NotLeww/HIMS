<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Http\Middleware\EnforceSingleActiveSession;
use App\Models\AuditLog;
use App\Models\LoginApprovalRequest;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserActiveSession;
use App\Notifications\NewDeviceApprovedNotification;
use App\Notifications\NewDeviceLoginAttemptNotification;
use App\Notifications\SessionTakeoverNotification;
use App\Notifications\SuspiciousLoginBlockedNotification;
use App\Services\DeviceSecurity\DeviceSecurityService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DeviceSecurityAndSingleSessionTest extends TestCase
{
    use RefreshDatabase;

    protected DeviceSecurityService $deviceSecurity;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('auth.device_security.enabled', true);
        config()->set('auth.device_security.trusted_device_lifetime_days', 30);
        config()->set('auth.device_security.approval_request_lifetime_minutes', 5);
        config()->set('auth.device_security.device_rejection_cooldown_minutes', 15);

        $this->deviceSecurity = app(DeviceSecurityService::class);
    }

    private function createDeviceRequest(string $ip = '192.168.1.50', string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0'): Request
    {
        $request = Request::create('/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $userAgent,
        ]);

        $session = app('session.store');
        $session->start();
        $request->setLaravelSession($session);

        return $request;
    }

    /**
     * 1. Unknown Device B requests login while Device A is active.
     */
    public function test_unknown_device_b_requests_login_while_device_a_is_active(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        // Establish Device A active session
        $deviceARequest = $this->createDeviceRequest('192.168.1.10', 'Mozilla/5.0 DeviceA Chrome/120');
        $this->deviceSecurity->activateSession($user, 'web', $deviceARequest);

        $this->assertDatabaseHas('user_active_sessions', [
            'user_id' => $user->id,
            'session_id' => $deviceARequest->session()->getId(),
        ]);

        // Device B attempts login from another IP/User-Agent
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.20',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 DeviceB Firefox/121',
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        // Device B must NOT immediately gain an authenticated session
        $this->assertGuest('web');

        // Pending approval request must be created
        $approvalRequest = LoginApprovalRequest::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($approvalRequest);
        $this->assertEquals(LoginApprovalRequest::STATUS_PENDING, $approvalRequest->status);
        $this->assertEquals('192.168.1.20', $approvalRequest->ip_address);

        // Device B is redirected to waiting screen
        $response->assertRedirect(route('auth.device-approval.waiting', $approvalRequest));
        $this->assertStringNotContainsString('token=', $response->headers->get('Location') ?? '');
        $response->assertSessionHas(DeviceSecurityService::approvalChallengeSessionKey($approvalRequest));

        Notification::assertSentTo($user, NewDeviceLoginAttemptNotification::class);
    }

    /**
     * 2. Device B cannot access protected routes while waiting.
     */
    public function test_device_b_cannot_access_protected_routes_while_waiting(): void
    {
        $user = User::factory()->create();
        $challengeToken = Str::random(64);

        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Accessing dashboard without active session must redirect to login
        $this->get('/dashboard')->assertRedirect(route('login'));

        // Accessing pending status poll endpoint without valid token fails
        $this->getJson(route('auth.device-approval.status', $approval))->assertUnauthorized();

        // With a challenge bound to Device B's pending session, status is visible
        $this->withSession([
            DeviceSecurityService::approvalChallengeSessionKey($approval) => hash('sha256', $challengeToken),
        ])->getJson(route('auth.device-approval.status', $approval))->assertOk()->assertJson([
            'status' => 'pending',
        ]);
    }

    public function test_cached_get_cancel_link_requires_post_confirmation_without_method_error(): void
    {
        $user = User::factory()->create();
        $challengeToken = Str::random(64);
        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'device_name' => 'Microsoft Edge on Windows',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);
        $session = [
            DeviceSecurityService::approvalChallengeSessionKey($approval) => hash('sha256', $challengeToken),
        ];

        $this->withSession($session)
            ->get(route('auth.device-approval.cancel-confirmation', $approval))
            ->assertOk()
            ->assertSee('Cancel this sign-in request?')
            ->assertSee('method="POST"', false)
            ->assertSee(route('auth.device-approval.cancel', $approval), false);

        $this->assertSame(LoginApprovalRequest::STATUS_PENDING, $approval->fresh()->status);

        $this->withSession($session)
            ->post(route('auth.device-approval.cancel', $approval))
            ->assertRedirect(route('login'));

        $this->assertSame(LoginApprovalRequest::STATUS_CANCELLED, $approval->fresh()->status);
    }

    /**
     * 3 & 4 & 5 & 6. Device A approves Device B -> Device B becomes authenticated -> Device A immediately loses access across tabs.
     */
    public function test_device_a_approves_device_b_and_device_a_immediately_loses_access(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        // Device A is active
        $deviceASessionId = 'device_a_session_123';
        UserActiveSession::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => $deviceASessionId,
            'ip_address' => '192.168.1.10',
            'last_active_at' => now(),
        ]);

        $challengeToken = Str::random(64);
        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'ip_address' => '192.168.1.20',
            'device_name' => 'Device B Firefox',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Device A approves Device B
        $this->deviceSecurity->approveRequest($approval, $user, true);
        Notification::assertNotSentTo($user, NewDeviceApprovedNotification::class);

        $approval->refresh();
        $this->assertEquals(LoginApprovalRequest::STATUS_APPROVED, $approval->status);

        // Device B claims session
        $deviceBRequest = $this->createDeviceRequest('192.168.1.20', 'Mozilla/5.0 DeviceB Firefox/121');
        $claimedUser = $this->deviceSecurity->claimApprovedRequest($approval, hash('sha256', $challengeToken), $deviceBRequest);

        $this->assertEquals($user->id, $claimedUser->id);

        // Check active session in DB: MUST be Device B's session now
        $activeSession = UserActiveSession::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($activeSession);
        $this->assertEquals($deviceBRequest->session()->getId(), $activeSession->session_id);
        $this->assertNotEquals($deviceASessionId, $activeSession->session_id);

        // Device A now makes a request with its superseded session ID
        $response = $this->actingAs($user, 'web')
            ->withSession([
                'hims:authenticated_session' => true,
            ])
            ->getJson('/dashboard/live');

        // Must receive 401 with X-Session-Replaced
        $response->assertStatus(401);
        $response->assertHeader('X-Session-Replaced', 'true');

        // Other tabs of Device A making a web request get redirected to login with session_replaced flashed
        $webResponse = $this->actingAs($user, 'web')
            ->withSession([
                'hims:authenticated_session' => true,
            ])
            ->get('/dashboard');

        $webResponse->assertRedirect(route('login'));
        $webResponse->assertSessionHas('session_replaced');

        Notification::assertSentTo($user, NewDeviceApprovedNotification::class);
    }

    /**
     * 7 & 8 & 9 & 10. Device A rejects Device B -> Device B denied + cooldown applied without locking user account.
     */
    public function test_device_a_rejects_device_b_applies_cooldown_without_locking_account(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $fingerprint = hash('sha256', '192.168.1.99|Suspicious Browser');
        $challengeToken = Str::random(64);

        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'ip_address' => '192.168.1.99',
            'device_fingerprint' => $fingerprint,
            'device_name' => 'Suspicious Browser',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Device A rejects
        $this->deviceSecurity->rejectRequest($approval, $user);

        $approval->refresh();
        $this->assertEquals(LoginApprovalRequest::STATUS_REJECTED, $approval->status);

        // Device B attempts to claim -> must be rejected
        $deviceBRequest = $this->createDeviceRequest('192.168.1.99', 'Suspicious Browser');
        try {
            $this->deviceSecurity->claimApprovedRequest($approval, hash('sha256', $challengeToken), $deviceBRequest);
            $this->fail('A rejected request must not be claimable.');
        } catch (ConflictHttpException) {
            $this->assertTrue(true);
        }

        // Suspicious device must have a 15-minute cooldown record
        $this->assertDatabaseHas('device_login_cooldowns', [
            'user_id' => $user->id,
            'device_fingerprint' => $fingerprint,
        ]);

        // Subsequent login from suspicious device is blocked by cooldown
        $blockedResponse = $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.99',
            'HTTP_USER_AGENT' => 'Suspicious Browser',
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $blockedResponse->assertSessionHasErrors('email');
        $this->assertStringContainsString('temporarily blocked', strtolower(session('errors')->first('email')));

        // CRITICAL: Account itself is NOT locked! Legitimate user from another IP/device can still attempt
        $this->deviceSecurity->ensureDeviceIsNotBlocked(
            $user,
            $this->createDeviceRequest('192.168.1.10', 'Legitimate Chrome'),
        );

        Notification::assertSentTo($user, SuspiciousLoginBlockedNotification::class);
    }

    /**
     * 11 & 12. Trusted device login bypasses approval and atomically revokes prior session.
     */
    public function test_trusted_device_bypasses_approval_and_revokes_prior_session(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        // Device A had active session
        $oldSessionId = 'old_session_device_a';
        UserActiveSession::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => $oldSessionId,
            'ip_address' => '192.168.1.10',
            'last_active_at' => now(),
        ]);

        // User previously trusted Device B
        $deviceBRequest = $this->createDeviceRequest('192.168.1.55', 'Mozilla/5.0 TrustedLaptop Chrome/120');
        $trustedData = $this->deviceSecurity->issueTrustedDevice($user, $deviceBRequest, 'Trusted Laptop');

        // Device B logs in with trusted cookie
        $response = $this->withCookie(config('auth.device_security.cookie_name'), $trustedData['token'])
            ->withServerVariables([
                'REMOTE_ADDR' => '192.168.1.55',
                'HTTP_USER_AGENT' => 'Mozilla/5.0 TrustedLaptop Chrome/120',
            ])->post('/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ]);

        // Device B authenticates immediately without waiting screen
        $this->assertAuthenticatedAs($user, 'web');
        $response->assertRedirect(route('dashboard', absolute: false));

        // Prior session revoked from user_active_sessions
        $activeSession = UserActiveSession::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($activeSession);
        $this->assertNotEquals($oldSessionId, $activeSession->session_id);

        // Audit log records SessionReplaced
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::SessionReplaced->value,
        ]);
    }

    /**
     * 13. An unknown device cannot become approval authority over the legitimate trusted device.
     */
    public function test_unknown_pending_device_cannot_become_approval_authority_over_trusted_device(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        // Unknown attacker creates pending request
        $attackerApproval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', 'attacker_token'),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Legitimate owner opens trusted device and authenticates
        $trustedRequest = $this->createDeviceRequest('192.168.1.1', 'Owner Device');
        $trustedData = $this->deviceSecurity->issueTrustedDevice($user, $trustedRequest, 'Owner Laptop');

        $response = $this->withCookie(config('auth.device_security.cookie_name'), $trustedData['token'])
            ->withServerVariables([
                'REMOTE_ADDR' => '192.168.1.1',
                'HTTP_USER_AGENT' => 'Owner Device',
            ])->post('/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ]);

        // Trusted device enters immediately
        $this->assertAuthenticatedAs($user, 'web');
        $response->assertRedirect(route('dashboard', absolute: false));

        // Unknown attacker's pending request MUST be cancelled
        $attackerApproval->refresh();
        $this->assertEquals(LoginApprovalRequest::STATUS_CANCELLED, $attackerApproval->status);
    }

    /**
     * 14. Unknown device login with no active session still requires owner approval.
     */
    public function test_unknown_device_with_no_active_session_waits_for_owner_approval(): void
    {
        Notification::fake();

        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        // User is not logged in anywhere (no active session, no trusted devices)
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.195',
            'HTTP_USER_AGENT' => 'Unknown Device',
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        // Must NOT authenticate immediately
        $this->assertGuest('web');

        // A pending approval exists; no OTP can authenticate the requester.
        $approval = LoginApprovalRequest::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($approval);
        $this->assertNull($approval->email_otp_hash);

        $response->assertRedirect(route('auth.device-approval.waiting', $approval));
        $this->assertStringNotContainsString('token=', $response->headers->get('Location') ?? '');
        $response->assertSessionHas(DeviceSecurityService::approvalChallengeSessionKey($approval));

        Notification::assertSentTo($user, NewDeviceLoginAttemptNotification::class);
    }

    /**
     * 15. Pending request expires correctly.
     */
    public function test_pending_request_expires_correctly(): void
    {
        $user = User::factory()->create();
        $challengeToken = Str::random(64);

        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinutes(5), // expired 5 minutes ago
        ]);

        $this->assertTrue($approval->isExpired());

        // Status poll marks it as expired
        $response = $this->withSession([
            DeviceSecurityService::approvalChallengeSessionKey($approval) => hash('sha256', $challengeToken),
        ])->getJson(route('auth.device-approval.status', $approval));

        $response->assertOk()->assertJson(['status' => 'expired']);

        $approval->refresh();
        $this->assertEquals(LoginApprovalRequest::STATUS_EXPIRED, $approval->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LoginApprovalExpired->value,
            'target_id' => (string) $user->id,
        ]);

        // Attempting to approve expired request fails
        $this->expectException(HttpException::class);
        $this->deviceSecurity->approveRequest($approval, $user);
    }

    /**
     * 16. Reusing an approved/rejected request fails.
     */
    public function test_reusing_an_approved_request_fails(): void
    {
        $user = User::factory()->create();
        $challengeToken = Str::random(64);

        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_COMPLETED,
            'requested_at' => now()->subMinutes(2),
            'expires_at' => now()->addMinutes(3),
        ]);

        $deviceRequest = $this->createDeviceRequest();

        $this->expectException(ConflictHttpException::class);
        $this->deviceSecurity->claimApprovedRequest($approval, hash('sha256', $challengeToken), $deviceRequest);
    }

    /**
     * 17 & 18. Double-click or concurrent approval does not create two active sessions.
     */
    public function test_double_click_approval_does_not_create_two_sessions(): void
    {
        $user = User::factory()->create();
        $challengeToken = Str::random(64);

        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        // First click approves
        $this->deviceSecurity->approveRequest($approval, $user);

        // Second click on approve must fail gracefully because status is no longer pending
        $this->expectException(HttpException::class);
        $this->deviceSecurity->approveRequest($approval, $user);
    }

    /**
     * 19. Two simultaneous trusted-device logins still result in exactly one active session.
     */
    public function test_single_active_session_invariant_strictly_enforced(): void
    {
        $user = User::factory()->create();

        $req1 = $this->createDeviceRequest('192.168.1.1', 'Browser 1');
        $this->deviceSecurity->activateSession($user, 'web', $req1);

        $req2 = $this->createDeviceRequest('192.168.1.2', 'Browser 2');
        $this->deviceSecurity->activateSession($user, 'web', $req2);

        // user_active_sessions has unique constraint on user_id
        $this->assertEquals(1, UserActiveSession::query()->where('user_id', $user->id)->count());
        $this->assertEquals($req2->session()->getId(), UserActiveSession::query()->where('user_id', $user->id)->first()->session_id);
    }

    /**
     * 20. Password change/reset invalidates sessions, pending requests, and trusted devices.
     */
    public function test_password_change_invalidates_sessions_and_trusted_devices(): void
    {
        $user = User::factory()->create();

        // Active session
        UserActiveSession::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => 'stale_session_before_password_change',
            'last_active_at' => now(),
        ]);

        // Pending request
        LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', 'some_token'),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Trusted device
        $trusted = $this->deviceSecurity->issueTrustedDevice($user, $this->createDeviceRequest(), 'My Phone');

        // User resets/changes password
        $this->deviceSecurity->handlePasswordChanged($user);

        // 1. Active sessions revoked
        $this->assertEquals(0, UserActiveSession::query()->where('user_id', $user->id)->count());

        // 2. Pending requests cancelled
        $this->assertEquals(0, LoginApprovalRequest::query()->where('user_id', $user->id)->where('status', LoginApprovalRequest::STATUS_PENDING)->count());

        // 3. Trusted devices revoked
        $trustedDevice = TrustedDevice::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($trustedDevice->revoked_at);
    }

    /**
     * 21. Revoked trusted device must go through new-device verification again.
     */
    public function test_revoked_trusted_device_requires_verification_again(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $request = $this->createDeviceRequest();
        $trustedData = $this->deviceSecurity->issueTrustedDevice($user, $request, 'Revoked Laptop');

        // Revoke the device
        $this->deviceSecurity->revokeTrustedDevice($trustedData['trustedDevice']);

        // Login with revoked cookie
        $response = $this->withCookie(config('auth.device_security.cookie_name'), $trustedData['token'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ]);

        // Device is no longer trusted; redirected to email verification or waiting
        $this->assertGuest('web');
        $this->assertStringContainsString('device-approval', $response->headers->get('Location') ?? '');
    }

    /**
     * 22. Expired trusted device must go through verification again.
     */
    public function test_expired_trusted_device_requires_verification_again(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $request = $this->createDeviceRequest();
        $trustedData = $this->deviceSecurity->issueTrustedDevice($user, $request, 'Expired Tablet');

        // Force expiry
        $trustedData['trustedDevice']->update(['expires_at' => now()->subDay()]);

        // Login with expired cookie
        $response = $this->withCookie(config('auth.device_security.cookie_name'), $trustedData['token'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'Password123!',
            ]);

        $this->assertGuest('web');
        $this->assertStringContainsString('device-approval', $response->headers->get('Location') ?? '');
    }

    public function test_email_scanner_get_requires_explicit_confirmation_and_approve_once_is_single_use(): void
    {
        $user = User::factory()->create();
        $challengeToken = Str::random(64);

        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $reviewUrl = URL::temporarySignedRoute('auth.device-approval.email.review', $approval->expires_at, [
            'approvalRequest' => $approval->id,
            'decision' => 'approve-once',
        ]);
        $this->get($reviewUrl)->assertOk()->assertSee('Approve Once');
        $this->assertSame(LoginApprovalRequest::STATUS_PENDING, $approval->fresh()->status);

        $confirmUrl = URL::temporarySignedRoute('auth.device-approval.email.confirm', $approval->expires_at, [
            'approvalRequest' => $approval->id,
            'decision' => 'approve-once',
        ]);
        $this->post($confirmUrl)->assertOk()->assertSee('approved for this attempt only');
        $this->assertSame(LoginApprovalRequest::STATUS_APPROVED, $approval->fresh()->status);
        $this->assertFalse($approval->fresh()->trust_device_on_approval);

        $this->post($confirmUrl)->assertOk()->assertSee('already been approved');

        $request = $this->createDeviceRequest();
        $this->deviceSecurity->claimApprovedRequest($approval->fresh(), hash('sha256', $challengeToken), $request);
        $this->assertDatabaseMissing('trusted_devices', ['user_id' => $user->id]);

        Auth::guard('web')->logout();
        $this->deviceSecurity->clearActiveSession($user);
        $this->withServerVariables([
            'REMOTE_ADDR' => '192.168.1.50',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
        ])->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest('web');
        $this->assertDatabaseHas('login_approval_requests', [
            'user_id' => $user->id,
            'status' => LoginApprovalRequest::STATUS_PENDING,
        ]);
    }

    public function test_expired_email_approval_link_is_rejected_without_changing_request(): void
    {
        $user = User::factory()->create();
        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', Str::random(64)),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now()->subMinutes(10),
            'expires_at' => now()->subMinute(),
        ]);

        $reviewUrl = URL::temporarySignedRoute('auth.device-approval.email.review', $approval->expires_at, [
            'approvalRequest' => $approval->id,
            'decision' => 'approve-once',
        ]);

        $this->get($reviewUrl)->assertForbidden();
        $this->assertSame(LoginApprovalRequest::STATUS_PENDING, $approval->fresh()->status);
    }

    public function test_approval_email_resend_is_throttled_and_keeps_the_same_pending_request(): void
    {
        Notification::fake();
        config()->set('auth.device_security.approval_resend_cooldown_seconds', 60);

        $user = User::factory()->create();
        $challengeToken = Str::random(64);
        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);
        $session = [
            DeviceSecurityService::approvalChallengeSessionKey($approval) => hash('sha256', $challengeToken),
        ];

        $this->withSession($session)
            ->post(route('auth.device-approval.resend-email', $approval))
            ->assertSessionHas('status', 'The sign-in approval email was resent.');

        $this->withSession($session)
            ->post(route('auth.device-approval.resend-email', $approval))
            ->assertSessionHasErrors('email');

        Notification::assertSentToTimes($user, NewDeviceLoginAttemptNotification::class, 1);
        $this->assertSame(LoginApprovalRequest::STATUS_PENDING, $approval->fresh()->status);
    }

    public function test_email_deny_wins_and_mail_failure_never_authenticates(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', Str::random(64)),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'device_fingerprint' => hash('sha256', 'device'),
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $denyUrl = URL::temporarySignedRoute('auth.device-approval.email.confirm', $approval->expires_at, [
            'approvalRequest' => $approval->id,
            'decision' => 'deny',
        ]);
        $this->post($denyUrl)->assertOk()->assertSee('was denied');
        $this->assertSame(LoginApprovalRequest::STATUS_REJECTED, $approval->fresh()->status);

        $dispatcher = \Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('send')->andThrow(new RuntimeException('Synthetic delivery failure'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.40',
            'HTTP_USER_AGENT' => 'Mail Failure Browser',
        ])->post('/login', ['email' => $user->email, 'password' => 'Password123!']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('web');
        $this->assertDatabaseMissing('login_approval_requests', [
            'user_id' => $user->id,
            'status' => LoginApprovalRequest::STATUS_PENDING,
        ]);
    }

    /**
     * 24. Audit logs are recorded for all device actions without exposing raw secrets.
     */
    public function test_audit_logs_record_actions_without_raw_secrets(): void
    {
        $user = User::factory()->create();
        $request = $this->createDeviceRequest();

        $trustedData = $this->deviceSecurity->issueTrustedDevice($user, $request, 'Audit Test Device');

        // Audit log exists for TrustedDeviceAdded
        $log = AuditLog::query()
            ->where('user_id', $user->id)
            ->where('action', AuditAction::TrustedDeviceAdded->value)
            ->first();

        $this->assertNotNull($log);

        // Raw token must NEVER appear in audit log description or database
        $this->assertStringNotContainsString($trustedData['token'], $log->description);
        $this->assertDatabaseMissing('audit_logs', [
            'description' => $trustedData['token'],
        ]);

        // DB stores only SHA-256 hash
        $this->assertDatabaseHas('trusted_devices', [
            'token_hash' => hash('sha256', $trustedData['token']),
        ]);
        $this->assertDatabaseMissing('trusted_devices', [
            'token_hash' => $trustedData['token'],
        ]);
    }

    public function test_email_approve_and_trust_controls_trust_server_side(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $challengeToken = Str::random(64);

        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
            'device_name' => 'Safari on macOS',
        ]);

        $confirmUrl = URL::temporarySignedRoute('auth.device-approval.email.confirm', $approval->expires_at, [
            'approvalRequest' => $approval->id,
            'decision' => 'approve-trust',
        ]);
        $this->post($confirmUrl)->assertOk();

        $request = $this->createDeviceRequest();
        $this->deviceSecurity->claimApprovedRequest($approval->fresh(), hash('sha256', $challengeToken), $request);

        $this->assertDatabaseHas('trusted_devices', [
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('user_active_sessions', [
            'user_id' => $user->id,
        ]);
        $this->assertTrue($approval->fresh()->trust_device_on_approval);
        $this->assertSame(LoginApprovalRequest::STATUS_COMPLETED, $approval->fresh()->status);
    }

    /**
     * 26. In-app modal polling returns pending request details to active Device A.
     */
    public function test_in_app_modal_polling_returns_pending_request_details(): void
    {
        $this->withoutMiddleware(EnforceSingleActiveSession::class);

        $user = User::factory()->create();

        // No pending request -> has_pending is false
        $this->actingAs($user, 'web');

        $this->getJson(route('auth.device-approvals.pending'))
            ->assertOk()
            ->assertJson(['has_pending' => false]);

        // Device B creates a pending request
        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', 'tok123'),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'device_name' => 'Firefox on Ubuntu',
            'browser' => 'Firefox',
            'platform' => 'Ubuntu',
            'ip_address' => '10.0.0.5',
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        // Now Device A polls -> receives request details
        $this->getJson(route('auth.device-approvals.pending'))
            ->assertOk()
            ->assertJson([
                'has_pending' => true,
                'request' => [
                    'id' => $approval->id,
                    'device_name' => 'Firefox on Ubuntu',
                    'browser' => 'Firefox',
                    'platform' => 'Ubuntu',
                    'ip_address' => '10.0.0.5',
                ],
            ]);
    }

    /**
     * 27. Profile trusted device can be revoked by account owner.
     */
    public function test_profile_trusted_device_can_be_revoked_by_owner(): void
    {
        $user = User::factory()->create();
        $trusted = $this->deviceSecurity->issueTrustedDevice($user, $this->createDeviceRequest(), 'MacBook Pro');

        $this->actingAs($user, 'web')
            ->post(route('profile.trusted-devices.destroy', $trusted['trustedDevice']))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('device_success');

        $trusted['trustedDevice']->refresh();
        $this->assertNotNull($trusted['trustedDevice']->revoked_at);
    }

    public function test_reverified_browser_reuses_its_existing_trusted_device_record(): void
    {
        $user = User::factory()->create();
        $firstRequest = $this->createDeviceRequest();
        $first = $this->deviceSecurity->issueTrustedDevice($user, $firstRequest);
        $first['trustedDevice']->update(['revoked_at' => now()]);

        $reverificationRequest = $this->createDeviceRequest();
        $reverificationRequest->cookies->set(
            config('auth.device_security.cookie_name'),
            $first['token'],
        );

        $reverified = $this->deviceSecurity->issueTrustedDevice($user, $reverificationRequest);

        $this->assertSame($first['trustedDevice']->id, $reverified['trustedDevice']->id);
        $this->assertNotSame($first['token'], $reverified['token']);
        $this->assertNull($reverified['trustedDevice']->revoked_at);
        $this->assertSame(1, TrustedDevice::query()->where('user_id', $user->id)->count());
    }

    public function test_device_management_uses_distinct_action_states_and_real_device_fields(): void
    {
        $user = User::factory()->create();
        $trusted = $this->deviceSecurity->issueTrustedDevice($user, $this->createDeviceRequest());

        $response = $this->actingAs($user, 'web')->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSeeText($trusted['trustedDevice']->display_name);
        $response->assertSeeText($trusted['trustedDevice']->user_agent_summary);
        $response->assertSee("processingAction === 'approve-once'", false);
        $response->assertSee("processingAction === 'approve-trust'", false);
        $response->assertSee("processingAction === 'reject'", false);
    }

    /**
     * 28. Session takeover sends notification to account owner.
     */
    public function test_session_takeover_sends_notification_to_owner(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        // Prior session
        UserActiveSession::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => 'prev_session',
            'last_active_at' => now(),
        ]);

        $trusted = $this->deviceSecurity->issueTrustedDevice($user, $this->createDeviceRequest(), 'iPad Pro');

        $takeoverRequest = $this->createDeviceRequest('192.168.1.88', 'iPad Safari');
        $takeoverRequest->cookies->set(config('auth.device_security.cookie_name'), $trusted['token']);

        $result = $this->deviceSecurity->handleLoginAttempt($takeoverRequest, $user, 'web', false);
        $this->assertTrue($result->isTrusted());
        Notification::assertNotSentTo($user, SessionTakeoverNotification::class);

        $this->deviceSecurity->activateSession(
            $user,
            'web',
            $takeoverRequest,
            $trusted['trustedDevice'],
        );

        Notification::assertSentTo($user, SessionTakeoverNotification::class);
    }

    public function test_authenticated_session_without_authoritative_active_record_fails_closed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->withSession(['hims:authenticated_session' => true])
            ->getJson('/dashboard/live');

        $response->assertUnauthorized();
        $response->assertHeader('X-Session-Replaced', 'true');
        $this->assertDatabaseMissing('user_active_sessions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_superseded_session_cannot_approve_a_pending_request(): void
    {
        $user = User::factory()->create();
        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', Str::random(64)),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        UserActiveSession::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'session_id' => 'different-current-session',
            'last_activity_at' => now(),
        ]);

        $response = $this->actingAs($user, 'web')
            ->withSession(['hims:authenticated_session' => true])
            ->postJson(route('auth.device-approvals.approve', $approval));

        $response->assertUnauthorized();
        $response->assertHeader('X-Session-Replaced', 'true');
        $this->assertSame(LoginApprovalRequest::STATUS_PENDING, $approval->fresh()->status);
    }

    public function test_rejection_cooldown_does_not_block_another_device_on_the_same_ip(): void
    {
        $user = User::factory()->create();
        $blockedRequest = $this->createDeviceRequest('192.168.1.99', 'Suspicious Browser');
        $challengeToken = Str::random(64);
        $approval = LoginApprovalRequest::create([
            'user_id' => $user->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', $challengeToken),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'ip_address' => '192.168.1.99',
            'device_fingerprint' => $this->deviceSecurity->resolveDeviceFingerprint($blockedRequest),
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->deviceSecurity->rejectRequest($approval, $user);

        $legitimateRequest = $this->createDeviceRequest('192.168.1.99', 'Legitimate Browser');
        $this->deviceSecurity->ensureDeviceIsNotBlocked($user, $legitimateRequest);

        $this->assertTrue(true);
    }

    public function test_device_security_blocks_direct_api_token_sign_in_and_existing_bearer_tokens(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'Unmanaged API Client',
        ])->assertStatus(428)->assertJson([
            'code' => 'DEVICE_SECURITY_REQUIRED',
        ]);

        $plainToken = $user->createToken('legacy-client')->plainTextToken;

        $this->withToken($plainToken)
            ->getJson('/api/v1/dashboard-summary')
            ->assertUnauthorized()
            ->assertJson([
                'code' => 'DEVICE_SECURITY_REQUIRED',
            ]);
    }

    public function test_reposting_credentials_from_the_same_unknown_device_cancels_the_older_request(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => Hash::make('Password123!')]);
        $server = [
            'REMOTE_ADDR' => '203.0.113.20',
            'HTTP_USER_AGENT' => 'Repeated Unknown Device',
        ];

        $this->withServerVariables($server)->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect();

        $this->withServerVariables($server)->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertRedirect();

        $this->assertSame(1, LoginApprovalRequest::query()
            ->where('user_id', $user->id)
            ->where('status', LoginApprovalRequest::STATUS_PENDING)
            ->count());
        $this->assertSame(1, LoginApprovalRequest::query()
            ->where('user_id', $user->id)
            ->where('status', LoginApprovalRequest::STATUS_CANCELLED)
            ->count());
    }

    public function test_user_cannot_approve_another_accounts_request(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $approval = LoginApprovalRequest::create([
            'user_id' => $owner->id,
            'guard' => 'web',
            'challenge_token_hash' => hash('sha256', Str::random(64)),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        try {
            $this->deviceSecurity->approveRequest($approval, $otherUser);
            $this->fail('Another account must not approve this request.');
        } catch (ConflictHttpException) {
            $this->assertTrue(true);
        }

        $this->assertSame(LoginApprovalRequest::STATUS_PENDING, $approval->fresh()->status);
    }

    public function test_password_change_preserves_only_the_current_authoritative_session(): void
    {
        $this->withoutMiddleware(EnforceSingleActiveSession::class);
        $user = User::factory()->create(['password' => Hash::make('CurrentPassword123!')]);
        $deviceSecurity = \Mockery::mock(DeviceSecurityService::class);
        $deviceSecurity->shouldReceive('handlePasswordChanged')
            ->once()
            ->withArgs(fn (User $changedUser, bool $revokeTrustedDevices, ?string $keepSessionId): bool => $changedUser->is($user)
                && $revokeTrustedDevices
                && is_string($keepSessionId)
                && $keepSessionId !== '');
        $this->app->instance(DeviceSecurityService::class, $deviceSecurity);

        $this->actingAs($user, 'web');

        $this->put(route('password.update'), [
            'current_password' => 'CurrentPassword123!',
            'password' => 'ChangedPassword456!',
            'password_confirmation' => 'ChangedPassword456!',
        ])->assertSessionHasNoErrors();
    }
}
