<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\ItemStockLevel;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $medical = ItemCategory::create(['name' => 'Medical Supplies', 'code' => 'MED', 'is_active' => true]);
        $ppe = ItemCategory::create(['name' => 'PPE', 'code' => 'PPE', 'parent_id' => $medical->id, 'is_active' => true]);
        $pharma = ItemCategory::create(['name' => 'Pharmaceuticals', 'code' => 'PHARMA', 'is_active' => true]);

        // Storage locations (hierarchical)
        $warehouse = StorageLocation::create(['name' => 'Main Warehouse', 'code' => 'WH-01', 'type' => 'warehouse', 'status' => 'active', 'capacity' => 10000]);
        $zoneA = StorageLocation::create(['name' => 'Zone A', 'code' => 'WH-01-A', 'type' => 'zone', 'parent_id' => $warehouse->id, 'status' => 'active']);
        $pharmacy = StorageLocation::create(['name' => 'Central Pharmacy', 'code' => 'PHARM-01', 'type' => 'pharmacy', 'status' => 'active', 'capacity' => 2000]);

        // Suppliers
        $medsupply = Supplier::create([
            'name' => 'MedSupply Corp',
            'contact_person' => 'Maria Santos',
            'email' => 'maria@medsupply.ph',
            'phone' => '09171234567',
            'address' => 'Quezon City, Metro Manila',
            'status' => 'active',
        ]);

        // N95 Masks - batch tracked, multiple batches at different expiry dates
        $masks = InventoryItem::create([
            'name' => 'N95 Respirator Mask',
            'sku' => 'PPE-MASK-N95',
            'category_id' => $ppe->id,
            'unit' => 'box',
            'is_batch_tracked' => true,
            'quantity_on_hand' => 0,
            'reorder_level' => 50,
            'expiry_alert_days' => 60,
            'supplier_id' => $medsupply->id,
            'default_location_id' => $zoneA->id,
            'status' => 'active',
        ]);

        $batch1 = ItemBatch::create([
            'item_id' => $masks->id,
            'batch_number' => 'N95-2026-001',
            'expiry_date' => now()->addMonths(3),
            'unit_cost' => 45.00,
            'initial_quantity' => 100,
            'status' => 'active',
        ]);

        $batch2 = ItemBatch::create([
            'item_id' => $masks->id,
            'batch_number' => 'N95-2026-002',
            'expiry_date' => now()->addMonths(8),
            'unit_cost' => 47.50,
            'initial_quantity' => 150,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $masks->id,
            'storage_location_id' => $zoneA->id,
            'item_batch_id' => $batch1->id,
            'quantity' => 35,
            'reserved_quantity' => 5,
        ]);

        ItemStockLevel::create([
            'item_id' => $masks->id,
            'storage_location_id' => $zoneA->id,
            'item_batch_id' => $batch2->id,
            'quantity' => 140,
            'reserved_quantity' => 0,
        ]);

        // Paracetamol 500mg - batch tracked, in pharmacy
        $paracetamol = InventoryItem::create([
            'name' => 'Paracetamol 500mg Tablets',
            'sku' => 'PHARMA-PARA-500',
            'category_id' => $pharma->id,
            'unit' => 'bottle',
            'is_batch_tracked' => true,
            'quantity_on_hand' => 0,
            'reorder_level' => 20,
            'expiry_alert_days' => 90,
            'supplier_id' => $medsupply->id,
            'default_location_id' => $pharmacy->id,
            'status' => 'active',
        ]);

        $paraBatch = ItemBatch::create([
            'item_id' => $paracetamol->id,
            'batch_number' => 'PARA-2026-045',
            'expiry_date' => now()->addMonths(18),
            'unit_cost' => 8.50,
            'initial_quantity' => 200,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $paracetamol->id,
            'storage_location_id' => $pharmacy->id,
            'item_batch_id' => $paraBatch->id,
            'quantity' => 180,
            'reserved_quantity' => 10,
        ]);

        // Surgical Gloves - not batch tracked
        $gloves = InventoryItem::create([
            'name' => 'Surgical Gloves (Large)',
            'sku' => 'PPE-GLOVE-L',
            'category_id' => $ppe->id,
            'unit' => 'box',
            'is_batch_tracked' => false,
            'quantity_on_hand' => 0,
            'reorder_level' => 30,
            'supplier_id' => $medsupply->id,
            'default_location_id' => $zoneA->id,
            'status' => 'active',
        ]);

        ItemStockLevel::create([
            'item_id' => $gloves->id,
            'storage_location_id' => $zoneA->id,
            'item_batch_id' => null,
            'quantity' => 25,
            'reserved_quantity' => 0,
        ]);

        // Sync cached totals
        foreach ([$masks, $paracetamol, $gloves] as $item) {
            $total = ItemStockLevel::where('item_id', $item->id)->sum('quantity');
            $totalCost = ItemStockLevel::query()
                ->where('item_stock_levels.item_id', $item->id)
                ->join('item_batches', 'item_stock_levels.item_batch_id', '=', 'item_batches.id')
                ->selectRaw('SUM(item_stock_levels.quantity * item_batches.unit_cost) as value')
                ->value('value');

            $item->update([
                'quantity_on_hand' => $total,
                'unit_cost' => $total > 0 ? ($totalCost / $total) : 0,
                'total_value' => $totalCost ?? 0,
                'status' => match (true) {
                    $total <= 0 => 'out_of_stock',
                    $total <= $item->reorder_level => 'low_stock',
                    default => 'in_stock',
                },
            ]);
        }

        $this->command->info('Inventory demo data seeded: 3 categories, 3 locations, 1 supplier, 3 items with batches and stock levels.');
    }
}
