# Scheduled Reports evidence

## Supported workflow

- Report types: every type exposed by `InventoryReportService::REPORT_TYPES`.
- Attachment formats: PDF, CSV, and the existing Excel-compatible `.xls` export.
- Frequencies: daily, weekly, and monthly in `Asia/Manila`; monthly days 29–31 clamp to the last day of shorter months.
- Recipients: active HIMS users with `view_reports`; attachment data is generated using the recipient's current permissions.
- Automation: Laravel runs `reports:run-scheduled` every minute. The command atomically claims due occurrences, records a unique execution, advances `next_run_at`, and dispatches `GenerateScheduledReport` through the configured queue.
- History: `scheduled_report_executions` records the scheduled time, report, format, recipient, generated record count, generation result, mail acceptance result, safe failure summary, and completion time.

## Verification completed

An isolated SQLite environment was used to create a short-term daily CSV schedule with current inventory data. Laravel's real `schedule:work` process reached the configured time without a browser or manual report-command invocation:

```text
scheduled_for=2026-09-28 11:24:00 Asia/Manila
mail_sent_at=2026-09-28 11:24:04 Asia/Manila
status=sent
mail_status=accepted
record_count=1
next_run_at=2026-09-29 11:24:00 Asia/Manila
```

The isolated scheduler test used the synchronous queue driver so it could not consume the shared development database's unrelated queue backlog. Normal HIMS operation continues to use the configured queue connection.

The focused and adjacent regression suites cover creation, persistence, due detection, current filtered data, CSV attachment contents, recipient authorization scope, email submission, history, last/next run timestamps, disabled schedules, duplicate prevention, retries, failure states, monthly date handling, editing, deletion with preserved history, audit events, and unchanged manual exports.

## Evaluation evidence to capture

1. Open **Reports & Analytics → Scheduled Reports** and capture the schedule row showing report, format, frequency/time, authorized recipient, and next run.
2. Let the configured time arrive with the server scheduler running; do not use a manual UI action.
3. Capture **Execution history and email log** showing the scheduled time, `Sent` result, `Accepted` email status, record count, recipient, and completion time.
4. Capture the received email and readable attachment in the configured test mail environment.
5. Retain the automated test output proving the due command used current filtered database data and did not duplicate a delivery.

Recommended checklist evidence entry:

> Scheduled-report execution history and email logs showing automatic report generation at the configured time, successful submission to the intended recipient, and a verified generated report attachment.

`Accepted` means the configured mail transport accepted the message. Final inbox delivery requires provider-side delivery logs or a received-email screenshot.

## Deployment requirement

Local development starts the web server, queue listener, and scheduler together with `composer run dev`.

Production automation still requires both a scheduler trigger and a queue worker. Configure the deployment host with equivalents of:

```cron
* * * * * cd /path/to/hims && php artisan schedule:run >> /dev/null 2>&1
```

```shell
php artisan queue:work --queue=default --tries=3 --timeout=240
```

Use the deployment platform's supervised services so both processes restart after failure. Confirm with `php artisan schedule:list`, worker health/logs, a short-term staging schedule, execution history, and the mail provider's accepted/delivered event. No production scheduler or provider delivery state is available inside this repository, so those two deployment checks must be completed in the deployed environment.
