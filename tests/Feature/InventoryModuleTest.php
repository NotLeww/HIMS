<?php

namespace Tests\Feature;

use App\Models\Models\InventoryItem;
use App\Models\Models\PurchaseOrder;
use App\Models\Models\StorageLocation;
use App\Models\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_dashboard_is_accessible_to_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Operations Dashboard');
        $response->assertSee('Quick actions');
    }

    public function test_stock_out_updates_inventory_balance_and_status_automatically(): void
    {
        $user = User::factory()->create();
        $item = InventoryItem::create([
            'name' => 'Gloves',
            'sku' => 'GL-100',
            'quantity_on_hand' => 25,
            'reorder_level' => 10,
            'unit_cost' => 5,
            'total_value' => 125,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'stock_out',
            'quantity' => 15,
            'remarks' => 'Issued to ward',
        ]);

        $response->assertRedirect('/inventory/stock-movements');
        $item->refresh();
        $this->assertSame(10, $item->quantity_on_hand);
        $this->assertSame('low_stock', $item->status);
        $this->assertSame(50.0, (float) $item->total_value);
    }

    public function test_transfer_rejects_insufficient_stock_before_updating_balance(): void
    {
        $user = User::factory()->create();
        $source = StorageLocation::create([
            'name' => 'Main Store',
            'code' => 'MAIN-01',
            'status' => 'active',
        ]);
        $destination = StorageLocation::create([
            'name' => 'Ward A',
            'code' => 'WARD-A',
            'status' => 'active',
        ]);
        $item = InventoryItem::create([
            'name' => 'Syringes',
            'sku' => 'SYR-100',
            'quantity_on_hand' => 3,
            'reorder_level' => 1,
            'unit_cost' => 2,
            'total_value' => 6,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post('/inventory/stock-movements', [
            'item_id' => $item->id,
            'movement_type' => 'transfer',
            'quantity' => 5,
            'from_location_id' => $source->id,
            'to_location_id' => $destination->id,
            'remarks' => 'Transfer request',
        ]);

        $response->assertSessionHasErrors('quantity');
        $item->refresh();
        $this->assertSame(3, $item->quantity_on_hand);
    }

    public function test_purchase_order_receive_does_not_fail_when_value_columns_are_missing(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::create([
            'name' => 'Metro Med Supply',
            'contact_person' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '09170000000',
            'address' => 'Manila',
            'status' => 'active',
        ]);
        $item = InventoryItem::create([
            'name' => 'Bandages',
            'sku' => 'BAND-001',
            'quantity_on_hand' => 10,
            'reorder_level' => 5,
            'status' => 'active',
        ]);
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_cost' => 2.5,
            'total_amount' => 12.5,
            'status' => 'pending',
        ]);

        if (Schema::hasColumn('inventory_items', 'total_value')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropColumn('total_value');
            });
        }

        if (Schema::hasColumn('inventory_items', 'unit_cost')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropColumn('unit_cost');
            });
        }

        $response = $this->actingAs($user)->post('/inventory/purchases/'.$purchaseOrder->id.'/receive');

        $response->assertRedirect('/inventory/purchases');
        $this->assertSame('received', $purchaseOrder->fresh()->status);
        $this->assertSame(15, $item->fresh()->quantity_on_hand);
    }

    public function test_inventory_alert_command_marks_low_stock_and_out_of_stock_items(): void
    {
        InventoryItem::create([
            'name' => 'Masks',
            'sku' => 'MASK-001',
            'quantity_on_hand' => 6,
            'reorder_level' => 10,
            'unit_cost' => 1,
            'total_value' => 6,
            'status' => 'active',
        ]);

        InventoryItem::create([
            'name' => 'Needles',
            'sku' => 'NEEDLE-001',
            'quantity_on_hand' => 0,
            'reorder_level' => 5,
            'unit_cost' => 2,
            'total_value' => 0,
            'status' => 'active',
        ]);

        $exitCode = Artisan::call('inventory:check-alerts');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'MASK-001', 'status' => 'low_stock']);
        $this->assertDatabaseHas('inventory_items', ['sku' => 'NEEDLE-001', 'status' => 'out_of_stock']);
    }
}
