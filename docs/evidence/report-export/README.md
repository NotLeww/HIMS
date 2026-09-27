# Report Export Evidence

**Checklist item:** Report Export  
**Verified:** 2026-09-27

## Result

CSV and the project's Excel-compatible `.xls` export were already implemented. The PDF option returned a printable HTML page rather than a PDF file, so it was corrected to generate a downloadable, paginated PDF through the existing HIMS PDF builder.

## Sample reports

All three samples were generated through `/inventory/reports/generate` from the same isolated, non-sensitive test dataset and filters:

- Report: Stock Status
- Period: September 1-27, 2026
- Category: Sample PPE
- Location: Evidence Store
- Records: Sample N95 Respirator and Sample Sterile Gloves
- Expected totals: 2 items, 155 units, PHP 5,925.00 valuation, 1 in stock, and 1 low stock

Files:

- `sample-stock-status.pdf`
- `sample-stock-status.xls`
- `sample-stock-status.csv`

## Validation

| Format | Validation result |
| --- | --- |
| PDF | Valid `%PDF-1.4` document; one renderable A4 page; extracted values match the two filtered records and expected totals; visual inspection found no clipped or overlapping content. |
| Excel | Opened successfully in Microsoft Excel as one worksheet with 24 used rows and 9 used columns; report records and PHP 5,925.00 total match the PDF and CSV. |
| CSV | Valid UTF-8 BOM; parsed successfully with 24 rows and both expected data records; values match the PDF and Excel export. CSV escaping for commas, quotes, and embedded line breaks is covered by the feature test. |

The export route remains protected by authentication and `view_reports`. Procurement and supplier-spend exports continue to require the financial-data permission. Empty filtered reports return valid output without fabricated rows.

## Automated verification

```text
php artisan test tests/Unit/DemoPdfBuilderTest.php tests/Feature/InventoryReportTest.php --stop-on-failure
47 passed (380 assertions)
```

The samples contain synthetic checklist data only. No production records or credentials are included.
