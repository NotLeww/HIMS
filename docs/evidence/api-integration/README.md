# API Integration Documentation and Verification Evidence

Date: 2026-09-28

Checklist item: **API Integration**  
Verification requirement: **REST APIs respond correctly with proper authentication and error handling.**  
Evidence: **API Documentation**

## Audit Result

Result: **Pass after focused corrections.**

HIMS already had 76 registered `/api/v1` routes, Sanctum authentication, permission-based authorization, server-side validation, API resources/workflow JSON responses, idempotency support for procurement and inventory workflows, safe unexpected-error handling, and browser integrations that use the same API. The audit preserved that architecture.

The audit found and corrected these specific gaps:

- malformed numeric workflow identifiers reached typed controller arguments and returned `500`; numeric route constraints now return a safe `404`;
- a stateful session could reach the bearer-token logout action and call `delete()` on Sanctum's transient token; the action now returns `400` unless a real bearer token is supplied;
- two procurement business-rule failures surfaced as `500`; draft/rejected PR sourcing and ineligible supplier awards now return `422`;
- JSON model-binding `404` responses exposed internal PHP model class names; JSON `404` messages are now uniformly `Not Found`;
- token device names could exceed the persistence limit; they are now limited to 255 characters and rejected with `422`;
- eight core collection endpoints now cap `per_page` to `1..100`, matching the existing supplier endpoint convention and preventing unbounded page sizes;
- the existing OpenAPI file described only token issuance and part of the inventory-item API. It has been updated and this document is the complete endpoint, authentication, validation, response, and verification catalog.

## Base URL, Media Type, and Authentication

- Base path: `/api/v1`
- Request/response media type: `application/json`, except supplier creation may use `multipart/form-data` for `logo`.
- Protected endpoints use `auth:sanctum` plus server-side `can:<permission>` middleware.
- First-party Blade pages use a stateful Sanctum session cookie. Unsafe browser requests must carry Laravel's CSRF/XSRF token.
- Bearer tokens are supported only when `auth.device_security.enabled` is disabled. With the default single-device policy enabled, direct token issuance returns `428 DEVICE_SECURITY_REQUIRED`, and existing bearer tokens are rejected on operational endpoints with `401 DEVICE_SECURITY_REQUIRED`.
- `POST /auth/logout` is intentionally bearer-token-only. Browser sessions use the panel's normal web logout route.
- The token endpoint uses `LoginLockoutService` for progressive failure delays and temporary lockout. No separate general API rate-limit middleware is configured.
- All procurement and enterprise inventory workflow endpoints pass through `EnsureIdempotency`. An optional `Idempotency-Key` replays a successful response for 30 minutes; reuse with a different payload returns `409`.

## Authorization Model

Permissions, not client-side visibility, authorize every operation. Roles receive permissions through `App\Enums\UserRole`; Gates are registered in `AppServiceProvider`.

| Permission | API capability |
| --- | --- |
| `view_inventory` | Read items, movements, locations, receipts, requisitions, adjustments, replenishment status, and dashboard inventory data |
| `manage_items` | Create or update inventory item masters |
| `record_movements` | Record stock movements |
| `manage_locations` | Create/update storage locations; status changes additionally require Super Administrator |
| `view_suppliers` / `manage_suppliers` | Read or maintain suppliers |
| `view_procurement` / `manage_procurement` | Read core procurement records or manage enterprise purchase requests |
| `view_procurement_sensitive_data` / `manage_sourcing` | Read quotes or create/update sourcing records and RFQs |
| `create_requisition` / `approve_requisition` / `issue_stock` | Create, approve/reject, pick, or issue requisitions |
| `issue_purchase_order` / `approve_purchase_order` / `receive_purchase_order` | Create, amend, or receive purchase orders |
| `generate_forecasts` / `view_reports` | Maintain or read demand plans |
| `perform_cycle_count` / `approve_adjustment` / `adjust_stock` | Count, approve, or request stock corrections |
| `inspect_stock` | Release or reject quality-control inspections |
| `view_warehouse_tasks` / `manage_warehouse_tasks` / `execute_warehouse_tasks` | Read, create/assign/cancel, or execute warehouse tasks |
| `evaluate_bids` / `award_procurement` | Submit/evaluate bids or record sourcing awards |

