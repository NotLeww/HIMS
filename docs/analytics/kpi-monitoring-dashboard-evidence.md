# KPI Monitoring Dashboard Evidence

**Checklist item:** KPI Monitoring  
**Verification requirement:** KPIs display correct values  
**Evidence type:** Dashboard  
**Verified:** 2026-09-27

## Result

KPI monitoring was already implemented. No production code or test changes were required. The dashboard page, live endpoint, and dashboard API use the same calculated inventory summary, while reports use the existing report service and the same stock-status rules.

## Dashboard evidence

The deterministic dashboard fixture in `tests/Feature/DashboardAccuracyTest.php` was compared with direct database queries and produced the following values:

| Dashboard KPI | Database rule | Expected and displayed |
| --- | --- | ---: |
| Tracked items | Non-archived inventory items | 2 |
| Units on hand | Sum of non-archived item quantities | 105 |
| Inventory value | Sum of quantity multiplied by unit cost | PHP 215.00 |
| Needs reorder | Active items at or below reorder level | 1 |
| Out of stock | Active items with zero quantity | 0 |
| Expiring soon | Stocked batches expiring in 1-90 days | 1 |
| Critical expiry | Stocked batches expiring in 1-30 days | 1 |
| Suppliers | Non-archived suppliers | 2 |
| Active suppliers | Non-archived active suppliers | 1 |
| Inactive suppliers | Non-archived inactive suppliers | 1 |
| Storage locations | Stored locations | 2 |
| Open purchase orders | Orders in the shared open-status set | 9 |

The page-rendered values, `/dashboard/live`, and `/api/v1/dashboard-summary` matched those database-derived expectations. The pending purchase-order list also returned the five newest open orders in the expected order.

Additional verified dashboard states:

- Inventory changes from 100 to 40 units changed the low-stock count from 0 to 1; replenishment to 140 returned it to 0 through the live endpoint.
- An empty database returned zero-valued KPIs and empty activity lists without fabricated values.
- Archived inventory items and suppliers were excluded according to the application's archive rules; inactive inventory remained visible but was excluded from active-item alert counts.
- Inventory value was visible only to roles with financial permissions.
- The system-incident KPI was restricted to super administrators and counted only open recovery records.
- Forecast period controls preserved the calculated demand total across 7-, 30-, 60-, and 90-day chart horizons.
- Report KPI filters used the selected date, category, location, status, movement type, and supplier criteria. The location fixture correctly changed global stock of 100 units to 30 units at Main Pharmacy, including 5 reserved units and PHP 300.00 stock value.

No production KPI value is hardcoded. Fixed values above exist only in isolated test fixtures and assertions.

## Verification performed

```text
php artisan test tests/Feature/DashboardAccuracyTest.php tests/Feature/DashboardLiveEndpointTest.php tests/Feature/InventoryReportTest.php tests/Feature/AiDemandForecastTest.php tests/Feature/ExpiryClassificationWorkflowTest.php --stop-on-failure
87 passed (829 assertions)

php artisan test tests/Feature/ErrorRecoveryTest.php --filter=test_super_admin_dashboard_shows_an_alert_while_incidents_are_open
1 passed (5 assertions)
```

The verification run added 0 bytes to `storage/logs/laravel.log`. Browser-console and responsive visual inspection were not run because no browser surface was available in the execution environment.

For the complete query-to-database mapping and earlier discrepancy validation, see `docs/analytics/dashboard-accuracy-validation-report.md`.
