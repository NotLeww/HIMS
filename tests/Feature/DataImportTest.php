<?php

namespace Tests\Feature;

use App\Jobs\ProcessDataImport;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\Import\DataImportExecutor;
use App\Services\Import\DataImportReader;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use ZipArchive;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    private function inventoryManager(): User
    {
        return User::factory()->inventoryManager()->create();
    }

    private function viewer(): User
    {
        return User::factory()->viewer()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/inventory/import')
            ->assertRedirect('/login');
    }

    public function test_user_without_import_permissions_is_forbidden(): void
    {
        $this->actingAs($this->viewer())
            ->get('/inventory/import')
            ->assertStatus(403);
    }

    public function test_user_without_import_permissions_cannot_preview_or_commit_directly(): void
    {
        $user = $this->viewer();
        $file = UploadedFile::fake()->createWithContent('items.csv', "sku,name\nBLOCKED-01,Blocked Item\n");

        $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ])->assertForbidden();

        $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => 'forged-token',
            'target' => 'items',
        ])->assertForbidden();

        $this->assertDatabaseMissing('inventory_items', ['sku' => 'BLOCKED-01']);
    }

    public function test_authorized_user_can_access_import_index(): void
    {
        $this->actingAs($this->inventoryManager())
            ->get('/inventory/import')
            ->assertStatus(200)
            ->assertSee('Data Ingress &amp; System Import', false)
            ->assertSee('Inventory Items')
            ->assertSee('Storage Locations')
            ->assertSee('Suppliers &amp; Vendors', false)
            ->assertSee(':disabled="!selectedFile || isValidating"', false)
            ->assertSee(':disabled="!validationResult || !validationResult.is_valid || isCommitting"', false)
            ->assertSee('this.isValidating = false', false)
            ->assertSee('this.isCommitting = false', false)
            ->assertSee('Bulk import processing')
            ->assertSee('hims:active-import-status', false)
            ->assertSee('pollImportStatus', false);
    }

    public function test_download_template_supports_csv_json_and_xlsx(): void
    {
        $user = $this->inventoryManager();

        // 1. CSV Template
        $csvRes = $this->actingAs($user)
            ->get('/inventory/import/template?target=items&format=csv');
        $csvRes->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('sku,name,description', $csvRes->streamedContent());

        // 2. JSON Template
        $jsonRes = $this->actingAs($user)
            ->get('/inventory/import/template?target=items&format=json');
        $jsonRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->assertStringContainsString('MED-PARA-500', $jsonRes->streamedContent());

        // 3. Excel Template (.xlsx)
        $xlsxRes = $this->actingAs($user)
            ->get('/inventory/import/template?target=items&format=xlsx');
        $xlsxRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_retained_csv_json_and_xlsx_evidence_files_import_successfully(): void
    {
        $user = $this->inventoryManager();
        $samples = [
            ['valid-items.csv', 'text/csv', 'CSV-EVIDENCE-001'],
            ['valid-items.json', 'application/json', 'JSON-EVIDENCE-001'],
            ['valid-items.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', '00012345678901234567'],
        ];

        foreach ($samples as [$name, $mime, $expectedSku]) {
            $path = base_path("docs/evidence/data-import/{$name}");
            $preview = $this->actingAs($user)->postJson('/inventory/import/preview', [
                'file' => new UploadedFile($path, $name, $mime, null, true),
                'target' => 'items',
                'mode' => 'create_only',
            ]);

            $preview->assertOk()->assertJsonPath('is_valid', true);
            $this->actingAs($user)->postJson('/inventory/import/commit', [
                'import_token' => $preview->json('import_token'),
                'target' => 'items',
            ])->assertOk()->assertJsonPath('success', true);

            $this->assertDatabaseHas('inventory_items', ['sku' => $expectedSku]);
        }
    }

    public function test_preview_and_commit_valid_csv_items(): void
    {
        $user = $this->inventoryManager();
        $category = ItemCategory::create(['name' => 'Antibiotics', 'code' => 'ABX']);
        $location = StorageLocation::create(['name' => 'Main Pharmacy', 'code' => 'MPHARM', 'status' => 'active']);

        $csvContent = "sku,name,description,category,unit,unit_cost,reorder_level,default_location\n".
            "MED-AMOX-500,Amoxicillin 500mg,Oral capsule,Antibiotics,capsule,6.50,100,MPHARM\n".
            "MED-CLAV-625,Co-Amoxiclav 625mg,Film-coated tablet,ABX,tablet,18.00,50,Main Pharmacy\n";

        $file = UploadedFile::fake()->createWithContent('items.csv', $csvContent);

        // Step 1: Preview
        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('valid_count', 2)
            ->assertJsonPath('invalid_count', 0)
            ->assertJsonPath('create_count', 2);

        $importToken = $previewRes->json('import_token');
        $this->assertNotNull($importToken);

        // Step 2: Commit
        $commitRes = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $importToken,
            'target' => 'items',
        ]);

        $commitRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('created', 2)
            ->assertJsonPath('total', 2);

        $this->assertDatabaseHas('inventory_items', [
            'sku' => 'MED-AMOX-500',
            'name' => 'Amoxicillin 500mg',
            'category_id' => $category->id,
            'default_location_id' => $location->id,
            'unit' => 'capsule',
            'unit_cost' => 6.50,
            'reorder_level' => 100,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'sku' => 'MED-CLAV-625',
            'name' => 'Co-Amoxiclav 625mg',
            'category_id' => $category->id,
            'default_location_id' => $location->id,
            'unit' => 'tablet',
            'unit_cost' => 18.00,
            'reorder_level' => 50,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'created_inventory_item',
            'module' => 'Inventory',
            'outcome' => 'success',
        ]);
    }

    public function test_preview_and_commit_valid_json_items(): void
    {
        $user = $this->inventoryManager();

        $jsonData = [
            [
                'sku' => 'SURG-GLV-07',
                'name' => 'Sterile Surgical Gloves Size 7.0',
                'unit' => 'pair',
                'unit_cost' => 25.00,
                'reorder_level' => 200,
            ],
            [
                'sku' => 'SURG-GLV-75',
                'name' => 'Sterile Surgical Gloves Size 7.5',
                'unit' => 'pair',
                'unit_cost' => 25.00,
                'reorder_level' => 200,
            ],
        ];

        $file = UploadedFile::fake()->createWithContent('gloves.json', json_encode($jsonData));

        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2);

        $token = $previewRes->json('import_token');

        $commitRes = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $token,
            'target' => 'items',
        ]);

        $commitRes->assertStatus(200)->assertJsonPath('created', 2);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'SURG-GLV-07']);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'SURG-GLV-75']);
    }

    public function test_preview_and_commit_valid_xlsx_items(): void
    {
        $user = $this->inventoryManager();

        $tmpPath = $this->xlsxFixture([
            ['sku', 'name', 'unit_cost'],
            ['00012345678901234567', 'Oral Rehydration Salts', '12.50'],
        ]);
        $file = new UploadedFile($tmpPath, 'salts.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 1);

        $token = $previewRes->json('import_token');
        $commitRes = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $token,
            'target' => 'items',
        ]);

        $commitRes->assertStatus(200)->assertJsonPath('created', 1);
        $this->assertDatabaseHas('inventory_items', [
            'sku' => '00012345678901234567',
            'name' => 'Oral Rehydration Salts',
            'unit_cost' => 12.50,
            'unit' => null,
        ]);

        @unlink($tmpPath);
    }

    public function test_xlsx_reports_missing_headers_duplicates_and_invalid_numbers(): void
    {
        $missingPath = $this->xlsxFixture([
            ['sku', 'unit_cost'],
            ['XLSX-MISSING-01', '10.00'],
        ]);
        $missing = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => new UploadedFile($missingPath, 'missing.xlsx', null, null, true),
            'target' => 'items',
            'mode' => 'create_only',
        ]);
        $missing->assertOk()
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'missing_header');

        $invalidPath = $this->xlsxFixture([
            ['sku', 'name', 'unit_cost'],
            ['XLSX-DUP-01', 'First Item', 'not-a-number'],
            ['XLSX-DUP-01', 'Second Item', '10.00'],
        ]);
        $invalid = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => new UploadedFile($invalidPath, 'invalid.xlsx', null, null, true),
            'target' => 'items',
            'mode' => 'create_only',
        ]);
        $invalid->assertOk()->assertJsonPath('is_valid', false);
        $types = array_column($invalid->json('errors'), 'type');
        $this->assertContains('invalid_value', $types);
        $this->assertContains('duplicate_record', $types);

        @unlink($missingPath);
        @unlink($invalidPath);
    }

    public function test_preview_detects_in_file_duplicate_skus(): void
    {
        $user = $this->inventoryManager();

        $csvContent = "sku,name\n".
            "MED-DUP-01,First Item\n".
            "MED-DUP-01,Second Duplicate Item\n";

        $file = UploadedFile::fake()->createWithContent('duplicates.csv', $csvContent);

        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('invalid_count', 1);

        $this->assertStringContainsString("Duplicate SKU 'MED-DUP-01'", $previewRes->json('errors.0.message'));
        $this->assertNull($previewRes->json('import_token'));
    }

    public function test_create_only_mode_rejects_existing_database_skus(): void
    {
        $user = $this->inventoryManager();
        InventoryItem::create([
            'sku' => 'MED-EXIST-01',
            'name' => 'Already In Database',
            'quantity_on_hand' => 10,
        ]);

        $csvContent = "sku,name\nMED-EXIST-01,Attempt Overwrite\n";
        $file = UploadedFile::fake()->createWithContent('existing.csv', $csvContent);

        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', false);

        $this->assertStringContainsString('already exists in the database', $previewRes->json('errors.0.message'));
    }

    public function test_update_or_create_mode_updates_existing_records(): void
    {
        $user = $this->inventoryManager();
        $item = InventoryItem::create([
            'sku' => 'MED-UPDATE-01',
            'name' => 'Original Name',
            'unit' => 'capsule',
            'unit_cost' => 10.00,
            'quantity_on_hand' => 10,
        ]);

        $csvContent = "sku,name,unit_cost\nMED-UPDATE-01,Updated Item Description,25.50\n";
        $file = UploadedFile::fake()->createWithContent('update.csv', $csvContent);

        // Preview in update_or_create mode
        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'update_or_create',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('update_count', 1);

        $token = $previewRes->json('import_token');

        // Commit
        $commitRes = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $token,
            'target' => 'items',
        ]);

        $commitRes->assertStatus(200)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('created', 0);

        $item->refresh();
        $this->assertEquals('Updated Item Description', $item->name);
        $this->assertEquals(25.50, (float) $item->unit_cost);
        $this->assertSame('capsule', $item->unit);
    }

    public function test_preview_detects_non_existent_category_or_location(): void
    {
        $user = $this->inventoryManager();

        $csvContent = "sku,name,category,default_location\n".
            "MED-INVALID-REL,Test Item,Unknown Specialty Category,NON-EXISTENT-LOC\n";

        $file = UploadedFile::fake()->createWithContent('invalid_rel.csv', $csvContent);

        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', false);

        $errors = $previewRes->json('errors');
        $this->assertCount(2, $errors);

        $fields = array_column($errors, 'field');
        $this->assertContains('category', $fields);
        $this->assertContains('default_location', $fields);
    }

    public function test_preview_and_commit_storage_locations(): void
    {
        $user = $this->inventoryManager();

        $csvContent = "code,name,type,zone,capacity,storage_classification\n".
            "LOC-ER-01,Emergency Ward Cabinet A,room,Emergency,500,ambient\n".
            "LOC-ICU-01,Intensive Care Narcotics Safe,narcotics_safe,ICU,150,secure_vault\n";

        $file = UploadedFile::fake()->createWithContent('locations.csv', $csvContent);

        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'locations',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2);

        $token = $previewRes->json('import_token');

        $commitRes = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $token,
            'target' => 'locations',
        ]);

        $commitRes->assertStatus(200)->assertJsonPath('created', 2);
        $this->assertDatabaseHas('storage_locations', ['code' => 'LOC-ER-01', 'capacity' => 500]);
        $this->assertDatabaseHas('storage_locations', ['code' => 'LOC-ICU-01', 'capacity' => 150]);
    }

    public function test_preview_and_commit_suppliers(): void
    {
        $user = $this->inventoryManager();

        $csvContent = "name,contact_person,email,phone,tax_number\n".
            "Bayer Philippines Inc.,Dr. Gomez,bayer.ph@bayer.com,+63 2 8555 1234,000-444-555-000\n";

        $file = UploadedFile::fake()->createWithContent('suppliers.csv', $csvContent);

        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'suppliers',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('is_valid', true);

        $token = $previewRes->json('import_token');

        $commitRes = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $token,
            'target' => 'suppliers',
        ]);

        $commitRes->assertStatus(200)->assertJsonPath('created', 1);
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Bayer Philippines Inc.',
            'email' => 'bayer.ph@bayer.com',
            'tax_number' => '000-444-555-000',
        ]);
    }

    public function test_commit_requires_valid_staged_token(): void
    {
        $user = $this->inventoryManager();

        $this->actingAs($user)
            ->postJson('/inventory/import/commit', [
                'import_token' => 'non-existent-token-uuid',
                'target' => 'items',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_staged_import_token_cannot_be_committed_twice(): void
    {
        $user = $this->inventoryManager();
        $preview = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('single-use.csv', "sku,name\nSINGLE-USE-001,Single Use Import\n"),
            'target' => 'items',
            'mode' => 'create_only',
        ])->assertOk();

        $payload = ['import_token' => $preview->json('import_token'), 'target' => 'items'];
        $this->actingAs($user)->postJson('/inventory/import/commit', $payload)
            ->assertOk()
            ->assertJsonPath('created', 1);
        $this->actingAs($user)->postJson('/inventory/import/commit', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(1, InventoryItem::where('sku', 'SINGLE-USE-001')->count());
    }

    public function test_preview_supports_case_insensitive_and_spaced_headers(): void
    {
        $user = $this->inventoryManager();

        // Headers with varied casing, spaces, and punctuation: "  SKU  ", "  Item Name (Required)  ", "  UNIT COST (₱)  "
        $csvContent = "\"  SKU  \",\"  Item Name (Required)  \",\"  UNIT COST (₱)  \"\n".
            "\"MED-CASE-01\",\"Ibuprofen 400mg Tablet\",\"3.75\"\n";

        $file = UploadedFile::fake()->createWithContent('spaced_headers.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('preview_rows.0.sku', 'MED-CASE-01')
            ->assertJsonPath('preview_rows.0.name', 'Ibuprofen 400mg Tablet');
    }

    public function test_preview_supports_utf8_bom_and_utf16_bom_csv(): void
    {
        $user = $this->inventoryManager();

        // 1. UTF-8 BOM
        $utf8BomContent = "\xEF\xBB\xBFsku,name,unit_cost\nMED-BOM-01,Salbutamol Inhaler,120.00\n";
        $utf8File = UploadedFile::fake()->createWithContent('bom_utf8.csv', $utf8BomContent);

        $resUtf8 = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $utf8File,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resUtf8->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('preview_rows.0.sku', 'MED-BOM-01');

        // 2. UTF-16LE BOM (\xFF\xFE)
        $rawUtf8 = "sku\tname\tunit_cost\nMED-UTF16-01\tDextrose 5% 500mL\t45.00\n";
        $utf16leContent = "\xFF\xFE".mb_convert_encoding($rawUtf8, 'UTF-16LE', 'UTF-8');
        $utf16File = UploadedFile::fake()->createWithContent('bom_utf16.csv', $utf16leContent);

        $resUtf16 = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $utf16File,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resUtf16->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('preview_rows.0.sku', 'MED-UTF16-01');
    }

    public function test_preview_supports_header_aliases_such_as_item_code_and_product_name(): void
    {
        $user = $this->inventoryManager();
        StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-STORE',
            'type' => 'warehouse',
            'status' => 'active',
        ]);

        // Uses "Item Code" and "Product Name" instead of "sku" and "name"
        $csvContent = "Item Code,Product Name,Default Location\n".
            "MED-ALIAS-01,Cefalexin 500mg,Main Store\n";

        $file = UploadedFile::fake()->createWithContent('aliases.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('preview_rows.0.sku', 'MED-ALIAS-01')
            ->assertJsonPath('preview_rows.0.name', 'Cefalexin 500mg');
    }

    public function test_preview_discovers_headers_after_title_banner(): void
    {
        $user = $this->inventoryManager();

        // Row 1: Hospital title banner
        // Row 2: Actual headers
        // Row 3: Data row
        $csvContent = "DJNRMHS Hospital Supplies Inventory Sheet - Sept 2026\n".
            "SKU,Item Name,Unit Cost\n".
            "MED-BANNER-01,Amoxicillin Clavulanate 625mg,28.50\n";

        $file = UploadedFile::fake()->createWithContent('with_title.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 1)
            ->assertJsonPath('preview_rows.0.sku', 'MED-BANNER-01');
    }

    public function test_preview_distinguishes_missing_headers_from_empty_required_values(): void
    {
        $user = $this->inventoryManager();

        // Case A: Missing required header 'name'
        $missingHeaderCsv = "sku,unit_cost,category\nMED-MISS-01,10.00,Medicines\n";
        $fileA = UploadedFile::fake()->createWithContent('missing_header.csv', $missingHeaderCsv);

        $resA = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $fileA,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resA->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'missing_header')
            ->assertJsonPath('errors.0.field', 'name');

        // Case B: Header 'sku' and 'name' are present, but row 2 has empty 'sku'
        $emptyValueCsv = "sku,name,unit_cost\n,Paracetamol 500mg,1.50\n";
        $fileB = UploadedFile::fake()->createWithContent('empty_val.csv', $emptyValueCsv);

        $resB = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $fileB,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resB->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'empty_required')
            ->assertJsonPath('errors.0.row', 2)
            ->assertJsonPath('errors.0.field', 'sku')
            ->assertJsonPath('errors.0.value', '[Empty]');
    }

    public function test_preview_detects_invalid_field_values_with_exact_row_and_field(): void
    {
        $user = $this->inventoryManager();

        // Row 2: Negative unit cost
        // Row 3: Negative reorder level
        $invalidCsv = "sku,name,unit_cost,reorder_level\n".
            "MED-INV-01,Valid Name,-15.00,10\n".
            "MED-INV-02,Another Item,5.00,-100\n";

        $file = UploadedFile::fake()->createWithContent('invalid_values.csv', $invalidCsv);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('invalid_count', 2);

        $errors = $res->json('errors');
        $this->assertSame(2, $errors[0]['row']);
        $this->assertSame('unit_cost', $errors[0]['field']);
        $this->assertSame('invalid_value', $errors[0]['type']);

        $this->assertSame(3, $errors[1]['row']);
        $this->assertSame('reorder_level', $errors[1]['field']);
        $this->assertSame('invalid_value', $errors[1]['type']);
    }

    public function test_preview_rejects_empty_files_and_headers_without_data_rows(): void
    {
        $user = $this->inventoryManager();

        // Case A: 0 bytes empty file
        $emptyFile = UploadedFile::fake()->createWithContent('empty.csv', '');
        $resA = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $emptyFile,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resA->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');

        // Case B: Headers present, but 0 data rows
        $headersOnlyFile = UploadedFile::fake()->createWithContent('headers_only.csv', "sku,name,unit_cost\n");
        $resB = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $headersOnlyFile,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resB->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
    }

    public function test_preview_ignores_extra_unrecognized_columns_without_failing(): void
    {
        $user = $this->inventoryManager();

        $extraColCsv = "sku,name,internal_tracking_id,warehouse_notes,legacy_code\n".
            "MED-EXTRA-01,Sodium Chloride 0.9% 1L,TRK-999,Keep in dry shelf,LEG-888\n";

        $file = UploadedFile::fake()->createWithContent('extra_cols.csv', $extraColCsv);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('preview_rows.0.sku', 'MED-EXTRA-01')
            ->assertJsonPath('preview_rows.0.name', 'Sodium Chloride 0.9% 1L');
    }

    public function test_csv_with_hospital_name_in_first_data_row_is_not_treated_as_header(): void
    {
        $user = $this->inventoryManager();

        // Row 1: Header row (sku, name, description)
        // Row 2: First data row containing institutional hospital name
        // Must never interpret Row 2 as the header or report missing headers!
        $csvContent = "sku,name,description\n".
            "DJNRMHS-001,Dr. Jose N. Rodriguez Memorial Hospital Sanitizer,70% Isopropyl Alcohol 500mL\n".
            "DJNRMHS-002,Paracetamol 500mg Tablet,Oral analgesic and antipyretic\n";

        $file = UploadedFile::fake()->createWithContent('hospital_items.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('valid_count', 2)
            ->assertJsonPath('invalid_count', 0)
            ->assertJsonPath('preview_rows.0.sku', 'DJNRMHS-001')
            ->assertJsonPath('preview_rows.0.name', 'Dr. Jose N. Rodriguez Memorial Hospital Sanitizer')
            ->assertJsonPath('preview_rows.1.sku', 'DJNRMHS-002');
    }

    public function test_csv_with_commas_inside_quoted_values(): void
    {
        $user = $this->inventoryManager();

        $csvContent = "sku,name,description,unit_cost\n".
            "MED-QUOT-01,\"Paracetamol, 500mg (Oral, Tablet)\",\"Relieves mild to moderate pain, fever, and headaches\",2.50\n".
            "MED-QUOT-02,\"Amoxicillin, 500mg (Capsule)\",\"Broad-spectrum, bactericidal antibiotic\",5.75\n";

        $file = UploadedFile::fake()->createWithContent('quoted_commas.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('preview_rows.0.sku', 'MED-QUOT-01')
            ->assertJsonPath('preview_rows.0.name', 'Paracetamol, 500mg (Oral, Tablet)');
    }

    public function test_csv_exported_from_excel_with_semicolon_delimiter(): void
    {
        $user = $this->inventoryManager();

        // Excel in European/regional settings frequently exports CSV with semicolon delimiter
        // even when data cells contain commas
        $csvContent = "sku;name;unit_cost\r\n".
            "DJNRMHS-001;Dr. Jose N. Rodriguez Memorial Hospital, Tala, Caloocan;15.50\r\n".
            "DJNRMHS-002;Sterile Gauze Pad, 4x4 inches, Box of 100;45.00\r\n";

        $file = UploadedFile::fake()->createWithContent('excel_semicolon.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('preview_rows.0.sku', 'DJNRMHS-001')
            ->assertJsonPath('preview_rows.0.name', 'Dr. Jose N. Rodriguez Memorial Hospital, Tala, Caloocan');
    }

    public function test_csv_exported_from_excel_with_escaped_quotes_and_crlf(): void
    {
        $user = $this->inventoryManager();

        $csvContent = "sku,name,description\r\n".
            "MED-ESC-01,\"Product with \"\"Brand Name\"\" in quotes\",\"Standard 500mg formulation\"\r\n";

        $file = UploadedFile::fake()->createWithContent('excel_escaped.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('preview_rows.0.sku', 'MED-ESC-01')
            ->assertJsonPath('preview_rows.0.name', 'Product with "Brand Name" in quotes');
    }

    public function test_csv_missing_required_headers_reports_actual_headers_not_data_row(): void
    {
        $user = $this->inventoryManager();

        // Missing both sku and name, but data row contains facility name
        $csvContent = "category,unit,unit_cost\n".
            "Medicines,tablet,10.00\n".
            "Medical Supplies,pack,25.00\n";

        $file = UploadedFile::fake()->createWithContent('genuinely_missing.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'missing_header')
            ->assertJsonPath('errors.0.field', 'sku, name');

        $this->assertStringContainsString('Missing required column headers: sku, name', $res->json('errors.0.message'));
        $this->assertStringContainsString('category, unit, unit_cost', $res->json('errors.0.value'));
    }

    public function test_csv_empty_required_fields_reports_empty_field_with_accurate_row_numbers(): void
    {
        $user = $this->inventoryManager();

        // Row 1: Headers (sku, name, unit_cost)
        // Row 2: Empty name
        // Row 3: Empty sku
        $csvContent = "sku,name,unit_cost\n".
            "MED-EMPTY-01,,5.00\n".
            ",Amoxicillin 500mg,10.00\n";

        $file = UploadedFile::fake()->createWithContent('empty_fields.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('invalid_count', 2);

        $errors = $res->json('errors');
        $this->assertSame(2, $errors[0]['row']);
        $this->assertSame('name', $errors[0]['field']);
        $this->assertSame('empty_required', $errors[0]['type']);
        $this->assertSame('[Empty]', $errors[0]['value']);

        $this->assertSame(3, $errors[1]['row']);
        $this->assertSame('sku', $errors[1]['field']);
        $this->assertSame('empty_required', $errors[1]['type']);
        $this->assertSame('[Empty]', $errors[1]['value']);
    }

    public function test_csv_rejects_malformed_binary_file(): void
    {
        $user = $this->inventoryManager();

        // Fake binary payload with null bytes
        $binaryContent = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01";
        $file = UploadedFile::fake()->createWithContent('fake_image.csv', $binaryContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');

        $this->assertStringContainsString('does not match the .csv extension', $res->json('errors.0.message'));
    }

    public function test_json_import_validation_is_consistent_with_csv(): void
    {
        $user = $this->inventoryManager();

        // 1. Valid JSON with aliases (item_code, item_name)
        $validJson = json_encode([
            [
                'item_code' => 'MED-JSON-01',
                'item_name' => 'Paracetamol 500mg',
                'unit_cost' => 1.50,
            ],
            [
                'item_code' => 'MED-JSON-02',
                'item_name' => 'Dr. Jose N. Rodriguez Memorial Hospital Alcohol',
                'unit_cost' => 45.00,
            ],
        ]);

        $fileValid = UploadedFile::fake()->createWithContent('valid.json', $validJson);
        $resValid = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $fileValid,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resValid->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('preview_rows.0.sku', 'MED-JSON-01')
            ->assertJsonPath('preview_rows.1.name', 'Dr. Jose N. Rodriguez Memorial Hospital Alcohol');

        // 2. JSON with missing required fields (keys)
        $missingKeyJson = json_encode([
            [
                'category' => 'Medicines',
                'unit_cost' => 1.50,
            ],
        ]);

        $fileMissing = UploadedFile::fake()->createWithContent('missing_key.json', $missingKeyJson);
        $resMissing = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $fileMissing,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resMissing->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'missing_header')
            ->assertJsonPath('errors.0.field', 'sku, name');
        $this->assertStringContainsString('Missing required fields / keys: sku, name', $resMissing->json('errors.0.message'));

        // 3. JSON with empty required values
        $emptyValJson = json_encode([
            [
                'sku' => 'MED-JSON-EMPTY',
                'name' => '',
            ],
        ]);

        $fileEmpty = UploadedFile::fake()->createWithContent('empty_val.json', $emptyValJson);
        $resEmpty = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $fileEmpty,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resEmpty->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'empty_required')
            ->assertJsonPath('errors.0.field', 'name');
    }

    public function test_json_reports_duplicate_identifiers_and_unknown_relationships(): void
    {
        $file = UploadedFile::fake()->createWithContent('invalid-items.json', json_encode([
            ['sku' => 'JSON-DUP-01', 'name' => 'First Item', 'category' => 'Unknown Category'],
            ['sku' => 'JSON-DUP-01', 'name' => 'Second Item'],
        ]));

        $res = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertOk()->assertJsonPath('is_valid', false);
        $types = array_column($res->json('errors'), 'type');
        $this->assertContains('referential_integrity', $types);
        $this->assertContains('duplicate_record', $types);
    }

    public function test_csv_with_item_description_header_maps_to_name(): void
    {
        $user = $this->inventoryManager();
        ItemCategory::create(['name' => 'Medicines', 'code' => 'MED']);

        $csvContent = "SKU,Item Description,Category,Unit Cost\n".
            "MED-DESC-01,Paracetamol 500mg Tablet,Medicines,1.50\n".
            "MED-DESC-02,Amoxicillin 500mg Capsule,Medicines,3.25\n";

        $file = UploadedFile::fake()->createWithContent('item_description.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('preview_rows.0.sku', 'MED-DESC-01')
            ->assertJsonPath('preview_rows.0.name', 'Paracetamol 500mg Tablet')
            ->assertJsonPath('preview_rows.1.sku', 'MED-DESC-02')
            ->assertJsonPath('preview_rows.1.name', 'Amoxicillin 500mg Capsule');
    }

    public function test_csv_with_institutional_report_export_structure_parses_successfully(): void
    {
        $user = $this->inventoryManager();
        ItemCategory::create(['name' => 'Medicines', 'code' => 'MED']);

        $csvContent = "Dr. Jose N. Rodriguez Memorial Hospital and Sanitarium\n".
            "Materials Management & Inventory Division\n".
            "Report:,Stock Status Report\n".
            "Generated:,2026-09-11 23:00:00,By:,Inventory Manager (Inventory Manager)\n".
            "Period:,Last 30 days\n".
            "Filter: Stock Status,All Stock Statuses\n".
            "\n".
            "--- SUMMARY ---\n".
            "Total Items,2\n".
            "Total Units,700\n".
            "\n".
            "SKU,Item Description,Category,Quantity on Hand,Unit Cost,Total Value,Status\n".
            "MED-REP-01,Paracetamol 500mg Tablet,Medicines,500,1.50,750.00,In Stock\n".
            "MED-REP-02,Amoxicillin 500mg Capsule,Medicines,200,3.25,650.00,In Stock\n";

        $file = UploadedFile::fake()->createWithContent('hims_stock_report.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', true)
            ->assertJsonPath('total_rows', 2)
            ->assertJsonPath('valid_count', 2)
            ->assertJsonPath('invalid_count', 0)
            ->assertJsonPath('preview_rows.0.sku', 'MED-REP-01')
            ->assertJsonPath('preview_rows.0.name', 'Paracetamol 500mg Tablet');
    }

    public function test_preview_rejects_unsupported_file_extensions(): void
    {
        $user = $this->inventoryManager();

        $extensions = ['pdf', 'docx', 'png', 'exe', 'zip'];

        foreach ($extensions as $ext) {
            $file = UploadedFile::fake()->create("sample.{$ext}", 100);

            $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
                'file' => $file,
                'target' => 'items',
                'mode' => 'create_only',
            ]);

            $res->assertStatus(422)
                ->assertJsonPath('is_valid', false)
                ->assertJsonPath('total_rows', 0)
                ->assertJsonPath('invalid_count', 1)
                ->assertJsonPath('errors.0.type', 'invalid_structure')
                ->assertJsonPath('errors.0.field', 'file');

            $this->assertStringContainsString("Unsupported file format [{$ext}]", $res->json('message'));
            $this->assertNull($res->json('import_token'));
        }
    }

    public function test_preview_rejects_extension_content_mismatches_and_allows_retry(): void
    {
        $user = $this->inventoryManager();
        $initialCount = InventoryItem::count();

        foreach (['spoofed.csv', 'spoofed.json', 'spoofed.xlsx'] as $name) {
            $response = $this->actingAs($user)->postJson('/inventory/import/preview', [
                'file' => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nnot the declared format\n%%EOF"),
                'target' => 'items',
                'mode' => 'create_only',
            ]);

            $response->assertUnprocessable()->assertJsonPath('is_valid', false);
            $this->assertStringNotContainsString('C:\\', $response->json('errors.0.message'));
        }

        $valid = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('corrected.csv', "sku,name\nRETRY-001,Corrected File\n"),
            'target' => 'items',
            'mode' => 'create_only',
        ]);
        $valid->assertOk()->assertJsonPath('is_valid', true);
        $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $valid->json('import_token'),
            'target' => 'items',
        ])->assertOk();

        $this->assertSame($initialCount + 1, InventoryItem::count());
        $this->assertDatabaseHas('inventory_items', ['sku' => 'RETRY-001']);
    }

    public function test_preview_rejects_duplicate_columns_that_map_to_the_same_field(): void
    {
        $response = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => UploadedFile::fake()->createWithContent(
                'duplicate-headers.csv',
                "sku,item_code,name\nDUP-HEADER-1,DUP-HEADER-2,Duplicate Header Item\n"
            ),
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $response->assertOk()
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure')
            ->assertJsonPath('import_token', null);
        $this->assertStringContainsString('Duplicate columns map to: sku', $response->json('errors.0.message'));
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'DUP-HEADER-1']);
    }

    public function test_preview_rejects_corrupted_or_empty_xlsx_files(): void
    {
        $user = $this->inventoryManager();

        // 1. 0-byte .xlsx file
        $emptyXlsx = UploadedFile::fake()->createWithContent('empty.xlsx', '');
        $resEmpty = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $emptyXlsx,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resEmpty->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('uploaded file is empty', $resEmpty->json('errors.0.message'));

        // 2. Non-zip / corrupted .xlsx file
        $corruptXlsx = UploadedFile::fake()->createWithContent('corrupt.xlsx', 'THIS_IS_NOT_A_VALID_ZIP_ARCHIVE');
        $resCorrupt = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $corruptXlsx,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resCorrupt->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('Excel file could not be read', $resCorrupt->json('errors.0.message'));

        // 3. Valid zip archive with corrupted worksheet XML
        $tempZip = tempnam(sys_get_temp_dir(), 'xlsx_corrupt_test');
        $zip = new ZipArchive;
        $zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><unclosed_corrupted_xml');
        $zip->close();
        $corruptXmlContent = file_get_contents($tempZip);
        @unlink($tempZip);

        $corruptXmlFile = UploadedFile::fake()->createWithContent('corrupt_xml.xlsx', $corruptXmlContent);
        $resXmlCorrupt = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $corruptXmlFile,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resXmlCorrupt->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('worksheet XML is corrupted or malformed', $resXmlCorrupt->json('errors.0.message'));
    }

    public function test_preview_rejects_macro_enabled_content_renamed_as_xlsx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'macro_xlsx_').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData/></worksheet>');
        $zip->addFromString('xl/vbaProject.bin', 'macro payload');
        $zip->close();

        $response = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => new UploadedFile($path, 'renamed-macro.xlsx', null, null, true),
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $response->assertUnprocessable()->assertJsonPath('is_valid', false);
        $this->assertStringContainsString('Macro-enabled Excel files are not supported', $response->json('errors.0.message'));
        @unlink($path);
    }

    public function test_preview_rejects_legacy_binary_xls_with_clear_guidance(): void
    {
        $user = $this->inventoryManager();

        // OLE2 Compound Document header for binary BIFF8 .xls
        $biffContent = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1\x00\x00\x00\x00\x00\x00\x00\x00";
        $file = UploadedFile::fake()->createWithContent('legacy_inventory.xls', $biffContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('Legacy binary Excel 97-2004 format (.xls BIFF) is not directly supported', $res->json('errors.0.message'));
    }

    public function test_preview_rejects_malformed_csv_unclosed_quotes(): void
    {
        $user = $this->inventoryManager();

        // Malformed CSV with an unclosed double quote (odd number of quotation marks)
        $csvContent = "sku,name,unit_cost\n\"MED-UNCLOSED,Paracetamol 500mg,1.50\n";
        $file = UploadedFile::fake()->createWithContent('unclosed_quote.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('malformed: contains an unclosed quote or unbalanced quotation marks', $res->json('errors.0.message'));
    }

    public function test_preview_rejects_malformed_csv_with_binary_control_characters(): void
    {
        $user = $this->inventoryManager();

        // CSV containing binary control characters
        $binaryControlContent = "sku,name\n\x01\x02\x03\x04MED-01,Test Product\n";
        $file = UploadedFile::fake()->createWithContent('control_chars.csv', $binaryControlContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('does not match the .csv extension', $res->json('errors.0.message'));
    }

    public function test_preview_rejects_malformed_json_syntax_and_primitives(): void
    {
        $user = $this->inventoryManager();

        // 1. Invalid JSON syntax
        $badSyntaxFile = UploadedFile::fake()->createWithContent('syntax_error.json', "{sku: 'bad', name: }");
        $resSyntax = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $badSyntaxFile,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resSyntax->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('Invalid JSON syntax', $resSyntax->json('errors.0.message'));

        // 2. Primitive scalar value (e.g. 123) instead of array of objects
        $scalarFile = UploadedFile::fake()->createWithContent('primitive.json', '12345');
        $resScalar = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $scalarFile,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resScalar->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('Expected a JSON array of objects', $resScalar->json('errors.0.message'));

        // 3. Array of non-objects (e.g. strings)
        $arrayOfStrings = UploadedFile::fake()->createWithContent('array_of_strings.json', json_encode(['MED-01', 'MED-02']));
        $resNonObject = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $arrayOfStrings,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resNonObject->assertStatus(422)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('Expected a JSON object with key-value pairs', $resNonObject->json('errors.0.message'));
    }

    public function test_preview_rejects_nested_json_values_for_scalar_fields(): void
    {
        $file = UploadedFile::fake()->createWithContent('nested.json', json_encode([
            ['sku' => 'JSON-NESTED-01', 'name' => ['unexpected']],
        ]));

        $res = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'file');
        $this->assertStringContainsString("record #1, field 'name'", $res->json('errors.0.message'));
    }

    public function test_preview_rejects_empty_json_files_and_empty_envelopes(): void
    {
        $user = $this->inventoryManager();

        // 1. Empty JSON array
        $emptyArrayFile = UploadedFile::fake()->createWithContent('empty_array.json', '[]');
        $resA = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $emptyArrayFile,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resA->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('empty or contains no readable data rows', $resA->json('errors.0.message'));

        // 2. Empty envelope {"data": []}
        $emptyEnvelope = UploadedFile::fake()->createWithContent('empty_envelope.json', '{"data": []}');
        $resB = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $emptyEnvelope,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resB->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
        $this->assertStringContainsString('empty or contains no readable data rows', $resB->json('errors.0.message'));

        // 3. Whitespace-only JSON
        $wsFile = UploadedFile::fake()->createWithContent('whitespace.json', "   \n\t   ");
        $resC = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $wsFile,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $resC->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure');
    }

    public function test_preview_rejects_whitespace_only_csv_files(): void
    {
        $user = $this->inventoryManager();

        $wsCsv = UploadedFile::fake()->createWithContent('whitespace.csv', "   \r\n\t   \n   \n");
        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $wsCsv,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'invalid_structure')
            ->assertJsonPath('errors.0.message', 'The uploaded file is empty or contains no readable data rows.');
    }

    public function test_preview_rejects_files_with_incorrect_structure_and_missing_required_headers(): void
    {
        $user = $this->inventoryManager();

        // File with headers that do not match expected HIMS import structure
        $csvContent = "random_column_a,random_column_b,unrelated_date\n".
            "Value 1,Value 2,2026-09-12\n";
        $file = UploadedFile::fake()->createWithContent('unrelated.csv', $csvContent);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(200)
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('errors.0.type', 'missing_header')
            ->assertJsonPath('errors.0.field', 'sku, name');

        $this->assertStringContainsString('Missing required column headers: sku, name', $res->json('errors.0.message'));
    }

    public function test_preview_rejects_file_exceeding_max_file_size(): void
    {
        $user = $this->inventoryManager();

        // 10241 KB exceeds 10240 KB limit (10 MB)
        $largeFile = UploadedFile::fake()->create('large_catalogue.csv', 10241);

        $res = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $largeFile,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertStringContainsString('The file size cannot exceed 10 MB', $res->json('errors.file.0'));
    }

    public function test_preview_rejects_more_than_configured_bulk_limit(): void
    {
        $rows = ['sku,name'];
        for ($i = 1; $i <= 10001; $i++) {
            $rows[] = "LIMIT-{$i},Limit Item {$i}";
        }

        $res = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('too-many.csv', implode("\n", $rows)),
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertOk()
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('total_rows', 10001)
            ->assertJsonPath('errors.0.type', 'record_limit')
            ->assertJsonPath('import_token', null);
    }

    public function test_bulk_commit_is_queued_once_and_reports_real_status(): void
    {
        Queue::fake();
        $user = $this->inventoryManager();
        $rows = ['sku,name'];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = sprintf('BULK-%05d,Bulk Item %d', $i, $i);
        }

        $preview = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('bulk.csv', implode("\n", $rows)),
            'target' => 'items',
            'mode' => 'create_only',
        ])->assertOk()->assertJsonPath('is_valid', true);

        $payload = ['import_token' => $preview->json('import_token'), 'target' => 'items'];
        $first = $this->actingAs($user)->postJson('/inventory/import/commit', $payload)
            ->assertAccepted()
            ->assertJsonPath('async', true)
            ->assertJsonPath('status.status', 'processing')
            ->assertJsonPath('status.total', 1000);

        $this->actingAs($user)->postJson('/inventory/import/commit', $payload)
            ->assertAccepted()
            ->assertJsonPath('status.status', 'processing');

        Queue::assertPushed(ProcessDataImport::class, 1);
        $this->actingAs($user)->getJson($first->json('status_url'))
            ->assertOk()
            ->assertJsonPath('processed', 0)
            ->assertJsonPath('total', 1000);
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_concurrent_imports_keep_users_and_statuses_isolated(): void
    {
        config(['imports.background_threshold' => 2]);
        Queue::fake();
        $firstUser = $this->inventoryManager();
        $secondUser = $this->inventoryManager();

        $preview = function (User $user, string $prefix) {
            return $this->actingAs($user)->postJson('/inventory/import/preview', [
                'file' => UploadedFile::fake()->createWithContent("{$prefix}.csv", "sku,name\n{$prefix}-1,First\n{$prefix}-2,Second\n"),
                'target' => 'items',
                'mode' => 'create_only',
            ]);
        };

        $firstPreview = $preview($firstUser, 'FIRST')->assertOk();
        $secondPreview = $preview($secondUser, 'SECOND')->assertOk();
        $firstCommit = $this->actingAs($firstUser)->postJson('/inventory/import/commit', [
            'import_token' => $firstPreview->json('import_token'),
            'target' => 'items',
        ])->assertAccepted();
        $secondCommit = $this->actingAs($secondUser)->postJson('/inventory/import/commit', [
            'import_token' => $secondPreview->json('import_token'),
            'target' => 'items',
        ])->assertAccepted();

        $this->assertNotSame($firstCommit->json('status.token'), $secondCommit->json('status.token'));
        $this->actingAs($firstUser)->getJson($secondCommit->json('status_url'))->assertNotFound();
        $this->actingAs($secondUser)->getJson($firstCommit->json('status_url'))->assertNotFound();
        Queue::assertPushed(ProcessDataImport::class, 2);
    }

    public function test_one_thousand_row_bulk_csv_completes_with_correct_database_state(): void
    {
        $user = $this->inventoryManager();
        $rows = ['sku,name'];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = sprintf('CSV-%05d,Measured CSV Item %d', $i, $i);
        }

        $preview = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('measured.csv', implode("\n", $rows)),
            'target' => 'items',
            'mode' => 'create_only',
        ])->assertOk()->assertJsonPath('valid_count', 1000);

        $commit = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => $preview->json('import_token'),
            'target' => 'items',
        ])->assertAccepted()->assertJsonPath('async', true);

        $this->actingAs($user)->getJson($commit->json('status_url'))
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('processed', 1000)
            ->assertJsonPath('created', 1000);

        $this->assertDatabaseCount('inventory_items', 1000);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'CSV-00001', 'name' => 'Measured CSV Item 1']);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'CSV-01000', 'name' => 'Measured CSV Item 1000']);
    }

    public function test_large_invalid_preview_caps_errors_without_losing_invalid_count(): void
    {
        $rows = ['sku,name'];
        for ($i = 1; $i <= 500; $i++) {
            $rows[] = "INVALID-{$i},";
        }

        $response = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('invalid-bulk.csv', implode("\n", $rows)),
            'target' => 'items',
            'mode' => 'create_only',
        ])->assertOk()
            ->assertJsonPath('is_valid', false)
            ->assertJsonPath('invalid_count', 500)
            ->assertJsonPath('import_token', null);

        $this->assertCount(200, $response->json('errors'));
        $this->assertStringContainsString('300 additional validation errors', $response->json('warnings.0'));
    }

    public function test_bulk_executor_rolls_back_all_chunks_on_database_failure(): void
    {
        $user = $this->inventoryManager();
        $records = [];
        for ($i = 1; $i <= 1000; $i++) {
            $records[] = [
                'sku' => $i === 501 ? 'ROLLBACK-00001' : sprintf('ROLLBACK-%05d', $i),
                'name' => "Rollback Item {$i}",
                '_mode' => 'create',
                '_existing_id' => null,
            ];
        }

        $failed = false;
        try {
            app(DataImportExecutor::class)->execute('items', $records, $user);
        } catch (QueryException) {
            $failed = true;
        }

        $this->assertTrue($failed);
        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_unexpected_parser_failures_do_not_expose_internal_details(): void
    {
        $this->mock(DataImportReader::class)
            ->shouldReceive('read')
            ->once()
            ->andThrow(new \RuntimeException('C:\\private\\imports\\secret.csv line 99'));

        $res = $this->actingAs($this->inventoryManager())->postJson('/inventory/import/preview', [
            'file' => UploadedFile::fake()->createWithContent('items.csv', "sku,name\nSAFE-01,Safe Item\n"),
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $res->assertUnprocessable()
            ->assertJsonPath('message', 'The uploaded file could not be parsed. Verify the file and try again.')
            ->assertJsonMissing(['message' => 'C:\\private\\imports\\secret.csv line 99']);
    }

    public function test_invalid_files_safely_prevent_commit_and_prevent_database_writes(): void
    {
        $user = $this->inventoryManager();
        $initialItemCount = InventoryItem::count();

        // 1. Attempt preview with an invalid file
        $invalidCsv = "sku,name,unit_cost\n\"UNCLOSED_QUOTE,Paracetamol,10.00\n";
        $file = UploadedFile::fake()->createWithContent('invalid_file.csv', $invalidCsv);

        $previewRes = $this->actingAs($user)->postJson('/inventory/import/preview', [
            'file' => $file,
            'target' => 'items',
            'mode' => 'create_only',
        ]);

        $previewRes->assertStatus(422)
            ->assertJsonPath('is_valid', false);

        $this->assertNull($previewRes->json('import_token'));

        // 2. Attempt commit without valid staging token
        $commitRes = $this->actingAs($user)->postJson('/inventory/import/commit', [
            'import_token' => 'invalid_or_forged_token',
            'target' => 'items',
        ]);

        $commitRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // 3. Verify zero database modification occurred
        $this->assertSame($initialItemCount, InventoryItem::count());
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function xlsxFixture(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'test_xlsx_').'.xlsx';
        $strings = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                if (! in_array($value, $strings, true)) {
                    $strings[] = $value;
                }
            }
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');

        $sharedStrings = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        foreach ($strings as $value) {
            $sharedStrings .= '<si><t>'.htmlspecialchars($value, ENT_XML1, 'UTF-8').'</t></si>';
        }
        $zip->addFromString('xl/sharedStrings.xml', $sharedStrings.'</sst>');

        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $rowIndex => $row) {
            $number = $rowIndex + 1;
            $sheet .= "<row r=\"{$number}\">";
            foreach ($row as $columnIndex => $value) {
                $column = chr(65 + $columnIndex);
                $sharedIndex = array_search($value, $strings, true);
                $sheet .= "<c r=\"{$column}{$number}\" t=\"s\"><v>{$sharedIndex}</v></c>";
            }
            $sheet .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }
}