An authenticated user without the named permission receives `403`; the action is not performed.

## Endpoint Catalog

All endpoints below are real registered routes. Unless marked public, each requires Sanctum authentication and the permission shown.

### Authentication

| Method | Path | Access | Request and result |
| --- | --- | --- | --- |
| `POST` | `/auth/token` | Public, progressive login lockout | Requires `email`, `password`; optional `device_name` string up to 255. Returns `200 {"token":"..."}` when token access is enabled, `401` invalid credentials, `422` validation, `428` device security/MFA/password renewal, or `429` throttled/locked. Super Administrator tokens are not issued here. |
| `POST` | `/auth/logout` | Authenticated bearer token | Revokes only the current personal access token and returns `204`. Returns `401` without authentication and `400` when called with a stateful session instead of a bearer token. |

### Core resource APIs

Collection endpoints accept optional `per_page`; values are bounded to `1..100`. GET collections return Laravel pagination (`data`, `links`, `meta`). Resource GET/PATCH responses use `{ "data": ... }`; creates return `201`.

| Method | Path | Permission | Purpose / body contract | Success |
| --- | --- | --- | --- | --- |
| `GET` | `/dashboard-summary` | `view_inventory` | Inventory dashboard summary. Financial and supplier fields are omitted unless the caller has the matching sensitive-data/supplier permissions. | `200` |
| `GET` | `/inventory-items` | `view_inventory` | Paginated item masters. | `200` |
| `POST` | `/inventory-items` | `manage_items` | Requires unique `sku` and `name`; validates barcode/GTIN uniqueness, active category/location/supplier references, UOM, tracking flags, classifications, nonnegative quantities/cost, and item status. | `201` |
| `GET` | `/inventory-items/{inventory_item}` | `view_inventory` | Read one item with permitted supplier/financial fields and expiry batches. | `200`, `404` |
| `PUT/PATCH` | `/inventory-items/{inventory_item}` | `manage_items` | Partial item update using the same field rules; unique values ignore the current item. | `200`, `404`, `422` |
| `GET` | `/suppliers` | `view_suppliers` | Paginated suppliers. | `200` |
| `POST` | `/suppliers` | `manage_suppliers` | Requires `name`; validates contact fields, business structure, phone, lead time, and optional JPG/PNG logo (max 3 MB, max 4096x4096, verified content). | `201` |
| `GET` | `/suppliers/{supplier}` | `view_suppliers` | Read one supplier; sensitive fields are permission-filtered by the resource. | `200`, `404` |
| `PUT/PATCH` | `/suppliers/{supplier}` | `manage_suppliers` | Partial supplier update; no permanent-delete endpoint exists. | `200`, `404`, `422` |
| `GET` | `/purchase-orders` | `view_procurement` | Paginated purchase orders. | `200` |
| `POST` | `/purchase-orders` | `issue_purchase_order` | Requires eligible `supplier_id`, active `item_id`, active `cost_center_id`, and quantity `1..1,000,000`; validates unit/conversion, future delivery date, payment terms, Incoterms, notes. | `201`, `422` |
| `GET` | `/purchase-orders/{purchase_order}` | `view_procurement` | Read one purchase order. | `200`, `404` |
| `PUT/PATCH` | `/purchase-orders/{purchase_order}` | `approve_purchase_order` | Partial update; status must be a `PurchaseOrderStatus` enum value. | `200`, `404`, `422` |
| `GET` | `/stock-movements` | `view_inventory` | Paginated newest-first append-only stock ledger. | `200` |
| `POST` | `/stock-movements` | `record_movements` | Requires existing `item_id`, valid movement type, and positive integer `quantity`; validates batch/location references, cost, and remarks. The inventory automation service owns balance changes. | `201`, `422` |
| `GET` | `/stock-movements/{stock_movement}` | `view_inventory` | Read one ledger movement. Update/delete routes are intentionally absent. | `200`, `404` |
| `GET` | `/storage-locations` | `view_inventory` | Paginated locations. | `200` |
| `POST` | `/storage-locations` | `manage_locations` | Requires `name` and supported `type`; validates code, hierarchy, classification, capacity, flags, and status. | `201`, `422` |
| `GET` | `/storage-locations/{storage_location}` | `view_inventory` | Read one location. | `200`, `404` |
| `PUT/PATCH` | `/storage-locations/{storage_location}` | `manage_locations`; Super Administrator for status change | Partial update with hierarchy and uniqueness validation. | `200`, `403`, `404`, `422` |
| `GET` | `/procurement-requests` | `view_procurement` | Paginated legacy procurement requests. | `200` |
| `POST` | `/procurement-requests` | `create_requisition` | Requires unique `request_number` and `title`; validates item, positive quantity, eligible supplier, dates, approval user, and numeric score. | `201`, `422` |
| `GET` | `/procurement-requests/{procurement_request}` | `view_procurement` | Read one request. | `200`, `404` |
| `PUT/PATCH` | `/procurement-requests/{procurement_request}` | `manage_procurement` | Partial request update. | `200`, `404`, `422` |
| `GET` | `/supplier-quotes` | `view_procurement_sensitive_data` | Paginated supplier quotes. | `200` |
| `POST` | `/supplier-quotes` | `manage_sourcing` | Requires existing `procurement_request_id`, eligible `supplier_id`, and nonnegative `quoted_price`. | `201`, `422` |
| `GET` | `/supplier-quotes/{supplier_quote}` | `view_procurement_sensitive_data` | Read one quote. | `200`, `404` |
| `PUT/PATCH` | `/supplier-quotes/{supplier_quote}` | `manage_sourcing` | Partial quote update with the same reference/price checks. | `200`, `404`, `422` |
| `GET` | `/demand-plans` | `view_reports` | Paginated demand plans. | `200` |
| `POST` | `/demand-plans` | `generate_forecasts` | Requires unique `plan_number` and existing `item_id`; numeric stock/usage/need/reorder inputs must be nonnegative. | `201`, `422` |
| `GET` | `/demand-plans/{demand_plan}` | `view_reports` | Read one plan. | `200`, `404` |
| `PUT/PATCH` | `/demand-plans/{demand_plan}` | `generate_forecasts` | Partial plan update. | `200`, `404`, `422` |

