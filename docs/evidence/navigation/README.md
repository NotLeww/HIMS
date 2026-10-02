# Navigation User Testing Evidence

Date: 2026-09-28  
Checklist item: Navigation  
Verification requirement: Navigation is intuitive and consistent  
Evidence type: User Testing

## Environment and scope

- Browser: Microsoft Edge 154.0.4258.37 on Windows, exercised through Edge's DevTools protocol.
- Application: isolated HIMS Navigation QA SQLite fixture on `127.0.0.1:8001`; the configured shared MySQL database was not used or changed.
- Viewports: 375 × 812, 768 × 1024, and 1280 × 900.
- Roles exercised: Super Administrator, Administrator, Inventory Manager, Warehouse Staff, Pharmacy Staff, Auditor, and Viewer.
- Device approval was disabled only on the disposable QA server through the existing `AUTH_DEVICE_SECURITY_ENABLED=false` environment switch so Navigation could be tested independently. Authentication and production security settings were not changed.

## User testing scenarios

| User role | Starting page | Navigation task and path | Context | Expected result | Actual result | Status |
|---|---|---|---|---|---|---|
| Administrator | `/dashboard` | Press `Tab` through Dashboard → Inventory → Smart Warehousing → Procurement & Sourcing → Supplier Management → Documents & Logistics → Administration | Edge, desktop, keyboard | Logical focus order; hidden submenu links are skipped | Focus reached Administration in that exact order; no hidden child received focus | Pass |
| Administrator | `/dashboard` | On Administration, press `Space`, `Tab`, `Shift+Tab`, `Escape`, `Tab`, `Shift+Tab`, `Enter`, then `Escape` | Edge, desktop, keyboard | Space/Enter opens; Tab enters submenu; Shift+Tab returns; Escape closes and restores focus; collapsed children are not tabbable; no trap or wrong navigation | Focus indicator was visible; Space and Enter opened; first child was User Management; Shift+Tab returned; Escape restored trigger focus; the next collapsed Tab target was Toggle navigation; no wrong route was activated | Pass |
| Administrator | `/dashboard` | Visit Dashboard, Inventory Items, Smart Warehousing, Procurement & Sourcing, Supplier Management, Documents & Logistics, and Reports; then use browser Back | Edge, desktop | Every destination loads, the matching major module remains active, and Back returns to the prior parent workflow | All seven routes loaded with an active navigation item; Back returned from Reports to Documents & Logistics | Pass |
| Administrator | `/dashboard` | Open with `Space`, close with `Escape`, reopen with `Enter`, select Inventory, and reopen after selection | Edge, 375 px, keyboard | Menu opens, closes, reopens, closes after navigation, and can be reopened on the destination | All five state transitions matched the expected result | Pass |
| Administrator | `/inventory/items` | Inspect page, body, main content, sidebar labels, and controls at 375, 768, and 1280 px | Edge, responsive | No page-level horizontal overflow, clipped navigation label, overlap, or inaccessible content | `documentElement`, `body`, and `main` stayed within their client widths at every tested viewport; navigation remained usable | Pass |
| Super Administrator | `/super-admin/dashboard` | Inspect major modules and Administration children | Edge, desktop | Full modules plus User Management, Roles & Permissions, Archive, Audit Trail, Privacy & Governance, Recovery Center, and Health Telemetry | All expected items rendered under the Super Admin panel | Pass |
| Administrator | `/dashboard` | Inspect major modules and Administration children | Edge, desktop | Operational modules plus User Management, Roles & Permissions, and Archive; no reserved Audit Trail or Recovery Center | Rendered exactly that scope | Pass |
| Inventory Manager | `/dashboard` | Inspect sidebar; request `/admin/users` directly | Edge, desktop | Operational modules without Administration; direct user administration denied | Operational modules rendered, Administration absent, direct request returned `403` | Pass |
| Warehouse Staff | `/dashboard` | Inspect sidebar; request `/admin/users` directly | Edge, desktop | Warehouse-relevant modules only; direct user administration denied | Inventory, Smart Warehousing, Procurement & Sourcing, and Documents & Logistics rendered; direct request returned `403` | Pass |
| Pharmacy Staff | `/dashboard` | Inspect sidebar; request `/admin/users` directly | Edge, desktop | Pharmacy-relevant modules only; direct user administration denied | Inventory, Procurement & Sourcing, and Documents & Logistics rendered; direct request returned `403` | Pass |
| Auditor | `/dashboard` | Inspect sidebar; request `/admin/users` directly | Edge, desktop | Read-oriented modules plus Archive and Audit Trail; no account-management or recovery links; direct user administration denied | Expected read/navigation scope rendered; direct request returned `403` | Pass |
| Viewer | `/dashboard` | Inspect sidebar; request `/admin/users` directly | Edge, desktop | Read-only module navigation without Administration; direct user administration denied | Expected read-only modules rendered, Administration absent, direct request returned `403` | Pass |

## Browser and server observations

- No navigation-component exception, failed navigation request, dead link, keyboard trap, or unexpected route activation was observed.
- Edge reported existing Demand Forecast page errors while Procurement-related content was loaded: `/inventory/demand-forecast/refresh` returned `422`, and its chart Alpine expressions referenced missing/null points. These errors are module-specific, not produced by the Navigation changes, and were not modified under this checklist.
- Direct `/admin/users` requests intentionally returned `403` for unauthorized roles and are recorded as successful authorization checks, not browser failures.

## Regression evidence

- Preserved prior focused result: **36 tests passed, 457 assertions**.
- Covered suites: `RoleBasedAccessTest`, `InventoryModuleTest`, `UiNavigationAuthorizationTest`, `SupplierManagementTest`, `DropdownNavigationTest`, and `SuperAdminAuthenticationTest`.
- Preserved prior Blade compilation result: passed. Blade compilation was rerun during final verification after the browser checks.

## Conclusion

Navigation passed the required user testing. The previously failing Edge keyboard sequence is fixed by removing collapsed dropdown descendants from the focus order while preserving expected disclosure behavior. The unrelated Demand Forecast console errors remain as a documented warning outside the Navigation scope.
