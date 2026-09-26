<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('destination_facility', 150)->change();
        });

        Schema::table('chain_of_custody_logs', function (Blueprint $table) {
            $table->string('package_condition', 50)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->string('destination_facility', 150)->default('Central Hospital Receiving Dock')->change();
        });

        Schema::table('chain_of_custody_logs', function (Blueprint $table) {
            $table->string('package_condition', 50)->nullable()->default('good_order')->change();
        });
    }
};