### Enterprise procurement workflows

All routes in this table support optional `Idempotency-Key` handling.

| Method | Path | Permission | Purpose / body contract | Success |
| --- | --- | --- | --- | --- |
| `GET` | `/procurement/requisitions` | `manage_procurement` | List purchase requests, 20 per page. | `200` |
| `GET` | `/procurement/requisitions/{requisition}` | `manage_procurement` | Read request, lines, cost center, sourcing, and approval chain. | `200`, `404` |
| `POST` | `/procurement/requisitions` | `manage_procurement` | Requires title, active cost center, and one or more item lines with positive quantity and nonnegative estimated price; validates budget, category, currency, priority, dates, contracts, and UOM. | `201`, `422` |
| `GET` | `/procurement/rfqs` | `manage_sourcing` | List RFQs, 20 per page. | `200` |
| `GET` | `/procurement/rfqs/{rfq}` | `manage_sourcing` | Read RFQ, lines, invitations, quotes, and evaluations. | `200`, `404` |
| `POST` | `/procurement/rfqs` | `manage_sourcing` | Requires title, future deadline, and invited accredited supplier IDs; requires a valid PR or custom item lines, validates currency, bidding type, weights, quantities, and prices. Draft/rejected PRs return `422`. | `201`, `422` |
| `POST` | `/procurement/rfqs/{rfqId}/quotes` | `evaluate_bids` | Requires eligible supplier, currency, Incoterms, payment terms, future validity date, and priced line offers. Closed deadlines or ineligible suppliers return `422`. | `201`, `404`, `422` |
| `POST` | `/procurement/rfqs/{rfqId}/evaluate` | `evaluate_bids` | Runs comparative scoring; no request body. Invalid RFQ state returns `422`. | `200`, `404`, `422` |
| `POST` | `/procurement/rfqs/{rfqId}/award` | `award_procurement` | Requires a `supplier_quote_id` belonging to the RFQ; optional justification. Ineligible suppliers return `422`. | `200`, `404`, `422` |
| `POST` | `/procurement/orders/generate` | `issue_purchase_order` | Requires either `sourcing_rfq_id` plus `supplier_quote_id`, or `purchase_request_id`. | `201`, `404`, `422` |

