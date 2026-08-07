<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SWS: make storage locations hierarchical.
 *
 * Lets the warehouse be modelled as zone > aisle > rack > bin so stock can
 * be rolled up at any level of the tree.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('code')->constrained('storage_locations')->nullOnDelete();
            $table->string('type')->default('bin')->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('type');
        });
    }
};
