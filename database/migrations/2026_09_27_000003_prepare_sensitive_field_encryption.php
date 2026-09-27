<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('phone')->nullable()->change();
            $table->char('phone_blind_index', 64)->nullable()->index()->after('phone');
        });

        Schema::table('privacy_requests', function (Blueprint $table): void {
            $table->text('requestor_name')->change();
            $table->text('requestor_email')->change();
            $table->text('details')->change();
            $table->text('resolution_notes')->nullable()->change();
            $table->text('export_payload')->nullable()->change();
            $table->text('package_manifest')->nullable()->change();
            $table->text('exclusions_summary')->nullable()->change();
        });

        Schema::table('security_incidents', function (Blueprint $table): void {
            $table->text('description')->change();
            $table->text('affected_system_or_data')->change();
            $table->text('breach_assessment')->nullable()->change();
            $table->text('containment_actions')->nullable()->change();
            $table->text('remediation_notes')->nullable()->change();
            $table->text('metadata')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('security_incidents', function (Blueprint $table): void {
            $table->text('description')->change();
            $table->string('affected_system_or_data', 150)->change();
            $table->text('breach_assessment')->nullable()->change();
            $table->text('containment_actions')->nullable()->change();
            $table->text('remediation_notes')->nullable()->change();
            $table->json('metadata')->nullable()->change();
        });

        Schema::table('privacy_requests', function (Blueprint $table): void {
            $table->string('requestor_name', 120)->change();
            $table->string('requestor_email', 150)->change();
            $table->text('details')->change();
            $table->text('resolution_notes')->nullable()->change();
            $table->json('export_payload')->nullable()->change();
            $table->json('package_manifest')->nullable()->change();
            $table->json('exclusions_summary')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['phone_blind_index']);
            $table->dropColumn('phone_blind_index');
            $table->string('phone', 32)->nullable()->change();
        });
    }
};