### Enterprise inventory workflows

All routes in this table support optional `Idempotency-Key` handling.

| Method | Path | Permission | Purpose / body contract | Success |
| --- | --- | --- | --- | --- |
| `GET` | `/inventory/receipts` | `view_inventory` | List receipts, 25 per page. | `200` |
| `GET` | `/inventory/receipts/{goodsReceiptNote}` | `view_inventory` | Read receipt, lines, and inspections. | `200`, `404` |
| `POST` | `/inventory/receipts` | `receive_purchase_order` | Requires PO, actual supplier, and at least one received line; validates locations, condition/discrepancy values, quantities, batch/lot/serial/date data, shipping references, and notes. | `201`, `404`, `422` |
| `POST` | `/inventory/qc/{batchId}/release` | `inspect_stock` | Requires positive `accepted_quantity` and target location; optional decision key/findings. | `200`, `404`, `422` |
| `POST` | `/inventory/qc/inspections/{inspection}/release` | `inspect_stock` | Same release contract using an inspection ID. | `200`, `404`, `422` |
| `POST` | `/inventory/qc/inspections/{inspection}/reject` | `inspect_stock` | Requires positive `rejected_quantity` and rejection reason; optional decision key. | `200`, `404`, `422` |
| `GET` | `/inventory/requisitions` | `view_inventory` | List material requisitions, 25 per page. | `200` |
| `GET` | `/inventory/requisitions/{requisition}` | `view_inventory` | Read requisition and allocation details. | `200`, `404` |
| `POST` | `/inventory/requisitions` | `create_requisition` | Requires department mapped to an active cost center and at least one item line with positive quantity; validates urgency, date, strategy, and notes. | `201`, `422` |
| `POST` | `/inventory/requisitions/{reqId}/approve` | `approve_requisition` | Approves and reserves available-to-promise stock; no body. | `200`, `404`, `422` |
| `POST` | `/inventory/requisitions/{reqId}/reject` | `approve_requisition` | Requires `rejection_reason` up to 500 characters. | `200`, `404`, `422` |
| `GET` | `/inventory/requisitions/{reqId}/picklist` | `issue_stock` | Generate FEFO/FIFO pick list. | `200`, `404` |
| `POST` | `/inventory/requisitions/{reqId}/issue` | `issue_stock` | Optional line overrides; each override requires line ID and positive quantity and may specify location/batch. | `200`, `404`, `422` |
| `GET` | `/inventory/cycle-counts` | `perform_cycle_count` | List cycle counts, 25 per page. | `200` |
| `GET` | `/inventory/cycle-counts/{cycleCountDoc}` | `perform_cycle_count` | Read one count and its lines. | `200`, `404` |
| `POST` | `/inventory/cycle-counts/schedule` | `perform_cycle_count` | Optional count type, location, and active authorized counter. | `201`, `422` |
| `POST` | `/inventory/cycle-counts/{countId}/submit` | `perform_cycle_count` | Requires `counts` object/array with nonnegative integer values. | `200`, `404`, `422` |
| `POST` | `/inventory/cycle-counts/{countId}/approve` | `approve_adjustment` | Approves and posts count variances; no body. | `200`, `404`, `422` |
| `GET` | `/inventory/adjustments` | `view_inventory` | List adjustments, 25 per page. | `200` |
| `GET` | `/inventory/adjustments/{inventoryAdjustment}` | `view_inventory` | Read one adjustment. | `200`, `404` |
| `POST` | `/inventory/adjustments` | `adjust_stock` | Requires item, location, adjustment type, positive quantity, reason code, and 5..500-character explanation; optional batch. | `201`, `422` |
| `POST` | `/inventory/adjustments/{adjustmentId}/authorize` | `approve_adjustment` | Approves/posts or advances dual approval; no body. | `200`, `404`, `422` |
| `GET` | `/inventory/items/{itemId}/replenishment-status` | `view_inventory` | Returns ATP, on-order, safety stock, reorder point, EOQ, and recommendation. | `200`, `404` |
| `POST` | `/inventory/items/{itemId}/replenish` | `create_requisition` | Evaluates replenishment; returns `201` with draft PR when created or `200` when no PR is needed. | `200/201`, `404`, `422` |
| `GET` | `/inventory/warehouse-tasks` | `view_warehouse_tasks` | Filter by enum `status`, enum `task_type`, assignee, and `per_page` `1..100`. | `200`, `422` |
| `GET` | `/inventory/warehouse-tasks/{warehouseTask}` | `view_warehouse_tasks` | Read task, events, scans, and exceptions. | `200`, `404` |
| `POST` | `/inventory/warehouse-tasks` | `manage_warehouse_tasks` | Requires task type, priority, positive quantity; validates distinct locations, item/batch/assignee, future due date, and notes. | `201`, `422` |
| `POST` | `/inventory/warehouse-tasks/{warehouseTask}/assign` | `manage_warehouse_tasks` | Requires existing `assigned_to_id`. | `200`, `404`, `422` |
| `POST` | `/inventory/warehouse-tasks/{warehouseTask}/start` | `execute_warehouse_tasks` | Starts the task; no body. | `200`, `404`, `422` |
| `POST` | `/inventory/warehouse-tasks/{warehouseTask}/scans` | `execute_warehouse_tasks` | Requires `scan_value` up to 1000 characters. | `200`, `404`, `422` |
| `POST` | `/inventory/warehouse-tasks/{warehouseTask}/complete` | `execute_warehouse_tasks` | Requires positive `quantity`; optional override reason. | `200`, `404`, `422` |
| `POST` | `/inventory/warehouse-tasks/{warehouseTask}/cancel` | `manage_warehouse_tasks` | Requires cancellation reason up to 1000 characters. | `200`, `404`, `422` |

