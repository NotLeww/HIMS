<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('activation_cancellation_reason', 64)->nullable()->after('status');
            $table->text('activation_cancellation_details')->nullable()->after('activation_cancellation_reason');
            $table->timestamp('activation_cancelled_at')->nullable()->after('activation_cancellation_details');
            $table->foreignId('activation_cancelled_by')->nullable()->after('activation_cancelled_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('activation_cancellation_notice_sent_at')->nullable()->after('activation_cancelled_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('activation_cancelled_by');
            $table->dropColumn([
                'activation_cancellation_reason',
                'activation_cancellation_details',
                'activation_cancelled_at',
                'activation_cancellation_notice_sent_at',
            ]);
        });
    }
};
