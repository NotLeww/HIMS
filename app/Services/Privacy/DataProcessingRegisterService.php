<?php

namespace App\Services\Privacy;

class DataProcessingRegisterService
{
    /**
     * Get the formal Records of Processing Activities (ROPA) under RA 10173 and ISO 27001.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActivities(): array
    {
        return array_map(function ($r) {
            return [
                'activity_id' => $r['id'],
                'process_name' => $r['name'],
                'system_module' => 'Core HIMS',
                'purpose' => $r['purpose'],
                'legal_basis' => $r['legal_basis'],
                'retention_period' => $r['retention_period'],
                'data_categories' => $r['data_categories'],
                'data_subjects' => $r['data_subjects'],
                'access_roles' => $r['access_roles'],
                'security_measures' => is_array($r['safeguards']) ? $r['safeguards'] : explode(', ', $r['safeguards']),
                'third_party_transfers' => $r['third_party_transfers'],
            ];
        }, $this->records());
    }

    public function records(): array
    {
        return [
            [
                'id' => 'ROPA-001',
                'name' => 'User Authentication & Identity Access Management',
                'purpose' => 'Authenticate hospital personnel, enforce least-privilege role boundaries, and protect patient care supply operations from unauthorized access.',
                'legal_basis' => 'RA 10173 Section 12(a) & 12(b): Legitimate purpose and performance of hospital employment obligations.',
                'data_categories' => [
                    'Employee Full Name',
                    'Institutional Email',
                    'Employee ID Number',
                    'Department Assignment',
                    'Role & Permissions',
                    'Bcrypt/Argon2id Password Hash',
                    'HMAC Blind Password Fingerprint',
                    'Encrypted MFA TOTP Secret',
                ],
                'data_subjects' => 'Hospital staff, pharmacy dispensers, warehouse personnel, logistics handlers, and system administrators.',
                'access_roles' => 'Super Administrator (Full Management), Administrator (Staff Provisioning), Self (Own Profile).',
                'retention_period' => 'No automated deletion. Active during employment and deactivated upon departure; identity attribution remains where required for inventory and audit integrity. Final disposal requires an approved institutional schedule.',
                'safeguards' => 'Multi-Factor Authentication (TOTP/Email), progressive lockout after 5 failed attempts, 4-minute inactivity timeout, blind fingerprinting, no-store browser cache controls.',
                'third_party_transfers' => 'None. Managed internally on hospital infrastructure.',
            ],
            [
                'id' => 'ROPA-002',
                'name' => 'Forensic Audit Logging & Security Accountability',
                'purpose' => 'Preserve an immutable chronological chain of evidence for stock movements, administrative decisions, authentication attempts, and system configuration mutations.',
                'legal_basis' => 'RA 10173 Section 20(c): Implementation of security measures and monitoring; ISO/IEC 27001:2022 A.8.15.',
                'data_categories' => [
                    'Actor Name & User ID',
                    'IP Address',
                    'User Agent',
                    'Approximate Browser Geolocation Coordinates',
                    'Action Timestamp (PHT)',
                    'Pre/Post State Diff Snapshots',
                ],
                'data_subjects' => 'All authenticated system operators and external failed login actors.',
                'access_roles' => 'Super Administrator only. Read-only append-only interface.',
                'retention_period' => 'Append-only and not automatically deleted. The institution must validate any permanent-retention requirement and approved disposal authority.',
                'safeguards' => 'Model-level update/delete suppression, sensitive authentication secrets (passwords, tokens) strictly excluded from snapshots, TLS in transit.',
                'third_party_transfers' => 'None.',
            ],
            [
                'id' => 'ROPA-003',
                'name' => 'Warehouse Custody & Material Issuance Sign-off',
                'purpose' => 'Track internal chain of custody, department requisitions, and physical disbursement of pharmaceuticals, medical supplies, and narcotics.',
                'legal_basis' => 'Executive Order No. 292 / COA Circulars / DOH Hospital Licensing Regulations.',
                'data_categories' => [
                    'Requisitioner Name & Department',
                    'Approver Name & Timestamp',
                    'Warehouse Dispenser Name',
                    'Witness Name (for controlled substances/narcotics)',
                ],
                'data_subjects' => 'Hospital ward nurses, pharmacy staff, warehouse custodians, department heads.',
                'access_roles' => 'Warehouse Staff, Pharmacy Staff, Inventory Manager, Department Requisitioners.',
                'retention_period' => 'Not automatically deleted because records are tied to physical stock and custody history. Final retention requires the institution\'s approved records schedule.',
                'safeguards' => 'Role-based access control, dual-custody verification for high-risk pharmaceuticals, tamper-evident stock movements.',
                'third_party_transfers' => 'None.',
            ],
            [
                'id' => 'ROPA-004',
                'name' => 'Supplier Accreditation & Procurement Contract Management',
                'purpose' => 'Verify supplier legal personality, PhilGEPS eligibility, BIR compliance, FDA establishment licenses, and contact points for medical supply delivery.',
                'legal_basis' => 'RA 12009 (New Government Procurement Act) / RA 10173 Section 12(b) (Contractual compliance).',
                'data_categories' => [
                    'Supplier Authorized Representative Name',
                    'Corporate Email Address',
                    'Contact Phone Numbers',
                    'Taxpayer Identification Number (TIN)',
                    'Accreditation & Quality Certificates',
                ],
                'data_subjects' => 'Commercial suppliers, distributors, accredited manufacturer representatives.',
                'access_roles' => 'Inventory Manager, Administrator, Super Administrator.',
                'retention_period' => 'No automated disposal. The hospital must approve the applicable COA/NAP/procurement retention schedule before deletion.',
                'safeguards' => 'Separate permission gates for commercial financials (ViewSupplierSensitiveData), private disk storage with SHA-256 checksums.',
                'third_party_transfers' => 'None outside government procurement submission requirements.',
            ],
            [
                'id' => 'ROPA-005',
                'name' => 'AI-Powered Inventory Analytics & Operational Assistant',
                'purpose' => 'Provide hospital staff with rapid inventory queries, reorder calculations, and stock risk forecasts using grounded database context.',
                'legal_basis' => 'RA 10173 Section 12(f): Legitimate interests of the hospital to prevent stockouts of critical medicine.',
                'data_categories' => [
                    'Sanitized User Inventory Questions',
                    'Aggregate Item Quantities',
                    'Item Consumption Patterns',
                ],
                'data_subjects' => 'Operating staff submitting inventory queries.',
                'access_roles' => 'Authenticated staff with ViewInventory permission.',
                'retention_period' => 'Chat conversations and their private attachments are deleted after the configured 30-day inactivity window by the scheduled retention sweep.',
                'safeguards' => 'Pattern-based redaction is applied before storage and external dispatch; personal fields are removed from structured AI context; raw image and PDF binary content remains inside HIMS and is not transmitted to the external AI provider.',
                'third_party_transfers' => 'Google Gemini API only when configured. Sanitized prompts, minimized operational context, and sanitized extracted text from supported text-based attachments may be transmitted; raw attachment files are not transmitted.',
            ],
            [
                'id' => 'ROPA-006',
                'name' => 'System Recovery & Security Incident Management',
                'purpose' => 'Detect, record, contain, and investigate application exceptions, authentication anomalies, and suspected personal data breaches.',
                'legal_basis' => 'RA 10173 Section 20(f) / NPC Circular 16-03 (Security Incident Management) / ISO/IEC 27001:2022 A.5.24.',
                'data_categories' => [
                    'Reporter & Assignee Names',
                    'Error Traces & Module Context',
                    'Target User Identifiers',
                    'Incident Timeline & Impact Classification',
                    'Breach Harm Assessment Notes',
                ],
                'data_subjects' => 'Affected users, system operators, DPO investigation team.',
                'access_roles' => 'Super Administrator (DPO / System Owner).',
                'retention_period' => 'No automated incident disposal. The DPO/records owner must approve a schedule; resolved system-recovery records are separately purged after their configured period.',
                'safeguards' => 'Restricted access under ManageSystemRecovery and ManagePrivacyCompliance permissions, safe exception interception that redacts stack traces in production.',
                'third_party_transfers' => 'National Privacy Commission (NPC) only when mandatory reportability thresholds are met under Section 20(f).',
            ],
        ];
    }
}
