<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatePhone = DB::table('users')
            ->select('phone_blind_index')
            ->whereNotNull('phone_blind_index')
            ->groupBy('phone_blind_index')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicatePhone) {
            throw new RuntimeException('Duplicate user phone numbers must be resolved before enabling account activation.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
            $table->unique('phone_blind_index', 'users_phone_blind_index_unique');
        });

        Schema::create('account_activation_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('channel', 10)->nullable();
            $table->string('otp_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('resend_available_at')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('password')->exists()) {
            throw new RuntimeException('Pending accounts must be activated or removed before rolling back account activation.');
        }

        Schema::dropIfExists('account_activation_challenges');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_phone_blind_index_unique');
            $table->string('password')->nullable(false)->change();
        });
    }
};
