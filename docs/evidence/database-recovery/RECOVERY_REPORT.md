# HIMS Backup Restoration Recovery Report

**Report date:** 2026-09-29

**Checklist item:** Restore Procedures

**Verification requirement:** Backup restoration has been successfully tested

**Evidence scope:** Automated isolated application-level recovery plus documented TiDB Cloud production procedure

## Executive result

| Portion | Status | Evidence |
| --- | --- | --- |
| Isolated application-level backup and restore | **PASS** | `tests/Feature/DatabaseRestoreProcedureTest.php` |
| Restored schema and representative row counts | **PASS** | Source and target schema definitions matched; all 12 representative tables were non-empty and their counts matched |
| Database integrity | **PASS** | SQLite `PRAGMA integrity_check` returned `ok`; `PRAGMA foreign_key_check` returned no violations |
| Relationship preservation | **PASS** | 12 restored inspection/acceptance reports retained non-empty goods-receipt lines; restored user consent relationship remained readable |
| Application-level readability | **PASS** | Restored Eloquent models/relationships loaded and an authenticated inventory-manager dashboard request returned HTTP 200 |
| Production TiDB Cloud recovery procedure | **DOCUMENTED** | `docs/TIDB_CLOUD_RECOVERY_PROCEDURE.md` |
| Live/production TiDB restore | **NOT PERFORMED** | Prohibited by task safety constraints; no authorized isolated TiDB target/client environment was available |

**Checklist conclusion:** **PASS for the automated isolated restoration requirement, with production qualification.** A real backup was produced from a representative isolated HIMS database and restored into a separate clean database, then verified through the application. Production disaster-recovery readiness remains **documented but not execution-tested** until an authorized TiDB Cloud restore drill is completed.

## Capability found before this work

The audit found `App\Services\Recovery\SystemHealthService::checkBackups()`, which scans three local backup directories and reports file count/latest modification time. That is presence monitoring only. The repository had no complete database backup command, restore command, backup schedule, repeatable restore test, recovery runbook, or prior recovery report.

The application documentation and `.env.example` identify the shared production-compatible platform as TiDB Cloud Serverless over the MySQL protocol with mandatory TLS. PingCAP renamed Serverless to TiDB Cloud Starter in 2025; the procedure uses the current name while preserving the project's documented architecture.

## Safety constraints

- The configured/live database was not connected to, read, dumped, migrated, overwritten, or modified.
- PHPUnit was pinned to `APP_ENV=testing`, `DB_CONNECTION=sqlite`, and `DB_DATABASE=:memory:` by `phpunit.xml`.
- The test created only UUID-named SQLite files beneath `storage/framework/testing` and deleted the containing directory in `tearDown()`.
- No production credentials, tokens, connection strings, or production data were used or committed.
- SQLite results are not represented as proof of TiDB Cloud recovery.

## Automated isolated recovery methodology

`DatabaseRestoreProcedureTest` performs this lifecycle:

1. Creates a unique temporary directory and an empty SQLite source database.
2. Registers explicit `recovery_source` and `recovery_target` SQLite connections and makes only the source active.
3. Runs all application migrations against the source.
4. Runs the existing `ComprehensiveDemoSeeder`, which supplies coherent HIMS users, inventory, supplier, procurement, shipment, receiving, inspection, warehousing, audit, and recovery records.
5. Records current privacy-policy consent for the synthetic inventory manager through the existing `ConsentService`; this also exercises its audit trail.
6. Captures source schema definitions and row counts for 12 non-empty representative tables.
7. Uses SQLite's native `VACUUM INTO` to produce a consistent backup file.
8. Copies that backup into a separately named, previously absent recovery target.
9. Switches Laravel's default connection to the target and performs all remaining checks there.
10. Disconnects both isolated connections and deletes all temporary databases and backup files.

The isolated backup mechanism is intentionally SQLite-native because that is the project's safe automated test environment. Production uses TiDB Cloud's managed snapshot restore/export mechanisms described in the separate runbook.

## Representative restored data

The source and target counts matched for these non-empty tables:

