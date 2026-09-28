# HIMS Background Processing Scheduler Logs

**Checklist item:** Background Processing  
**Verification date:** 2026-09-29 (Asia/Manila)  
**Repository baseline:** `eb35090`  
**Environment:** Laravel 12.63.0, PHP 8.2.12  
**Result:** **Application implementation passes; deployed queue processing requires operational follow-up**

## Verification summary

The existing Laravel scheduler and queue implementation is complete and passed isolated execution, idempotency, failure, authorization, and rollback tests. No source-code defect was reproduced, so no scheduler, command, job, schema, or queue configuration was changed.

The configured MySQL database queue was inspected read-only and contained **527 pending jobs**, **0 reserved jobs**, and **1 failed job**. The failed row matches the fixed synthetic record created by `ErrorRecoveryDemoSeeder`; it is not an unexplained failure. The pending backlog is operational evidence that the deployment queue worker is absent, unhealthy, paused, or otherwise not consuming this database. Its exact infrastructure cause cannot be confirmed from the repository.

Accordingly, the checklist is satisfied at application/test level but is **not fully verified for the current deployment** until the worker is supervised, the backlog is reviewed and drained safely, and current host scheduler/worker logs are captured.

## Registered scheduled tasks

`routes/console.php` is the effective Laravel 12 scheduler source. `php artisan schedule:list` reported exactly these five tasks; the legacy `app/Console/Kernel.php` schedule method is not present in the effective schedule listing.

| Command | Purpose | Effective schedule | Coordination | Expected effect and evidence | Failure behavior | Status |
|---|---|---|---|---|---|---|
| `inventory:check-alerts` | Recalculate cached item totals; raise/resolve low-stock and expiry alerts | Daily at 01:00 PHT | Single scheduled invocation | Console reports raised/resolved counts; item totals and alert state verified in database | Command exception is surfaced to scheduler/host logs; inventory services preserve balance rules | Pass in isolation |
| `suppliers:check-compliance` | Raise/resolve accreditation, document, and contract expiry alerts | Daily at 01:15 PHT | `withoutOverlapping` | Console reports active/resolved counts; eligibility and alert changes verified | Failure is surfaced; repeated sweep does not duplicate active compliance alerts | Pass in isolation |
| `procurement:close-expired-rfqs` | Close published RFQs whose submission deadline elapsed | Every minute | `withoutOverlapping` | Console reports transitioned count; eligible RFQ changes to bidding-closed and receives procurement audit evidence | Only eligible published rows are selected; already-transitioned RFQs are not processed again | Pass in isolation |
| `privacy:enforce-retention` | Prune only expired ephemeral data while preserving permanent records | Daily at 02:00 PHT | `withoutOverlapping` | Command prints per-category counts; `--dry-run` safely reports candidates without deletion | Exceptions surface; permanent audit history is explicitly preserved | Pass in isolated dry-run/tests |
| `reports:run-scheduled` | Atomically claim due report occurrences and dispatch report generation | Every minute | `withoutOverlapping`, `onOneServer` | Unique execution history stores schedule, status, record count, mail state, and completion time | Queue-dispatch failure marks execution failed; generation/mail failures record safe summaries and application logs | Pass in isolation |

No scheduled backup task exists in this application repository. Backup/recovery documentation expects infrastructure-managed snapshots and recovery procedures; no duplicate application scheduler was invented. The manual `hims:create-super-admin`, `db:check`, and `inspire` commands are registered but are not recurring background tasks.

## Queued background jobs

| Job | Dispatch source | Queue controls | Success behavior | Failure / duplicate behavior | Status |
|---|---|---|---|---|---|
| `GenerateScheduledReport` | `reports:run-scheduled` | `ShouldQueue`, `ShouldBeUnique`; 3 tries; 240s timeout; 10-minute unique lock; per-execution `WithoutOverlapping`; 60/300s backoff | Rechecks current owner/recipient permissions, generates current scoped data, submits mail, and marks execution sent once | Duplicate scheduler runs/retries do not resend; unauthorized work is skipped; safe generation/mail failure state and log are recorded | Pass |
| `ProcessDataImport` | Bulk-import commit endpoint above background threshold | `ShouldQueue`; 1 try; 600s timeout | Rechecks active owner and target permission, commits through the transactional import executor, reports progress, and clears staging | Duplicate commit token dispatches once; failure rolls back all chunks, marks staging failed, and records a redacted failure audit | Pass |
| `WarmAiDemandForecast` | Dashboard cache miss through `AiDemandForecastService` | `ShouldQueue`; 1 try; 180s timeout; cache lock ownership | Generates only while the claimed cache entry remains pending and the requesting account remains active | Concurrent views enqueue once; inactive account skips; lock is released in `finally` | Pass |

No queued event/listener classes were found. Notification classes are synchronous on this baseline unless invoked by one of the queued jobs above.

## Scheduler registration log

Captured read-only at **2026-09-29 03:31 PHT**:

