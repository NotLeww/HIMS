# Dependency Security Report

**Project:** HIMS  
**Checklist item:** Dependency Security  
**Audit date:** 2026-09-27 (Asia/Manila, UTC+08:00)  
**Final status:** PASS - final Composer and npm audits report no known vulnerabilities, including zero critical vulnerabilities, in the locked production and development dependency sets.

This result is limited to advisories available to Composer/Packagist and npm at audit time. It is not a guarantee that undisclosed vulnerabilities do not exist.

## Environment

| Tool | Version |
|---|---|
| PHP | 8.2.12 |
| Composer | 2.10.3 |
| Laravel | 12.63.0 |
| Node.js | 24.20.0 |
| npm | 11.19.0 |

## Locked dependency inventory

The inventory was generated from `composer.json`, `composer.lock`, `package.json`, and `package-lock.json`. Lockfiles are the authoritative resolved-version inventory.

| Ecosystem | Direct production | Transitive production | Direct development | Transitive development | Resolved total |
|---|---:|---:|---:|---:|---:|
| Composer | 7 | 80 | 8 | 28 | 123 |
| npm | 2 | 1 | 10 | 222 | 235 |
| Combined | 9 | 81 | 18 | 250 | 358 |

The npm audit metadata reports four production dependencies because it includes the root project; the lockfile contains three resolved production package entries plus the root. Optional platform packages are included in npm's resolved total and 75 entries are marked optional.

All direct Composer packages were reviewed against application, framework package-discovery, CLI, test, or development use. All direct npm packages were reviewed against Vite/Tailwind configuration, application imports, chart rendering, camera scanning, or development scripts. No dependency was confirmed unused, so none was removed.

Resolved Composer download hosts are `github.com` and `api.github.com`; resolved npm packages use `registry.npmjs.org`. No arbitrary Git repository dependency, unofficial fork, abandoned Composer package, committed `.npmrc`, Composer auth file, npm token, or private repository credential was found.

## Initial findings

The pre-remediation evidence is retained in `docs/security/scans/2026-09-27/composer-audit.json` and `docs/security/scans/2026-09-27/npm-audit.json`.

| Scope | Critical | High | Moderate | Low | Notes |
|---|---:|---:|---:|---:|---|
| Composer production and development | 0 | 9 | 7 | 0 | 16 advisories affecting transitive `guzzlehttp/guzzle` and `league/commonmark` |
| npm production | 0 | 0 | 0 | 0 | Deployed browser dependencies were clean |
| npm development/build | 0 | 3 | 1 | 0 | Findings affected PostCSS and its transitive build-tool dependency chain |
| Combined | 0 | 12 | 8 | 0 | No critical finding; compatible fixes were available for all findings |

### Applicability and dependency paths

- `laravel/framework` required vulnerable `guzzlehttp/guzzle` 7.14.0. Guzzle is security-sensitive HTTP client infrastructure and the findings included host/cookie/redirect/proxy handling issues, so it was upgraded rather than accepted as unreachable.
- `laravel/framework` required vulnerable `league/commonmark` 2.8.2. The advisories included denial-of-service and unsafe-link/XSS parsing cases. Even though HIMS does not expose a general user-controlled Markdown editor, a supported compatible fix existed, so it was upgraded.
- `postcss` was a direct development dependency used by Tailwind/Vite. Its vulnerable `postcss`, `nanoid`, `browserslist`, and `baseline-browser-mapping` chain is build-time rather than deployed browser runtime, but it processes project/build inputs and was upgraded.

## Remediation

No manifest constraints, framework major versions, or package managers changed. Composer and npm updated only lockfile-compatible packages.