- `users` and `user_consents`
- `inventory_items` and `suppliers`
- `purchase_orders` and `shipments`
- `goods_receipt_notes` and `grn_line_items`
- `inspection_acceptance_reports`
- `warehouse_tasks`
- `audit_logs`
- `system_recovery_records`

Meaningful records include the existing seeder's 12 `IAR-REV-2026-*` inspection/acceptance reports. After restore, every report retained its goods receipt note and at least one related line item. The restored inventory manager retained current privacy consent and could read the dashboard through the normal authenticated HTTP/application path.

## Integrity and readability checks

- Backup file creation succeeded and copying it into the clean target succeeded.
- Target database integrity returned `ok`.
- Target foreign-key validation returned zero violations.
- Source and target non-system table names and complete SQLite `CREATE TABLE` definitions matched.
- All selected source tables were non-empty and their exact target row counts matched.
- Restored Eloquent model casts and relationships loaded successfully.
- The restored consent relationship was queryable through `User::consents()`.
- An authenticated request using the restored inventory-manager model rendered the dashboard with HTTP 200.

## Exact automated result

Final focused command:

```text
php artisan test tests/Feature/DatabaseRestoreProcedureTest.php
```

Final result recorded after completion:

```text
PASS  Tests\Feature\DatabaseRestoreProcedureTest
Tests: 1 passed (11 assertions)
```

The first continuation run reached the restored database and passed nine assertions, then failed only because the seeded user correctly lacked mandatory current privacy consent and was redirected. The source setup was corrected to record synthetic consent through the real service before backup; no restore assertion or production safeguard was weakened.

Adjacent regression commands also passed:

```text
php artisan test tests/Feature/ComprehensiveDemoSeederTest.php
Tests: 2 passed (26 assertions)

php artisan test tests/Feature/ErrorRecoveryTest.php
Tests: 35 passed (299 assertions)

php -l tests/Feature/DatabaseRestoreProcedureTest.php
No syntax errors detected

vendor/bin/pint --test tests/Feature/DatabaseRestoreProcedureTest.php
passed
```

Across the focused and adjacent PHPUnit runs: **38 tests passed with 336 assertions**.

## Production TiDB Cloud recovery

The production runbook is [TiDB Cloud Recovery Procedure](../../TIDB_CLOUD_RECOVERY_PROCEDURE.md). It documents:

- automatic snapshot selection and restore to a new Starter instance;
- a portable SQL export/import alternative;
- plan/retention prerequisites and Starter limitations;
- least-privilege credentials, TLS, secret-manager handling, and encryption-key continuity;
- aggregate, relationship, migration, model, route, and log verification;
- controlled application reconnection, worker/scheduler handling, rollback, monitoring, and evidence capture.

No TiDB Cloud operation was executed locally. Current official documentation says Starter automatic backups are daily, snapshot restore creates a new instance, manual backup and point-in-time restore are unavailable for Starter, database users/permissions are not restored, and instances over 1 TiB require support for restore by default. These claims must be rechecked before a drill.

## Limitations and next required production evidence

- SQLite and TiDB differ in engine behavior; this test proves application-level recoverability, not TiDB snapshot compatibility or cloud operational access.
- Recovery time objective, recovery point objective, network/TLS cutover, cloud permissions, target capacity, restored database grants, and real production-scale duration were not exercised.
- Full production verification requires an authorized non-production TiDB Cloud drill: restore an approved snapshot to a new isolated instance, validate aggregates/relationships/application reads, record timing and recovery point, then dispose of the target under policy.
- No MySQL/TiDB client or Docker-based compatible target was available on this development machine.

## Evidence files

- `tests/Feature/DatabaseRestoreProcedureTest.php`
- `docs/TIDB_CLOUD_RECOVERY_PROCEDURE.md`
- `phpunit.xml`
- `database/seeders/ComprehensiveDemoSeeder.php`
- `app/Services/Recovery/SystemHealthService.php`
- `docs/CLOUD_DATABASE.md`

No dump or temporary database is retained as evidence; the deterministic test recreates and removes its synthetic artifacts on every run.
