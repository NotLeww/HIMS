<?php

namespace Tests\Feature;

use App\Enums\RecoveryStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\KpiProcessReview;
use App\Models\ProcessRecommendation;
use App\Models\PurchaseOrder;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\SupplierScorecard;
use App\Models\SystemRecoveryAttempt;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\Analytics\BottleneckAnalysisService;
use App\Services\Analytics\SupplierScoringService;
use App\Services\Import\ImportStagingService;
use App\Services\Recovery\SmartRetryService;
use App\Support\AuthenticationContext;
use Carbon\CarbonImmutable;
use Database\Seeders\ErrorRecoveryDemoSeeder;
use Database\Seeders\OperationalMetricsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecoveryAndMetricsSeedingTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $inventoryManager;

    private User $warehouseStaff;

    private User $pharmacyStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SuperAdministrator,
            'status' => 'active',
            'is_protected' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::Administrator,
            'status' => 'active',
        ]);

        $this->inventoryManager = User::factory()->create([
            'role' => UserRole::InventoryManager,
            'status' => 'active',
        ]);

        $this->warehouseStaff = User::factory()->create([
            'role' => UserRole::WarehouseStaff,
            'status' => 'active',
        ]);

        $this->pharmacyStaff = User::factory()->create([
            'role' => UserRole::PharmacyStaff,
            'status' => 'active',
        ]);
    }

    public function test_error_recovery_demo_seeder_populates_expected_records(): void
    {
        $this->seed(ErrorRecoveryDemoSeeder::class);

        $this->assertSame(8, SystemRecoveryRecord::count());
        $this->assertSame(4, SystemRecoveryRecord::open()->count());
        $this->assertGreaterThanOrEqual(1, SystemRecoveryRecord::recovered()->count());
        $this->assertGreaterThanOrEqual(1, SystemRecoveryRecord::retryable()->count());

        // Check specific incident attributes
        $importIncident = SystemRecoveryRecord::where('error_id', 'REC-2026-IMP-001')->firstOrFail();
        $this->assertSame('Imports', $importIncident->module);
        $this->assertSame(RecoveryStatus::Failed, $importIncident->status);
        $this->assertTrue($importIncident->is_retryable);
        $this->assertIsArray($importIncident->technical_details);
        $this->assertSame(
            ['target' => 'items', 'staged_rows' => 120],
            $importIncident->technical_details['context']
        );

        // The seeded token has genuinely lapsed, so the incident is a truthful
        // stand-in for an expired import session rather than a failed retry.
        $this->assertNull(app(ImportStagingService::class)->retrieve(
            $importIncident->retry_payload['import_token'],
            $this->inventoryManager->id
        ));

        // Check failed jobs: exactly one, and it is the incident's reference target.
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertDatabaseHas('failed_jobs', [
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'queue' => 'notifications',
        ]);
        $this->assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            SystemRecoveryRecord::where('error_id', 'REC-2026-QUE-002')->value('reference_id')
        );

        // The Audit Trail is append-only and records what people did. The seeder
        // seeds incidents, so it deliberately writes nothing there.
        $this->assertSame(0, AuditLog::where('module', 'System Recovery')->count());
    }

    public function test_seeded_queue_incident_retries_through_a_real_worker_to_recovered(): void
    {
        $this->seed(ErrorRecoveryDemoSeeder::class);
        $record = SystemRecoveryRecord::where('error_id', 'REC-2026-QUE-002')->firstOrFail();

        $this->actingAs($this->superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);
        app(SmartRetryService::class)->retry($record, $this->superAdmin);

        Artisan::call('queue:work', [
            'connection' => 'database',
            '--queue' => 'notifications',
            '--once' => true,
            '--tries' => 1,
        ]);

        $record->refresh();
        $this->assertSame(RecoveryStatus::Recovered, $record->status);
        $this->assertSame('succeeded', $record->last_attempt_outcome->value);
        $this->assertSame(0, DB::table('failed_jobs')->where('uuid', $record->reference_id)->count());
    }

    private function createItem(string $name = 'Paracetamol', string $sku = 'MED-PARA-500'): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'sku' => $sku,
            'unit' => 'box',
            'quantity_on_hand' => 100,
            'unit_cost' => 10.00,
            'total_value' => 1000.00,
        ]);
    }

    private function createLocation(string $name = 'Main Warehouse', string $code = 'WH-01'): StorageLocation
    {
        return StorageLocation::create([
            'name' => $name,
            'code' => $code,
            'type' => 'warehouse',
            'status' => 'active',
        ]);
    }

    public function test_operational_metrics_demo_seeder_populates_reviews_and_adjustments(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 10, 2, 12, 0, 0, 'Asia/Manila'));

        $reviewedSuppliers = collect([
            ['name' => 'MedSupply Demonstration Corp', 'status' => 'active', 'standard_lead_time_days' => 5],
            ['name' => 'Clinical Diagnostics Demo Inc', 'status' => 'active', 'standard_lead_time_days' => 10],
            ['name' => 'Suspended Supplier Demo Corp', 'status' => 'suspended', 'standard_lead_time_days' => 7],
        ])->map(fn (array $attributes) => Supplier::create($attributes));

        $archivedSupplier = Supplier::create([
            'name' => 'Archived Supplier Demo Corp',
            'status' => 'archived',
        ]);
        $item = $this->createItem();
        $location = $this->createLocation();

        $this->seed(OperationalMetricsDemoSeeder::class);

        $this->assertSame(2, KpiProcessReview::count());
        $this->assertDatabaseHas('kpi_process_reviews', ['review_number' => 'REV-2026-Q3', 'status' => 'approved']);
        $this->assertDatabaseHas('kpi_process_reviews', ['review_number' => 'REV-2026-Q4', 'status' => 'submitted']);

        $approvedReview = KpiProcessReview::where('review_number', 'REV-2026-Q3')->firstOrFail();
        $submittedReview = KpiProcessReview::where('review_number', 'REV-2026-Q4')->firstOrFail();

        $this->assertSame($reviewedSuppliers->count(), $approvedReview->supplierScorecards()->count());
        $this->assertSame(0, $submittedReview->supplierScorecards()->count());
        $this->assertSame($reviewedSuppliers->count(), $approvedReview->metrics_summary['suppliers_evaluated']);
        $this->assertSame(0, $submittedReview->metrics_summary['suppliers_evaluated']);

        foreach ([$approvedReview, $submittedReview] as $review) {
            $calculatedBottlenecks = app(BottleneckAnalysisService::class)
                ->evaluate($review->period_start, $review->period_end);

            $this->assertEquals($calculatedBottlenecks['stages'], $review->metrics_summary['bottleneck_stages']);
            $this->assertEquals($calculatedBottlenecks['critical_bottleneck'], $review->metrics_summary['critical_bottleneck']);
        }

        $this->assertNotContains(0, collect($approvedReview->metrics_summary['bottleneck_stages'])->pluck('sample_count')->all());
        $this->assertSame([0], collect($submittedReview->metrics_summary['bottleneck_stages'])->pluck('sample_count')->unique()->values()->all());
        $this->assertFalse(PurchaseOrder::where('requested_at', '>', now())->exists());

        $calculatedScores = app(SupplierScoringService::class)
            ->evaluate($approvedReview->period_start, $approvedReview->period_end)
            ->keyBy('supplier_id');

        foreach ($reviewedSuppliers as $supplier) {
            $scorecard = $supplier->fresh()->latestApprovedScorecard;

            $this->assertNotNull($scorecard);
            $this->assertTrue($supplier->purchaseOrders()->exists());
            $this->assertSame(
                number_format($calculatedScores[$supplier->id]['total_score'], 2, '.', ''),
                $scorecard->total_score
            );
        }

        $this->assertNull($archivedSupplier->fresh()->latestApprovedScorecard);
        $this->assertGreaterThanOrEqual(1, ProcessRecommendation::count());
        $this->assertGreaterThanOrEqual(1, InventoryAdjustment::count());

        $criticalRec = ProcessRecommendation::where('priority', 'critical')->firstOrFail();
        $this->assertSame('pending', $criticalRec->status);
        $this->assertNotEmpty($criticalRec->problem_detected);

        $adjustment = InventoryAdjustment::where('adjustment_number', 'ADJ-2026-0001')->firstOrFail();
        $this->assertSame('damage', $adjustment->adjustment_type);
        $this->assertSame('posted', $adjustment->status);
    }

    public function test_reseeding_is_idempotent_and_does_not_create_duplicate_records(): void
    {
        Supplier::create([
            'name' => 'MedSupply Demonstration Corp',
            'status' => 'active',
        ]);
        $this->createItem();
        $this->createLocation();

        // First run
        $this->seed(ErrorRecoveryDemoSeeder::class);
        $this->seed(OperationalMetricsDemoSeeder::class);

        $recoveryCount = SystemRecoveryRecord::count();
        $failedJobsCount = DB::table('failed_jobs')->count();
        $reviewCount = KpiProcessReview::count();
        $scorecardCount = SupplierScorecard::count();
        $purchaseOrderCount = DB::table('purchase_orders')->count();
        $purchaseRequestCount = DB::table('purchase_requests')->count();
        $rfqCount = DB::table('sourcing_rfqs')->count();
        $goodsReceiptCount = DB::table('goods_receipt_notes')->count();
        $inspectionReportCount = DB::table('inspection_acceptance_reports')->count();
        $adjustmentCount = InventoryAdjustment::count();

        $importIncident = SystemRecoveryRecord::where('error_id', 'REC-2026-IMP-001')->firstOrFail();
        $importIncident->forceFill([
            'status' => RecoveryStatus::Retrying,
            'retry_count' => 1,
            'last_retried_at' => now(),
        ])->save();

        // Second run
        $this->seed(ErrorRecoveryDemoSeeder::class);
        $this->seed(OperationalMetricsDemoSeeder::class);

        $this->assertSame($recoveryCount, SystemRecoveryRecord::count());
        $this->assertSame($failedJobsCount, DB::table('failed_jobs')->count());
        $this->assertSame($reviewCount, KpiProcessReview::count());
        $this->assertSame($scorecardCount, SupplierScorecard::count());
        $this->assertSame($purchaseOrderCount, DB::table('purchase_orders')->count());
        $this->assertSame($purchaseRequestCount, DB::table('purchase_requests')->count());
        $this->assertSame($rfqCount, DB::table('sourcing_rfqs')->count());
        $this->assertSame($goodsReceiptCount, DB::table('goods_receipt_notes')->count());
        $this->assertSame($inspectionReportCount, DB::table('inspection_acceptance_reports')->count());
        $this->assertSame($adjustmentCount, InventoryAdjustment::count());
        $this->assertSame(RecoveryStatus::Retrying, $importIncident->fresh()->status);
        $this->assertSame(1, $importIncident->fresh()->retry_count);
    }
}
