# Personal Data Protection — Implementation and Evidence

## Scope and status

This document records the personal-data processing observed in HIMS and the technical controls implemented in the repository. It is evidence for the **Personal Data Protection** checklist item; it is not, by itself, a legal opinion or a declaration that the deploying institution is fully compliant with Republic Act No. 10173. The hospital's Data Protection Officer (DPO) must validate the stated purposes, lawful bases, recipients, notices, and records-retention periods against actual operations and contracts.

HIMS is an inventory, procurement, warehouse, logistics, account-administration, audit, and security-governance application. The inspected schema does not define patient charts, diagnoses, or treatment records. Free-text fields and uploads can nevertheless contain personal information if users enter it, so the application warns users not to submit unrelated personal or clinical data.

## Personal-data inventory and lifecycle

| Processing activity | Personal information and source | Purpose | Storage | Authorized access | Retention and disposal | External recipient |
| --- | --- | --- | --- | --- | --- | --- |
| Workforce accounts and profiles | Name, structured name, institutional email, employee ID, department, phone, profile image, role/status; entered during account provisioning and profile maintenance | Authentication, authorization, staff identification, operational attribution | `users`, private authentication records, and avatar files | The data subject; users with the specific account-management permission, subject to protected-account rules | Active accounts remain usable; deactivation revokes access while identity attribution is retained for inventory/audit integrity. No automatic hard-delete period is implemented | Configured email/SMS providers receive only the destination and security-message content needed for enabled authentication workflows |
| Authentication and device security | Password hash, MFA secret/phone, OTP state, lockout state, sessions, trusted-device identifiers, IP address, user agent | Protect accounts and investigate unauthorized access | Authentication, session, trusted-device, and security-event tables | The data subject for own security settings; narrowly authorized administrators for account/security operations | Session lifetime is configured; trusted-device lifetime is configured. Other security evidence follows the institution's approved security-retention process | Configured email/SMS providers when those delivery channels are enabled |
| Audit trail | Actor/target identifiers and names, employee ID snapshot, IP address, user agent, optional rounded browser coordinates, event before/after values | Accountability, security monitoring, and inventory chain of custody | Append-only `audit_logs` | Users with `view_audit_trail` | Not automatically deleted because the current implementation treats it as permanent evidentiary history; institutional records/legal review is required before any disposal design | None. Coordinates are converted using local application logic and are not sent to a public reverse-geocoding service |
| Supplier and procurement records | Authorized representative/contact names, business email/phone/mobile, TIN/tax number, addresses, delivery contacts, compliance/accreditation documents | Supplier accreditation, procurement, receiving, and contract administration | Supplier/procurement tables; uploaded evidence in controlled application storage | Supplier/procurement roles; sensitive fields and downloads require their dedicated permissions | No automatic purge is implemented. The hospital must approve the applicable COA/NAP/procurement schedule before disposal | Government procurement/regulatory recipients where required by the real transaction; no general application-level onward transfer |
| Warehouse, inventory, requisition, receiving, and logistics attribution | Requester, approver, receiver, dispenser, witness, assignee, driver name/contact, creator/updater IDs | Fulfil duties, approvals, custody, delivery, and traceability | Operational transaction tables and documents | Role- and workflow-specific inventory, warehouse, pharmacy, procurement, and logistics permissions | Preserved with the operational ledger. No automated personal-data erasure is applied where it would break transaction attribution | None through ordinary HIMS operation |
| AI assistant | Authenticated user ID, pattern-sanitized prompt, conversation title/messages, sanitized attachment filename, private attachment | Grounded inventory assistance and conversation continuity | `ai_chat_conversations`, `ai_chat_messages`, private local attachment storage | Conversation owner; attachment downloads are owner-scoped | Conversations and attachments are deleted after the configured inactivity window (default `30` days) by `privacy:enforce-retention` | Google Gemini only when configured: sanitized prompts, personal-field-minimized structured context, and sanitized extracted text from supported text-based files. Raw image/PDF binaries are not transmitted |
| Data-subject requests (DSR) | Requestor name/email, request details, decision/resolution, exclusions, export payload/manifest, download evidence | Receive, assess, and fulfil applicable privacy rights | Encrypted `privacy_requests` fields; generated ZIP package in private local storage | Request owner for their case/package; users with `manage_privacy_compliance` | Download package expires after the configured period (default `7` days) and the sweep deletes the file and payload/manifest while preserving the case decision for accountability | None unless disclosure is required by law or the data subject directs an authorized release |
| Security incidents and recovery | Reporter/assignee/affected-user attribution, incident details, affected systems/data, assessments, containment/remediation notes, exception class | Detect, investigate, contain, and document security events | Security-incident and recovery tables | Users with the dedicated security-recovery or privacy-compliance permissions | Resolved recovery records are purged after the configured period (minimum/default `180` days). Incident disposal is not automatic and requires an approved institutional schedule | NPC or other competent authority only when an authorized officer determines notification is legally required |
| Notifications | Recipient ID and workflow/security message metadata | Deliver in-application operational and security notices | Laravel notifications table | Intended recipient and authorized workflow components | Read notifications are deleted after the configured period (minimum/default `90` days) | Email/SMS provider only when a corresponding external notification is enabled |