```text
0  1 * * *  php artisan inventory:check-alerts
15 1 * * *  php artisan suppliers:check-compliance
*  * * * *  php artisan procurement:close-expired-rfqs
0  2 * * *  php artisan privacy:enforce-retention
*  * * * *  php artisan reports:run-scheduled

schedule:list exit code: 0
```

All five command signatures were also present in `php artisan list`.

## Isolated execution log

The focused suite used the repository's testing configuration: SQLite `:memory:`, synchronous queue, array cache/session/mailer, and no external provider delivery.

```text
php artisan test --compact \
  tests/Feature/InventoryModuleTest.php \
  tests/Feature/SupplierManagementTest.php \
  tests/Feature/EnterpriseProcurementTest.php \
  tests/Feature/Privacy/DataRetentionTest.php \
  tests/Feature/ScheduledReportTest.php \
  tests/Feature/DataImportTest.php \
  tests/Feature/AiDemandForecastTest.php \
  tests/Feature/ErrorRecoveryTest.php \
  tests/Feature/RecoveryAndMetricsSeedingTest.php

209 passed (1,678 assertions), 71.31s
exit code: 0
```

Covered effects include inventory-alert rollups, supplier compliance, expired RFQ closure, retention dry-run and pruning boundaries, scheduled-report execution and delivery state, duplicate suppression, queued 1,000-row import completion, import rollback, forecast warm-up locking, queue failure recovery, retry outcomes, and failed-job diagnostics.

### Real database queue transport

A disposable SQLite file was migrated, configured with Laravel's `database` queue, given one synthetic `ProcessDataImport` job with a deliberately absent staging token, and processed with the real worker. It did not access the configured MySQL queue.

```text
queued=1
2026-09-29 03:34:30 App\Jobs\ProcessDataImport RUNNING
2026-09-29 03:34:30 App\Jobs\ProcessDataImport 20.22ms DONE
{"remaining":0,"failed":0}
queue worker exit code: 0
```

The temporary SQLite database was removed after verification.

### Existing end-to-end scheduler evidence

The previously retained isolated `schedule:work` evidence in `docs/evidence/scheduled-reports/README.md` records a scheduler-triggered report without manual command invocation:

```text
scheduled_for=2026-09-28 11:24:00 Asia/Manila
mail_sent_at=2026-09-28 11:24:04 Asia/Manila
status=sent
mail_status=accepted
record_count=1
next_run_at=2026-09-29 11:24:00 Asia/Manila
```

## Failure and application-log evidence

Only log bytes written by the focused suite were reviewed. Seventeen entries were generated by deliberate negative-path tests:

- 7 rejected-file content-inspection notices;
- 4 simulated recovery-attempt errors;
- 2 simulated scheduled-report generation/mail errors;
- 1 simulated transaction error;
- 1 simulated database-write error;
- 1 redacted import-parser error;
- 1 safe-fallback error.

The corresponding tests asserted diagnostic logging, safe public/error summaries, rollback or unchanged state, and absence of credential leakage. No unexplained test runtime error occurred.

## Configured queue status (read-only)

Captured at **2026-09-29 03:31 PHT** without reading payloads or mutating queue records:

```text
connection=database
pending_jobs=527
reserved_jobs=0
default_queue=526
notifications_queue=1
oldest_pending=2026-09-17 12:49:26 UTC
newest_pending=2026-09-28 17:37:54 UTC
failed_jobs=1
failed_at_as_stored=2026-09-24 18:35:42
```

The one failed row's non-payload metadata matches the deterministic recovery-demo fixture created by `ErrorRecoveryDemoSeeder`. No queued or failed payload, exception detail, credential, token, or personal data was displayed.

## Issue and required deployment action

| ID | Finding | Evidence | Code fix |
|---|---|---|---|
| BG-01 | Configured database queue has an unconsumed backlog | 527 pending, zero reserved, oldest pending since 2026-09-17 | None: job handlers and database transport pass; worker supervision is deployment state outside the repository |

Before marking the deployed checklist fully complete:

1. Confirm the intended host/environment for this database queue.
2. Inspect supervisor/service and worker logs without exposing payloads.
3. Start or restart a supervised worker using the deployment's approved configuration.
4. Review the backlog age and job classes, then drain it under operational monitoring; do not delete or bulk-retry blindly.
5. Confirm pending count decreases, new jobs complete, and no genuine failed jobs remain.
6. Confirm a supervised `schedule:run` trigger executes every minute and retain its host logs.
7. Run a short-term staging schedule and capture its execution history/provider acceptance as final deployment evidence.

Typical commands already documented by the project are:

```text
* * * * * cd /path/to/hims && php artisan schedule:run
php artisan queue:work --queue=default --tries=3 --timeout=240
```

The exact service manager, paths, user, queue list, restart policy, and log retention must be supplied by the deployment environment. No production worker was started and no queued/failed record was retried, deleted, or modified during this verification.

## Changes and regression status

- Application/source changes: **none**.
- Evidence added: this scheduler-log report only.
- Current full repository regression on the same application/test code: **1,647 passed, 12,466 assertions** in **353.33 seconds**.
- Remaining limitation: production scheduler invocation and queue-worker health require deployment access and operational approval.