| Package | Before | After | Scope |
|---|---:|---:|---|
| `guzzlehttp/guzzle` | 7.14.0 | 7.15.5 | Composer production transitive |
| `guzzlehttp/promises` | 2.5.1 | 2.5.3 | Guzzle transitive |
| `guzzlehttp/psr7` | 2.12.4 | 2.13.1 | Guzzle transitive |
| `league/commonmark` | 2.8.2 | 2.10.3 | Composer production transitive |
| `nette/schema` | 1.3.5 | 1.3.6 | CommonMark transitive |
| `nette/utils` | 4.1.4 | 4.1.5 | CommonMark transitive |
| `postcss` | 8.5.16 | 8.5.28 | npm development direct |
| `nanoid` | 3.3.15 | 3.3.19 | PostCSS transitive |
| `browserslist` | 4.28.6 | 4.29.1 | Autoprefixer transitive |
| `baseline-browser-mapping` | 2.10.43 | 2.11.26 | Browserslist transitive |
| `update-browserslist-db` | 1.2.3 | 1.3.3 | Browserslist transitive |
| `node-releases` | 2.0.51 | 2.0.57 | Browserslist transitive |
| `electron-to-chromium` | 1.5.389 | 1.5.439 | Browserslist transitive |
| `caniuse-lite` | 1.0.30001805 | 1.0.30001812 | Browserslist transitive |

## Final audit results

| Scope | Critical | High | Moderate | Low | Total | Result |
|---|---:|---:|---:|---:|---:|---|
| Composer production only | 0 | 0 | 0 | 0 | 0 | PASS |
| Composer including development | 0 | 0 | 0 | 0 | 0 | PASS; no abandoned packages |
| npm production only | 0 | 0 | 0 | 0 | 0 | PASS |
| npm including development | 0 | 0 | 0 | 0 | 0 | PASS |

Acceptance result: **no known critical-severity vulnerabilities remain in the locked deployed HIMS dependency set.** The final scans also contain no known high, moderate, or low findings.

## Compatibility verification

- `composer validate --strict --no-check-publish`: passed.
- `composer install --no-interaction --prefer-dist`: passed; lockfile contents were installable and Laravel package discovery completed.
- Isolated `npm ci --no-fund` from the updated `package.json` and `package-lock.json`: passed; 165 packages installed and zero vulnerabilities reported.
- `php artisan route:list --except-vendor`: passed; 377 application routes discovered.
- Targeted HIMS regression suite: passed, 498 tests and 3,403 assertions. Coverage included login/logout, all panels, lockout, TOTP MFA, trusted devices and single-session enforcement, API sessions, inventory, Smart Warehousing, procurement, suppliers, barcode/QR scanning, reports, notifications, user management, roles and permissions, audit trail, AI flows, mail, and password reset.
- `npm run build`: passed with Vite 7.3.6; 86 modules transformed and all production assets emitted.

The full Laravel suite was also run: 1,558 tests passed and 22 failed. Independent reruns confirmed all 22 failures are existing privacy-consent fixture/expectation failures in `GlobalPasswordHistoryTest`, `SuperAdminPasswordConfirmationTest`, and `SuperAdminProvisioningTest` (HTTP 428 or redirects to consent before the asserted behavior). They are outside the changed dependency paths and are disclosed rather than suppressed. The focused dependency-regression suite above passed completely.

The workspace-level `npm ci` could not replace an in-use Windows `esbuild.exe`; the same lockfile then passed `npm ci` in an isolated temporary directory. A normal workspace `npm install` restored dependencies and reported zero vulnerabilities, and the production build passed.

## Evidence

- Initial Composer audit: `docs/security/scans/2026-09-27/composer-audit.json`
- Initial npm audit: `docs/security/scans/2026-09-27/npm-audit.json`
- Final Composer all-dependency audit: `composer-audit-final.json`
- Final Composer production-only audit: `composer-audit-production-final.json`
- Final npm all-dependency audit: `npm-audit-final.json`
- Final npm production-only audit: `npm-audit-production-final.json`
- Updated resolved versions: `composer.lock` and `package-lock.json`

Recommended checklist Evidence entry:

`Composer and npm dependency audit reports with remediation records and final scan results confirming no known critical vulnerabilities in the deployed HIMS dependency set.`

## Reproduction commands

```powershell
composer validate --strict --no-check-publish
composer audit --locked --format=json
composer audit --locked --no-dev --format=json
composer install --no-interaction --prefer-dist
npm.cmd audit --json
npm.cmd audit --omit=dev --json
npm.cmd ci --no-fund
npm.cmd run build
php artisan route:list --except-vendor
php artisan test
```

No CI configuration exists in the repository, so no new CI platform or dependency scanner was added solely for this checklist item.
