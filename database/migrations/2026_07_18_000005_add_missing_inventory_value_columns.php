<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_items', 'unit_cost')) {
                $table->decimal('unit_cost', 12, 2)->default(0)->after('reorder_level');
            }

            if (!Schema::hasColumn('inventory_items', 'total_value')) {
                $table->decimal('total_value', 12, 2)->default(0)->after('unit_cost');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'total_value')) {
                $table->dropColumn('total_value');
            }

            if (Schema::hasColumn('inventory_items', 'unit_cost')) {
                $table->dropColumn('unit_cost');
            }
        });
    }
};
