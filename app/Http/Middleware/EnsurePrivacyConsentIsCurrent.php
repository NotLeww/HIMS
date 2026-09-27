<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Privacy\ConsentService;
use App\Support\AuthenticationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivacyConsentIsCurrent
{
    /** @var list<string> */
    private const EXEMPT_ROUTE_PATTERNS = [
        'consent.*',
        'logout',
        'admin.logout',
        'super-admin.logout',
        'privacy.notice',
        'terms',
        'terms.*',
        'password.*',
        'auth.device-approval.*',
    ];

    /** @var list<string> */
    private const EXEMPT_PATH_PREFIXES = [
        'consent',
        'logout',
        'admin/logout',
        'super-admin/logout',
        'privacy-notice',
        'privacy-policy',
        'privacy',
        'terms',
        'terms-of-use',
        'up',
    ];

    public function __construct(
        private readonly ConsentService $consentService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guardName = AuthenticationContext::authenticatedGuard();
        $user = $guardName !== null
            ? auth($guardName)->user()
            : $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $this->consentService->needsPrivacyPolicyConsent($user)) {
            return $next($request);
        }

        if ($this->isExempt($request)) {
            return $next($request);
        }

        $consentUrl = route('consent.privacy-policy');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Privacy policy acknowledgment is required before using the system.',
                'code' => 'CONSENT_REQUIRED',
                'redirect' => $consentUrl,
            ], 428)->header('X-HIMS-Consent-Required', $consentUrl);
        }

        return redirect()->guest($consentUrl);
    }

    private function isExempt(Request $request): bool
    {
        foreach (self::EXEMPT_ROUTE_PATTERNS as $pattern) {
            if ($request->routeIs($pattern)) {
                return true;
            }
        }

        $path = trim($request->path(), '/');

        foreach (self::EXEMPT_PATH_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
