<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMS + SWS: per-location stock balances.
 *
 * This is the source of truth for how much of an item sits where.
 * `inventory_items.quantity_on_hand` is a cached rollup of these rows.
 *
 * A null `item_batch_id` represents stock held without batch tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('storage_location_id')->constrained('storage_locations')->cascadeOnDelete();
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->timestamps();

            // One balance row per item/location/batch triple.
            $table->unique(
                ['item_id', 'storage_location_id', 'item_batch_id'],
                'item_stock_levels_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_stock_levels');
    }
};
