<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('id')->constrained('suppliers')->restrictOnDelete();
            $table->index(['supplier_id', 'role', 'status']);
        });

        Schema::create('purchase_order_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('responded_by')->constrained('users')->restrictOnDelete();
            $table->string('response', 30);
            $table->string('exception_type', 50)->nullable();
            $table->text('message')->nullable();
            $table->timestamp('responded_at');
            $table->timestamps();
            $table->index(['supplier_id', 'responded_at']);
        });

        Schema::create('shipment_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('po_line_id')->constrained('po_line_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('lot_number', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'po_line_id']);
        });

        Schema::create('supplier_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('grn_line_item_id')->unique()->constrained('grn_line_items')->cascadeOnDelete();
            $table->string('status', 30)->default('open');
            $table->string('supplier_response_type', 50)->nullable();
            $table->text('supplier_response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 80);
            $table->date('invoice_date');
            $table->decimal('total_amount', 14, 2);
            $table->string('status', 30)->default('submitted');
            $table->text('match_notes')->nullable();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['supplier_id', 'invoice_number']);
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('po_line_id')->constrained('po_line_items')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 14, 2);
            $table->string('match_status', 30)->default('pending');
            $table->timestamps();
            $table->unique(['supplier_invoice_id', 'po_line_id']);
        });

        Schema::table('supplier_products', function (Blueprint $table) {
            $table->string('gtin', 14)->nullable()->after('supplier_sku');
            $table->string('approval_status', 30)->default('approved')->after('is_active');
            $table->boolean('vmi_enabled')->default(false)->after('approval_status');
            $table->unsignedInteger('vmi_min')->nullable()->after('vmi_enabled');
            $table->unsignedInteger('vmi_max')->nullable()->after('vmi_min');
            $table->index(['supplier_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropIndex(['supplier_id', 'approval_status']);
            $table->dropColumn(['gtin', 'approval_status', 'vmi_enabled', 'vmi_min', 'vmi_max']);
        });
        Schema::dropIfExists('supplier_invoice_lines');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('supplier_discrepancies');
        Schema::dropIfExists('shipment_line_items');
        Schema::dropIfExists('purchase_order_acknowledgements');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['supplier_id', 'role', 'status']);
            $table->dropColumn('supplier_id');
        });
    }
};
