# Invalid File / Error Handling Report

## Scope reviewed

- CSV, XLSX/XLS, and JSON inventory imports
- Logistics document upload and revision
- Supplier compliance documents
- AI-chat attachments
- Profile avatars and supplier logos
- Camera/barcode image scanning (local browser decoding only; no server file persistence)
- Private storage, permissions, audit behavior, loading states, and CSV exports

All authoritative checks run server-side. Uploads are stored on private disks except profile avatars, which are served through the existing protected avatar endpoint.

## Test evidence

| Test case | Why invalid | Expected result | Actual user-facing error | Result |
|---|---|---|---|---|
| `unsupported.exe` | Unsupported extension | Reject before parsing | `Unsupported file format [exe]. Please upload a CSV (.csv), Excel (.xlsx / .xls), or JSON (.json) file.` | Pass |
| `spoofed.csv` | PDF signature renamed as CSV | Reject content mismatch | `The file content does not match the .csv extension. Upload a valid CSV file.` | Pass |
| `corrupted.xlsx` | Not a ZIP/OpenXML workbook | Reject corrupted workbook | `The uploaded Excel file could not be read. The file may be corrupted or invalid.` | Pass |
| Macro-enabled workbook renamed as XLSX | Contains VBA/macros | Reject active content | `Macro-enabled Excel files are not supported. Remove macros and upload a standard .xlsx file.` | Pass |
| `malformed.json` | Truncated JSON syntax | Reject syntax error | `Invalid JSON syntax: Syntax error` | Pass |
| `missing-headers.csv` | Missing `sku` and `name` | Reject schema | `Missing required column headers: sku, name.` | Pass |
| Empty CSV/XLSX/JSON | No usable content | Reject empty upload | `The uploaded file is empty. Select a file containing data.` | Pass |
| Duplicate aliases `sku,item_code` | Both map to SKU | Reject ambiguous columns | `Invalid import structure. Duplicate columns map to: sku. Remove the duplicate columns and retry.` | Pass |
| Invalid item row | Bad numeric/required/reference value | Return row and field errors | Specific row, field, value, and correction message returned | Pass |
| Oversized import | More than 10 MB | Reject at request boundary | `The file size cannot exceed 10 MB.` | Pass |
| More than 5,000 import rows | Synchronous batch limit exceeded | Reject without staging | Maximum-record message with actual count | Pass |
| Image renamed as PDF | Extension/content mismatch | Reject before storage | `The file content does not match the .pdf extension. Upload a valid PDF file.` | Pass |
| PNG renamed as JPG | Image MIME/extension mismatch | Reject before storage | `The image content does not match the .jpg extension.` | Pass |
| Unexpected parser exception | Internal failure | Hide internals | `The uploaded file could not be parsed. Verify the file and try again.` | Pass |

## Integrity and recovery proof

- Invalid import previews receive no staging token and create no inventory records.
- Import commit is transactional; a staged token is single-use and a second submission is rejected.
- Rejected supplier/logistics documents and AI attachments create no document/message records and leave no stored file.
- An invalid AI attachment does not create an empty conversation.
- A valid corrected CSV succeeds immediately after a failed upload.
- Import and AI controls disable duplicate submissions and clear loading state in `finally` blocks.
- CSV report exports prefix formula-like text while preserving legitimate negative numbers.
- Content-mismatch diagnostics log only a safe basename, extension, and rejection reason; raw file contents are never logged.

## Automated verification

```shell
php artisan test tests/Feature/DataImportTest.php tests/Feature/DocumentTrackingAndLogisticsTest.php tests/Feature/SupplierManagementTest.php tests/Feature/AiChatbotAttachmentTest.php tests/Feature/ProfilePictureTest.php tests/Feature/SupplierLogoTest.php tests/Feature/InventoryReportTest.php
```

The tests use SQLite in memory and fake storage; they do not touch the active database or shared files.

Latest focused result: **221 passed, 1,524 assertions**.

The broader project suite completed with **1,580 passed and 23 failed**. The failures were outside these upload/import workflows and were dominated by privacy-consent redirects in authentication/administration tests, plus an inventory-dashboard assertion. The focused upload/report suite remained green after formatting.
