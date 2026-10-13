# HIMS audit-trail coverage matrix

Audited 2026-10-13 against the Laravel web/API entry points, domain services, observers, console workflows, RBAC permissions, audit model/writer, audit administration UI, and focused feature tests.

HIMS currently models one hospital organization: operational records do not carry a `hospital_id` or equivalent tenant key. Hospital-side audit access is therefore controlled by `ViewAuditTrail`; supplier users have no audit-trail route. Procurement also retains its workflow-specific ledger, while recognized business actions are mirrored once into the organization-wide audit trail.

| Module | Action | Audit Required | Current Status | Required Fix | Verification |
|---|---|---:|---|---|---|
| Authentication | Successful and failed login; logout | Yes | Working | None | `AuditTrailTest`, `AuthenticationTest`, `AdminAuthenticationTest` |
| Authentication | Lockout/unlock and suspicious-login block | Yes | Working | None | `LoginLockoutTest`, `UserManagementTest`, `DeviceSecurityAndSingleSessionTest` |
| Authentication | Password and MFA changes/verification | Yes | Working | None | `AuditTrailTest`, `PasswordUpdateTest`, `AuthenticatorMfaTest`, `SmsMfaTest` |
| Authentication | Login approval, trusted device, and session replacement lifecycle | Yes | Working | None | `DeviceSecurityAndSingleSessionTest`, `SessionManagementTest` |
| Account activation | Initiate, OTP request/verify/failure/rate limit, completion/cancellation | Yes | Working | None | `AccountActivationTest`, `AccountProvisioningConfigurationTest` |
| User administration | Create/update role or status/archive/unarchive/delete | Yes | Working | None | `AuditTrailTest`, `UserManagementTest`, `ArchiveMasterRecordsTest` |
| Notification feed | Open feed, poll, mark one/all read | No | Was noisy | Removed future acknowledgement logs; historical enum retained so old rows still render | `NotificationSystemTest` asserts read-state changes create no audit row |
| Supplier master | Create and material company/contact/logo updates | Yes | Working | None | `SupplierManagementTest`, `SupplierLogoTest`, `SupplierCompanyProfileTest` |
| Supplier onboarding | Send/fail/accept invitation | Yes | Working | None | `SupplierInvitationOnboardingTest`, `AccountActivationTest` |
| Supplier onboarding | Revoke invitation | Yes | Was generic and targeted the account | Added supplier-specific action and invitation target; exactly one global event | `SupplierInvitationOnboardingTest` |
| Supplier profile | Save an incomplete draft | No | Was noisy | Removed future draft-save logs; historical enum retained | `SupplierCompanyProfileTest` asserts no draft-save event |
| Supplier profile | Submit/resubmit, request changes, approve/reject profile | Yes | Working | None | `SupplierCompanyProfileTest`, `SupplierManagementTest` |
| Supplier accreditation | Submit, approve/reject, suspend/inactivate/reactivate | Yes | Working | None | `SupplierManagementTest` |
| Supplier records | Archive/unarchive | Yes | Working | None | `ArchiveMasterRecordsTest` |
| Supplier compliance | Upload/replace/remove/download/verify/reject evidence | Yes | Working | None | `SupplierPortalWorkflowTest`, `SupplierManagementTest` |
| Supplier catalogue | Add/update/approve product; add price; add/update contract | Yes | Working | None | `SupplierPortalWorkflowTest`, `SupplierManagementTest` |
| Purchase requests | Create and final approval | Yes | Working | None | `EnterpriseProcurementTest`, `ProcurementWorkflowTest` |
| Purchase requests | Approval/rejection at each routing step | Yes | Was only in the procurement ledger | Added recognized global step actions with the same transaction/correlation context | `EnterpriseProcurementTest` |
| Purchase requests | Final rejection | Yes | Missing from the global trail | Added final rejected-request event | `EnterpriseProcurementTest` |
| Sourcing/RFQ | Publish an immediately active RFQ | Yes | Was mislabeled as created | Corrected web/API paths to `Published Sourcing RFQ` | `EnterpriseProcurementTest`, `ProcurementWorkflowTest` |
| Sourcing/RFQ | Scheduled bidding close | Yes | Was only in the procurement ledger | Added recognized system-actor close action | `EnterpriseProcurementTest` |
| Supplier quotes | Initial bid submission | Yes | Working | None | `SupplierPortalWorkflowTest`, `ProcurementWorkflowTest` |
| Supplier quotes | Bid retry/revision | Yes | Was mislabeled as another initial submission | Added `Revised Supplier Quote` with safe old/new bid values on web and API paths | `SupplierPortalWorkflowTest`; API workflow regression suite |
| Sourcing/RFQ | Evaluate bids and award winner | Yes | Working | None | `EnterpriseProcurementTest`, `ProcurementWorkflowTest` |
| Purchase orders | Issue, approve/reject, amend, cancel | Yes | Working | None | `EnterpriseProcurementTest`, `PurchaseOrderWorkspaceTest` |
| Supplier fulfillment | Accept/reject PO, submit ASN, respond to discrepancy, submit invoice | Yes | Working | None | `SupplierPortalWorkflowTest`, `PostDeliveryReceivingWorkflowTest` |
| Receiving | Post goods receipt | Yes | Was labeled as creation although posting is immediate | Corrected to `Posted Goods Receipt` | `PostDeliveryReceivingWorkflowTest`, `EnterpriseProcurementTest` |
| Receiving & quality | Inspect/release/reject quarantine stock; resolve discrepancy | Yes | Working | None | `PostDeliveryReceivingWorkflowTest` |
| Store requisitions | Create/approve/reject/cancel/issue/acknowledge | Yes | Working | None | `MaterialRequisitionWorkflowTest` |
| Inventory movements | Receipt, issue, transfer, QC, and other standalone stock movement | Yes | Working | None | `EnterpriseInventorySystemTest`, `SpecificMovementTypeTest`, workflow suites |
| Inventory adjustments | Direct adjustment and approved adjustment posting | Yes | Direct path emitted duplicate semantic events and lacked one outer transaction | Made adjustment, movement, and one final audit event atomic; movement references the adjustment | `AuditTrailTest`, `StockAdjustmentTest` |
| Stock transfers | Dispatch and receive | Yes | Working | None | `StockTransferWorkflowTest` |
| Cycle counts | Schedule, blind count, resulting adjustment | Yes | Working | None | `CycleCountWorkflowTest` |
| Item master | Create/update/archive/unarchive and import changes | Yes | Working | None | `InventoryItemApiTest`, `ArchiveMasterRecordsTest`, `DataImportTest` |
| Storage locations | Create and status change | Yes | Working | None | `StorageLocationLifecycleTest`, `SmartWarehousingWorkflowTest` |
| Warehouse work | Create/assign/start/complete/cancel task; raise/resolve exception | Yes | Working | None | `WarehouseTaskWebTest`, `SmartWarehousingAdvancedWorkflowTest` |
| Controlled stock | Dangerous-drug transaction, consignment use, narcotics export | Yes | Working | None | `SmartWarehousingAdvancedWorkflowTest`, `SupplyChainModuleTest` |
| Logistics documents | Upload/download/verify/revise/archive | Yes | Working | None | `DocumentTrackingAndLogisticsTest` |
| Logistics | IAR creation/inspection/acceptance/rejection/transmission; custody transfer; shipment milestones | Yes | Working | None | `DocumentTrackingAndLogisticsTest`, `InboundShipmentPickupLocationTest` |
| Imports | Start, completion, and failure | Yes | Working | None | `DataImportTest` |
| Forecasting & process review | Generate/refresh/fail forecast; review lifecycle and implementation | Yes | Working | None | `DemandForecastTest`, `EvidenceBasedProcessReviewTest` |
| Reports | Export and scheduled-report create/update/toggle/delete | Yes | Working | None | `InventoryReportTest`, `ScheduledReportTest`, `ReportExportAuditTest` |
| Privacy & security governance | Consent, privacy requests, package download, deletion, incidents, retention | Yes | Working | None | Privacy feature suites |
| AI assistant | Attachment analysis/failure and blocked prompt-security attempt | Yes | Working | None | `AiChatbotAttachmentTest`, `AiPromptProtectionTest` |
| System recovery | Failure/retry/recovery/maintenance events | Yes | Working | None | `ErrorRecoveryTest`, `RecoveryAndMetricsSeedingTest` |
| Audit administration | List/search/filter/detail/print audit records | No | Working and intentionally read-only | Keep ordinary audit reads unlogged; retain permission guard and pagination | `AuditTrailTest`, `RoleAuthorizationAuditTest` |

## Accuracy, safety, and access notes

- The central writer stores action/module/category, actor ID plus role/name snapshot, target type/ID/name, timestamp, outcome, source, correlation ID, request/device/location context, and supplied old/new values.
- Sensitive keys are recursively redacted before persistence. Authentication secrets, passwords, tokens, OTP values, and uploaded file contents are not stored in audit payloads.
- `AuditLog` blocks application-level update and delete operations. The admin UI is read-only and guarded server-side by `ViewAuditTrail`; filters are validated and results paginate at 25 rows.
- Business audit writes run inside the owning transaction where one exists. Failed or rolled-back operations therefore do not leave a success event. System jobs use a null actor and explicit system source.
- Old enum values that no longer have emitters remain readable for historical rows. Their presence in the enum does not mean the noisy event is still generated.
- The audit is organization-wide, not multi-tenant. A future multi-hospital deployment would require a hospital/tenant key on both operational and audit records plus scoped queries and indexes before claiming tenant isolation.
