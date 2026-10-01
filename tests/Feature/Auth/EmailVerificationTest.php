<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Email verified. Your HIMS account is now activated. You may sign in.');

        $this->get($verificationUrl)->assertRedirect(route('login'));
        Event::assertDispatchedTimes(Verified::class, 1);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }

    #[DataProvider('verificationPanelProvider')]
    public function test_verification_email_uses_the_accounts_login_panel(
        UserRole $role,
        string $expectedLoginRoute,
        string $expectedPanel,
    ): void {
        $user = User::factory()->unverified()->role($role)->create();
        $url = (new VerifyEmail)->toMail($user)->actionUrl;

        $this->get($url)
            ->assertRedirect(route($expectedLoginRoute));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($expectedPanel, $query['panel']);
    }

    public function test_relative_verification_signature_survives_an_equivalent_host_change(): void
    {
        $user = User::factory()->unverified()->create();
        $url = (new VerifyEmail)->toMail($user)->actionUrl;
        $urlWithDifferentOrigin = 'https://hims.example.test'.parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

        $this->get($urlWithDifferentOrigin)
            ->assertRedirect(route('login'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_activation_email_has_clear_hims_copy_and_expiry(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = (new VerifyEmail)->toMail($user);

        $this->assertSame('Activate your HIMS account', $mail->subject);
        $this->assertSame('Activate HIMS Account', $mail->actionText);
        $this->assertContains('This activation link expires in 60 minutes.', $mail->outroLines);
    }

    public function test_expired_verification_link_explains_how_to_resend(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->email), 'panel' => 'staff'],
            absolute: false,
        );

        $this->get($url)
            ->assertForbidden()
            ->assertSee('Activation link expired')
            ->assertSee('Ask a HIMS administrator to resend the activation email.');
    }

    public function test_expired_admin_link_first_returns_to_the_admin_login_panel(): void
    {
        $user = User::factory()->unverified()->role(UserRole::Administrator)->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->email), 'panel' => 'admin'],
            absolute: false,
        );

        $this->get($url)
            ->assertForbidden()
            ->assertSee('Back to Admin Login')
            ->assertSee(route('admin.login'), escape: false);
    }

    public function test_invalid_verification_link_explains_how_to_resend(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get(route('verification.verify', [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]))
            ->assertForbidden()
            ->assertSee('Activation link invalid')
            ->assertSee('Ask a HIMS administrator to resend the activation email.');
    }

    public function test_super_admin_verification_returns_to_the_super_admin_login(): void
    {
        $user = User::factory()->unverified()->role(UserRole::SuperAdministrator)->create();
        $url = (new VerifyEmail)->toMail($user)->actionUrl;

        $this->get($url)->assertRedirect(route('super-admin.login'));
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->get($verificationUrl)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[DataProvider('unverifiedLoginProvider')]
    public function test_unverified_accounts_cannot_sign_in_to_any_panel(
        UserRole $role,
        string $loginRoute,
        string $guard,
    ): void {
        $user = User::factory()->unverified()->role($role)->create();

        $this->post(route($loginRoute), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest($guard);
    }

    public function test_verification_does_not_reactivate_a_disabled_account(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::Inactive]);
        $url = (new VerifyEmail)->toMail($user)->actionUrl;

        $this->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Email verified. An administrator must reactivate your account before you can sign in.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertSame(UserStatus::Inactive, $user->fresh()->status);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertGuest();
    }

    public function test_a_signed_link_cannot_verify_a_different_account(): void
    {
        $first = User::factory()->unverified()->create();
        $second = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $second->id, 'hash' => sha1($first->email)],
        );

        $this->get($url)->assertForbidden();

        $this->assertFalse($first->fresh()->hasVerifiedEmail());
        $this->assertFalse($second->fresh()->hasVerifiedEmail());
    }

    public function test_unverified_account_cannot_use_protected_api_routes(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard-summary')
            ->assertForbidden()
            ->assertJson(['message' => 'Your email address is not verified.']);
    }

    /** @return array<string, array{UserRole, string, string}> */
    public static function verificationPanelProvider(): array
    {
        return [
            'staff' => [UserRole::Viewer, 'login', 'staff'],
            'admin' => [UserRole::Administrator, 'admin.login', 'admin'],
            'super admin' => [UserRole::SuperAdministrator, 'super-admin.login', 'super_admin'],
        ];
    }

    /** @return array<string, array{UserRole, string, string}> */
    public static function unverifiedLoginProvider(): array
    {
        return [
            'staff' => [UserRole::Viewer, 'login', AuthenticationContext::WEB_GUARD],
            'admin' => [UserRole::Administrator, 'admin.login.store', AuthenticationContext::ADMIN_GUARD],
            'super admin' => [UserRole::SuperAdministrator, 'super-admin.login.store', AuthenticationContext::SUPER_ADMIN_GUARD],
        ];
    }
}
