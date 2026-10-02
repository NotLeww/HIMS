<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_approval_requests', function (Blueprint $table) {
            $table->boolean('trust_device_on_approval')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('login_approval_requests', function (Blueprint $table) {
            $table->dropColumn('trust_device_on_approval');
        });
    }
};
