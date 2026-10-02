# Dashboard Accuracy Validation Report

**Checklist:** Operational Analytics & Dashboards — Dashboard Accuracy  
**Validated:** 2026-09-27  
**Method:** Automated feature tests against Laravel's isolated SQLite database, plus source tracing and a production asset build. No application records were edited for this validation.

## Scope and data flow

The inventory-manager and super-administrator dashboards render `resources/views/dashboard.blade.php` from `InventoryController::index()`. Initial stock KPIs and `/dashboard/live` both use `InventoryReportService`; `/api/v1/dashboard-summary` uses the same service. Forecast values come from `AiDemandForecastService` and `DemandForecastService`. The view derives forecast cards, filters, and chart series from the returned forecast item array rather than separate totals.

The validated dashboard components are:

- KPI cards: tracked items, units on hand, items needing reorder, out of stock, expiring soon, critical expiry, inventory value, storage locations, and open system incidents.
- Forecast area: current stock, historical consumption, predicted demand, reorder units, projected risk, confidence, item/period filters, and historical/forecast chart series.
- Inventory-attention list and live alert markup.
- Operational snapshot: supplier totals, active suppliers, storage locations, open purchase orders, and out-of-stock count.
- Pending purchase-order table and recent stock-movement table.
- Matching dashboard live and API payloads.

## Representative database comparison

The test database contained one active, one inactive, and one archived item; active and archived expiring batches; active, inactive, and archived suppliers; two storage locations; every purchase-order status; and two dated stock movements.

| Dashboard value | Database source and calculation | Expected from DB | Actual | Result |
|---|---|---:|---:|---|
| Tracked items | `inventory_items` where status is not `archived` | 2 | 2 | Match |
| Units on hand | Sum of `quantity_on_hand` for the same items | 105 | 105 | Match |
| Inventory value | Sum of `quantity_on_hand * unit_cost` for the same items | ₱215.00 | ₱215.00 | Match |
| Needs reorder | Non-archived items with quantity above zero and at/below reorder level | 1 | 1 | Match |
| Out of stock | Non-archived items with quantity at/below zero | 0 | 0 | Match |
| Expiring soon | Active, stocked batches expiring in 1–90 days whose item is not archived | 1 | 1 | Match |
| Critical expiry | Same batch set within the critical threshold | 1 | 1 | Match |
| Inventory attention | Non-archived low/out-of-stock items, ordered by urgency | Inactive item only | Inactive item only | Match |
| Suppliers | Suppliers where status is not `archived` | 2 | 2 | Match |
| Active suppliers | Suppliers with status `active` | 1 | 1 | Match |
| Inactive suppliers | Suppliers with status `inactive` | 1 | 1 | Match |
| Storage locations | All `storage_locations` rows | 2 | 2 | Match |
| Open purchase orders | Statuses for which `PurchaseOrderStatus::isOpen()` is true | 9 | 9 | Match |
| Pending PO table | Five newest open orders by request time, then ID | Direct DB top 5 IDs | Same 5 IDs | Match |
| Recent movements | Six newest by movement time, then ID | Newer row, older row | Same order | Match |
| Forecast incoming procurement | Quantity in open purchase orders for the item | 9 | 9 | Match |
| AI daily-summary open POs | Same shared open-status set | 9 | 9 | Match |

The live endpoint returned the same stock and expiry values. The API returned items `2`, on-hand `105`, low stock `1`, out of stock `0`, value `215`, suppliers `2`, active suppliers `1`, locations `2`, and the same movement order.

## Forecast, filters, roles, and live behavior

| Scenario | Expected | Actual | Result |
|---|---|---|---|
| Forecast from recorded movement history | Historical consumption 18; predicted demand 24 | 18; 24 | Match |
| Forecast chart totals | Each 7/30/60/90-day series spans its selected days and sums to predicted demand | All four periods satisfied both conditions | Match |
| Cached 60-day filter | 60-day period and saved 60-day result | Returned the cached 60-day result | Match |
| Empty forecast inventory | No fabricated series | HTTP 422 with an empty-inventory message | Match |
| Invalid AI item ID | Unknown database item is not rendered | Rejected by validation | Match |
| Financial restriction | User without financial permission must not receive inventory value | Value absent from live payload | Match |
| Authentication | Guest must not access live data | Redirected to login | Match |
| Super-admin recovery KPI | One open incident produces the recovery card; closed states are excluded by `open()` | Card rendered for one open incident | Match |
| Real-time stock issue | On-hand changes from 100 to 40; low-stock count from 0 to 1 | Live response returned 40 and 1 | Match |
| Real-time replenishment | On-hand changes to 140; low-stock count returns to 0 | Live response returned 140 and 0 | Match |
| Zero records | All stock, expiry, PO, supplier, and location counts are zero; value is 0.0; movements empty | Exact zero/empty values returned | Match |

Inventory and supplier records use an explicit `archived` lifecycle rather than Laravel soft deletes. Archived records were therefore included in the representative test and verified as excluded where the dashboard represents active catalogue data.

## Discrepancies and remediation

| Discrepancy | Root cause | Fix | Final result |
|---|---|---|---|
| Archived items inflated tracked items, units, valuation, reorder, out-of-stock, expiry, and attention values | Shared reporting and dashboard queries did not apply the catalogue's archive rule | Excluded archived items in the shared inventory snapshot, stock-status, expiry, live-expiry, and attention queries | Match |
| Archived suppliers inflated supplier total | Total supplier queries counted every row | Excluded status `archived` in page and API totals | Match |
| Open PO count/list missed valid workflow states | Controllers used an incomplete legacy status array | Added `PurchaseOrderStatus::openValues()` based on the existing `isOpen()` rule and reused it | Match |
| Forecast procurement and AI daily summary disagreed with the dashboard PO definition | Each service maintained a different status array | Reused `PurchaseOrderStatus::openValues()` in both services | Match |
| Equal-time movement/order rows could be returned inconsistently | Queries had no deterministic tie-breaker | Added descending ID as the secondary ordering | Match |

No dashboard total or business value was hardcoded. Fixed numeric values exist only in the isolated regression fixtures and assertions.

## Verification evidence

- Combined dashboard, live endpoint, inventory report, forecast, and expiry run — **86 passed, 814 assertions**.
- Focused super-admin recovery dashboard test — **1 passed, 5 assertions**.
- `vendor/bin/pint --dirty` — completed; only import ordering required formatting.
- `npm run build` — completed successfully; 86 modules transformed with no build errors.

All tested dashboard values matched their database records after remediation. Tests exercised rendered pages, JSON endpoints, authorization, forecast filters/calculations, empty state, archived state, and live updates. No production or shared database was queried or modified; browser-console inspection was not performed, while server rendering, endpoint behavior, frontend source assertions, and the production asset build completed without errors.