## Implemented protections

### Collection and transparency

- The public notice at `resources/views/legal/privacy-notice.blade.php` describes the actual categories, purposes, recipients, retention behavior, security controls, and supported rights.
- Account registration/provisioning, supplier creation, and AI assistant workflows show contextual collection notices and link to the public notice.
- The supplier form asks users to enter only business and authorized-representative details needed for accreditation/procurement.
- The AI form warns against submitting patient records, credentials, or unnecessary personal information.

### Access, disclosure, and purpose limitation

- Existing Laravel authentication guards and permission gates remain in force. Administrative privacy governance requires `manage_privacy_compliance`; audit access requires `view_audit_trail`; sensitive supplier API fields and documents require `view_supplier_sensitive_data`.
- DSR downloads require either ownership of the request or the privacy-compliance permission. Files are streamed from private storage rather than exposed as public URLs.
- AI conversations and attachments are owner-scoped.
- Profile images are available only to their owner or a user who both has `manage_users` and may manage that specific target under protected-account rules.
- Supplier API resources conditionally omit sensitive contact, address, tax, commercial, and document data unless the caller has the dedicated permission.

### Storage, encryption, and secret handling

- Passwords use Laravel's one-way `hashed` cast; they are not reversibly encrypted.
- User phone/MFA data, DSR content, and security-incident details use application encryption as defined in the relevant model casts. User phone lookup uses a keyed blind index rather than plaintext lookup.
- Passwords, MFA secrets, remember tokens, lockout details, and blind-index values are hidden from normal user serialization.
- Encryption keys and service credentials are environment/secret-manager values. `.env.example` contains names and safe defaults/placeholders only; no actual key, certificate, token, or personal record belongs in the repository.
- This privacy control preserves the separate database-encryption design documented in `docs/security/database-encryption.md`.

### AI data minimization

- `AiDataSanitizerService` pattern-redacts common Philippine identifiers, email addresses, mobile numbers, payment-card patterns, and key/value credentials before a prompt is stored or dispatched.
- Structured inventory context is recursively minimized so known actor, employee, contact, email, phone, IP, device, and attribution fields are replaced before an external request.
- Raw image/PDF attachment binaries stay within HIMS. Text extracted from supported text-based documents can be sent only after the same pattern sanitation. Pattern redaction is a risk reduction, not a guarantee; the notice therefore tells staff not to submit unnecessary personal information.
- AI failure audit entries retain the exception class for diagnosis without recording raw external exception messages that could echo request data.

### Logging, location, retention, and disposal

- Audit logging retains accountability while established audit redaction rules exclude secrets and authentication material.
- Audit location labeling is local-only; the application no longer calls public reverse-geocoding endpoints with browser coordinates.
- `DataRetentionService` and the scheduled `privacy:enforce-retention` command delete expired AI conversations/private attachments, expired DSR packages and their embedded payloads, read notifications, and resolved recovery records. The command supports `--dry-run` for review.
- DSR case records are preserved after package disposal so the institution can show what was requested and decided without retaining the released archive indefinitely.
- Records without an approved automated schedule are not silently deleted. That limitation is stated in the public notice and this evidence record.

## Supported data-subject rights

- **Information:** the privacy notice is publicly reachable, including before authentication.
- **Access:** an authenticated user may file an access request; an approved export is private and owner-scoped.
- **Correction:** users can update supported profile fields and can file a rectification request for records that require controlled correction.
- **Objection and erasure review:** the DSR workflow accepts these request types for DPO review. They are not unconditional deletes; inventory ledgers, security evidence, legal holds, and audit obligations may require preservation.
- **Complaint/inquiry:** the notice identifies the configured DPO contact and NPC channel. Deployments must set and verify the actual institutional contact and registration reference.

