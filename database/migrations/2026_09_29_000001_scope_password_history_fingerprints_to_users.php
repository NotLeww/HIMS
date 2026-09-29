<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('password_histories', function (Blueprint $table) {
            $table->dropUnique('password_histories_password_fingerprint_unique');
            $table->unique(
                ['user_id', 'password_fingerprint'],
                'password_histories_user_password_fingerprint_unique',
            );
        });
    }

    public function down(): void
    {
        $hasCrossAccountDuplicates = DB::table('password_histories')
            ->whereNotNull('password_fingerprint')
            ->select('password_fingerprint')
            ->groupBy('password_fingerprint')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasCrossAccountDuplicates) {
            throw new RuntimeException('Cannot restore global password fingerprint uniqueness without deleting per-user history.');
        }

        Schema::table('password_histories', function (Blueprint $table) {
            $table->dropUnique('password_histories_user_password_fingerprint_unique');
            $table->unique('password_fingerprint');
        });
    }
};
