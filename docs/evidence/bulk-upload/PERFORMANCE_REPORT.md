# Bulk Upload Performance Report

## Result

HIMS successfully validated and imported 10,000 inventory items from each supported bulk format: CSV, JSON, and XLSX. Every run persisted the expected record count, preserved representative identifiers and category relationships, completed without timeout or memory exhaustion, and produced one start and one completion audit event.

## Architecture and changes

- Existing upload -> preview -> validate -> stage -> commit workflow retained.
- CSV input is read from a stream instead of copying the entire file into a second in-memory buffer.
- Validated imports of 1,000 or more records are dispatched through the existing Laravel queue.
- Import state is owner-scoped and retained for 24 hours; the UI polls real backend state and can resume after navigation from its saved status URL.
- Commits process 250-record chunks inside one database transaction. The import remains atomic: a later chunk failure rolls back every earlier chunk.
- New records use 250-row inserts. Existing records are preloaded per chunk instead of being fetched one at a time.
- Bulk imports write summary audit events rather than one audit row per imported record.
- Duplicate commit requests are atomically claimed in cache, so the same staged token is dispatched once.
- Validation remains unchanged for required fields, duplicates, references, permissions, file integrity, and business rules. Browser output is capped at 200 error details while retaining the true rejected-row count.

## Measured results

Command:

```shell
php artisan test tests/Feature/BulkUploadPerformanceTest.php --stop-on-failure
```

Environment: Windows, PHP 8.2.12, Laravel 12.63.0, SQLite in-memory database, array cache, synchronous test queue, 512 MiB PHP memory limit. Duration covers HTTP preview, parsing, validation, staging, queued-job execution, database commit, status retrieval, and integrity assertions.

| Format | Records | File size | Duration | Peak PHP memory | DB queries | Successful | Rejected | Result |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | --- |
| CSV | 10,000 | 458,911 bytes | 1.910 s | 100 MiB | 101 | 10,000 | 0 | Pass |
| JSON | 10,000 | 798,895 bytes | 1.226 s | 114 MiB | 101 | 10,000 | 0 | Pass |
| XLSX | 10,000 | 178,821 bytes | 1.357 s | 120 MiB | 101 | 10,000 | 0 | Pass |

An isolated 1,000-row CSV check dropped from 7.55 seconds before batched inserts to 2.02 seconds immediately after the optimization. This comparison includes framework startup and is therefore indicative; the 10,000-row table above is the final acceptance measurement.

## Integrity and reliability evidence

- Exact database count: 10,000 records after each format-specific run.
- First and last identifiers verified: `*-00001` and `*-10000`.
- Category foreign key verified on a representative record for every format.
- Leading-zero identifier suffixes remain text in XLSX.
- Forced duplicate-key failure after earlier chunks proves zero records remain after rollback.
- Same-token double submission queues exactly one job.
- Two users can initiate separate imports without sharing tokens or status data.
- Unauthorized and forged status requests receive no import state.
- A 500-invalid-row file reports all 500 rejected rows while limiting rendered details to 200.
- Small CSV, JSON, and XLSX imports, invalid-file detection, permissions, recovery, notifications, and audit-trail tests remain green.

## Verification summary

- Import and performance suites: 62 passed, 472 assertions after UI-status assertions.
- Recovery, audit, and notification suites: 77 passed, 589 assertions.
- PHP/Pint formatting and `git diff --check`: passed.

## Practical limits and deployment notes

- Verified maximum: 10,000 records per import and 10 MiB per file. Larger sizes are rejected with a clear validation message and are not claimed as supported.
- CSV parsing is streamed, while JSON decoding and the native XLSX XML parser retain the normalized dataset in memory. The measured 120 MiB maximum is below the tested 512 MiB limit.
- Production must use a real Laravel queue connection and active worker for background execution. A `sync` queue remains suitable for automated tests but processes within the request.
- Status history is operational cache data retained for 24 hours, not permanent import-history storage.
- The validation is structural and business-rule validation, not antivirus scanning.

## Evidence package

- This report and the reproducible performance test.
- Automated final status and database-count assertions.
- Measured metric output retained in `metrics.txt`.
- The browser connector exposed no browser surface in this environment, so a genuine screenshot could not be captured. The rendered UI contract and polling behavior are covered by the feature suite; capture the visible status panel during the next deployed 10,000-row import if a visual artifact is mandatory.
