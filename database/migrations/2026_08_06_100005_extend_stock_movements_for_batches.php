<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * IMS: extend stock_movements for batch and source tracking.
 *
 * Adds the batch a movement applied to, the cost it moved at, and a
 * polymorphic reference so a movement can point back at whatever caused it
 * (a goods receipt, a requisition, a manual adjustment).
 *
 * `user_id` was a bare unsigned integer; it becomes a real foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('item_batch_id')->nullable()->after('item_id')->constrained('item_batches')->nullOnDelete();
            $table->decimal('unit_cost', 12, 2)->default(0)->after('quantity');
            $table->nullableMorphs('reference');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropMorphs('reference');
            $table->dropConstrainedForeignId('item_batch_id');
            $table->dropColumn('unit_cost');
        });
    }
};
