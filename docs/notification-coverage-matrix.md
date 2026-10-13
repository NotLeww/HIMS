# HIMS notification coverage matrix

Audited 2026-10-13 against the Laravel application, RBAC enums, supplier ownership relations, notification destinations, web/API workflow entry points, and focused feature tests.

HIMS currently models one hospital organization: users and workflow records do not carry a `hospital_id` or equivalent tenant key. Hospital-side isolation is therefore permission/responsibility based. Supplier-side isolation is enforced with `users.supplier_id`, supplier relationships, scoped recipient queries, and destination re-authorization when a notification is opened.

| Module | Trigger event | Recipient role | Recipient scope | Existing status | Required action | Verification |
|---|---|---|---|---|---|---|
| Notification feed | Bell opens or polls | Any active authenticated user | Only the authenticated user's morph-many notification feed | Working | Keep | `NotificationSystemTest`: feed ownership, pagination, empty state, unread count |
| Notification feed | Mark one/all as read | Any active authenticated user | Only notification rows owned by that user | Working | Keep | `NotificationSystemTest`: cross-user 404 and read state; routine acknowledgement intentionally creates no audit event |
| Notification feed | Open notification link | Notification owner with current destination access | Re-authorized permission and supplier/record relationship at click time | Working | Keep | `NotificationSystemTest`: permission downgrade and authorized redirect |
| Notification feed | New item arrives through polling after an initially empty page | Any active authenticated user | Current user's feed | Broken | Fix | Empty state now hides and “Mark all as read” is always rendered under Alpine state; Blade assertions pass |
| Supplier onboarding | Vendor Administrator invitation sent/resend | Invited pending supplier account | Exact email/user and supplier invitation | Working | Keep | `SupplierInvitationOnboardingTest`; email notification architecture retained |
| Supplier onboarding | Invitation accepted | Supplier approvers | Active users with `ApproveSuppliers`; no read-only supplier managers | Incorrectly routed | Fix | `NotificationWorkflowAuditTest`: Administrator receives; passive procurement roles do not |
| Company profile | Supplier submits/resubmits profile | Supplier approvers | Active users with `ApproveSuppliers`; actor excluded | Incorrectly routed and same-day resubmission could dedupe | Fix | `NotificationWorkflowAuditTest`: approver receives, unrelated supplier and non-approver do not |
| Company profile | Hospital requests corrections | Vendor Administrator | Active `SupplierManageProfile` users linked to that supplier | Working | Keep | `NotificationWorkflowAuditTest`: linked supplier only, actor excluded |
| Accreditation | Internal compliance reviewer submits accreditation for decision | Supplier approvers | Active users with `ApproveSuppliers`; actor excluded | Incorrectly routed | Fix | Recipient permission aligned with the approving route |
| Accreditation | Approval/rejection decision | Vendor Administrator | Active `SupplierManageProfile` users linked to the affected supplier | Working | Keep | `NotificationWorkflowAuditTest`: Vendor Administrator receives, Vendor Operations does not |
| Supplier status | Suspension/reactivation | Vendor Administrator | Active `SupplierManageProfile` users linked to the affected supplier | Working | Keep | `SupplierManagementTest` plus workflow routing coverage |
| Supplier status | Inactivation | Vendor Administrator | Active `SupplierManageProfile` users linked to the affected supplier | Missing | Implement | `NotificationWorkflowAuditTest`: exact supplier only; Vendor Operations and other supplier excluded |
| Compliance | Supplier uploads/replaces a document | Compliance reviewers | Active users with `ReviewSupplierCompliance`; actor excluded | Incorrectly routed | Fix | `NotificationWorkflowAuditTest`: both Administrator and Inventory Manager reviewers receive |
| Compliance | Document verified/rejected | Vendor Administrator | Active `SupplierManageProfile` users linked to the affected supplier | Working | Keep | `NotificationWorkflowAuditTest`: linked supplier only |
| Compliance | Scheduled expiry/blocking alert | Vendor Administrator | Active `SupplierManageProfile` users linked to affected supplier | Working | Keep | `SupplierManagementTest` / compliance alert service |
| Supplier catalogue | Supplier submits product | Supplier managers | Active users with `ManageSuppliers` | Working, inaccurate product-name field | Fix copy | Workflow uses `supplier_product_name`; notification audit suite passes |
| Supplier catalogue | Hospital approves submitted product | Vendor Administrator | Active `SupplierManageProfile` users linked to affected supplier | Missing | Implement | `NotificationWorkflowAuditTest`: real approval route, exact supplier only |
| Sourcing/RFQ | RFQ published | Vendor Administrators eligible to bid | Active `SupplierSubmitBids` users belonging only to invited eligible suppliers | Working | Keep | `NotificationWorkflowAuditTest`: invited supplier receives, uninvited supplier does not |
| Sourcing/RFQ | Supplier bid submitted | Bid evaluators | Active users with `EvaluateBids` | Working | Keep | `NotificationWorkflowAuditTest`: evaluator receives; supplier boundaries preserved |
| Sourcing/RFQ | Final award converted to PO | Winner and participating losing bidders | Active `SupplierSubmitBids` users linked to suppliers that actually bid | Working | Keep | `NotificationWorkflowAuditTest`: winner and losing bidder receive distinct outcomes |
| Procurement approvals | Current DOA step becomes pending | Required workflow role | Active users in current step role plus Super Administrator; initiator excluded | Working | Keep | `NotificationSystemTest`: only current role and override role receive |
| Procurement approvals | Chain approved/rejected | Original requester/PO creator | User related to approved/rejected target | Working | Keep | Approval routing tests and workflow destination checks |
| Procurement approvals | PO approval due/overdue | Current approval responsibility | Current role or `ApprovePurchaseOrder`, depending on chain availability | Working | Keep | `PurchaseOrderWorkspaceTest` and reminder service coverage |
| Purchase orders | PO approved/issued or revised | Vendor Administrator / Vendor Operations | Active `SupplierFulfillOrders` users linked to PO supplier | Working | Keep | Observer plus `NotificationWorkflowAuditTest`; destination verifies PO supplier ownership |
| Purchase orders | Supplier accepts/rejects PO | PO creator and issuers | Exact creator plus active `IssuePurchaseOrder` users, without duplicate creator delivery | Working | Keep | `NotificationWorkflowAuditTest` |
| Delivery | Supplier submits ASN | Receiving personnel | Active users with `ReceivePurchaseOrder` | Working | Keep | `NotificationWorkflowAuditTest` |
| Receiving | GRN enters quarantine | QC inspectors | Active users with `InspectStock` | Working | Keep | `PostDeliveryReceivingWorkflowTest` |
| Receiving | Dock discrepancy flagged | Procurement operators | Exact PO creator plus active `ManageProcurement` users | Over-broad (`ViewProcurement`) | Fix | `PostDeliveryReceivingWorkflowTest`; read-only roles no longer selected by recipient query |
| Receiving | Discrepancy reported to supplier | Vendor Administrator / Vendor Operations | Active `SupplierFulfillOrders` users linked to receipt supplier | Working | Keep | `NotificationWorkflowAuditTest`: linked supplier only |
| Receiving | Supplier responds to discrepancy | Receiving personnel | Active users with `ReceivePurchaseOrder`; actor excluded | Working | Keep | `NotificationWorkflowAuditTest` |
| Receiving | Hospital resolves discrepancy | Vendor Administrator / Vendor Operations | Active `SupplierFulfillOrders` users linked to receipt supplier | Missing | Implement | `NotificationWorkflowAuditTest`: real resolution route and cross-supplier exclusion |
| Quality control | Stock accepted/rejected or PO fully accepted | Procurement operators; warehouse executor for put-away | Active `ManageProcurement` users; `ExecuteWarehouseTasks` for generated put-away task | Over-broad (`ViewProcurement`) | Fix | `PostDeliveryReceivingWorkflowTest`: notification destinations and linked records |
| Supplier invoices | Supplier submits invoice | Procurement operators | Active users with `ManageProcurement` | Over-broad (`ViewProcurement`) | Fix | `NotificationWorkflowAuditTest`: manager receives; Viewer and Auditor do not |
| Store requisitions | Requisition submitted | Requisition approvers | Active users with `ApproveRequisition`; actor excluded | Working | Keep | Issuance engine and notification system coverage |
| Store requisitions | Approved/rejected/issued | Original requester | Exact requesting user | Working | Keep | `NotificationWorkflowAuditTest`: requester navigation works without approval permission |
| Inventory adjustments | Approval/second approval required | Authorized approval role | `ApproveAdjustment` or configured second-tier roles; actor excluded | Working | Keep | Adjustment approval feature coverage |
| Inventory alerts | Low/out-of-stock transition | Users able to acknowledge alerts | Active `AcknowledgeAlerts` users; stable event dedupe | Working | Keep | `NotificationSystemTest`: role exclusions and duplicate prevention |
| Warehouse tasks | Actionable exception/assignment event | Warehouse task handlers | Active permission holders or assigned user as implemented by task service | Working | Keep | Warehouse task feature coverage |
| Logistics documents | Document uploaded for verification | Logistics verifiers | Active `VerifyLogisticsDocuments` users; uploader excluded | Working | Keep | `NotificationWorkflowAuditTest` |
| Logistics documents | Verified/rejected | Original uploader | Exact uploader, subject to current logistics destination access | Working | Keep | `NotificationWorkflowAuditTest` |
| Security/account | Password/MFA/session/device/account state changes | Affected account | Exact user only | Working | Keep | `NotificationSystemTest` and security feature suites; notification payload excludes secrets |
| Data import | Import fails | Import actor | Exact initiating user only | Working | Keep | `NotificationSystemTest`: safe message, exception detail excluded |

## Delivery and reliability notes

- Operational HIMS notifications use Laravel's database channel. Supplier invitations and authentication/security challenges retain their existing email delivery where applicable.
- UUIDv5 IDs provide per-recipient business-event deduplication; unique-key races are handled without creating duplicate rows.
- Notifications are emitted only after the owning operation succeeds or inside its database transaction, so rollback removes transaction-coupled rows.
- The bell polls every 60 seconds while the page is visible. There is no websocket/broadcast notification transport in the current application.
- No notification was added for draft saves, ordinary record edits, downloads, list views, evaluation previews, or other actions that do not hand work or material status information to another user.
