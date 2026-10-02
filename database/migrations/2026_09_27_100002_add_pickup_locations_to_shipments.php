<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_id')->nullable()->change();
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
            $table->string('pickup_location_type', 40)->nullable()->after('supplier_id')->index();
            $table->string('pickup_location_name', 150)->nullable()->after('pickup_location_type')->index();
            $table->foreignId('pickup_storage_location_id')->nullable()->after('pickup_location_name')
                ->constrained('storage_locations')->nullOnDelete();
            $table->string('pickup_contact_name', 150)->nullable()->after('origin_address');
            $table->string('pickup_contact_number', 50)->nullable()->after('pickup_contact_name');
            $table->foreignId('destination_storage_location_id')->nullable()->after('destination_facility')
                ->constrained('storage_locations')->nullOnDelete();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });

        // The old origin_address was already used as the physical custody
        // origin. Preserve that reliable history without guessing a master ID.
        DB::table('shipments')
            ->whereNotNull('origin_address')
            ->where('origin_address', '!=', '')
            ->update([
                'pickup_location_type' => 'legacy_origin',
                'pickup_location_name' => DB::raw('origin_address'),
            ]);
    }

    public function down(): void
    {
        if (DB::table('shipments')->whereNull('purchase_order_id')->orWhereNull('supplier_id')->exists()) {
            throw new RuntimeException('Cannot restore required shipment purchase-order and supplier links while direct-transfer records exist.');
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropConstrainedForeignId('pickup_storage_location_id');
            $table->dropConstrainedForeignId('destination_storage_location_id');
            $table->dropIndex(['pickup_location_type']);
            $table->dropIndex(['pickup_location_name']);
            $table->dropColumn([
                'pickup_location_type',
                'pickup_location_name',
                'pickup_contact_name',
                'pickup_contact_number',
            ]);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_order_id')->nullable(false)->change();
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
        });
    }
};
