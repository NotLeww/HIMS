# Error Messages Functional Test

Date: 2026-09-28

Checklist item: Error Messages

Result: Pass

| Feature/page | Action | Expected behavior | Actual message/behavior | Result |
| --- | --- | --- | --- | --- |
| Staff, Admin, and Super Admin login | Submit invalid credentials | Reject securely without identifying valid accounts | `Incorrect email or password.` | Pass |
| Inventory and procurement forms | Submit missing or invalid values | Identify each affected field and preserve valid input | Field-specific Laravel validation errors are returned through the shared field/error components | Pass |
| Protected inventory and administration routes | Request an action without its permission | Deny the server request without changing data | HTTP 403 permission denial; authorization remains enforced by route/controller middleware | Pass |
| Record detail and download routes | Request an unknown or unavailable record | Return a safe not-found response | HTTP 404 without database, path, or stack-trace details | Pass |
| Supplier/procurement workflows | Submit a duplicate or conflicting record | Explain the conflict and next action | Specific conflict messages identify the duplicate and direct the user to update/reactivate the existing record | Pass |
| Logistics document upload | Upload an invalid or content-mismatched file | Reject the file with a corrective action | `Document upload failed: The file content does not match the .pdf extension. Upload a valid PDF file.` | Pass |
| Logistics verification | Simulate an unexpected database/internal failure | Log the exception, preserve data, and hide technical details | `Document verification could not be completed. Please try again.` No SQL, path, or exception detail is returned | Pass |
| Process review availability check | Verify the failed-request fallback contract | Stop loading, disable submission, and provide retry guidance | Production-rendered fallback: `Availability Check Failed` and `Availability could not be checked. Please try again.` | Pass |
| Dashboard inventory assistant | Submit invalid input or encounter an unexpected service failure | Return a safe, actionable API error without directing users to internal logs | Validation identifies invalid attachments; unexpected failures advise retrying and contacting system support | Pass |
| Report generation | Submit invalid dates or unsupported report options | Reject before generation with specific field guidance | Date range and option validation errors identify the invalid field/rule | Pass |
| Unexpected web/API failure | Trigger a safe test exception | Show a stable error response and reference without internals | Branded 500 response or safe JSON message with an incident reference; no SQL, stack trace, or internal path | Pass |
| Expired form/session | Submit with an invalid CSRF/session token | Explain recovery without resubmitting automatically | `Your session has expired or the page is no longer available. Please refresh the page and try again.` | Pass |

Verification commands:

```text
php artisan test tests/Feature/DocumentTrackingAndLogisticsTest.php tests/Feature/EvidenceBasedProcessReviewTest.php
Result: 35 passed, 265 assertions

php artisan test tests/Feature/ErrorRecoveryTest.php tests/Feature/PageExpiredErrorPageTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/AdminAuthenticationTest.php tests/Feature/InventoryReportTest.php tests/Feature/SupplierManagementTest.php tests/Feature/UiNavigationAuthorizationTest.php
Result: 158 passed, 1,324 assertions

php artisan test tests/Feature/AiInventoryAssistantTest.php
Result: 120 passed, 528 assertions

npm run build
php artisan view:cache
git diff --check
```

The application identifies itself as `Hospital Information Management System`; the former inventory-focused expansion is absent from application-owned source and configuration.
