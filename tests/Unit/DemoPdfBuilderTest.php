<?php

namespace Tests\Unit;

use App\Support\DemoPdfBuilder;
use PHPUnit\Framework\TestCase;

class DemoPdfBuilderTest extends TestCase
{
    public function test_general_document_wraps_long_table_cells_with_configured_column_widths(): void
    {
        $longItemName = 'Verorab Inactivated Rabies Vaccine 0.5mL + Diluent [VAC-RAB-VER05]';

        $pdf = DemoPdfBuilder::create(
            title: 'DELIVERY RECEIPT',
            sections: [[
                'heading' => 'DELIVERED INVENTORY',
                'table' => [
                    'headers' => ['Item / Product Name & SKU', 'Batch / Lot No.', 'Expiry Date', 'Quantity', 'Unit Cost (PHP)'],
                    'widths' => [2.8, 1.25, 1.1, 0.9, 1.1],
                    'rows' => [[$longItemName, 'VER-2026-881', '2028-09-24', '500 vials', '1,450.00']],
                ],
            ]],
        );

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('(VER-2026-881) Tj', $pdf);
        $this->assertStringNotContainsString('('.$longItemName.') Tj', $pdf);
    }

    public function test_generated_pdf_contains_renderable_page_content(): void
    {
        $pdf = DemoPdfBuilder::create('Logistics record', [
            ['heading' => 'DETAILS', 'lines' => ['Reference: TEST-001']],
        ]);

        $this->assertMatchesRegularExpression('/\/Type\s*\/Page\b/', $pdf);
        $this->assertStringContainsString('/Contents', $pdf);
    }

    public function test_long_tables_continue_on_additional_pages_without_dropping_rows(): void
    {
        $rows = array_map(
            fn (int $number) => ['ITEM-'.$number, 'Inventory item '.$number, (string) $number],
            range(1, 80)
        );

        $pdf = DemoPdfBuilder::create('Stock Status', [[
            'heading' => 'INVENTORY',
            'table' => [
                'headers' => ['SKU', 'Item', 'Units'],
                'rows' => $rows,
            ],
        ]]);

        $this->assertGreaterThan(1, substr_count($pdf, '/Type/Page/Parent'));
        $this->assertStringContainsString('(ITEM-1) Tj', $pdf);
        $this->assertStringContainsString('(ITEM-80) Tj', $pdf);
    }

    public function test_branded_headers_and_footers_repeat_on_every_page_with_an_embedded_logo(): void
    {
        $rows = array_map(
            fn (int $number) => ['ITEM-'.$number, 'Inventory item '.$number, (string) $number],
            range(1, 80)
        );

        $pdf = DemoPdfBuilder::create('Stock Status', [[
            'heading' => 'INVENTORY',
            'table' => [
                'headers' => ['SKU', 'Item', 'Units'],
                'rows' => $rows,
            ],
        ]], 'Last 30 days', [
            'organization' => 'DJNRMHS',
            'address' => 'Tala, Caloocan City',
            'system' => 'Hospital Inventory Management System',
            'logo_path' => dirname(__DIR__, 2).'/public/img/hims-logo.png',
            'footer' => 'DJNRMHS | Generated 2026-09-28 12:00:00',
        ]);

        $pageCount = substr_count($pdf, '/Type/Page/Parent');

        $this->assertGreaterThan(1, $pageCount);
        $this->assertSame($pageCount, substr_count($pdf, '/Im1 Do'));
        $this->assertSame($pageCount, substr_count($pdf, '(DJNRMHS) Tj'));
        $this->assertSame($pageCount, substr_count($pdf, '(DJNRMHS | Generated 2026-09-28 12:00:00) Tj'));
        $this->assertStringContainsString('/Subtype/Image', $pdf);
        $this->assertStringContainsString('(Page 1 of '.$pageCount.') Tj', $pdf);
        $this->assertStringContainsString('(Page '.$pageCount.' of '.$pageCount.') Tj', $pdf);
    }
}