## Repository evidence map

| Evidence | Project location |
| --- | --- |
| Public privacy notice | `resources/views/legal/privacy-notice.blade.php` |
| Privacy identity/retention configuration | `config/privacy.php`, `.env.example` |
| Processing register and classification inventory | `app/Services/Privacy/DataProcessingRegisterService.php`, `app/Services/Privacy/DataClassificationService.php` |
| Governance authorization | `app/Http/Controllers/Admin/PrivacyGovernanceController.php`, `app/Enums/Permission.php` |
| DSR authorization/private package flow | `app/Http/Controllers/Privacy/DsarDownloadController.php`, `app/Services/Privacy/DsarPackageService.php`, `app/Models/PrivacyRequest.php` |
| Retention enforcement | `app/Services/Privacy/DataRetentionService.php`, `app/Console/Commands/EnforceDataRetentionCommand.php`, `routes/console.php` |
| AI minimization and storage | `app/Services/Privacy/AiDataSanitizerService.php`, `app/Services/AiInventoryAssistantService.php`, `app/Http/Controllers/DashboardAiAssistantController.php` |
| Avatar access control | `app/Http/Controllers/ProfileController.php` |
| Supplier field-level API disclosure | `app/Http/Resources/SupplierResource.php`, `app/Http/Controllers/SupplierController.php` |
| Personal-field encryption | `app/Models/User.php`, `app/Models/PrivacyRequest.php`, `app/Models/SecurityIncident.php` |
| Local-only audit location | `app/Services/AuditReverseGeocoder.php` |

## Verification procedure

Run these checks in a test environment. Do not run a live retention sweep until the DPO/records owner has reviewed the dry-run counts and a current backup exists.

```powershell
php artisan test tests/Feature/Privacy
php artisan test tests/Feature/ProfilePictureTest.php
php artisan test tests/Feature/AiChatbotConversationHistoryTest.php tests/Feature/AiChatbotAttachmentTest.php tests/Feature/AiInventoryAssistantTest.php
php artisan test tests/Feature/SupplierManagementTest.php
php artisan privacy:enforce-retention --dry-run
```

For operational evidence:

1. Capture the public Privacy Notice with no personal records visible.
2. Capture the Super Administrator privacy-governance ROPA and classification pages with secrets and record values redacted.
3. Sign in as each representative role and record allowed/denied results for user management, supplier sensitive details/downloads, audit trail, privacy governance, DSR ownership, AI conversation ownership, and avatar access.
4. In a non-production database, use synthetic values to confirm encrypted model fields are not readable plaintext in raw database rows and are decrypted only through authorized application paths.
5. Run `php artisan privacy:enforce-retention --dry-run`; approve the candidate counts, take a backup, then run the live command in a maintenance window. Confirm old AI attachments and DSR archives are absent from private storage while required case/audit records remain.
6. Review production application and proxy logs for personal values using an authorized, access-controlled procedure; do not copy raw log contents into tickets or screenshots.

## Deployment and governance actions still required

- Set and verify `DPO_NAME`, `DPO_EMAIL`, `DPO_PHONE`, and (only if officially issued) `NPC_REGISTRATION_NUMBER` in the production secret/environment manager.
- Confirm the scheduler is running so `privacy:enforce-retention` executes daily at 02:00. Without the scheduler, configured periods are policy values only.
- Have the DPO/records officer approve retention for audit, account attribution, supplier/procurement, logistics, operational ledgers, privacy cases, and security incidents. The application intentionally does not guess or automatically erase these records.
- Inventory the actual production email, SMS, Gemini, hosting, database, backup, and monitoring providers; execute the required data-processing/confidentiality agreements and cross-border-transfer assessment where applicable.
- Verify production HTTPS, encryption keys, backups, access reviews, breach procedures, and secure disposal independently. Repository code cannot prove the deployment's operational controls.

## Legal references

- Republic Act No. 10173: <https://lawphil.net/statutes/repacts/ra2012/ra_10173_2012.html>
- NPC Implementing Rules and Regulations: <https://privacy.gov.ph/implementing-rules-regulations-data-privacy-act-2012/>
- NPC guidance on creating a privacy manual: <https://privacy.gov.ph/creating-a-privacy-manual/>
- NPC Circular 16-01, Security of Personal Data in Government Agencies: <https://privacy.gov.ph/npc-circular-16-01-security-of-personal-data-in-government-agencies/>
