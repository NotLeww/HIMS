# Interactive Charts Demonstration

**Checklist:** Interactive Charts  
**Verification requirement:** Charts support filtering and drill-down  
**Validated:** 2026-09-27

## Result

Filtering and drill-down were already implemented before this checklist review. No production component, endpoint, query, or dependency was added or replaced.

The existing implementation uses:

- `ReportController::index()` for validated period, custom date, category, location, supplier, movement-type, and stock-status filters.
- `InventoryReportService::build()` for the database-backed chart aggregates.
- Existing clickable chart bars for Inventory Health, Movement Activity, and Procurement Spend by Supplier.
- `ReportController::generate()` JSON responses for the records behind a selected chart bar.
- One existing Alpine drill-down modal with loading, success, empty, error/retry, search, card/table, Escape-key, and close-button states.
- The dashboard demand chart's existing item, category, risk, period, and actual/forecast controls, point inspector, and full forecast detail modal.

## Demonstration

The automated demonstration creates four stock-movement records:

- Matching Medicine / Main Pharmacy / Issuance / 1 day ago / 4 units.
- Matching Medicine / Main Pharmacy / Stock In / 1 day ago / 4 units.
- Other Supply / Ward Store / Issuance / 1 day ago / 4 units.
- Matching Medicine / Main Pharmacy / Issuance / 90 days ago / 4 units.

| Step | User-visible action | Verified result |
|---|---|---|
| 1. Original chart | Open Reports & Analytics with the default 30-day window | Movement Activity shows **Issuance: 2 movements, 8 units**. |
| 2. Apply filters | Select **Last 30 days**, category **Medicines**, location **Main Pharmacy**, and movement type **Issuance** | The server validates the filters and rebuilds the chart from the matching ledger scope. |
| 3. Filtered chart | View Movement Activity after applying the filters | Issuance changes to **1 movement, 4 units**. |
| 4. Select chart element | Select the **Issuance** chart bar | The existing drill-down modal requests `movement_history` JSON while preserving the same period, category, location, and movement-type filters. |
| 5. View matching details | Inspect the returned modal records | Exactly one row appears: **Matching Medicine**. Other Supply and the 90-day-old movement are absent. |

This sequence is executed by `test_chart_drilldown_matches_the_active_period_category_location_and_movement_type`, so the displayed aggregate and detail records are checked against actual isolated database rows rather than hardcoded production values.

## Other verified chart behavior

- Location filtering uses the selected location's stock balances for KPI and Inventory Health values. A fixture with 100 hospital-wide units and 30 units in Main Pharmacy reports 30 units, 5 reserved units, ₱300 value, and one low-stock item when Main Pharmacy is selected.
- Empty filtered datasets return an empty result without fabricated values; a 150-row drill-down returns all 150 records while the modal limits the initial visible set for usability.
- The dashboard demand chart updates for 7-, 30-, 60-, and 90-day forecast periods; each returned series covers the selected number of days and sums to the validated predicted demand.
- Rapid forecast period changes cancel stale requests, preventing an older response from replacing the latest selection.
- Chart point inspection uses bounded tooltip positioning, and the full forecast modal exposes the selected item's detailed forecast.

## Security and responsive behavior

- Reports require authentication and `view_reports` authorization on the controller.
- Procurement financial chart data is omitted for unauthorized roles, and a direct supplier-spend drill-down request returns HTTP 403.
- The drill-down uses a single labelled dialog, supports Escape and keyboard focus behavior, and provides responsive card and fixed-layout table modes without a page-level horizontal scrollbar.
- Existing live-dashboard polling remains separate from chart interaction and continues to reflect stock issues and replenishment accurately.

## Verification evidence

- Baseline focused run before evidence changes: **79 tests passed, 700 assertions**.
- Final focused regression run after adding the explicit demonstration assertions: **79 tests passed, 704 assertions**.
- Focused demonstration test: **1 test passed, 23 assertions**.
- `npm run build`: completed successfully with 86 modules transformed.
- The focused demonstration added **0 bytes** to `storage/logs/laravel.log`.
- Covered suites: `InventoryReportTest`, `AiDemandForecastTest`, and `DashboardLiveEndpointTest`.
- Browser-console interaction could not be captured because no browser surface was available in the verification environment. The existing interaction hooks, JSON responses, accessibility markup, responsive contracts, and production asset build were verified instead.
