<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMS: batch/lot tracking.
 *
 * Batch and expiry data previously lived as single columns on
 * `inventory_items`, which limited each item to exactly one batch. Moving
 * them here lets one item hold many batches with distinct expiry dates,
 * which is what FEFO picking and expiry alerting require.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('batch_number');
            $table->string('lot_number')->nullable();
            $table->date('manufactured_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('received_at')->nullable();
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->integer('initial_quantity')->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'batch_number']);
            // FEFO picking and the expiry-alert sweep both scan by expiry date.
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_batches');
    }
};
