# Source Code Security Scan Report

**Project:** HIMS  
**Checklist item:** Source Code Security  
**Scan date:** 2026-09-27 (Asia/Manila, UTC+08:00)  
**Final status:** PASS for the verified scope — the completed SAST, secrets, and DAST scans identified no unresolved critical vulnerabilities. The DAST coverage limits below remain applicable.

This status is limited to the tools, rules, reachable runtime surface, and source revision described below. It does not mean that the application is free of every possible vulnerability. Non-critical findings and coverage limits are retained in this report rather than suppressed.

## Inspected application

- Laravel 12.63.0 on PHP 8.2, with Blade, Alpine.js, Tailwind CSS, and Vite.
- MySQL is the configured application database; verification used a new isolated SQLite database under `storage/framework/testing` and did not modify the configured MySQL data.
- Authentication uses separate Laravel session guards for staff, administrator, and super-administrator panels, plus Sanctum for `/api/v1`.
- Authorization uses role/permission enums, gates, policy-style checks, route middleware, and controller checks.
- Request validation uses Form Requests and controller validation. Database access primarily uses Eloquent/query builder and parameter binding.
- Existing controls include MFA, lockout, password history/expiry, single-session and device approval enforcement, CSRF protection, security headers, audit logging, and authorization regression tests.

## SAST

