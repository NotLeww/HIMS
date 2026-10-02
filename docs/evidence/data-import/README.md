# Data Import verification evidence

The retained files exercise the Inventory Items schema without depending on seeded category or location names.

- `valid-items.csv`: valid rows, numeric conversion, and quoted commas.
- `invalid-items.csv`: duplicate SKU plus an invalid decimal.
- `valid-items.json`: valid array-of-objects import.
- `invalid-items.json`: invalid nested value where a scalar is required.
- `valid-items.xlsx`: valid OpenXML workbook with SKU `00012345678901234567` stored as text to verify leading-zero and large-identifier preservation.

Automated proof is in `tests/Feature/DataImportTest.php`. Run:

```shell
php artisan test tests/Feature/DataImportTest.php
```

The feature suite covers preview and transactional commit for CSV, JSON, and XLSX; persisted record assertions; malformed files; missing fields; duplicates; relationship failures; size and row limits; authorization; safe unexpected errors; and audit-log creation.
