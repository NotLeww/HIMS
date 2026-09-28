# Responsive Layout Evidence

Date: 2026-09-28  
Checklist item: Responsive Layout  
Evidence entry: **Responsive test results and desktop/tablet/mobile screenshots confirming that HIMS layouts reflow correctly, controls remain usable, and no page-level horizontal overflow occurs across representative screen sizes.**

## Environment and scope

- Browser: Microsoft Edge 154 on Windows through the DevTools protocol.
- Application: isolated SQLite QA copy on `127.0.0.1:8001`; the configured shared database was not used or changed.
- Viewports: 320×720, 375×812, 430×860, 768×1024, 1024×900, 1280×900, and 1440×900.
- Roles: Administrator and Inventory Manager.
- Browser checks: **161 passed, 0 responsive failures** (91 administrator + 70 operational).
- Laravel regression: **129 tests passed, 1,166 assertions**.

## Pages and components reviewed

- Shared app/guest shells, sidebar, topbar, search, notifications, user menus, page headers, fields, cards, tables, dropdowns, and modals.
- Login, MFA/OTP templates, and device-approval UI.
- Dashboard cards, alert/forecast panels, and chart containers.
- Inventory catalog/filter/table, Smart Warehousing, scan workstation, warehouse-task list/detail contracts, Procurement, Suppliers, inbound shipments, Reports, and scheduled reports.
- User Management, Roles & Permissions, and Audit Trail.

Each browser check verified the requested route, page/body/main width containment, actual horizontal page scrollability, visible control bounds, and intentional local table scroll regions.

## Issues found and fixed

1. Inventory filters could extend past `main` at 1024 px. The shared toolbar now wraps while preserving full-width mobile controls and the wide-screen row.
2. The permission matrix leaked its wide table into page-level scrolling at tablet widths. It now uses the shared table shell, whose layout/paint containment keeps dense data locally scrollable without widening the page; the sticky first column activates only on wide screens.
3. The Purchase Order filter toolbar used five rigid columns inside a split panel at 1024 px. It now uses two columns on tablet/small-laptop widths and returns to one row at `xl`.

The shared HIMS UI/UX skill now records permanent responsive acceptance rules for reflow, forms, tables, modals, charts, readable text, touch targets, accessibility, breakpoint verification, and zero page-level horizontal scrolling.

## Screenshots

- Authentication: `authentication-mobile-375.png`
- Dashboard: `dashboard-mobile-375.png`, `dashboard-tablet-768.png`, `dashboard-desktop-1440.png`
- Inventory: `inventory-mobile-375.png`, `inventory-tablet-768.png`, `inventory-desktop-1440.png`
- Inbound shipment modal: `inbound-shipment-modal-mobile-375.png`
- Warehouse scanner: `warehouse-scan-station-mobile-375.png`

Raw browser results are in `results.json` and `operational-results.json`.

## Verification notes and limitations

- The inbound shipment modal occupied 375×788 px inside a 375×812 viewport, had no document/modal horizontal overflow, and retained vertical access to the full form.
- The isolated fixture contained no warehouse task row, so a populated task detail screenshot was unavailable. `WarehouseTaskWebTest` passed the compact detail/scanner workspace and action-modal contracts.
- The existing Demand Forecast refresh endpoint returned `422` in the empty isolated fixture and produced its established error state. This was also observed before this task; it did not create responsive overflow, but a populated forecast chart was not visually verified.
- Expected authorization responses were observed where the Administrator role intentionally lacks scanner and Audit Trail access; the Inventory Manager pass covered the scanner successfully.
