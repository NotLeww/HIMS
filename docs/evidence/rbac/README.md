# RBAC User Matrix Evidence

Audit date: 2026-09-29

## Verdict

The application already had a centralized RBAC implementation. This audit reused it and fixed two authorization/data-exposure gaps; it did not add a second role system, new roles, new permissions, or new database tables.

## Source of truth

- Roles: `app/Enums/UserRole.php`
- Permissions: `app/Enums/Permission.php`
- User authorization: `app/Models/User.php` (`hasPermission`)
- Gate registration: `app/Providers/AppServiceProvider.php`
- Live matrix: `/admin/permissions`, protected by `manage_users`
- Persistence: `users.role` and `users.status`; roles and permissions are backed enums, not database-managed RBAC records.

## Role summary

| Role | Granted permissions | Intended access |
|---|---:|---|
| Super Administrator | 57 | Full system administration through the dedicated super-admin panel |
| Administrator | 24 | User and operational administration, excluding high-risk stock operations and audit ownership |
| Inventory Manager | 47 | Inventory, procurement, warehousing, reporting, and forecasting operations |
| Warehouse Staff | 18 | Physical receiving, storage, movement, task, and custody operations |
| Pharmacy Staff | 13 | Pharmacy stock issue, transfer, requisition, inspection, and controlled-stock operations |
| Auditor | 12 | Read-only reports, audit, archive, and sensitive evidence review |
| Viewer | 6 | Read-only operational summaries without sensitive commercial or personal data |

## User Matrix

`Granted` means `UserRole::grants()` returns true for the permission. `—` means denied.

| Module | Permission | Super Administrator | Administrator | Inventory Manager | Warehouse Staff | Pharmacy Staff | Auditor | Viewer |
|---|---|---|---|---|---|---|---|---|
| Inventory | View stock levels (`view_inventory`) | Granted | Granted | Granted | Granted | Granted | Granted | Granted |
| Inventory | Adjust stock balances (`adjust_stock`) | Granted | — | Granted | — | — | — | — |
| Inventory | Approve stock adjustments (`approve_adjustment`) | Granted | Granted | Granted | — | — | — | — |
| Inventory | Manage inventory items (`manage_items`) | Granted | — | Granted | — | — | — | — |
| Records & Analysis | View reports (`view_reports`) | Granted | Granted | Granted | Granted | Granted | Granted | Granted |
| Records & Analysis | Manage scheduled reports (`manage_scheduled_reports`) | Granted | Granted | Granted | — | — | — | — |
| Records & Analysis | Generate demand forecasts (`generate_forecasts`) | Granted | Granted | Granted | — | — | — | — |
| Records & Analysis | View evidence-based process reviews (`view_process_reviews`) | Granted | Granted | Granted | — | — | Granted | Granted |
| Records & Analysis | Draft evidence-based process reviews (`create_process_review`) | Granted | — | Granted | — | — | — | — |
| Records & Analysis | Approve or reject process reviews (Maker-Checker) (`approve_process_review`) | Granted | Granted | — | — | — | — | — |
| Records & Analysis | Execute review corrective action recommendations (`implement_process_review`) | Granted | — | Granted | — | — | — | — |
| Procurement | View supplier profiles (`view_suppliers`) | Granted | Granted | Granted | — | — | Granted | Granted |
| Procurement | View sensitive supplier evidence and commercial data (`view_supplier_sensitive_data`) | Granted | Granted | Granted | — | — | Granted | — |
| Procurement | View procurement and purchase orders (`view_procurement`) | Granted | Granted | Granted | Granted | Granted | Granted | Granted |
| Procurement | View sensitive procurement financials and evaluations (`view_procurement_sensitive_data`) | Granted | Granted | Granted | — | — | Granted | — |
| Procurement | Manage suppliers (`manage_suppliers`) | Granted | — | Granted | — | — | — | — |
| Procurement | Review supplier compliance (`review_supplier_compliance`) | Granted | Granted | Granted | — | — | — | — |
| Procurement | Approve and suspend suppliers (`approve_suppliers`) | Granted | Granted | — | — | — | — | — |
| Procurement | Manage procurement (`manage_procurement`) | Granted | — | Granted | — | — | — | — |
| Procurement | Create department requisitions (`create_requisition`) | Granted | — | Granted | — | Granted | — | — |
| Procurement | Approve department requisitions (`approve_requisition`) | Granted | Granted | Granted | — | — | — | — |
| Procurement | Manage sourcing and RFQs (`manage_sourcing`) | Granted | — | Granted | — | — | — | — |
| Procurement | Evaluate supplier quotations and bids (`evaluate_bids`) | Granted | — | Granted | — | — | — | — |
| Procurement | Recommend and award sourcing events (`award_procurement`) | Granted | — | Granted | — | — | — | — |
| Procurement | Create and dispatch purchase orders (`issue_purchase_order`) | Granted | — | Granted | — | — | — | — |
| Procurement | Approve purchase orders and revisions (`approve_purchase_order`) | Granted | — | Granted | — | — | — | — |
| Procurement | Manage procurement categories and policy (`manage_procurement_policy`) | Granted | Granted | — | — | — | — | — |
| Stock Movements | Issue and dispense stock (`issue_stock`) | Granted | — | Granted | Granted | Granted | — | — |
| Stock Movements | Record stock movements (`record_movements`) | Granted | — | Granted | Granted | — | — | — |
| Stock Movements | Transfer stock between locations (`transfer_stock`) | Granted | — | Granted | Granted | Granted | — | — |
| Warehousing | Acknowledge stock alerts (`acknowledge_alerts`) | Granted | — | Granted | Granted | — | — | — |
| Warehousing | Inspect and release quarantine stock (`inspect_stock`) | Granted | — | Granted | Granted | Granted | — | — |
| Warehousing | Perform cycle counts (`perform_cycle_count`) | Granted | — | Granted | Granted | — | — | — |
| Warehousing | Manage storage locations (`manage_locations`) | Granted | Granted | Granted | — | — | — | — |
| Warehousing | Receive purchase order deliveries (`receive_purchase_order`) | Granted | — | Granted | Granted | — | — | — |
| Warehousing | View warehouse tasks and scans (`view_warehouse_tasks`) | Granted | Granted | Granted | Granted | — | Granted | — |
| Warehousing | Create, assign, and cancel warehouse tasks (`manage_warehouse_tasks`) | Granted | — | Granted | — | — | — | — |
| Warehousing | Execute assigned warehouse tasks (`execute_warehouse_tasks`) | Granted | — | Granted | Granted | — | — | — |
| Warehousing | Resolve warehouse exceptions (`resolve_warehouse_exceptions`) | Granted | — | Granted | — | — | — | — |
| Warehousing | Print internal warehouse labels (`print_warehouse_labels`) | Granted | Granted | Granted | Granted | — | — | — |
| Warehousing | Configure warehouse zones and spatial hierarchy (`manage_warehouse_topology`) | Granted | Granted | Granted | — | — | — | — |
| Warehousing | Monitor telemetry and release excursion holds (`manage_telemetry_excursions`) | Granted | — | Granted | — | — | — | — |
| Warehousing | Access and execute narcotics vault operations (`access_narcotics_vault`) | Granted | — | Granted | — | Granted | — | — |
| Warehousing | Record surgical consignment implant consumption (`record_consignments`) | Granted | — | Granted | Granted | Granted | — | — |
| Logistics & Records | View logistics and document tracking records (`view_logistics_records`) | Granted | Granted | Granted | Granted | Granted | Granted | Granted |
| Logistics & Records | View sensitive logistics evidence and custody details (`view_logistics_sensitive_data`) | Granted | Granted | Granted | Granted | Granted | Granted | — |
| Logistics & Records | Register shipments, DRs, and logistics records (`manage_logistics_records`) | Granted | — | Granted | Granted | — | — | — |
| Logistics & Records | Verify, approve, and review logistics document records (`verify_logistics_documents`) | Granted | — | Granted | — | Granted | — | — |
| Logistics & Records | Conduct technical inspection and sign IAR inspection portion (`perform_technical_inspection`) | Granted | — | — | — | Granted | — | — |
| Logistics & Records | Accept deliveries and post IAR to inventory ledger (`approve_iar_acceptance`) | Granted | — | Granted | — | — | — | — |
| Logistics & Records | Record and sign chain-of-custody transfer events (`manage_chain_of_custody`) | Granted | — | Granted | Granted | — | — | — |
| Administration | Manage users (`manage_users`) | Granted | Granted | — | — | — | — | — |
| Administration | View audit trail (`view_audit_trail`) | Granted | — | — | — | — | Granted | — |
| Administration | Review system failure records (`manage_system_recovery`) | Granted | — | — | — | — | — | — |
| Administration | Manage privacy and security governance (`manage_privacy_compliance`) | Granted | — | — | — | — | — | — |
| Administration | View archive (`view_archive`) | Granted | Granted | — | — | — | Granted | — |
| Administration | Archive and unarchive master records (`manage_archive`) | Granted | Granted | — | — | — | — | — |

