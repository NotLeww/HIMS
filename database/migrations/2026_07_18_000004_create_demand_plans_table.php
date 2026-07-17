<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_number')->unique();
            $table->foreignId('item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->integer('current_stock')->default(0);
            $table->integer('historical_usage')->default(0);
            $table->integer('upcoming_need')->default(0);
            $table->integer('reorder_point')->default(0);
            $table->string('trigger_reason')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_plans');
    }
};
