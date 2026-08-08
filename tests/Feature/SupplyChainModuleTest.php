<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyChainModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_inventory_manager_can_create_an_inventory_item(): void
    {
        // The item master needs manage_items — the storeroom owns the catalogue.
        $user = User::factory()->inventoryManager()->create();
        $supplier = Supplier::create([
            'name' => 'Acme Medical Supplies',
            'email' => 'sales@acme.com',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post('/inventory/items', [
            'name' => 'Gloves',
            'sku' => 'GL-100',
            'category' => 'consumables',
            'unit' => 'box',
            'quantity_on_hand' => 50,
            'reorder_level' => 10,
            'supplier_id' => $supplier->id,
            'warehouse_name' => 'Main Store',
        ]);

        $response->assertRedirect('/inventory/items');
        $this->assertDatabaseHas('inventory_items', [
            'name' => 'Gloves',
            'sku' => 'GL-100',
        ]);
    }
}
