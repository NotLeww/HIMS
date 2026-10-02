<?php

namespace App\Services\Privacy;

use App\Enums\AuditAction;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\AuditLogger;
use App\Support\AuditBrowserLocation;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ConsentService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Record explicit user consent in the database and audit trail.
     */
    public function recordConsent(
        User $user,
        string $type,
        ?string $version = null,
        ?bool $isMandatory = null,
        string $source = 'system',
        ?Request $request = null,
        ?array $metadata = null,
    ): UserConsent {
        $version = $version ?? config('privacy.policy_version', 'v1.0');
        $isMandatory = $isMandatory ?? ($type === UserConsent::TYPE_PRIVACY_POLICY);

        // Prevent duplicate active records for the identical type and version
        $existing = UserConsent::query()
            ->where('user_id', $user->id)
            ->where('consent_type', $type)
            ->where('policy_version', $version)
            ->where('status', UserConsent::STATUS_CONSENTED)
            ->whereNull('withdrawn_at')
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $consent = UserConsent::create([
            'user_id' => $user->id,
            'consent_type' => $type,
            'policy_version' => $version,
            'status' => UserConsent::STATUS_CONSENTED,
            'is_mandatory' => $isMandatory,
            'consented_at' => now(),
            'withdrawn_at' => null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            'source' => $source,
            'metadata' => $metadata,
        ]);

        $this->audit->record(
            action: AuditAction::GrantedConsent,
            description: "Granted consent for {$type} (version {$version}) via {$source}.",
            actor: $user,
            target: $consent,
            targetName: $user->name,
            newValues: [
                'consent_type' => $type,
                'policy_version' => $version,
                'is_mandatory' => $isMandatory,
                'status' => UserConsent::STATUS_CONSENTED,
                'source' => $source,
            ],
        );

        return $consent;
    }

    /**
     * Withdraw an optional consent. Mandatory system policies cannot be withdrawn.
     */
    public function withdrawConsent(User $user, string $type, ?Request $request = null): ?UserConsent
    {
        if ($type === UserConsent::TYPE_PRIVACY_POLICY) {
            throw new InvalidArgumentException('Mandatory system privacy policy consent cannot be withdrawn while maintaining an active account.');
        }

        $active = UserConsent::query()
            ->where('user_id', $user->id)
            ->where('consent_type', $type)
            ->where('status', UserConsent::STATUS_CONSENTED)
            ->whereNull('withdrawn_at')
            ->latest('consented_at')
            ->first();

        if ($active === null) {
            return null;
        }

        $active->update([
            'status' => UserConsent::STATUS_WITHDRAWN,
            'withdrawn_at' => now(),
        ]);

        if ($type === UserConsent::TYPE_AUDIT_BROWSER_LOCATION && $request?->hasSession()) {
            $request->session()->forget([AuditBrowserLocation::SESSION_KEY, 'browser_location']);
        }

        $this->audit->record(
            action: AuditAction::WithdrewConsent,
            description: "Withdrew consent for {$type} (version {$active->policy_version}).",
            actor: $user,
            target: $active,
            targetName: $user->name,
            oldValues: [
                'status' => UserConsent::STATUS_CONSENTED,
                'consented_at' => $active->consented_at?->toIso8601String(),
            ],
            newValues: [
                'status' => UserConsent::STATUS_WITHDRAWN,
                'withdrawn_at' => $active->withdrawn_at?->toIso8601String(),
            ],
        );

        return $active;
    }

    /**
     * Check if a user has an active consent for a specific type and optional version.
     */
    public function hasActiveConsent(User $user, string $type, ?string $version = null): bool
    {
        $query = UserConsent::query()
            ->where('user_id', $user->id)
            ->where('consent_type', $type)
            ->where('status', UserConsent::STATUS_CONSENTED)
            ->whereNull('withdrawn_at');

        if ($version !== null) {
            $query->where('policy_version', $version);
        }

        return $query->exists();
    }

    /**
     * Check if the user needs to provide or renew mandatory Privacy Policy consent.
     */
    public function needsPrivacyPolicyConsent(User $user): bool
    {
        $currentVersion = config('privacy.policy_version', 'v1.0');

        return ! $this->hasActiveConsent($user, UserConsent::TYPE_PRIVACY_POLICY, $currentVersion);
    }

    /**
     * Get a comprehensive summary of a user's consents and history for profile display.
     *
     * @return array<string, mixed>
     */
    public function getUserConsentSummary(User $user): array
    {
        $currentVersion = config('privacy.policy_version', 'v1.0');

        $policyConsent = UserConsent::query()
            ->where('user_id', $user->id)
            ->where('consent_type', UserConsent::TYPE_PRIVACY_POLICY)
            ->where('status', UserConsent::STATUS_CONSENTED)
            ->whereNull('withdrawn_at')
            ->latest('consented_at')
            ->first();

        $locationConsent = UserConsent::query()
            ->where('user_id', $user->id)
            ->where('consent_type', UserConsent::TYPE_AUDIT_BROWSER_LOCATION)
            ->latest('id')
            ->first();

        $history = UserConsent::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->take(10)
            ->get();

        return [
            'policy' => [
                'required_version' => $currentVersion,
                'consented' => $policyConsent !== null,
                'is_current' => $policyConsent?->policy_version === $currentVersion,
                'version' => $policyConsent?->policy_version,
                'consented_at' => $policyConsent?->consented_at,
            ],
            'optional_location' => [
                'type' => UserConsent::TYPE_AUDIT_BROWSER_LOCATION,
                'is_active' => $locationConsent !== null && $locationConsent->isActive(),
                'status' => $locationConsent?->status ?? 'unspecified',
                'consented_at' => $locationConsent?->consented_at,
                'withdrawn_at' => $locationConsent?->withdrawn_at,
            ],
            'history' => $history,
        ];
    }

    /**
     * Audit statistics for Admin Privacy Governance dashboard.
     *
     * @return array<string, int>
     */
    public function getConsentAuditStats(): array
    {
        $currentVersion = config('privacy.policy_version', 'v1.0');

        $totalActiveUsers = User::query()->where('status', 'active')->count();

        $currentVersionConsents = UserConsent::query()
            ->where('consent_type', UserConsent::TYPE_PRIVACY_POLICY)
            ->where('policy_version', $currentVersion)
            ->where('status', UserConsent::STATUS_CONSENTED)
            ->whereNull('withdrawn_at')
            ->distinct('user_id')
            ->count('user_id');

        $olderVersionConsents = UserConsent::query()
            ->where('consent_type', UserConsent::TYPE_PRIVACY_POLICY)
            ->where('policy_version', '!=', $currentVersion)
            ->where('status', UserConsent::STATUS_CONSENTED)
            ->whereNull('withdrawn_at')
            ->whereNotIn('user_id', function ($query) use ($currentVersion) {
                $query->select('user_id')
                    ->from('user_consents')
                    ->where('consent_type', UserConsent::TYPE_PRIVACY_POLICY)
                    ->where('policy_version', $currentVersion)
                    ->where('status', UserConsent::STATUS_CONSENTED)
                    ->whereNull('withdrawn_at');
            })
            ->distinct('user_id')
            ->count('user_id');

        $locationOptIns = UserConsent::query()
            ->where('consent_type', UserConsent::TYPE_AUDIT_BROWSER_LOCATION)
            ->where('status', UserConsent::STATUS_CONSENTED)
            ->whereNull('withdrawn_at')
            ->distinct('user_id')
            ->count('user_id');

        return [
            'total_active_users' => $totalActiveUsers,
            'current_version_consented' => $currentVersionConsents,
            'older_version_consented' => $olderVersionConsents,
            'pending_consent' => max(0, $totalActiveUsers - $currentVersionConsents),
            'location_opt_ins' => $locationOptIns,
        ];
    }
}
