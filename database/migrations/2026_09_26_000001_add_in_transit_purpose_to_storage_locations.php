<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->boolean('is_in_transit')->default(false)->after('is_dispatch_staging');
            $table->index(['is_in_transit', 'status']);
        });

        // Earlier releases created this canonical buffer in application code.
        // Its exact code makes the purpose safe to derive without inventing data.
        DB::table('storage_locations')
            ->where('code', 'LOC-IN-TRANSIT')
            ->update(['is_in_transit' => true]);
    }

    public function down(): void
    {
        Schema::table('storage_locations', function (Blueprint $table) {
            $table->dropIndex(['is_in_transit', 'status']);
            $table->dropColumn('is_in_transit');
        });
    }
};
