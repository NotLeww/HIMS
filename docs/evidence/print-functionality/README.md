# Print Functionality Evidence

Evidence entry:

> Printed report samples and browser print-preview results confirming complete data, correct pagination, readable tables, preserved formatting, and no clipped or overlapping content.

## Browser print verification

Microsoft Edge 154.0.4258.37 generated the PDFs from the production Blade print template using its browser print engine.

| Sample | Layout | Pages | Verified |
| --- | --- | ---: | --- |
| `output/pdf/hims-normal-report-print.pdf` | A4 portrait | 1 | Complete title, context, filters, table, totals, sign-off, and footer |
| `output/pdf/hims-wide-filtered-report-print.pdf` | A4 landscape | 3 | All 12 columns visible, Low Stock filter preserved, leading-zero SKU/barcode/GTIN intact, repeated headers |
| `output/pdf/hims-long-report-print.pdf` | A4 landscape | 5 | 80 rows, long text wrapping, repeated headers, totals only on final page, first and final identifiers intact |

Automated PDF inspection found zero text words outside page bounds in all three samples. Representative first, middle, and final page PNG renders are stored in this directory for visual review.

## Regression verification

`php artisan test tests/Feature/InventoryReportTest.php tests/Feature/AuditTrailTest.php tests/Feature/RisPrintViewTest.php tests/Feature/DocumentTrackingAndLogisticsTest.php tests/Feature/ScheduledReportTest.php`

Result: 127 passed, 975 assertions.

`npm run build`

Result: production assets built successfully.

The automated Windows browser connector did not expose the installed Edge window, so the native print-preview dialog itself could not be saved as a screenshot. The saved PDFs were nevertheless produced by Edge's print engine, then rendered and visually inspected page by page.
