# Historical Reports Evidence

**Checklist:** Historical Reports  
**Verification requirement:** Historical data is available for analysis  
**Validated:** 2026-09-27

## Result

Historical reporting was already fully implemented before this review. No production route, controller, service, view, query, or export code was added or replaced.

The existing Reports & Analytics module provides:

- Today, 7-day, 30-day, 90-day, 12-month, all-time, and custom date ranges.
- Movement History, procurement expense, supplier commitments, consumption, movement-type, stock, valuation, location, and expiry reports.
- Category, storage location, supplier, movement type, and stock-status filters where applicable.
- On-screen totals, charts, historical ledgers, and a clearly displayed reporting period.
- JSON, CSV, Excel, and printable/PDF-oriented exports using the same report service and metadata.

## Historical report demonstration

The evidence test uses the existing **Movement History** report with a past custom period from 60 days ago through 30 days ago. Its isolated database contains:

| Stored record | Date/filter relationship | Expected report behavior |
|---|---|---|
| Archived Historical Medicine, Issuance, 7 units at ₱3.00 | Inside period; matching category, location, and type | Included |
| Archived Historical Medicine, Stock In, 5 units | Inside period; wrong movement type | Excluded |
| Other Historical Supply, Issuance, 13 units | Inside period; wrong category | Excluded |
| Archived Historical Medicine, Issuance, 11 units | 75 days ago; outside period | Excluded |

The matching item is archived after the movement is recorded. The historical ledger remains available because HIMS preserves operational history and uses an archive lifecycle rather than soft-deleting these records.

### Verified application result

| Report output | Direct database expectation | Application result | Status |
|---|---:|---:|---|
| Matching historical rows | 1 | 1 | Match |
| Total movements | 1 | 1 | Match |
| Total units moved | 7 | 7 | Match |
| Total movement value | ₱21.00 | ₱21.00 | Match |
| Included item | Archived Historical Medicine | Archived Historical Medicine | Match |
| Reporting period | Selected custom start/end dates | Same dates with `Custom Range` label | Match |

The JSON response is compared with a direct `stock_movements` query using the same inclusive date boundaries, category, location, and movement type. The CSV export contains the same item and totals and excludes the nonmatching item.

## Query and accuracy trace

- Movement history filters `stock_movements.moved_at` between the selected start-of-day and end-of-day boundaries.
- Procurement expense filters `purchase_orders.requested_at`; received procurement totals use `received_at` or accepted quality-release movements as appropriate.
- Consumption and movement-type analytics use the movement ledger's `moved_at` field.
- Current stock/valuation reports are identified in the UI as point-in-time snapshots; date controls apply to historical transaction sections rather than pretending current balances are historical snapshots.
- Database aggregation uses stored quantities, costs, statuses, and relationships; production report values are not hardcoded.

## Permissions and exports

- `ReportController` requires authentication and the `view_reports` permission for both page and export endpoints.
- Procurement financial reports additionally require `view_procurement_sensitive_data`; direct unauthorized requests return HTTP 403.
- Nonfinancial roles do not receive protected cost/value columns.
- Report exports record an `ExportedSystemReport` audit event. Guests are redirected to login and produce no export audit entry.
- JSON, CSV, Excel, and printable reports carry the report title, selected period, active filters, generation time, and requesting user.

## Verification evidence

- Baseline before evidence changes: **46 tests passed, 369 assertions** across `InventoryReportTest` and `ReportExportAuditTest`.
- Historical evidence test: **1 test passed, 11 assertions**.
- Final focused regression run: **47 tests passed, 380 assertions**.
- The final regression run added **0 bytes** to `storage/logs/laravel.log`.
- Existing coverage verifies preset/custom/all-time periods, invalid and future ranges, database totals, category/location/supplier/type/status filters, empty periods, every application role, protected financial reports, all report types, and all export formats.
- Browser-console capture was unavailable because the verification environment exposed no browser surface. Server responses, rendered contracts, exports, automated behavior, and application logs were checked instead.
