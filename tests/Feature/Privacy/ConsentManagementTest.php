<?php

namespace Tests\Feature\Privacy;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Privacy\ConsentService;
use App\Support\AuditBrowserLocation;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_missing_privacy_consent(): void
    {
        $response = $this->post('/register', [
            'name' => 'Dr. Maria Santos',
            'email' => 'maria.santos@hospital.gov.ph',
            'password' => 'HospitalSecure2026!',
            'password_confirmation' => 'HospitalSecure2026!',
        ]);

        $response->assertSessionHasErrors('privacy_consent');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'maria.santos@hospital.gov.ph']);
        $this->assertDatabaseCount('user_consents', 0);
    }

    public function test_registration_succeeds_with_explicit_consent_and_creates_consent_record(): void
    {
        $currentVersion = config('privacy.policy_version', 'v1.0');

        $response = $this->post('/register', [
            'name' => 'Dr. Maria Santos',
            'email' => 'maria.santos@hospital.gov.ph',
            'password' => 'HospitalSecure2026!',
            'password_confirmation' => 'HospitalSecure2026!',
            'privacy_consent' => '1',
        ]);

        $this->assertAuthenticated();
        $user = User::where('email', 'maria.santos@hospital.gov.ph')->firstOrFail();

        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'consent_type' => UserConsent::TYPE_PRIVACY_POLICY,
            'policy_version' => $currentVersion,
            'status' => UserConsent::STATUS_CONSENTED,
            'source' => 'registration',
        ]);

        $consent = $user->consents()->first();
        $this->assertNotNull($consent);
        $this->assertNotNull($consent->consented_at);
        $this->assertNull($consent->withdrawn_at);
        $this->assertSame($currentVersion, $consent->policy_version);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::GrantedConsent->value,
            'target_type' => UserConsent::class,
            'target_id' => $consent->id,
        ]);
    }

    public function test_unconsented_authenticated_user_is_redirected_to_consent_page(): void
    {
        $user = User::factory()->unconsented()->create();
        $this->assertFalse($user->hasConsentedToCurrentPolicy());

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('consent.privacy-policy'));
    }

    public function test_unconsented_api_request_receives_428_precondition_required(): void
    {
        $user = User::factory()->unconsented()->create();

        $response = $this->actingAs($user)->getJson('/dashboard');

        $response->assertStatus(428);
        $response->assertJson([
            'message' => 'Privacy policy acknowledgment is required before using the system.',
            'code' => 'CONSENT_REQUIRED',
        ]);
    }

    public function test_exempt_routes_are_accessible_without_consent_redirect_loop(): void
    {
        $user = User::factory()->unconsented()->create();

        // Privacy Policy view itself
        $this->actingAs($user)->get(route('consent.privacy-policy'))->assertOk();

        // Legal public policy notice
        $this->actingAs($user)->get(route('privacy.notice'))->assertOk();

        // Terms of use
        $this->actingAs($user)->get(route('terms'))->assertOk();

        // Logout
        $response = $this->actingAs($user)->post(route('logout'));
        $this->assertGuest();
        $response->assertRedirect('/login');
    }

    public function test_user_can_explicitly_submit_privacy_policy_consent(): void
    {
        $user = User::factory()->unconsented()->create();
        $currentVersion = config('privacy.policy_version', 'v1.0');

        $response = $this->actingAs($user)->post(route('consent.privacy-policy.store'), [
            'privacy_consent' => '1',
            'policy_version' => $currentVersion,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->hasConsentedToCurrentPolicy());

        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'consent_type' => UserConsent::TYPE_PRIVACY_POLICY,
            'policy_version' => $currentVersion,
            'status' => UserConsent::STATUS_CONSENTED,
            'source' => 'login_prompt',
        ]);
    }

    public function test_consent_submission_rejects_missing_checkbox(): void
    {
        $user = User::factory()->unconsented()->create();
        $currentVersion = config('privacy.policy_version', 'v1.0');

        $response = $this->actingAs($user)->post(route('consent.privacy-policy.store'), [
            'policy_version' => $currentVersion,
        ]);

        $response->assertSessionHasErrors('privacy_consent');
        $this->assertFalse($user->fresh()->hasConsentedToCurrentPolicy());
    }

    public function test_consent_submission_rejects_tampered_policy_version(): void
    {
        $user = User::factory()->unconsented()->create();

        $response = $this->actingAs($user)->post(route('consent.privacy-policy.store'), [
            'privacy_consent' => '1',
            'policy_version' => 'v99.9-fabricated',
        ]);

        $response->assertSessionHasErrors('policy_version');
        $this->assertFalse($user->fresh()->hasConsentedToCurrentPolicy());
    }

    public function test_duplicate_submission_does_not_create_duplicate_active_records(): void
    {
        $user = User::factory()->unconsented()->create();
        $currentVersion = config('privacy.policy_version', 'v1.0');

        // First submission
        $this->actingAs($user)->post(route('consent.privacy-policy.store'), [
            'privacy_consent' => '1',
            'policy_version' => $currentVersion,
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(1, $user->consents()->where('consent_type', UserConsent::TYPE_PRIVACY_POLICY)->count());

        // Second duplicate submission
        $this->actingAs($user)->post(route('consent.privacy-policy.store'), [
            'privacy_consent' => '1',
            'policy_version' => $currentVersion,
        ])->assertRedirect(route('dashboard'));

        // Count must still be exactly 1
        $this->assertSame(1, $user->consents()->where('consent_type', UserConsent::TYPE_PRIVACY_POLICY)->count());
    }

    public function test_existing_consent_remains_recognized_after_refresh(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);
        $service->recordConsent($user, UserConsent::TYPE_PRIVACY_POLICY);

        $this->assertTrue($user->hasConsentedToCurrentPolicy());

        // Navigating to dashboard should now succeed without redirect
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_older_policy_version_consent_requires_re_consent(): void
    {
        $user = User::factory()->unconsented()->create();
        $currentVersion = config('privacy.policy_version', 'v1.0');

        // Create an older consent record (e.g. v0.9)
        UserConsent::create([
            'user_id' => $user->id,
            'consent_type' => UserConsent::TYPE_PRIVACY_POLICY,
            'policy_version' => 'v0.9',
            'status' => UserConsent::STATUS_CONSENTED,
            'consented_at' => now()->subMonths(6),
            'source' => 'legacy_registration',
        ]);

        $service = app(ConsentService::class);

        // System distinguishes older version from current version
        $this->assertFalse($user->hasConsentedToCurrentPolicy());
        $this->assertTrue($service->needsPrivacyPolicyConsent($user));

        // When user accesses dashboard, they are prompted for the current version
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertRedirect(route('consent.privacy-policy'));

        // User accepts current version
        $this->actingAs($user)->post(route('consent.privacy-policy.store'), [
            'privacy_consent' => '1',
            'policy_version' => $currentVersion,
        ])->assertRedirect(route('dashboard'));

        // Now user has 2 records in history: v0.9 and v1.0
        $this->assertSame(2, $user->consents()->count());
        $this->assertTrue($user->fresh()->hasConsentedToCurrentPolicy());
    }

    public function test_optional_consent_can_be_granted_and_withdrawn_by_user(): void
    {
        $user = User::factory()->create();
        // Give user mandatory consent first
        app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_PRIVACY_POLICY);

        // Initially no optional location consent
        $this->assertFalse($user->hasConsentedTo(UserConsent::TYPE_AUDIT_BROWSER_LOCATION));

        // 1. Grant optional geolocation consent via profile
        $grantResponse = $this->actingAs($user)->patch(route('profile.consent.optional'), [
            'consent_type' => UserConsent::TYPE_AUDIT_BROWSER_LOCATION,
            'granted' => '1',
        ]);

        $grantResponse->assertRedirect(route('profile.edit'));
        $grantResponse->assertSessionHas('status', 'consent-updated');
        $this->assertTrue($user->hasConsentedTo(UserConsent::TYPE_AUDIT_BROWSER_LOCATION));

        $record = $user->consents()->where('consent_type', UserConsent::TYPE_AUDIT_BROWSER_LOCATION)->first();
        $this->assertNotNull($record);
        $this->assertSame(UserConsent::STATUS_CONSENTED, $record->status);
        $this->assertNotNull($record->consented_at);
        $this->assertNull($record->withdrawn_at);

        // Verify Grant Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::GrantedConsent->value,
            'target_type' => UserConsent::class,
            'target_id' => $record->id,
        ]);

        // 2. Withdraw optional geolocation consent
        $withdrawResponse = $this->actingAs($user)
            ->withSession([AuditBrowserLocation::SESSION_KEY => ['latitude' => 14.5995, 'longitude' => 120.9842]])
            ->patch(route('profile.consent.optional'), [
                'consent_type' => UserConsent::TYPE_AUDIT_BROWSER_LOCATION,
                'granted' => '0',
            ]);

        $withdrawResponse->assertRedirect(route('profile.edit'));
        $this->assertFalse($user->fresh()->hasConsentedTo(UserConsent::TYPE_AUDIT_BROWSER_LOCATION));
        $withdrawResponse->assertSessionMissing(AuditBrowserLocation::SESSION_KEY);

        $record->refresh();
        $this->assertSame(UserConsent::STATUS_WITHDRAWN, $record->status);
        $this->assertNotNull($record->withdrawn_at);

        // Verify Withdrawal Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::WithdrewConsent->value,
            'target_type' => UserConsent::class,
            'target_id' => $record->id,
        ]);
    }

    public function test_mandatory_privacy_policy_consent_cannot_be_withdrawn_as_optional(): void
    {
        $user = User::factory()->create();
        app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_PRIVACY_POLICY);

        // Attempt to withdraw mandatory privacy policy via optional consent route
        $response = $this->actingAs($user)->patch(route('profile.consent.optional'), [
            'consent_type' => UserConsent::TYPE_PRIVACY_POLICY,
            'granted' => '0',
        ]);

        $response->assertSessionHasErrors('consent_type');
        $this->assertTrue($user->hasConsentedToCurrentPolicy());
    }

    public function test_user_cannot_modify_another_users_consent(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        app(ConsentService::class)->recordConsent($userA, UserConsent::TYPE_PRIVACY_POLICY);
        app(ConsentService::class)->recordConsent($userB, UserConsent::TYPE_PRIVACY_POLICY);

        // Acting as userA, updating consent applies only to userA
        $this->actingAs($userA)->patch(route('profile.consent.optional'), [
            'consent_type' => UserConsent::TYPE_AUDIT_BROWSER_LOCATION,
            'granted' => '1',
            'user_id' => $userB->id, // Malicious injection attempt
        ]);

        $this->assertTrue($userA->fresh()->hasConsentedTo(UserConsent::TYPE_AUDIT_BROWSER_LOCATION));
        $this->assertFalse($userB->fresh()->hasConsentedTo(UserConsent::TYPE_AUDIT_BROWSER_LOCATION));
    }

    public function test_admin_cannot_fabricate_user_consent_through_governance_panel(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        app(ConsentService::class)->recordConsent($admin, UserConsent::TYPE_PRIVACY_POLICY);
        $user = User::factory()->create();

        // Admin checks Privacy Governance Consent tab
        $response = $this->actingAs($admin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('super-admin.privacy.index', ['tab' => 'consent']));
        $response->assertOk();
        $response->assertSee('Workforce Consent Audit Registry');
        $response->assertSee('cannot grant, modify, or fabricate consent on behalf of workforce members');
        $response->assertDontSee('Mark Consented on Behalf of User');

        // There is no POST/PUT route allowing an admin to forge a consent record for another user
        $routes = collect(app('router')->getRoutes())->map(fn ($r) => $r->uri());
        $this->assertFalse($routes->contains('admin/privacy/consents/forge'));
        $this->assertFalse($routes->contains('admin/privacy/consents/grant-for-user'));
    }

    public function test_profile_page_displays_consent_management_card_and_history(): void
    {
        $user = User::factory()->create();
        $service = app(ConsentService::class);
        $service->recordConsent($user, UserConsent::TYPE_PRIVACY_POLICY);

        $locationOffResponse = $this->actingAs($user)->get(route('profile.edit'));
        $locationOffResponse->assertSee('Turn on audit geolocation?');
        $locationOffResponse->assertSee('data-confirm-label="Turn on"', false);

        $service->recordConsent($user, UserConsent::TYPE_AUDIT_BROWSER_LOCATION);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('Consent & Privacy Preferences');
        $response->assertSee('System Privacy Policy & Terms of Use');
        $response->assertSee('High-Accuracy Audit Geolocation');
        $response->assertSee('View consent history');
        $response->assertSee('consent-history-modal');
        $response->assertSee('Turn off audit geolocation?');
        $response->assertSee('data-confirm-label="Turn off"', false);
        $response->assertSee(config('privacy.policy_version', 'v1.0'));
        $response->assertSee('On since');
        $response->assertSee('Turn off');
        $response->assertDontSee('Active ('.config('privacy.policy_version', 'v1.0').')');
        $response->assertDontSee('Opted In');
    }

    public function test_consent_confirmation_page_has_accessible_markup(): void
    {
        $user = User::factory()->unconsented()->create();

        $response = $this->actingAs($user)->get(route('consent.privacy-policy'));

        $response->assertOk();
        $response->assertSee('id="privacy_consent"', false);
        $response->assertSee('for="privacy_consent"', false);
        $response->assertSee('aria-describedby="consent-description"', false);
        $response->assertSee('href="'.route('privacy.notice').'"', false);
        $response->assertSee('target="_blank"', false);
    }
}
