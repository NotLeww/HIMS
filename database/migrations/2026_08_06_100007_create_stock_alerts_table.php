<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SWS: persisted low-stock and expiry alerts.
 *
 * The nightly sweep raises rows here and resolves them once the underlying
 * condition clears, so the dashboard can show what is currently wrong and
 * who has already acknowledged it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->foreignId('item_batch_id')->nullable()->constrained('item_batches')->cascadeOnDelete();
            $table->foreignId('storage_location_id')->nullable()->constrained('storage_locations')->nullOnDelete();
            $table->string('type');
            $table->string('severity')->default('warning');
            $table->string('status')->default('open');
            $table->string('message');
            $table->integer('threshold_value')->nullable();
            $table->integer('current_value')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // The sweep looks up "is there already a live alert of this kind?"
            $table->index(['item_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_alerts');
    }
};
