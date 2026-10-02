<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->string('report_type', 50);
            $table->string('report_format', 10);
            $table->string('frequency', 10);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->time('run_at');
            $table->json('filters')->nullable();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->string('last_status', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('scheduled_report_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduled_report_id')->nullable()->constrained('scheduled_reports')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('scheduled_for');
            $table->string('report_type', 50);
            $table->string('report_format', 10);
            $table->json('filters')->nullable();
            $table->string('recipient_email');
            $table->string('status', 20)->default('pending')->index();
            $table->string('mail_status', 20)->default('pending');
            $table->unsignedInteger('record_count')->nullable();
            $table->string('failure_stage', 20)->nullable();
            $table->string('error_summary', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('mail_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['scheduled_report_id', 'scheduled_for'], 'scheduled_report_occurrence_unique');
            $table->index(['scheduled_report_id', 'created_at'], 'scheduled_report_history_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_report_executions');
        Schema::dropIfExists('scheduled_reports');
    }
};
