<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User Management: give accounts a job function and an on/off switch.
 *
 * The Breeze `users` table carried only name/email/password, so every screen
 * was open to every logged-in account. These columns are what the Gate layer
 * reads to tell a warehouse clerk apart from an administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default(UserRole::Viewer->value)->after('email');
            $table->string('status')->default(UserStatus::Active->value)->after('role');
            $table->string('employee_id')->nullable()->unique()->after('status');
            $table->string('department')->nullable()->after('employee_id');
            $table->string('phone', 32)->nullable()->after('department');
            $table->timestamp('last_login_at')->nullable()->after('phone');

            $table->index(['role', 'status']);
        });

        // Existing accounts predate roles. The seeded demo account is the only
        // one that can reach the user screens, so it has to be the one that can
        // grant access to everyone else — otherwise the first migration on a
        // populated database locks the module behind an empty admin list.
        DB::table('users')->update(['role' => UserRole::Administrator->value]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropUnique(['employee_id']);
            $table->dropColumn([
                'role',
                'status',
                'employee_id',
                'department',
                'phone',
                'last_login_at',
            ]);
        });
    }
};
