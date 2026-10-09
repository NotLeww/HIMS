<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DpriReferencePrice;
use App\Models\GoodsReceiptNoteLine;
use App\Models\InspectionAcceptanceReport;
use App\Models\InventoryItem;
use App\Models\KpiProcessReview;
use App\Models\LogisticsDocument;
use App\Models\ProcurementCategory;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\SourcingRfq;
use App\Models\Supplier;
use App\Models\SupplierScorecard;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Models\WarehouseTask;
use Carbon\CarbonImmutable;
use Database\Seeders\ComprehensiveDemoSeeder;
use Database\Seeders\DemandForecastDemoSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\ErrorRecoveryDemoSeeder;
use Database\Seeders\InventoryDemoSeeder;
use Database\Seeders\LogisticsDemoSeeder;
use Database\Seeders\OperationalMetricsDemoSeeder;
use Database\Seeders\ProcessReviewDemoSeeder;
use Database\Seeders\ProcurementDemoSeeder;
use Database\Seeders\SmartWarehousingDemoSeeder;
use Database\Seeders\SupplierManagementDemoSeeder;
use Database\Seeders\SupplierReviewEvidenceDemoSeeder;
use Database\Seeders\SupplierScorecardDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\ConfiguresAccountProvisioning;
use Tests\TestCase;

class ComprehensiveDemoSeederTest extends TestCase
{
    use ConfiguresAccountProvisioning;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureAccountProvisioning();
    }

    public function test_opt_in_seeder_provides_database_records_for_every_demo_module(): void
    {
        $this->travelTo(CarbonImmutable::create(2026, 10, 2, 12, 0, 0, 'Asia/Manila'));

        $this->seed(ComprehensiveDemoSeeder::class);

        foreach (UserRole::cases() as $role) {
            $this->assertTrue(User::active()->role($role)->exists(), "Missing active {$role->label()} demo account.");
        }

        $this->assertTrue(InventoryItem::where('sku', 'PPE-MASK-N95')->exists());
        $this->assertSame(12, InventoryItem::where('sku', 'like', 'FCAST-%')->count());
        $this->assertTrue(Supplier::procurementEligible()->exists());
        $this->assertTrue(ProcurementCategory::query()->exists());
        $this->assertTrue(SourcingRfq::query()->exists());
        $this->assertTrue(PurchaseOrder::query()->exists());
        $this->assertTrue(Shipment::query()->exists());
        $this->assertTrue(InspectionAcceptanceReport::query()->exists());
        $this->assertTrue(LogisticsDocument::query()->exists());
        $this->assertTrue(DpriReferencePrice::query()->exists());
        $this->assertTrue(WarehouseTask::query()->exists());
        $this->assertTrue(KpiProcessReview::whereIn('review_number', ['REV-2026-Q3', 'REV-2026-Q4'])->exists());
        $this->assertTrue(SupplierScorecard::query()->exists());
        $this->assertTrue(SystemRecoveryRecord::where('error_id', 'like', 'REC-2026-%')->exists());

        $this->assertFalse(PurchaseOrder::where('requested_at', '>', now())->exists());
    }

    public function test_it_can_be_repeated_without_duplicating_demo_records(): void
    {
        $this->seed(ComprehensiveDemoSeeder::class);
        $counts = $this->representativeCounts();
        $reviewedDocument = LogisticsDocument::where('tracking_number', 'DOC-INV-202609-00002')->firstOrFail();
        $reviewedDocument->update([
            'status' => 'archived',
            'verification_notes' => 'Preserve workflow state during reseeding.',
        ]);

        $this->seed(ComprehensiveDemoSeeder::class);

        $this->assertSame($counts, $this->representativeCounts());
        $this->assertSame('archived', $reviewedDocument->fresh()->status);
        $this->assertSame('Preserve workflow state during reseeding.', $reviewedDocument->fresh()->verification_notes);
    }

    public function test_every_demo_seeder_refuses_non_development_environments(): void
    {
        $this->app->detectEnvironment(fn (): string => 'staging');

        $seeders = [
            ComprehensiveDemoSeeder::class,
            DemandForecastDemoSeeder::class,
            DemoUserSeeder::class,
            ErrorRecoveryDemoSeeder::class,
            InventoryDemoSeeder::class,
            LogisticsDemoSeeder::class,
            OperationalMetricsDemoSeeder::class,
            ProcessReviewDemoSeeder::class,
            ProcurementDemoSeeder::class,
            SmartWarehousingDemoSeeder::class,
            SupplierManagementDemoSeeder::class,
            SupplierReviewEvidenceDemoSeeder::class,
            SupplierScorecardDemoSeeder::class,
        ];

        foreach ($seeders as $seeder) {
            try {
                $this->app->make($seeder)->run();
                $this->fail("{$seeder} accepted demonstration data outside local/testing.");
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'Demonstration data is only available in local and testing environments.',
                    $exception->getMessage(),
                );
            }
        }
    }

    /** @return array<string, int> */
    private function representativeCounts(): array
    {
        return [
            'users' => User::count(),
            'items' => InventoryItem::count(),
            'suppliers' => Supplier::count(),
            'procurement_categories' => ProcurementCategory::count(),
            'rfqs' => SourcingRfq::count(),
            'purchase_orders' => PurchaseOrder::count(),
            'shipments' => Shipment::count(),
            'inspection_reports' => InspectionAcceptanceReport::count(),
            'goods_receipt_lines' => GoodsReceiptNoteLine::count(),
            'logistics_documents' => LogisticsDocument::count(),
            'dpri_prices' => DpriReferencePrice::count(),
            'warehouse_tasks' => WarehouseTask::count(),
            'process_reviews' => KpiProcessReview::count(),
            'supplier_scorecards' => SupplierScorecard::count(),
            'recovery_records' => SystemRecoveryRecord::count(),
        ];
    }
}
