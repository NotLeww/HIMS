<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pr_line_items', function (Blueprint $table): void {
            $table->string('uom', 40)->change();
        });

        Schema::table('rfq_line_items', function (Blueprint $table): void {
            $table->string('uom', 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pr_line_items', function (Blueprint $table): void {
            $table->string('uom', 40)->default('unit')->change();
        });

        Schema::table('rfq_line_items', function (Blueprint $table): void {
            $table->string('uom', 40)->default('unit')->change();
        });
    }
};