**Tool:** Semgrep Community Edition 1.178.0  
**Final scan:** 2026-09-27 18:22:34 +08:00  
**Configuration:** Semgrep Registry `auto` rules over explicit application paths (`app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `tests`, and project manifests); generated assets were excluded. A separate `p/secrets` scan was run over Git-tracked repository files with dependencies, generated assets, storage, temporary files, and `docs/security/scans` excluded.  
**Coverage:** 254 applicable code rules over 673 source targets; 41 secret-detection rules over 712 targets in the separate secrets pass. Generated scan evidence was not scanned as application source.  
**Execution errors:** 0.

**Final secrets scan:** 2026-09-27 18:33:07 +08:00; 0 findings and 0 execution errors.

### Final SAST result

| Severity | Findings | Resolution |
|---|---:|---|
| Critical | 0 | None found |
| High / error | 0 | None found |
| Warning | 4 | Reviewed; no confirmed critical vulnerability |
| Secret findings | 0 | None found |

Reviewed warnings:

1. Two `unlink` warnings operate only on application-created temporary paths: one originates from `tempnam()` and the other from an internal DSAR temporary-file collection. No request-controlled deletion path was found.
2. The non-literal JavaScript regular expression is built from a fixed in-code list of browser camera error names, not user-controlled input. No regular-expression injection path was found.
3. One legacy public redirect document loads a remote font stylesheet without SRI. This is a genuine hardening observation but is not a critical vulnerability; the warning remains visible in the raw result.

No application source remediation was made because the SAST review found no confirmed critical issue. Rules were not disabled and findings were not suppressed.

## DAST

**Tool:** OWASP ZAP 2.17.0 traditional spider and active scanner  
**Target:** `http://127.0.0.1:8091/` only  
**Final scan:** 2026-09-27 18:17:20 +08:00  
**Environment:** local Laravel server with `APP_DEBUG=false`, a newly migrated isolated SQLite database, array cache, local file sessions, synchronous queue, non-delivering mail, and device-security integration disabled for the isolated scan. No production or configured MySQL data was targeted.

### Final DAST result

| ZAP risk | Alert types | Status |
|---|---:|---|
| Critical | 0 | None found |
| High | 0 | None found |
| Medium | 2 | Reviewed; unresolved non-critical hardening items |
| Low | 5 | Reviewed |
| Informational | 2 | Reviewed |

The final scan reproduced the initial alert result. No critical or high runtime vulnerability was identified within the successfully exercised surface.

Finding review:

- Missing CSP is a legitimate medium hardening gap on public responses. Existing responses still supply `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy`. CSP remediation was not folded into this critical-only checklist change because it requires compatibility design for the current frontend resources.
- Missing SRI and cross-domain JavaScript alerts were triggered by the local Vite HMR reference recorded in the existing untracked `public/hot` development marker. This development-only reference is not a built deployment asset.
- The no-HttpOnly alert names Laravel's `XSRF-TOKEN`, which is intentionally readable by JavaScript so clients can echo the CSRF token; the encrypted session cookie was observed with `HttpOnly` and `SameSite=Lax`.
- The `X-Powered-By` alert is emitted by the PHP development server. Production PHP/web-server configuration should suppress it.
- Missing `nosniff` alerts were limited to static icons and `robots.txt` served directly by the PHP development server; Laravel responses had the configured header.
- Large-redirect and authentication/session discovery alerts did not establish disclosure or bypass. They remain in the raw output for review.

Classification summary:

- **Confirmed non-critical findings:** missing CSP on public responses and missing SRI on the legacy remote-font stylesheet; dependency advisories are listed separately below.
- **Environment/framework findings:** Vite HMR resource alerts, the PHP development-server banner and direct static-file headers, and Laravel's intentionally JavaScript-readable `XSRF-TOKEN` cookie.
- **False positives after data-flow review:** the two Semgrep `unlink` warnings and fixed-list JavaScript regular-expression warning did not expose request-controlled sinks.
- **Informational findings:** large redirects and ZAP authentication/session discovery did not demonstrate disclosure, authentication bypass, or session compromise.

DAST coverage limits and environment diagnostics:

- The traditional spider and active scan completed and produced the final report. The browser-dependent DOM XSS rule was skipped because Firefox/geckodriver was unavailable in the scan environment. This is a coverage limitation, not a clean DOM XSS result.
- The OAST Log4Shell rule was skipped because no out-of-band testing service was configured. No OAST result is claimed.
- ZAP recorded one Firefox-profile initialization error, three related warnings, and a 1% network-failure insight. These diagnostics are retained in `storage/framework/testing/zap-home/zap.log`; they did not prevent the remaining traditional spider and active rules from completing.

Runtime checks supplementing ZAP confirmed that a protected API request with `Accept: application/json` returned 401, a login POST without a CSRF token returned 419, and malformed token input was rejected with validation status 422. No authenticated account was seeded for ZAP, so the active spider covered the public and unauthenticated attack surface; authenticated authorization behavior was verified through the application test suite below.

## Dependency audit (supplementary)

Dependency audits were preserved to avoid overlooking known vulnerable packages, although they are software-composition analysis rather than SAST/DAST:

- Composer: 0 critical, 9 high, and 7 medium advisories across `guzzlehttp/guzzle` and `league/commonmark`.
- npm: 0 critical, 3 high, and 1 moderate advisory.

These non-critical dependency advisories remain visible in the raw reports. They do not contradict the checklist's critical-vulnerability threshold, but they should be scheduled for compatible dependency updates and regression testing.

## Regression verification

Focused Laravel security tests completed successfully:

```text
Tests: 134 passed (1,013 assertions)
Duration: 20.51s
```

Coverage included security headers, all three authentication guards, role-based authorization, session-authenticated and unauthenticated API access, device/single-session controls, login lockout, inventory API operations, and password rules.

The local application served the scanned pages successfully. ZAP completed both traditional spiders and the remaining active rules, subject to the browser/OAST limitations documented above. No application source code was changed because no confirmed critical finding required remediation.

## Evidence artifacts

| Artifact | Purpose | SHA-256 |
|---|---|---|
| `semgrep-baseline.json` | Initial SAST result | See repository artifact |
| `semgrep-secrets-baseline.json` | Initial secret scan | See repository artifact |
| `semgrep-final.json` | Final source-only SAST result | `6D01CBF01D50990DE2271627E727255CBC15EF2D7339E173AF8B4C0B6A3C2A89` |
| `semgrep-secrets-final.json` | Final secret scan, excluding generated scan evidence | `54A8B46E0E84E1FE658590DABCCFCCD6B542FAFE3F87F849DAA21666A422DA8A` |
| `zap-initial.json` | Initial ZAP report | See repository artifact |
| `zap-final.json` | Final ZAP report | `31D0A3B3F0D3688A11FA72646357CF61FFB7797FBBAE4BB32617C610DBA4DAC0` |
| `composer-audit.json` | Composer advisory evidence | `A8932454C5828812C8836384929DC97BE5CF2C71788760CD138046EB1FEF6600` |
| `npm-audit.json` | npm advisory evidence | `63AB21FAD03C92A4D681195DEC3091C5A02F10EEBE8C8BE0A746DAD6507BF9CC` |

The evidence files were checked for Laravel session-cookie values, private-key blocks, and common environment-secret assignments; none were present.

## Reproduction commands

The installed tool paths are workstation-specific; the effective commands were:

```powershell
semgrep scan --config auto --json --output semgrep-final.json --exclude public\build app bootstrap config database public resources routes tests composer.json package.json vite.config.js
semgrep scan --config p/secrets --json --output semgrep-secrets-final.json --exclude vendor --exclude node_modules --exclude public\build --exclude storage --exclude tmp --exclude docs\security\scans .
zap.bat -cmd -dir <isolated-zap-home> -quickurl http://127.0.0.1:8091/ -quickout zap-final.json -quickprogress -config api.disablekey=true
composer audit --format=json
npm audit --json
php artisan test tests/Feature/Privacy/SecurityHeadersTest.php tests/Feature/RoleBasedAccessTest.php tests/Feature/SessionApiAccessTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/AdminAuthenticationTest.php tests/Feature/SuperAdminAuthenticationTest.php tests/Feature/DeviceSecurityAndSingleSessionTest.php tests/Feature/InventoryItemApiTest.php tests/Feature/LoginLockoutTest.php tests/Unit/PasswordStandardTest.php
```

## Final determination

The Source Code Security checklist requirement is satisfied for the verified scan scope: final Semgrep code and secret scans and the successfully executed OWASP ZAP rules show **no unresolved critical findings**. The medium/high non-critical observations and DAST coverage limits above are explicitly retained and must not be interpreted as a completely clean or exhaustive security posture.
