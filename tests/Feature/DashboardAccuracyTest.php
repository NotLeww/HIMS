<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Ai\HimsAiToolRegistry;
use App\Services\AiDemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardAccuracyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_values_match_their_database_records(): void
    {
        $manager = User::factory()->inventoryManager()->create();
        $location = StorageLocation::create(['name' => 'Main Store', 'code' => 'MAIN', 'status' => 'active']);
        StorageLocation::create(['name' => 'Inactive Store', 'code' => 'OLD', 'status' => 'inactive']);

        $active = $this->item('Active Item', 'ACTIVE', 100, 20, 2.00, 'active');
        $inactive = $this->item('Inactive Item', 'INACTIVE', 5, 10, 3.00, 'inactive');
        $archived = $this->item('Archived Item', 'ARCHIVED', 25, 30, 100.00, 'archived');

        $this->expiringBatch($active, $location, 'ACTIVE-BATCH', 10, 20);
        $this->expiringBatch($archived, $location, 'ARCHIVED-BATCH', 7, 10);

        $activeSupplier = Supplier::create(['name' => 'Active Supplier', 'status' => 'active']);
        Supplier::create(['name' => 'Inactive Supplier', 'status' => 'inactive']);
        Supplier::create(['name' => 'Archived Supplier', 'status' => 'archived', 'archived_at' => now()]);

        foreach (PurchaseOrderStatus::cases() as $index => $status) {
            PurchaseOrder::create([
                'po_number' => 'PO-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'supplier_id' => $activeSupplier->id,
                'item_id' => $active->id,
                'quantity' => 1,
                'unit_cost' => 2,
                'total_amount' => 2,
                'status' => $status->value,
                'requested_at' => now()->subMinutes($index),
            ]);
        }

        $olderMovement = StockMovement::create([
            'item_id' => $active->id,
            'movement_type' => MovementType::StockIn,
            'quantity' => 10,
            'to_location_id' => $location->id,
            'moved_at' => now()->subHour(),
        ]);
        $newerMovement = StockMovement::create([
            'item_id' => $inactive->id,
            'movement_type' => MovementType::Issuance,
            'quantity' => 2,
            'from_location_id' => $location->id,
            'moved_at' => now(),
        ]);

        Cache::put('demand-forecast:v3:90:30', [
            'source' => 'statistical',
            'analysis_days' => 90,
            'forecast_days' => 30,
            'forecast_period' => 'Next 30 days',
            'generated_at' => now()->toIso8601String(),
            'summary' => [],
            'items' => [],
        ]);

        $expectedItems = InventoryItem::query()->where('status', '!=', 'archived')->get();
        $expectedOpenStatuses = collect(PurchaseOrderStatus::cases())
            ->filter(fn (PurchaseOrderStatus $status): bool => $status->isOpen())
            ->pluck('value');
        $expectedPendingOrderIds = PurchaseOrder::query()
            ->whereIn('status', $expectedOpenStatuses)
            ->latest('requested_at')
            ->latest('id')
            ->take(5)
            ->pluck('id')
            ->all();

        $page = $this->actingAs($manager)->get('/dashboard')->assertOk();
        $live = $this->actingAs($manager)->getJson('/dashboard/live')->assertOk();
        $api = $this->actingAs($manager)->getJson('/api/v1/dashboard-summary')->assertOk();

        $this->assertSame($expectedItems->count(), $page->viewData('totalItems'));
        $this->assertSame($expectedItems->sum('quantity_on_hand'), $page->viewData('totalOnHand'));
        $this->assertSame(
            (float) $expectedItems->sum(fn (InventoryItem $item): float => $item->quantity_on_hand * $item->unit_cost),
            $page->viewData('totalInventoryValue'),
        );
        $this->assertSame(1, $page->viewData('lowStockItems'));
        $this->assertSame(0, $page->viewData('outOfStockItems'));
        $this->assertSame(1, $page->viewData('expiringSoonCount'));
        $this->assertSame(1, $page->viewData('criticalExpiryCount'));
        $this->assertSame(2, $page->viewData('totalSuppliers'));
        $this->assertSame(1, $page->viewData('activeSuppliers'));
        $this->assertSame(1, $page->viewData('inactiveSuppliers'));
        $this->assertSame(2, $page->viewData('storageLocations'));
        $this->assertSame(
            PurchaseOrder::query()->whereIn('status', $expectedOpenStatuses)->count(),
            $page->viewData('pendingPoCount'),
        );
        $this->assertSame($expectedPendingOrderIds, $page->viewData('pendingPurchaseOrders')->pluck('id')->all());
        $this->assertSame([$inactive->id], $page->viewData('attentionItems')->pluck('id')->all());
        $this->assertSame([$newerMovement->id, $olderMovement->id], $page->viewData('recentMovements')->pluck('id')->all());

        $live->assertJsonPath('totalItems', $expectedItems->count())
            ->assertJsonPath('totalOnHand', $expectedItems->sum('quantity_on_hand'))
            ->assertJsonPath('lowStockItems', 1)
            ->assertJsonPath('outOfStockItems', 0)
            ->assertJsonPath('expiringSoonCount', 1)
            ->assertJsonPath('criticalExpiryCount', 1);

        $api->assertJsonPath('total_items', 2)
            ->assertJsonPath('total_on_hand', 105)
            ->assertJsonPath('low_stock_items', 1)
            ->assertJsonPath('out_of_stock_items', 0)
            ->assertJsonPath('total_inventory_value', 215)
            ->assertJsonPath('total_suppliers', 2)
            ->assertJsonPath('active_suppliers', 1)
            ->assertJsonPath('storage_locations', 2)
            ->assertJsonPath('recent_movements.0.id', $newerMovement->id)
            ->assertJsonPath('recent_movements.1.id', $olderMovement->id);

        $this->assertSame(9, app(AiDemandForecastService::class)
            ->reorderRecommendationForItem($active, $manager)['incoming_procurement']);
        $this->assertSame(9, app(HimsAiToolRegistry::class)
            ->getDailySummary($manager)['pending_purchase_orders_count']);
    }

    public function test_empty_dashboard_values_are_database_zeroes(): void
    {
        $manager = User::factory()->inventoryManager()->create();

        $page = $this->actingAs($manager)->get('/dashboard')->assertOk();
        $live = $this->actingAs($manager)->getJson('/dashboard/live')->assertOk();

        foreach (['totalItems', 'totalOnHand', 'lowStockItems', 'outOfStockItems', 'expiringSoonCount', 'criticalExpiryCount'] as $metric) {
            $this->assertSame(0, $page->viewData($metric), $metric);
            $live->assertJsonPath($metric, 0);
        }

        $this->assertSame(0.0, $page->viewData('totalInventoryValue'));
        $this->assertSame(0, $page->viewData('pendingPoCount'));
        $this->assertSame(0, $page->viewData('totalSuppliers'));
        $this->assertSame(0, $page->viewData('storageLocations'));
        $this->assertTrue($page->viewData('recentMovements')->isEmpty());
    }

    private function item(
        string $name,
        string $sku,
        int $quantity,
        int $reorderLevel,
        float $unitCost,
        string $status,
    ): InventoryItem {
        return InventoryItem::create([
            'name' => $name,
            'sku' => $sku,
            'quantity_on_hand' => $quantity,
            'reserved_quantity' => 0,
            'reorder_level' => $reorderLevel,
            'unit_cost' => $unitCost,
            'status' => $status,
            'archived_at' => $status === 'archived' ? now() : null,
        ]);
    }

    private function expiringBatch(
        InventoryItem $item,
        StorageLocation $location,
        string $batchNumber,
        int $quantity,
        int $daysUntilExpiry,
    ): void {
        $batch = ItemBatch::create([
            'item_id' => $item->id,
            'batch_number' => $batchNumber,
            'expiry_date' => today()->addDays($daysUntilExpiry),
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $item->id,
            'item_batch_id' => $batch->id,
            'storage_location_id' => $location->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
        ]);
    }
}
