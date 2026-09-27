<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class BulkUploadPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const RECORDS = 10_000;

    public function test_ten_thousand_row_csv_bulk_import(): void
    {
        $rows = ['sku,name,category'];
        for ($i = 1; $i <= self::RECORDS; $i++) {
            $rows[] = sprintf('CSV-%05d,CSV Item %d,Bulk Medical Supplies', $i, $i);
        }

        $this->runScenario('CSV', UploadedFile::fake()->createWithContent('bulk.csv', implode("\n", $rows)), 'CSV');
    }

    public function test_ten_thousand_row_json_bulk_import(): void
    {
        $rows = [];
        for ($i = 1; $i <= self::RECORDS; $i++) {
            $rows[] = ['sku' => sprintf('JSON-%05d', $i), 'name' => "JSON Item {$i}", 'category' => 'Bulk Medical Supplies'];
        }

        $this->runScenario('JSON', UploadedFile::fake()->createWithContent('bulk.json', json_encode($rows, JSON_THROW_ON_ERROR)), 'JSON');
    }

    public function test_ten_thousand_row_xlsx_bulk_import(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bulk_xlsx_').'.xlsx';
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $sheet .= '<row r="1"><c r="A1" t="inlineStr"><is><t>sku</t></is></c><c r="B1" t="inlineStr"><is><t>name</t></is></c><c r="C1" t="inlineStr"><is><t>category</t></is></c></row>';
        for ($i = 1; $i <= self::RECORDS; $i++) {
            $row = $i + 1;
            $sheet .= sprintf(
                '<row r="%1$d"><c r="A%1$d" t="inlineStr"><is><t>XLSX-%2$05d</t></is></c><c r="B%1$d" t="inlineStr"><is><t>XLSX Item %2$d</t></is></c><c r="C%1$d" t="inlineStr"><is><t>Bulk Medical Supplies</t></is></c></row>',
                $row,
                $i,
            );
        }
        $sheet .= '</sheetData></worksheet>';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        try {
            $this->runScenario(
                'XLSX',
                new UploadedFile($path, 'bulk.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
                'XLSX',
            );
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function runScenario(string $format, UploadedFile $file, string $prefix): void
    {
        $user = User::factory()->inventoryManager()->create();
        $category = ItemCategory::create([
            'name' => 'Bulk Medical Supplies',
            'code' => 'BULK-MEDICAL',
            'is_active' => true,
        ]);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $started = hrtime(true);
        $fileSize = $file->getSize();

        $preview = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ])->assertOk()
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('valid_count', self::RECORDS);

        $commit = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $preview->json('import_token'),
            'target' => 'items',
        ])->assertAccepted()->assertJsonPath('async', true);

        $this->actingAs($user)->getJson($commit->json('status_url'))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('processed', self::RECORDS)
            ->assertJsonPath('created', self::RECORDS);

        $this->assertSame(self::RECORDS, InventoryItem::query()->where('sku', 'like', $prefix.'-%')->count());
        $this->assertDatabaseHas('inventory_items', ['sku' => $prefix.'-00001']);
        $this->assertDatabaseHas('inventory_items', ['sku' => $prefix.'-10000']);
        $this->assertSame($category->id, InventoryItem::query()->where('sku', $prefix.'-00001')->value('category_id'));
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::BulkImportStarted)->count());
        $this->assertSame(1, AuditLog::query()->where('action', AuditAction::BulkImportCompleted)->count());
        $this->assertSame(0, AuditLog::query()->where('action', AuditAction::CreatedInventoryItem)->count());

        $duration = (hrtime(true) - $started) / 1_000_000_000;
        fwrite(STDERR, sprintf(
            "\nBULK_METRIC format=%s records=%d bytes=%d duration=%.3fs peak_memory=%d queries=%d\n",
            $format,
            self::RECORDS,
            $fileSize,
            $duration,
            memory_get_peak_usage(true),
            $queryCount,
        ));
    }
}
