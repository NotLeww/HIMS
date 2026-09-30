<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_process_reviews', function (Blueprint $table) {
            $table->dropForeign(['evaluator_id']);
            $table->foreign('evaluator_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kpi_process_reviews', function (Blueprint $table) {
            $table->dropForeign(['evaluator_id']);
            $table->foreign('evaluator_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
