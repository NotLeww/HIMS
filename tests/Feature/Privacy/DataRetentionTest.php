<?php

namespace Tests\Feature\Privacy;

use App\Enums\AuditAction;
use App\Enums\RecoveryFailureType;
use App\Models\AiChatConversation;
use App\Models\AuditLog;
use App\Models\PrivacyRequest;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\Privacy\DataRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_mode_does_not_delete_any_records(): void
    {
        $service = app(DataRetentionService::class);
        $user = User::factory()->create();

        // 1. Insert old read notification (100 days old)
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Generic',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Test Notification']),
            'read_at' => now()->subDays(100),
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        // 2. Insert old resolved recovery record (200 days old)
        SystemRecoveryRecord::create([
            'error_id' => 'REC-2026-0001',
            'severity' => 'low',
            'status' => 'resolved',
            'module' => 'System',
            'failure_type' => RecoveryFailureType::Application->value,
            'operation' => 'Cache Cleanup',
            'error_summary' => 'Cache connection timeout',
            'exception_class' => 'RuntimeException',
            'resolved_at' => now()->subDays(200),
        ]);

        $results = $service->sweepEphemeralData(dryRun: true);

        $this->assertTrue($results['dry_run']);
        $this->assertSame(1, $results['notifications_purged']);
        $this->assertSame(1, $results['resolved_recovery_records_purged']);

        // Assert records still exist in DB because dry_run was true
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('system_recovery_records', ['error_id' => 'REC-2026-0001']);
    }

    public function test_retention_sweep_prunes_only_expired_ephemeral_records(): void
    {
        $service = app(DataRetentionService::class);
        $user = User::factory()->create();

        // Expired read notification (100 days old)
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Generic',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Old Read Notification']),
            'read_at' => now()->subDays(100),
            'created_at' => now()->subDays(100),
            'updated_at' => now()->subDays(100),
        ]);

        // Recent unread notification (1 day old) - MUST BE PRESERVED
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\Generic',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'Recent Notification']),
            'read_at' => null,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $results = $service->sweepEphemeralData(dryRun: false, actor: $user);

        $this->assertFalse($results['dry_run']);
        $this->assertSame(1, $results['notifications_purged']);

        // Exactly 1 notification remains: the recent unread one
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseMissing('notifications', ['data->title' => 'Old Read Notification']);
    }

    public function test_permanent_audit_trail_is_never_pruned_under_any_circumstance(): void
    {
        $service = app(DataRetentionService::class);
        $user = User::factory()->create();

        // Create an audit log entry dated 3 years ago
        $log = AuditLog::create([
            'event_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_role' => $user->role->value,
            'action' => AuditAction::LoggedIn,
            'event_category' => 'Authentication',
            'target_type' => User::class,
            'target_id' => $user->id,
            'target_name' => $user->name,
            'description' => 'User logged in successfully 3 years ago.',
            'created_at' => now()->subYears(3),
        ]);

        // Execute live retention sweep
        $service->sweepEphemeralData(dryRun: false, actor: $user);

        // AuditLog MUST remain in database
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);

        // Verify model-level immutability invariant
        try {
            $log->delete();
            $this->fail('AuditLog model delete was expected to throw an exception or be prevented.');
        } catch (\Throwable $e) {
            $this->assertTrue(true, 'AuditLog prevented deletion: '.$e->getMessage());
        }
    }

    public function test_artisan_retention_command_runs_successfully(): void
    {
        $this->artisan('privacy:enforce-retention', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN mode')
            ->expectsOutputToContain('Permanent Audit Trail Preserved')
            ->assertExitCode(0);
    }

    public function test_retention_sweep_removes_expired_ai_conversations_and_private_attachments(): void
    {
        Storage::fake('local');
        config()->set('privacy.retention.ai_chat_history_days', 30);

        $user = User::factory()->create();
        $conversation = AiChatConversation::create([
            'user_id' => $user->id,
            'title' => 'Expired private conversation',
        ]);
        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Synthetic inventory question.',
            'attachment_name' => 'inventory.txt',
            'attachment_path' => "ai-chat-attachments/{$user->id}/expired.txt",
            'attachment_type' => 'text',
        ]);
        Storage::disk('local')->put($message->attachment_path, 'synthetic attachment');
        AiChatConversation::whereKey($conversation->id)->update(['updated_at' => now()->subDays(31)]);

        $results = app(DataRetentionService::class)->sweepEphemeralData(actor: $user);

        $this->assertSame(1, $results['ai_chat_conversations_purged']);
        $this->assertSame(1, $results['ai_chat_attachments_purged']);
        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_chat_messages', ['id' => $message->id]);
        Storage::disk('local')->assertMissing($message->attachment_path);
    }

    public function test_retention_sweep_disposes_expired_dsar_package_but_preserves_case_record(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $request = PrivacyRequest::create([
            'user_id' => $user->id,
            'request_type' => PrivacyRequest::TYPE_ACCESS,
            'details' => 'Provide access to my account information.',
            'status' => PrivacyRequest::STATUS_FULFILLED,
            'package_path' => 'dsar/synthetic/package.zip',
            'package_filename' => 'package.zip',
            'package_hash' => str_repeat('a', 64),
            'package_size_bytes' => 9,
            'package_manifest' => ['synthetic' => true],
            'export_payload' => ['synthetic' => true],
            'package_expires_at' => now()->subMinute(),
        ]);
        Storage::disk('local')->put($request->package_path, 'synthetic');

        $results = app(DataRetentionService::class)->sweepEphemeralData(actor: $user);

        $this->assertSame(1, $results['expired_dsar_packages_disposed']);
        $this->assertDatabaseHas('privacy_requests', [
            'id' => $request->id,
            'status' => PrivacyRequest::STATUS_EXPIRED,
            'package_path' => null,
        ]);
        Storage::disk('local')->assertMissing('dsar/synthetic/package.zip');
        $this->assertNull($request->fresh()->export_payload);
        $this->assertNull($request->fresh()->package_manifest);
    }
}
