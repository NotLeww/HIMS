<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMS: reshape inventory_items.
 *
 * Drops the columns superseded by `item_categories`, `item_batches` and
 * `item_stock_levels`, and adds the reference/config columns the new
 * structure needs.
 *
 * `quantity_on_hand` and `reserved_quantity` deliberately stay: they become
 * a cached rollup of `item_stock_levels`, recomputed by
 * InventoryAutomationService after every movement, so existing dashboard
 * and listing queries keep working without a per-row aggregate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('sku')->constrained('item_categories')->nullOnDelete();
            $table->foreignId('default_location_id')->nullable()->after('supplier_id')->constrained('storage_locations')->nullOnDelete();
            $table->boolean('is_batch_tracked')->default(true)->after('unit');
            $table->unsignedInteger('expiry_alert_days')->default(30)->after('reorder_level');
        });

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['category', 'warehouse_name', 'batch_number', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('default_location_id');
            $table->dropColumn(['is_batch_tracked', 'expiry_alert_days']);

            $table->string('category')->nullable();
            $table->string('warehouse_name')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
        });
    }
};
