# TiDB Cloud Recovery Procedure

## Scope

HIMS documents its production database as **TiDB Cloud Serverless**. PingCAP renamed that plan to **TiDB Cloud Starter** on August 12, 2025; existing endpoints and data were unchanged. This runbook therefore follows the current Starter documentation. Confirm the plan shown in the TiDB Cloud console before a recovery, because Essential has different retention and point-in-time capabilities.

This procedure restores to a **new TiDB Cloud instance**. It must not overwrite or test against the source instance. The repository's SQLite recovery test is separate application-level evidence and does not prove this procedure.

## Prerequisites

- An approved incident or recovery drill, a recovery owner, a second operator for review, and agreed recovery point and recovery time objectives.
- Authorized TiDB Cloud console access to the correct organization and source instance.
- Confirmation of the instance plan, region, backup retention, latest successful automatic snapshot, and required recovery timestamp.
- Capacity and budget for a new isolated Starter instance.
- A secret-manager entry for new target credentials. Keep the stable HIMS `APP_KEY`, `APP_PREVIOUS_KEYS`, and `DB_BLIND_INDEX_KEY` available to the isolated verifier because restored encrypted application fields depend on them.
- An isolated HIMS verification deployment with outbound mail, workers, schedulers, webhooks, and integrations disabled until validation is complete.
- For an export-based recovery, encrypted object storage with least-privilege access and a retention policy. Prefer a cloud role over long-lived access keys where supported.

Never put passwords, access keys, tokens, connection strings, database dumps, or patient/employee data in tickets, terminal history, screenshots, or this repository.

## Backup and recovery mechanisms

### Managed automatic backups (primary)

TiDB Cloud automatically creates daily backups for Starter. At the time this runbook was verified, free Starter retention was one day; a paid Starter instance could retain up to 30 days. Starter did not support manual backups or point-in-time restore. Confirm current settings in **Data > Backup** before relying on a recovery point.

Use the TiDB Cloud snapshot restore workflow for incident recovery. It creates a new instance and leaves the source unchanged. TiDB database user credentials and grants are not restored, so provision new least-privilege credentials on the target.

### SQL export (secondary portable copy)

Before an authorized high-risk change, use **Data > Import > Export Data to** and export the complete HIMS database in SQL format to approved encrypted object storage. The managed export service provides a consistent export without locking online workloads. Record the task ID, selected database/tables, snapshot time, completion status, object location, retention, and a storage checksum or inventory result without recording secrets.

TiDB Cloud currently limits Starter/Essential exports to 1 TiB. Local Starter exports expire from TiDB Cloud's staging area after two days, so durable evidence must be downloaded or written directly to approved external storage. Restore an export only into an empty, isolated target through TiDB Cloud's SQL import workflow or an approved MySQL client with TLS verification.

## Managed snapshot recovery

1. Freeze recovery decisions. Record the incident time, desired recovery point, source instance identifier, plan, region, and approvers. Do not change or delete the source.
2. In the TiDB Cloud console, open the source Starter instance and select **Data > Backup**.
3. Confirm the chosen automatic snapshot is successful, unexpired, and earlier than the destructive/corrupting event. If no acceptable snapshot exists, stop and escalate to TiDB Cloud Support; do not improvise on production.
4. Select **Restore**, choose **Snapshot Restore**, select the approved snapshot, and enter a unique recovery-instance name containing the incident/drill reference. Review capacity and region, then start the restore.
5. Wait until the new instance is **Available**. A restore running longer than TiDB Cloud's documented three-hour limit is canceled and its new target is deleted; the source remains unchanged.
6. Create new target database credentials and grants using least privilege. Do not reuse or disclose the source password. Store credentials only in the deployment secret manager.
7. Point the isolated verification deployment—not production traffic—at the target using TLS identity verification and the committed public CA. Supply the existing application encryption/blind-index keys through the secret manager. Clear and rebuild configuration through the normal deployment workflow.
8. Keep queues, the scheduler, outbound mail, and external integrations disabled. Perform the checks below. If any check fails, leave production on the source, preserve logs without sensitive values, and investigate or choose another valid snapshot.
9. After approval, put HIMS in maintenance mode, stop workers and schedulers, prevent writes, update the production secret-managed endpoint to the validated target, rebuild configuration, restart services, and perform the post-reconnection checks.
10. Retain the old instance unchanged for the approved rollback window. Roll back by restoring the former endpoint and application services if validation fails; do not merge data ad hoc between instances.

