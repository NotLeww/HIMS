<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_requisitions', function (Blueprint $table): void {
            $table->string('delivery_method', 30)->nullable()->after('required_date');
            $table->string('delivery_method_notes')->nullable()->after('delivery_method');
            $table->string('destination_location')->nullable()->after('delivery_method');
            $table->string('destination_address')->nullable()->after('destination_location');
            $table->string('delivery_contact_name')->nullable()->after('destination_address');
            $table->string('delivery_contact_phone', 40)->nullable()->after('delivery_contact_name');
            $table->time('delivery_window_start')->nullable()->after('delivery_contact_phone');
            $table->time('delivery_window_end')->nullable()->after('delivery_window_start');

            $table->index(['delivery_method', 'status'], 'material_requisitions_delivery_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('material_requisitions', function (Blueprint $table): void {
            $table->dropIndex('material_requisitions_delivery_status_idx');
            $table->dropColumn([
                'delivery_method',
                'delivery_method_notes',
                'destination_location',
                'destination_address',
                'delivery_contact_name',
                'delivery_contact_phone',
                'delivery_window_start',
                'delivery_window_end',
            ]);
        });
    }
};
