<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\InspectionAcceptanceReport;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\Privacy\ConsentService;
use App\Support\AuthenticationContext;
use Database\Seeders\ComprehensiveDemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\ConfiguresAccountProvisioning;
use Tests\TestCase;

class DatabaseRestoreProcedureTest extends TestCase
{
    use ConfiguresAccountProvisioning;

    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureAccountProvisioning();
        $this->temporaryDirectory = storage_path('framework/testing/database-restore-'.Str::uuid());
        File::makeDirectory($this->temporaryDirectory, recursive: true);
    }

    protected function tearDown(): void
    {
        DB::disconnect('recovery_source');
        DB::disconnect('recovery_target');
        File::deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_backup_restores_into_an_isolated_database_with_valid_data_and_relationships(): void
    {
        $startedAt = microtime(true);
        $source = $this->temporaryDirectory.'/source.sqlite';
        $backup = $this->temporaryDirectory.'/backup.sqlite';
        $target = $this->temporaryDirectory.'/restored.sqlite';
        File::put($source, '');

        config()->set('database.connections.recovery_source', $this->sqliteConnection($source));
        config()->set('database.connections.recovery_target', $this->sqliteConnection($target));
        config()->set('database.default', 'recovery_source');
        DB::setDefaultConnection('recovery_source');

        $this->assertSame(0, Artisan::call('migrate', [
            '--database' => 'recovery_source',
            '--force' => true,
        ]));
        $this->seed(ComprehensiveDemoSeeder::class);

        $inventoryManager = User::query()->where('role', UserRole::InventoryManager)->firstOrFail();
        app(ConsentService::class)->recordConsent($inventoryManager, UserConsent::TYPE_PRIVACY_POLICY);

        $expectedTables = $this->tableDefinitions('recovery_source');
        $expectedCounts = $this->representativeCounts('recovery_source');
        $this->assertNotContains(0, $expectedCounts);
        $sourcePdo = DB::connection('recovery_source')->getPdo();
        $sourcePdo->exec('VACUUM INTO '.$sourcePdo->quote($backup));
        $this->assertFileExists($backup);
        $backupSize = File::size($backup);
        $this->assertGreaterThan(0, $backupSize);
        $this->assertSame("SQLite format 3\0", file_get_contents($backup, false, null, 0, 16));
        $this->assertTrue(File::copy($backup, $target));

        config()->set('database.default', 'recovery_target');
        DB::setDefaultConnection('recovery_target');

        $this->assertSame('ok', DB::connection()->scalar('PRAGMA integrity_check'));
        $this->assertSame([], DB::connection()->select('PRAGMA foreign_key_check'));
        $this->assertSame($expectedTables, $this->tableDefinitions('recovery_target'));
        $this->assertSame($expectedCounts, $this->representativeCounts('recovery_target'));

        $reports = InspectionAcceptanceReport::query()
            ->with('goodsReceiptNote.lines')
            ->get();
        $this->assertNotEmpty($reports);
        $this->assertTrue($reports->every(
            fn (InspectionAcceptanceReport $report): bool => $report->goodsReceiptNote?->lines->isNotEmpty()
        ));
        $this->assertFalse(
            InspectionAcceptanceReport::query()
                ->where('iar_number', 'like', 'IAR-REV-2026-%')
                ->exists()
        );

        $inventoryManager = User::query()->where('role', UserRole::InventoryManager)->firstOrFail();
        $this->assertTrue($inventoryManager->consents()->consented()->currentVersion()->exists());
        $this->actingAs($inventoryManager, AuthenticationContext::WEB_GUARD)
            ->get(route('dashboard'))
            ->assertOk();

        fwrite(STDOUT, sprintf(
            "\nBACKUP_LOG task=database-restore-procedure type=sqlite-vacuum environment=testing status=completed identifier=%s/backup.sqlite size_bytes=%d duration_ms=%d integrity=ok foreign_key_violations=0\n",
            basename($this->temporaryDirectory),
            $backupSize,
            (int) round((microtime(true) - $startedAt) * 1000),
        ));
    }

    /** @return array<string, mixed> */
    private function sqliteConnection(string $database): array
    {
        return [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => 5000,
        ];
    }

    /** @return array<string, string> */
    private function tableDefinitions(string $connection): array
    {
        return collect(DB::connection($connection)->select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        ))->mapWithKeys(fn (object $table): array => [$table->name => $table->sql])->all();
    }

    /** @return array<string, int> */
    private function representativeCounts(string $connection): array
    {
        return collect([
            'users',
            'user_consents',
            'inventory_items',
            'suppliers',
            'purchase_orders',
            'shipments',
            'goods_receipt_notes',
            'grn_line_items',
            'inspection_acceptance_reports',
            'warehouse_tasks',
            'audit_logs',
            'system_recovery_records',
        ])->mapWithKeys(fn (string $table): array => [
            $table => DB::connection($connection)->table($table)->count(),
        ])->all();
    }
}
