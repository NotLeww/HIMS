# HIMS Backup Logs

**Checklist item:** Section 6 — Database Architecture / Backup Procedures  
**Verification date:** 2026-09-29 (Asia/Manila)  
**Requirement:** Automated backups are functioning  
**Overall result:** **Production verification incomplete; isolated backup verification passed**

## Architecture decision

HIMS documents production as TiDB Cloud Serverless, now named TiDB Cloud Starter. Production database backups are therefore owned by TiDB Cloud's managed backup service, not by Laravel Scheduler.

Current official TiDB documentation confirms that Starter and Essential receive automatic daily backups. Free Starter retention is one day; Starter with a spending limit and Essential support configurable retention from 1 to 30 days, with 14 days as the documented default. Manual backups are not supported for Starter or Essential. Snapshot restore is supported; point-in-time restore is available only for Essential as a preview capability.

No Laravel data-dump task was added. Doing so would duplicate the managed production control, introduce credential and storage risk, and would still not prove that the TiDB Cloud snapshot service is functioning.

Official references:

- [TiDB Cloud Starter and Essential backup and restore](https://docs.pingcap.com/tidbcloud/backup-and-restore-serverless/)
- [TiDB Cloud Starter and Essential data export](https://docs.pingcap.com/tidbcloud/serverless-export/)

## Verification status

| Control | Result | Evidence |
| --- | --- | --- |
| Automated application backup | **Not applicable** | No application data-backup command, package, job, script, or environment contract exists; production uses managed snapshots |
| Laravel backup schedule | **Not applicable / correctly absent** | `php artisan schedule:list` contains five operational tasks and no backup task |
| TiDB Cloud automatic-backup capability | **Verified from current official documentation** | Automatic daily backups are a managed Starter/Essential feature |
| TiDB Cloud backup configuration for the deployed HIMS instance | **Not directly verified** | No authorized TiDB Cloud console/session was provided |
| Latest production snapshot completion | **Not directly verified** | No provider task ID, timestamp, status, or console log was available |
| Isolated backup execution | **Pass** | SQLite `VACUUM INTO` created a non-empty backup during `DatabaseRestoreProcedureTest` |
| Isolated backup integrity | **Pass** | SQLite header, `PRAGMA integrity_check`, foreign-key validation, schema, counts, relationships, and application readability passed |
| Isolated restore | **Pass** | Backup restored into a separately named clean SQLite target and passed application checks |
| Backup artifacts in public/Git paths | **Pass** | No `.sql`, `.sqlite`, dump, or backup artifact is tracked in Git or stored under `public` |
| Backup logs | **Available for isolated verification only** | This report preserves the actual controlled-run log below |

The production checklist must not be marked fully passed until an authorized operator exports sanitized evidence from **TiDB Cloud → Data → Backup** showing the configured cycle/retention and a recent successful snapshot for the deployed HIMS instance.

## Actual isolated backup log

The following line was emitted by the final isolated backup/restore test run. The UUID identifies only a temporary test directory, which was deleted during test teardown.

```text
BACKUP_LOG task=database-restore-procedure type=sqlite-vacuum environment=testing status=completed identifier=database-restore-2e29c0b3-1260-4c3c-93f3-5d4417d1b503/backup.sqlite size_bytes=1658880 duration_ms=24667 integrity=ok foreign_key_violations=0
```

Additional verified results:

```text
Source and restored schemas: exact match
Representative non-empty tables compared: 12
Representative row counts: exact match
Restored inspection/acceptance relationships: 12 of 12 readable
Restored privacy consent relationship: readable
Authenticated restored application route: HTTP 200
Temporary backup/source/target files after teardown: deleted
```

This SQLite result proves the application-level backup/restore test and integrity checks. It is not presented as a TiDB Cloud backup log or proof of a production snapshot.

## Scheduler inspection log

```text
0  1 * * *  php artisan inventory:check-alerts
15 1 * * *  php artisan suppliers:check-compliance
*  * * * *  php artisan procurement:close-expired-rfqs
0  2 * * *  php artisan privacy:enforce-retention
*  * * * *  php artisan reports:run-scheduled

NO_APPLICATION_DATA_BACKUP_COMMANDS_REGISTERED
NO_BACKUP_ENVIRONMENT_CONFIGURATION_FOUND
```

The framework's `schema:dump` command is available, but it is a schema maintenance command—not an automated data backup—and is not scheduled.

## Local recovery artifacts

Two ignored files exist under `storage/app/backups`:

| File | Size | Last modified | Classification |
| --- | ---: | --- | --- |
| `system_recovery_records-pre-2026_09_17-20260917-203812.json` | 11,721 bytes | 2026-09-17 20:38 PHT | Partial table recovery snapshot |
| `system_recovery_records-pre-cleanup-20260917-204515.json` | 15,553 bytes | 2026-09-17 20:45 PHT | Partial table recovery snapshot |

These files are not full database backups, are not produced by an automated schedule, and do not prove TiDB Cloud backup health. They are excluded from Git by `storage/app/.gitignore` and are outside the public web root.

The Recovery Center previously reported any file in a local backup directory as a `healthy` backup. That diagnostic now reports local files as informational recovery artifacts and explicitly states that they do not verify TiDB Cloud managed backups.

## Automated test results

```text
php artisan test --compact tests/Feature/DatabaseRestoreProcedureTest.php tests/Feature/ErrorRecoveryTest.php
36 passed (316 assertions), 28.48s

php artisan test --compact tests/Feature/ComprehensiveDemoSeederTest.php
2 passed (26 assertions), 2.59s
```

The restore test now explicitly verifies that the backup file exists, is non-empty, and has the SQLite database header before copying it into the isolated target.

## Relationship with Restore Procedures

The backup and restore evidence now forms one consistent chain:

```text
TiDB Cloud automatic snapshot capability
    → production snapshot log still required from authorized console
    → documented restore-to-new-instance procedure
    → isolated application backup and restore verification
```

The production runbook remains `docs/TIDB_CLOUD_RECOVERY_PROCEDURE.md`. The isolated restore evidence remains `docs/evidence/database-recovery/RECOVERY_REPORT.md`. Neither document claims that a live TiDB snapshot, export, import, or restore was executed.

## Required production evidence

An authorized TiDB Cloud operator must retain a sanitized backup log containing:

- organization/instance reference suitable for internal identification, without connection strings;
- confirmed plan and region;
- configured daily backup cycle, backup time, and retention;
- latest snapshot identifier or provider task reference;
- snapshot start/completion timestamps and successful status;
- expiration timestamp or retention window;
- failed snapshot reason and provider escalation reference, if applicable;
- operator/reviewer and evidence-capture timestamp.

Do not include database passwords, access tokens, connection URLs, encryption keys, raw database contents, or patient/employee data.

## Safety statement

No production or configured TiDB database was connected to, read, dumped, modified, exported, restored, or otherwise operated on during this audit. No production backup file was created or committed.