## Export recovery

1. Create a new empty Starter target. Never import over the source or a target containing application data.
2. Verify the export task completed and the object set is complete, encrypted, within retention, and from the approved recovery point.
3. Grant TiDB Cloud time-limited, least-privilege read access to only the export prefix.
4. Open the target's **Data > Import** page, choose import from cloud storage, select SQL, map the complete exported HIMS schema and tables, review the scan results, and start the import. An approved TLS-configured MySQL client may be used instead when its version and procedure have already been validated.
5. Wait for completion and retain the import task ID/status as evidence. Revoke temporary object-storage access.
6. Run all verification and controlled reconnection steps below. Do not promote a partially imported target.

## Verification before promotion

Record aggregate results only; do not export sensitive rows.

1. Confirm the target endpoint/instance ID differs from the source and the HIMS connection uses TLS.
2. Run `php artisan db:check` from the isolated verifier and confirm the MySQL driver and TiDB server version.
3. Compare source-at-recovery evidence with target aggregates for `migrations`, `users`, `inventory_items`, `suppliers`, `purchase_orders`, `shipments`, `goods_receipt_notes`, `grn_line_items`, `inspection_acceptance_reports`, `warehouse_tasks`, `audit_logs`, and `system_recovery_records`.
4. Verify critical totals such as inventory quantity/value and purchase-order totals using approved aggregate queries.
5. Check for orphaned foreign keys across representative chains: supplier to purchase order, purchase order to goods receipt note, goods receipt note to line items, and goods receipt note to inspection/acceptance report. Treat zero-orphan query results as independent recovery evidence rather than relying only on constraint metadata.
6. Verify the expected migration set and application schema are present. Do not run migrations during restore validation unless an approved deployment requires them.
7. Through normal HIMS models and routes, use dedicated test accounts to read the dashboard, inventory item, supplier, purchase order, receiving, and inspection/acceptance views. Confirm encrypted fields can be read only by authorized flows.
8. Review application logs for connection, decryption, authorization, and SQL errors without exposing query values or secrets.

## Post-reconnection checks

- Confirm login, dashboard, inventory, procurement, receiving, audit search, session/cache persistence, and one reversible test transaction approved for the drill.
- Restart workers and the scheduler only after read validation; confirm queue processing and scheduled jobs do not duplicate work from the recovery interval.
- Re-enable outbound integrations deliberately and monitor error rate, latency, and database connections.
- Record the actual recovery point, data-loss window, start/end times, verification results, approvers, new instance ID, and rollback deadline in the incident record.
- Rotate temporary credentials and revoke temporary cloud-storage permissions. Apply the approved retention/disposal policy to exports and the old instance.

## Known limitations

- Starter automatic backups are daily; the free plan's short retention can make the available recovery point substantially older than the incident.
- Starter has no manual backup and no point-in-time restore. Essential supports point-in-time restore only within its supported retention and current feature status.
- Restores create a new instance, and database users/permissions are not restored.
- Restoring Starter/Essential instances larger than 1 TiB requires TiDB Cloud Support by default.
- No TiDB Cloud snapshot restore or export/import was executed for the repository evidence dated 2026-09-29.

## Official references

- [Serverless was renamed to Starter](https://docs.pingcap.com/tidbcloud/release-notes-2025/#august-12-2025)
- [Back up and restore TiDB Cloud Starter or Essential](https://docs.pingcap.com/tidbcloud/backup-and-restore-serverless/)
- [Export data from TiDB Cloud Starter or Essential](https://docs.pingcap.com/tidbcloud/serverless-export/)
- [Import SQL files from cloud storage](https://docs.pingcap.com/tidbcloud/import-sample-data-serverless/)
- [Import through a MySQL client](https://docs.pingcap.com/tidbcloud/import-with-mysql-cli-serverless/)
- [Configure external storage access](https://docs.pingcap.com/tidbcloud/serverless-external-storage/)

References were reviewed on 2026-09-29. Recheck them and the source instance's console settings before every production recovery because cloud capabilities and limits can change.