Permanent `DELETE` endpoints are intentionally absent. Item/supplier/location lifecycle is handled by status, while movements, forecasts, counts, receipts, and audit-relevant workflow records retain history. Unsupported methods return `405`.

## Response and Error Contracts

### Successful resource response

```json
{
  "data": {
    "id": 123,
    "sku": "DEMO-001",
    "name": "Demonstration item",
    "status": "active"
  }
}
```

### Successful workflow response

```json
{
  "message": "Operation completed.",
  "data": {
    "id": 123,
    "status": "pending"
  }
}
```

Some procurement workflow responses also include `"status": "success"`. This established distinction is preserved because existing consumers already handle it; no cosmetic response redesign was performed.

### Validation failure — `422`

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

### Authentication and authorization

```json
{ "message": "Unauthenticated." }
```

Status: `401`

```json
{ "message": "This action is unauthorized." }
```

Status: `403`

### Missing or malformed resource — `404`

```json
{ "message": "Not Found" }
```

Internal model class names, SQL, paths, and stack traces are not returned.

### Idempotency conflict — `409`

```json
{ "message": "This Idempotency-Key was already used with a different request payload." }
```

Successful replays include `X-Idempotent-Replay: true`.

### Unexpected failure — `500`

```json
{
  "success": false,
  "message": "An unexpected system error occurred. Operations were safely aborted.",
  "error_id": "REC-EXAMPLE"
}
```

The public response contains only a correlation ID. Recovery details remain server-side and are redacted by the recovery service.

## Frontend Consumers

The API is actively consumed by existing Blade/JavaScript screens:

- `resources/views/inventory/alerts/index.blade.php` requests `/api/v1/inventory-items` and initializes Sanctum CSRF state;
- `resources/views/inventory/purchases/index.blade.php` requests demand plans, procurement requests, supplier quotes, inventory items, and suppliers;
- `resources/js/app.js` recognizes `/api/v1/*` requests in the shared request/error behavior;
- `SessionApiAccessTest` logs in through the real form and verifies that session cookies can read all nine core browser-consumed endpoints and write a supplier without replacing the authentication system.

## Verification Matrix

