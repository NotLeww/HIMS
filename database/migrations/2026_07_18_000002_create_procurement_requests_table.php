<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('requested_quantity')->default(0);
            $table->string('priority')->default('medium');
            $table->string('status')->default('pending');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('approved_by')->nullable();
            $table->text('approval_notes')->nullable();
            $table->decimal('evaluation_score', 5, 2)->nullable();
            $table->string('evaluation_status')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_requests');
    }
};
