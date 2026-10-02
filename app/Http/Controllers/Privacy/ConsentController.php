<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Privacy\ConsentService;
use App\Support\AuthenticationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConsentController extends Controller
{
    /**
     * Display the mandatory Privacy Policy consent prompt for unconsented/outdated users.
     */
    public function showPrivacyPolicyConsent(Request $request, ConsentService $consentService): View|RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard();
        $user = $guard !== null ? auth($guard)->user() : $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $consentService->needsPrivacyPolicyConsent($user)) {
            return redirect()->to(AuthenticationContext::dashboardRoute());
        }

        return view('consent.privacy-policy', [
            'user' => $user,
            'hospitalName' => config('privacy.hospital_name', 'Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium (DJNRMHS)'),
            'policyVersion' => config('privacy.policy_version', 'v1.0'),
            'policyDocumentRef' => config('privacy.policy_document_ref', 'DPA-2012-HIMS-POL'),
            'policyEffectiveDate' => config('privacy.policy_effective_date', 'September 2026'),
            'dpoEmail' => config('privacy.dpo_email', 'privacy@djnrmhs.gov.ph'),
        ]);
    }

    /**
     * Process and record the mandatory Privacy Policy consent submission.
     */
    public function storePrivacyPolicyConsent(Request $request, ConsentService $consentService): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard();
        $user = $guard !== null ? auth($guard)->user() : $request->user();

        abort_unless($user instanceof User, 401);

        $expectedVersion = config('privacy.policy_version', 'v1.0');

        $validated = $request->validate([
            'privacy_consent' => ['required', 'accepted'],
            'policy_version' => ['required', 'string', 'in:'.$expectedVersion],
        ], [
            'privacy_consent.required' => 'You must actively check and agree to the Privacy Policy to proceed.',
            'privacy_consent.accepted' => 'You must actively check and agree to the Privacy Policy to proceed.',
            'policy_version.in' => 'The submitted Privacy Policy version is outdated or invalid. Please refresh the page.',
        ]);

        $consentService->recordConsent(
            user: $user,
            type: UserConsent::TYPE_PRIVACY_POLICY,
            version: $validated['policy_version'],
            isMandatory: true,
            source: 'login_prompt',
            request: $request,
        );

        $request->session()->put('profile_success', 'Privacy Policy acknowledgment recorded successfully.');

        return redirect()->intended(AuthenticationContext::dashboardRoute());
    }

    /**
     * Update or withdraw an optional user consent (e.g. Geolocation Audit Capture).
     */
    public function updateOptionalConsent(Request $request, ConsentService $consentService): RedirectResponse
    {
        $guard = AuthenticationContext::authenticatedGuard();
        $user = $guard !== null ? auth($guard)->user() : $request->user();

        abort_unless($user instanceof User, 401);

        $status = $request->input('status');
        if ($status === null && $request->has('granted')) {
            $status = $request->boolean('granted') ? 'consented' : 'withdrawn';
        }
        $request->merge(['status' => $status]);

        $validated = $request->validate([
            'consent_type' => ['required', 'string', Rule::in([UserConsent::TYPE_AUDIT_BROWSER_LOCATION])],
            'status' => ['required', 'string', Rule::in(['consented', 'withdrawn'])],
        ]);

        $type = $validated['consent_type'];
        $status = $validated['status'];

        if ($status === 'consented') {
            $consentService->recordConsent(
                user: $user,
                type: $type,
                version: config('privacy.policy_version', 'v1.0'),
                isMandatory: false,
                source: 'profile_settings',
                request: $request,
            );

            $message = 'Optional consent for high-accuracy geolocation audit logging has been granted.';
        } else {
            $consentService->withdrawConsent(
                user: $user,
                type: $type,
                request: $request,
            );

            $message = 'Optional consent for high-accuracy geolocation audit logging has been withdrawn.';
        }

        $request->session()->put('profile_success', $message);

        return redirect()->route('profile.edit')->with('status', 'consent-updated');
    }
}