| Scenario | Method / endpoint | Auth state | Expected | Actual | Result |
| --- | --- | --- | --- | --- | --- |
| Authenticated GET | `GET /api/v1/inventory-items` | Viewer session/token | `200`, paginated resource | `200`, `data/links/meta` | Pass |
| Create | `POST /api/v1/inventory-items` | Inventory Manager | `201`, persisted resource | `201`, item returned and persisted | Pass |
| Update | `PATCH /api/v1/inventory-items/{id}` | Inventory Manager | `200`, updated resource | `200`, updated name returned/persisted | Pass |
| Unauthenticated | `GET /api/v1/inventory-items` | None | `401` | `401` | Pass |
| Invalid bearer/session use | `POST /api/v1/auth/logout` | Stateful session | `400`, no exception | `400`, bearer-token guidance | Pass |
| Unauthorized role | `POST /api/v1/inventory-items` | Viewer | `403`, no write | `403`, database unchanged | Pass |
| Invalid body | `POST /api/v1/inventory-items` | Inventory Manager | `422`, field errors, no write | `422`, `sku/name` errors | Pass |
| Missing resource | `GET /api/v1/inventory-items/999999` | Inventory Manager | safe `404` | `404 {"message":"Not Found"}` | Pass |
| Invalid route parameter | `POST /api/v1/inventory/adjustments/not-a-number/authorize` | Inventory Manager | safe `404` | `404`, no controller type error | Pass |
| Unsupported delete | `DELETE /api/v1/inventory-items/{id}` | Inventory Manager | `405`, record retained | `405`, record retained | Pass |
| Pagination boundary | `GET /api/v1/inventory-items?per_page=100000` | Viewer | bounded page size | `200`, `meta.per_page=100` | Pass |
| Token validation | `POST /api/v1/auth/token` with 256-character device name | Valid credentials | `422`, no token | `422`, token table unchanged | Pass |
| Bearer logout | `POST /api/v1/auth/logout` | Valid bearer token | `204`, current token revoked | `204`, token removed | Pass |
| Procurement domain error | RFQ from draft PR / award to ineligible supplier | Inventory Manager | semantic `422` | `422`, validation/error JSON | Pass |
| Unexpected failure | Test JSON route throws runtime exception | JSON request | redacted `500` with reference | safe message and `error_id`; no SQL/path/credential text | Pass |
| Browser integration | Nine core read endpoints and supplier create | Real form-login session | `200` reads, `201` write | all expected statuses and persisted supplier | Pass |

## Verification Commands

```text
php artisan route:list --path=api
# 76 routes

php artisan test tests/Feature/ApiIntegrationTest.php
# 7 passed, 36 assertions
# Error-log delta: one expected entry from the deliberately injected redaction-test exception; no additional API errors.

php artisan test tests/Feature/EnterpriseProcurementTest.php --filter=test_rfq_and_award_business_rule_failures_return_validation_errors
# 1 passed, 5 assertions

php artisan test tests/Feature/ApiIntegrationTest.php tests/Feature/SessionApiAccessTest.php tests/Feature/InventoryItemApiTest.php tests/Feature/EnterpriseProcurementTest.php tests/Feature/RoleAuthorizationAuditTest.php tests/Feature/ErrorRecoveryTest.php tests/Feature/DeviceSecurityAndSingleSessionTest.php
# 124 passed, 944 assertions

php artisan test
# 1,621 passed, 23 failed, 12,346 assertions
```

The 23 full-suite failures reproduce independently and are outside this API change: one stale Privacy Policy consent fixture in `GlobalPasswordHistoryTest`, one dashboard content expectation in `InventoryModuleTest`, 17 Super Admin confirmation cases redirected by current privacy-consent middleware, and four Super Admin provisioning cases redirected by the same middleware. The focused API/security regression set passes completely. Tests use SQLite `:memory:` through `phpunit.xml`; no shared application database was modified.

## Implementation Sources

- Route registry: `routes/api.php`
- Authentication and token revocation: `app/Http/Controllers/Api/AuthController.php`
- API authorization: controller `HasMiddleware` declarations plus `App\Enums\Permission`
- Validation: `app/Http/Requests/*` and inline workflow controller validation
- Serialization: `app/Http/Resources/*` and established workflow JSON payloads
- Safe exception responses: `bootstrap/app.php`
- Browser session integration: `bootstrap/app.php` stateful API middleware
- Focused regression evidence: `tests/Feature/ApiIntegrationTest.php`