## Enforcement evidence

- Every permission enum is registered as a Laravel Gate, and inactive users fail `hasPermission`.
- Staff, administrator, and super-administrator sessions use separate guards/panels with role-panel validation.
- Web controllers use route/controller middleware, `Gate::authorize`, or operation-specific checks for protected actions.
- `/api/v1` business routes use Sanctum authentication and Gate authorization; the two routes without a business Gate are authentication endpoints.
- Sidebar modules and actions use the same `@can`/`@canany` abilities as backend authorization.
- Ownership-sensitive records, including DSAR downloads and AI conversations/attachments, include object-level checks in addition to role permissions.

## Findings fixed

1. Import template downloads accepted any authenticated user. The target-specific permission check now runs for template downloads as it already did for preview and commit.
2. Global search exposed supplier contact details and procurement financial values to read-only users. Search fields and rendered metadata now honor the corresponding sensitive-data permissions.

## Verification

- Role matrix, direct URLs, allowed/denied actions, mutation prevention, sensitive data, dashboards, navigation, and permission-matrix access: 41 tests / 584 assertions.
- Permission enum and role mapping: 2 tests / 357 assertions.
- API, inventory CRUD/reporting, scheduled reports, and report-export audit coverage: 75 tests / 560 assertions.
- Authentication, user management, search, and import coverage: 150 tests / 1,064 assertions.
- Core audit total: 268 tests / 2,565 assertions.
- The inspected tail of `storage/logs/laravel.log` contained no matching `AuthorizationException`, `Forbidden`, or `PermissionDenied` entries.

The matrix above is generated from the same enum mapping used by the live `/admin/permissions` screen; it is evidence documentation, not a separate authorization source.
