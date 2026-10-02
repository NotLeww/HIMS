<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->index();
            $table->string('device_uuid', 36)->nullable()->index();
            $table->string('display_name', 255);
            $table->string('user_agent_summary', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('first_trusted_at');
            $table->timestamp('last_used_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('user_active_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('session_id', 255)->index();
            $table->string('guard', 50)->default('web');
            $table->string('device_name', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('trusted_device_id')->nullable()->constrained('trusted_devices')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('login_approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('guard', 50)->default('web');
            $table->boolean('remember')->default(false);
            $table->string('challenge_token_hash', 64)->index();
            $table->string('claim_token_hash', 64)->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('device_fingerprint', 64)->nullable()->index();
            $table->string('device_name', 255)->nullable();
            $table->string('platform', 100)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('location_summary', 255)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('responded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email_otp_hash', 64)->nullable();
            $table->timestamp('email_otp_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('device_login_cooldowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->index();
            $table->string('device_fingerprint', 64)->index();
            $table->string('login_approval_request_id', 36)->nullable();
            $table->timestamp('blocked_until')->index();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_login_cooldowns');
        Schema::dropIfExists('login_approval_requests');
        Schema::dropIfExists('user_active_sessions');
        Schema::dropIfExists('trusted_devices');
    }
};
