<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AuthenticationContext;
use Illuminate\Auth\Events\Verified;
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

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    #[DataProvider('unverifiedPanelProvider')]
    public function test_unverified_accounts_cannot_access_business_pages(
        UserRole $role,
        string $guard,
        string $routeName,
    ): void {
        $user = User::factory()->unverified()->role($role)->create();

        $this->actingAs($user, $guard)
            ->get(route($routeName))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_account_can_still_correct_its_profile_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
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
    public static function unverifiedPanelProvider(): array
    {
        return [
            'staff' => [UserRole::Viewer, AuthenticationContext::WEB_GUARD, 'dashboard'],
            'admin' => [UserRole::Administrator, AuthenticationContext::ADMIN_GUARD, 'dashboard'],
            'super admin' => [UserRole::SuperAdministrator, AuthenticationContext::SUPER_ADMIN_GUARD, 'super-admin.dashboard'],
        ];
    }
}
