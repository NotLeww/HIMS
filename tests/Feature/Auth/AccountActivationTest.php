<?php

namespace Tests\Feature\Auth;

use App\Contracts\SmsGateway;
use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AccountActivationChallenge;
use App\Models\User;
use App\Notifications\AccountActivationOtp;
use App\Notifications\AccountCreated;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountActivationTest extends TestCase
{
    use RefreshDatabase;

    private FakeActivationSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeActivationSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);
    }

    public function test_activation_mobile_number_accepts_digits_only(): void
    {
        $this->get(route('activation.start'))
            ->assertOk()
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('oninput="this.value = this.value.replace(/[^0-9]/g, \'\').slice(0, 11)"', false);

        $this->post(route('activation.identify'), [
            'email' => 'pending@example.test',
            'phone' => '0917abc4567',
        ])->assertSessionHasErrors('phone');
    }

    public function test_admin_created_user_completes_email_otp_activation_and_can_log_in(): void
    {
        Notification::fake();
        $user = $this->createPendingUser();

        $this->assertSame(UserStatus::PendingActivation, $user->status);
        $this->assertNull($user->password);
        $this->assertNull($user->password_changed_at);
        $this->assertArrayNotHasKey('password', $user->toArray());

        $this->beginActivation($user)
            ->post(route('activation.send'), ['channel' => 'email'])
            ->assertRedirect(route('activation.verify'));

        $otp = null;
        Notification::assertSentTo($user, AccountActivationOtp::class, function (AccountActivationOtp $notification) use (&$otp): bool {
            $otp = $notification->otp;

            return true;
        });

        $this->post(route('activation.verify.store'), ['otp' => $otp])
            ->assertRedirect(route('activation.password'));

        $this->get(route('activation.password'))
            ->assertOk()
            ->assertSee('aria-controls="activation-password"', false)
            ->assertSee('aria-controls="activation-password-confirmation"', false)
            ->assertSee("showPassword ? 'Hide password' : 'Show password'", false);

        $this->post(route('activation.password.store'), [
            'password' => 'Activated2!Secure',
            'password_confirmation' => 'Activated2!Secure',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue(Hash::check('Activated2!Secure', $user->password));
        $this->assertNotNull($user->password_changed_at);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AccountActivationCompleted->value,
            'target_id' => (string) $user->id,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Activated2!Secure',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated(AuthenticationContext::WEB_GUARD);
    }

    public function test_activation_email_link_opens_password_setup_and_redirects_to_the_users_login_panel(): void
    {
        $user = User::factory()->unverified()->role(UserRole::Administrator)->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
        ]);
        $url = (new AccountCreated)->toMail($user)->viewData['activationUrl'];

        $this->get($url)
            ->assertRedirect(route('activation.password'))
            ->assertSessionHas('status', 'Email verified. Create your password to finish activating your HIMS account.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('account_activation_challenges', [
            'user_id' => $user->id,
            'channel' => 'email',
        ]);

        $this->post(route('activation.password.store'), [
            'password' => 'Activated2!Secure',
            'password_confirmation' => 'Activated2!Secure',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHas('status', 'Account activated. You can now sign in with your new password.');

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_admin_created_user_completes_sms_otp_activation(): void
    {
        $user = $this->createPendingUser('09171234568');

        $this->beginActivation($user)
            ->post(route('activation.send'), ['channel' => 'sms'])
            ->assertRedirect(route('activation.verify'));

        $this->assertSame($user->phone, $this->sms->destination);
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $this->sms->message);
        preg_match('/\b(\d{6})\b/', $this->sms->message, $matches);

        $this->post(route('activation.verify.store'), ['otp' => $matches[1]])
            ->assertRedirect(route('activation.password'));
        $this->assertSame(UserStatus::PendingActivation, $user->fresh()->status);
        $this->assertNull($user->fresh()->password);
        $this->post(route('activation.password.store'), [
            'password' => 'Activated3!Secure',
            'password_confirmation' => 'Activated3!Secure',
        ])->assertRedirect(route('login'));

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertSame(UserRole::WarehouseStaff, $user->fresh()->role);
    }

    public function test_pending_user_cannot_log_in_and_admin_cannot_activate_by_status_toggle(): void
    {
        $user = $this->createPendingUser();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'anything'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user, AuthenticationContext::WEB_GUARD)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest(AuthenticationContext::WEB_GUARD);

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)
            ->patch(route('admin.users.toggle-status', $user))
            ->assertSessionHasErrors('status');

        $this->assertSame(UserStatus::PendingActivation, $user->fresh()->status);
    }

    public function test_wrong_expired_reused_and_superseded_codes_cannot_activate_account(): void
    {
        Notification::fake();
        $user = $this->createPendingUser();
        $this->beginActivation($user)->post(route('activation.send'), ['channel' => 'email']);
        $first = $this->lastEmailOtp($user);

        $this->post(route('activation.verify.store'), ['otp' => '000000'])->assertSessionHasErrors('otp');

        AccountActivationChallenge::query()->where('user_id', $user->id)->update([
            'resend_available_at' => now()->subSecond(),
        ]);
        $this->post(route('activation.send'), ['channel' => 'email']);
        $second = $this->lastEmailOtp($user);
        $this->assertNotSame($first, $second);
        $this->post(route('activation.verify.store'), ['otp' => $first])->assertSessionHasErrors('otp');

        AccountActivationChallenge::query()->where('user_id', $user->id)->update(['expires_at' => now()->subSecond()]);
        $this->post(route('activation.verify.store'), ['otp' => $second])->assertSessionHasErrors('otp');

        AccountActivationChallenge::query()->where('user_id', $user->id)->update([
            'otp_hash' => Hash::make($second),
            'expires_at' => now()->addMinute(),
            'failed_attempts' => 0,
        ]);
        $this->post(route('activation.verify.store'), ['otp' => $second])->assertRedirect(route('activation.password'));
        $this->post(route('activation.verify.store'), ['otp' => $second])->assertSessionHasErrors('otp');
        $this->assertSame(UserStatus::PendingActivation, $user->fresh()->status);
    }

    public function test_resend_cooldown_and_masked_contacts_are_enforced(): void
    {
        Notification::fake();
        $user = $this->createPendingUser();

        $this->beginActivation($user);
        $this->get(route('activation.method'))
            ->assertSee('a*********@example.test')
            ->assertSee('•••••••4567')
            ->assertDontSee($user->email)
            ->assertDontSee($user->phone);

        $this->post(route('activation.send'), ['channel' => 'email']);
        $this->post(route('activation.send'), ['channel' => 'email'])
            ->assertSessionHasErrors('channel');
        Notification::assertSentToTimes($user, AccountActivationOtp::class, 1);
    }

    public function test_unknown_and_ineligible_accounts_receive_the_same_identification_response(): void
    {
        $active = User::factory()->create(['phone' => '09171234569']);
        $inactive = User::factory()->inactive()->create(['phone' => '09171234570']);
        $archived = User::factory()->create([
            'phone' => '09171234571',
            'status' => UserStatus::Archived,
        ]);

        foreach ([
            ['email' => 'missing@example.test', 'phone' => '09170000000'],
            ['email' => $active->email, 'phone' => $active->phone],
            ['email' => $inactive->email, 'phone' => $inactive->phone],
            ['email' => $archived->email, 'phone' => $archived->phone],
        ] as $index => $identity) {
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.'.($index + 2)]);
            $this->post(route('activation.identify'), $identity)->assertRedirect(route('activation.method'));
            $this->post(route('activation.send'), ['channel' => 'email'])
                ->assertRedirect(route('activation.verify'))
                ->assertSessionHas('status', 'If the account is eligible, a verification code has been sent.');
            $this->flushSession();
        }
    }

    public function test_attempt_limit_invalidates_the_code(): void
    {
        Notification::fake();
        $user = $this->createPendingUser();
        $this->beginActivation($user)->post(route('activation.send'), ['channel' => 'email']);
        $otp = $this->lastEmailOtp($user);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('activation.verify.store'), ['otp' => '000000'])
                ->assertSessionHasErrors('otp');
        }

        $this->post(route('activation.verify.store'), ['otp' => $otp])
            ->assertSessionHasErrors('otp');
        $this->assertSame(UserStatus::PendingActivation, $user->fresh()->status);
    }

    public function test_sms_delivery_failure_invalidates_the_unsent_code(): void
    {
        $user = $this->createPendingUser();
        $this->sms->succeeds = false;

        $this->beginActivation($user)
            ->post(route('activation.send'), ['channel' => 'sms'])
            ->assertSessionHasErrors('channel');

        $challenge = AccountActivationChallenge::query()->whereBelongsTo($user)->firstOrFail();
        $this->assertNull($challenge->otp_hash);
        $this->assertNull($challenge->expires_at);
        $this->assertSame(UserStatus::PendingActivation, $user->fresh()->status);
    }

    public function test_public_activation_send_route_is_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.99']);
        $this->post(route('activation.identify'), [
            'email' => 'missing@example.test',
            'phone' => '09170000000',
        ])->assertRedirect(route('activation.method'));

        // The preceding identification request shares Laravel's guest/IP
        // throttle bucket, leaving nine send attempts in this window.
        foreach (range(1, 9) as $attempt) {
            $this->post(route('activation.send'), ['channel' => 'email'])
                ->assertRedirect(route('activation.verify'));
        }

        $this->post(route('activation.send'), ['channel' => 'email'])
            ->assertStatus(429);
    }

    private function createPendingUser(string $phone = '09171234567'): User
    {
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'surname' => 'Activation',
            'first_name' => 'Account',
            'middle_name' => '',
            'email' => 'activation@example.test',
            'role' => UserRole::WarehouseStaff->value,
            'department' => 'Central Supply',
            'phone' => $phone,
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        auth(AuthenticationContext::ADMIN_GUARD)->logout();

        return User::query()->where('email', 'activation@example.test')->firstOrFail();
    }

    private function beginActivation(User $user): self
    {
        $this->post(route('activation.identify'), [
            'email' => $user->email,
            'phone' => $user->phone,
        ])->assertRedirect(route('activation.method'));

        $this->get(route('activation.method'))->assertOk();

        return $this;
    }

    private function lastEmailOtp(User $user): string
    {
        $otp = '';
        Notification::assertSentTo($user, AccountActivationOtp::class, function (AccountActivationOtp $notification) use (&$otp): bool {
            $otp = $notification->otp;

            return true;
        });

        return $otp;
    }
}

class FakeActivationSmsGateway implements SmsGateway
{
    public ?string $destination = null;

    public string $message = '';

    public bool $succeeds = true;

    public function available(): bool
    {
        return true;
    }

    public function send(string $mobileNumber, #[\SensitiveParameter] string $message): bool
    {
        $this->destination = $mobileNumber;
        $this->message = $message;

        return $this->succeeds;
    }
}
