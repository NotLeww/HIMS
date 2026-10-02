<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\RecoveryAttemptOutcome;
use App\Enums\RecoveryFailureType;
use App\Enums\RecoveryRetryHandler;
use App\Enums\RecoveryStatus;
use App\Exceptions\RecoveryNotRetryableException;
use App\Exceptions\SafeOperationException;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\SystemRecoveryAttempt;
use App\Models\SystemRecoveryRecord;
use App\Models\User;
use App\Services\Import\ImportStagingService;
use App\Services\Recovery\SafeExecutionService;
use App\Services\Recovery\SmartRetryService;
use App\Support\AuthenticationContext;
use Illuminate\Contracts\Queue\Job as QueueJobContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers the recovery workflow end to end against the real producers,
 * handlers, and reconciliation listeners.
 */
class ErrorRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /** The failure text an incident is created with, kept verbatim for assertions. */
    private const ORIGINAL_FAILURE = 'SQLSTATE[23000]: Integrity constraint violation: the batch could not be committed';

    private function superAdmin(): User
    {
        return User::factory()->superAdministrator()->create([
            'name' => 'Super Admin Officer',
            'employee_id' => 'EMP-0001',
        ]);
    }

    private function inventoryManager(): User
    {
        return User::factory()->inventoryManager()->create([
            'name' => 'Pharmacy Custodian',
            'employee_id' => 'EMP-0003',
        ]);
    }

    /**
     * A validated inventory record in the shape the import validator emits.
     *
     * @return array<string, mixed>
     */
    private function itemRecord(string $sku, string $name): array
    {
        return [
            'sku' => $sku,
            'name' => $name,
            'category_id' => null,
            'default_location_id' => null,
            'unit' => 'box',
            'unit_cost' => 450.00,
            'reorder_level' => 5,
            'safety_stock' => 0,
            'expiry_alert_days' => 30,
            'status' => 'active',
            '_mode' => 'create',
            '_existing_id' => null,
        ];
    }

    private function stageItems(string $sku, string $name, int $userId): string
    {
        return app(ImportStagingService::class)->stage(
            target: 'items',
            mode: 'create_only',
            records: [$this->itemRecord($sku, $name)],
            userId: $userId
        );
    }

    /**
     * Record a failed import incident the way the running system does: through
     * SafeExecutionService, with the still-live staging token attached so the
     * recovery service can genuinely replay it.
     */
    private function failedImportIncident(string $token, ?string $message = null): SystemRecoveryRecord
    {
        return app(SafeExecutionService::class)->recordFailure(
            exception: new RuntimeException($message ?? self::ORIGINAL_FAILURE),
            module: 'Imports',
            operation: 'data_import',
            context: ['target' => 'items', 'staged_rows' => 1],
            isRetryable: true,
            retryHandler: RecoveryRetryHandler::Import,
            retryPayload: ['import_token' => $token, 'target' => 'items'],
            strategy: 'automatic_rollback',
            failureType: RecoveryFailureType::Import,
            affectedResource: 'inventory items',
            referenceId: $token,
        );
    }

    /**
     * A stand-in for the queue worker's job instance, which is what the
     * reconciliation listeners read the UUID, queue, and job name from.
     */
    private function queueJobMock(string $uuid, string $name = 'Illuminate\Notifications\SendQueuedNotifications'): QueueJobContract
    {
        $job = $this->createMock(QueueJobContract::class);
        $job->method('uuid')->willReturn($uuid);
        $job->method('getQueue')->willReturn('notifications');
        $job->method('resolveName')->willReturn($name);

        return $job;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function queueIncident(string $uuid, RecoveryStatus $status, array $attributes = []): SystemRecoveryRecord
    {
        return SystemRecoveryRecord::create([
            'error_id' => 'REC-QUE-'.strtoupper(Str::random(6)),
            'module' => 'Queue',
            'failure_type' => RecoveryFailureType::QueueJob,
            'operation' => 'queue_job',
            'error_summary' => 'Connection could not be established with host smtp.hospital.local:587',
            'status' => $status,
            'strategy_applied' => 'queue_worker_failure',
            'is_retryable' => true,
            'retry_handler' => RecoveryRetryHandler::QueueJob,
            'retry_payload' => ['failed_job_uuid' => $uuid],
            'reference_id' => $uuid,
            'retry_count' => 0,
            ...$attributes,
        ]);
    }

    // ---------------------------------------------------------------------
    // The full workflow: genuine failure to verified recovery
    // ---------------------------------------------------------------------

    public function test_a_genuinely_failed_import_is_recorded_reviewed_and_recovered(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);

        $token = $this->stageItems('REC-SKU-001', 'Recovered Surgical Scalpel', $superAdmin->id);
        $record = $this->failedImportIncident($token);

        // 1. The failure is recorded with real attribution and no side effects.
        $this->assertSame(RecoveryStatus::Failed, $record->status);
        $this->assertSame(RecoveryFailureType::Import, $record->failure_type);
        $this->assertSame(RecoveryRetryHandler::Import, $record->retry_handler);
        $this->assertSame('inventory items', $record->affected_resource);
        $this->assertSame($token, $record->reference_id);
        $this->assertSame($superAdmin->id, $record->user_id);
        $this->assertStringContainsString('Super Admin Officer', (string) $record->user_snapshot);
        $this->assertSame(0, $record->retry_count);
        $this->assertTrue($record->canRetry());

        // The rolled-back import left nothing behind.
        $this->assertSame(0, InventoryItem::count());

        // 2. The recovery service actually re-runs the import.
        $outcome = app(SmartRetryService::class)->retry($record, $superAdmin);
        $this->assertSame(RecoveryAttemptOutcome::Succeeded, $outcome->outcome);

        $record->refresh();
        $this->assertSame(RecoveryStatus::Recovered, $record->status);
        $this->assertSame(1, $record->retry_count);
        $this->assertSame(RecoveryAttemptOutcome::Succeeded, $record->last_attempt_outcome);
        $this->assertNull($record->last_attempt_error);
        $this->assertNotNull($record->resolved_at);
        $this->assertNotNull($record->last_retried_at);

        $this->assertSame(1, InventoryItem::count());
        $this->assertTrue(InventoryItem::where('sku', 'REC-SKU-001')->exists());

        // The staged payload was consumed, so the same batch cannot be applied twice.
        $this->assertNull(app(ImportStagingService::class)->retrieve($token, $superAdmin->id));

        // 3. The original failure was preserved, not overwritten by the outcome.
        $this->assertSame(self::ORIGINAL_FAILURE, $record->error_summary);
        $this->assertSame(self::ORIGINAL_FAILURE, $record->technical_details['message']);

        // 4. Exactly one attempt, attributed to the operator who ran it.
        $attempt = SystemRecoveryAttempt::where('system_recovery_record_id', $record->id)->sole();
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame(RecoveryAttemptOutcome::Succeeded, $attempt->outcome);
        $this->assertSame(RecoveryRetryHandler::Import, $attempt->handler);
        $this->assertSame($superAdmin->id, $attempt->actor_user_id);

        // 5. Every step reached the Audit Trail.
        foreach ([AuditAction::SystemOperationFailed, AuditAction::TriggeredRecoveryAction, AuditAction::SystemOperationRecovered] as $action) {
            $this->assertTrue(
                AuditLog::where('action', $action)->where('target_name', $record->error_id)->exists(),
                "Expected an audit entry for [{$action->value}] on incident {$record->error_id}."
            );
        }
    }

    public function test_an_expired_staging_token_is_reported_without_replaying_anything(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);

        // A token that was never staged, standing in for an expired import session.
        $record = $this->failedImportIncident((string) Str::uuid());

        $outcome = app(SmartRetryService::class)->retry($record, $superAdmin);
        $this->assertSame(RecoveryAttemptOutcome::Skipped, $outcome->outcome);

        $record->refresh();
        $this->assertSame(RecoveryStatus::NotRecoverable, $record->status);
        $this->assertSame(1, $record->retry_count);
        $this->assertSame(RecoveryAttemptOutcome::Skipped, $record->last_attempt_outcome);
        $this->assertStringContainsString('expired or was already consumed', (string) $record->last_attempt_error);

        // Nothing was created from a payload that no longer exists.
        $this->assertSame(0, InventoryItem::count());
        $this->assertSame(self::ORIGINAL_FAILURE, $record->error_summary);

        $attempt = SystemRecoveryAttempt::where('system_recovery_record_id', $record->id)->sole();
        $this->assertSame(RecoveryAttemptOutcome::Skipped, $attempt->outcome);
    }

    public function test_a_failed_retry_records_the_attempt_without_touching_the_original_failure(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);

        // Two rows sharing one SKU: the unique index rejects the second insert and
        // the executor rolls the whole batch back, so the replay genuinely fails.
        $token = app(ImportStagingService::class)->stage(
            target: 'items',
            mode: 'create_only',
            records: [
                $this->itemRecord('REC-DUP-001', 'Duplicate SKU First Row'),
                $this->itemRecord('REC-DUP-001', 'Duplicate SKU Second Row'),
            ],
            userId: $superAdmin->id
        );

        $record = $this->failedImportIncident($token);

        $outcome = app(SmartRetryService::class)->retry($record, $superAdmin);
        $this->assertSame(RecoveryAttemptOutcome::Failed, $outcome->outcome);

        $record->refresh();
        $this->assertSame(RecoveryStatus::RecoveryFailed, $record->status);
        $this->assertSame(RecoveryAttemptOutcome::Failed, $record->last_attempt_outcome);
        $this->assertNotNull($record->last_attempt_error);

        // The whole batch rolled back: a partial apply is impossible.
        $this->assertSame(0, InventoryItem::count());

        // Original failure untouched; the attempt's own error is separate from it.
        $this->assertSame(self::ORIGINAL_FAILURE, $record->error_summary);
        $this->assertNotSame($record->error_summary, $record->last_attempt_error);

        // The incident remains retryable after a failed attempt.
        $this->assertTrue($record->canRetry());
    }

    public function test_repeated_attempts_are_appended_then_refused_once_the_budget_is_spent(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);

        $token = app(ImportStagingService::class)->stage(
            target: 'items',
            mode: 'create_only',
            records: [
                $this->itemRecord('REC-DUP-002', 'Duplicate SKU First Row'),
                $this->itemRecord('REC-DUP-002', 'Duplicate SKU Second Row'),
            ],
            userId: $superAdmin->id
        );

        $record = $this->failedImportIncident($token);

        foreach ([1, 2, 3] as $attemptNumber) {
            app(SmartRetryService::class)->retry($record, $superAdmin);

            $record->refresh();
            $this->assertSame(RecoveryStatus::RecoveryFailed, $record->status);
            $this->assertSame($attemptNumber, $record->retry_count);
        }

        $attempts = SystemRecoveryAttempt::where('system_recovery_record_id', $record->id)
            ->orderBy('attempt_number')
            ->get();

        $this->assertSame([1, 2, 3], $attempts->pluck('attempt_number')->all());
        $this->assertTrue($attempts->every(
            fn (SystemRecoveryAttempt $attempt): bool => $attempt->outcome === RecoveryAttemptOutcome::Failed
        ));

        // Nothing was written by any of the three attempts.
        $this->assertSame(0, InventoryItem::count());

        // The budget is spent, so another retry is refused.
        $record->refresh();
        $this->assertFalse($record->canRetry());
        $this->assertSame(
            'The retry budget of '.SystemRecoveryRecord::MAX_RECOVERY_ATTEMPTS.' attempts is exhausted.',
            $record->retryBlockedReason()
        );

        try {
            app(SmartRetryService::class)->retry($record, $superAdmin);
            $this->fail('An incident with an exhausted retry budget should be refused.');
        } catch (RecoveryNotRetryableException $e) {
            $this->assertStringContainsString('attempts is exhausted', $e->getMessage());
        }

        $record->refresh();
        $this->assertSame(3, $record->retry_count);
        $this->assertSame(3, SystemRecoveryAttempt::where('system_recovery_record_id', $record->id)->count());
    }

    public function test_a_recovered_incident_refuses_a_repeated_recovery_and_keeps_one_attempt(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);

        $token = $this->stageItems('REC-SKU-010', 'Recovered Gauze Pad', $superAdmin->id);
        $record = $this->failedImportIncident($token);

        app(SmartRetryService::class)->retry($record, $superAdmin);
        $this->assertSame(1, InventoryItem::count());

        // A repeated submission must not apply the same batch a second time.
        try {
            app(SmartRetryService::class)->retry($record, $superAdmin);
            $this->fail('A recovered incident should refuse another retry.');
        } catch (RecoveryNotRetryableException $e) {
            $this->assertStringContainsString('already closed', $e->getMessage());
        }

        $record->refresh();
        $this->assertSame(RecoveryStatus::Recovered, $record->status);
        $this->assertSame(1, $record->retry_count);
        $this->assertSame(1, InventoryItem::count());
        $this->assertSame(1, SystemRecoveryAttempt::where('system_recovery_record_id', $record->id)->count());
    }

    public function test_an_incident_awaiting_a_worker_cannot_be_retried_again(): void
    {
        $superAdmin = $this->superAdmin();
        $uuid = (string) Str::uuid();

        $record = $this->queueIncident($uuid, RecoveryStatus::RecoveryPending, ['retry_count' => 1]);

        $this->assertFalse($record->canRetry());
        $this->assertSame('A recovery attempt is already in progress.', $record->retryBlockedReason());

        try {
            app(SmartRetryService::class)->retry($record, $superAdmin);
            $this->fail('An in-flight incident should refuse another retry.');
        } catch (RecoveryNotRetryableException $e) {
            $this->assertSame('A recovery attempt is already in progress.', $e->getMessage());
        }

        $record->refresh();
        $this->assertSame(RecoveryStatus::RecoveryPending, $record->status);
        $this->assertSame(1, $record->retry_count);
        $this->assertSame(0, SystemRecoveryAttempt::count());
    }

    public function test_an_interrupted_retry_can_resume_with_the_next_ledger_number(): void
    {
        $superAdmin = $this->superAdmin();
        $record = $this->queueIncident((string) Str::uuid(), RecoveryStatus::Retrying, [
            'retry_count' => 1,
            'last_retried_at' => now()->subMinutes(10),
        ]);

        SystemRecoveryAttempt::create([
            'system_recovery_record_id' => $record->getKey(),
            'attempt_number' => 1,
            'outcome' => RecoveryAttemptOutcome::Skipped,
            'handler' => RecoveryRetryHandler::QueueJob,
            'message' => 'Earlier attempt did not execute.',
        ]);

        $outcome = app(SmartRetryService::class)->retry($record, $superAdmin);
        $this->assertSame(RecoveryAttemptOutcome::Skipped, $outcome->outcome);

        $record->refresh();
        $this->assertSame(2, $record->retry_count);
        $this->assertSame(RecoveryStatus::NotRecoverable, $record->status);
        $this->assertSame([1, 2], $record->attempts()->pluck('attempt_number')->all());
    }

    /**
     * Two operators can submit a retry for the same incident at the same moment.
     * The claim re-reads the row under a lock, so a request built from a stale
     * copy is refused rather than running the operation a second time.
     */
    public function test_a_retry_request_built_from_a_stale_record_is_refused_by_the_locked_re_read(): void
    {
        $superAdmin = $this->superAdmin();
        $record = $this->queueIncident((string) Str::uuid(), RecoveryStatus::Failed);

        // The second request loaded the incident while it still looked retryable.
        $staleCopy = SystemRecoveryRecord::findOrFail($record->getKey());
        $this->assertTrue($staleCopy->canRetry());

        // Meanwhile the first request dispatched its retry and a worker took it.
        $record->forceFill([
            'status' => RecoveryStatus::RecoveryPending,
            'retry_count' => 1,
            'last_retried_at' => now(),
        ])->save();

        try {
            app(SmartRetryService::class)->retry($staleCopy, $superAdmin);
            $this->fail('A retry built from a stale record should have been refused.');
        } catch (RecoveryNotRetryableException $e) {
            $this->assertSame('A recovery attempt is already in progress.', $e->getMessage());
        }

        // The claim was refused before touching state, so nothing was double-counted.
        $record->refresh();
        $this->assertSame(RecoveryStatus::RecoveryPending, $record->status);
        $this->assertSame(1, $record->retry_count);
        $this->assertSame(0, SystemRecoveryAttempt::count());
    }

    public function test_an_incident_without_a_retry_handler_offers_no_retry_and_refuses_one(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);

        $record = app(SafeExecutionService::class)->recordFailure(
            exception: new RuntimeException('The export stream exceeded the memory limit.'),
            module: 'Reports',
            operation: 'report_export',
            isRetryable: false,
            strategy: 'automatic_rollback',
            failureType: RecoveryFailureType::Export,
            affectedResource: 'PDEA Form 8 dangerous drugs register',
        );

        $this->assertSame(RecoveryStatus::NotRecoverable, $record->status);
        $this->assertFalse($record->is_retryable);
        $this->assertNull($record->retry_handler);
        $this->assertFalse($record->canRetry());

        try {
            app(SmartRetryService::class)->retry($record, $superAdmin);
            $this->fail('An incident without a handler should refuse retry.');
        } catch (RecoveryNotRetryableException $e) {
            $this->assertStringContainsString('no safe automated retry', $e->getMessage());
        }

        $record->refresh();
        $this->assertSame(RecoveryStatus::NotRecoverable, $record->status);
        $this->assertSame(0, $record->retry_count);
        $this->assertSame(0, SystemRecoveryAttempt::count());
    }

    // ---------------------------------------------------------------------
    // Queue worker reconciliation
    // ---------------------------------------------------------------------

    public function test_a_failed_queue_job_is_recorded_as_a_retryable_incident(): void
    {
        $this->superAdmin();
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'notifications',
            'payload' => json_encode(['displayName' => 'Illuminate\\Notifications\\SendQueuedNotifications']),
            'exception' => 'TransportException: Connection could not be established with host smtp.hospital.local:587',
            'failed_at' => now(),
        ]);

        event(new JobFailed(
            'database',
            $this->queueJobMock($uuid),
            new RuntimeException('Connection could not be established with host smtp.hospital.local:587')
        ));

        $record = SystemRecoveryRecord::where('reference_id', $uuid)->sole();

        $this->assertSame(RecoveryStatus::Failed, $record->status);
        $this->assertSame(RecoveryFailureType::QueueJob, $record->failure_type);
        $this->assertSame(RecoveryRetryHandler::QueueJob, $record->retry_handler);
        $this->assertTrue($record->is_retryable);
        $this->assertTrue($record->canRetry());
        $this->assertSame($uuid, $record->retry_payload['failed_job_uuid']);
        $this->assertSame('SendQueuedNotifications', $record->affected_resource);

        // A worker-driven failure has no operator behind it, and says so.
        $this->assertNull($record->user_id);
        $this->assertSame('System / Automated', $record->user_snapshot);
        $this->assertSame('notifications', $record->technical_details['context']['queue']);
    }

    public function test_a_redispatched_queue_job_that_succeeds_marks_the_incident_recovered(): void
    {
        $uuid = (string) Str::uuid();
        $record = $this->queueIncident($uuid, RecoveryStatus::RecoveryPending, ['retry_count' => 1]);

        event(new JobProcessed('database', $this->queueJobMock($uuid)));

        $record->refresh();
        $this->assertSame(RecoveryStatus::Recovered, $record->status);
        $this->assertNotNull($record->resolved_at);
        $this->assertNull($record->last_attempt_error);
        $this->assertStringContainsString('completed successfully', (string) $record->resolution_notes);

        // The worker's verdict, not the dispatch, is what closed the incident —
        // so the recorded last outcome is the verdict, not the dispatch.
        $this->assertSame(RecoveryAttemptOutcome::Succeeded, $record->last_attempt_outcome);
        $this->assertNull($record->resolved_by_user_id);
        $this->assertTrue(
            AuditLog::where('action', AuditAction::SystemOperationRecovered)
                ->where('target_name', $record->error_id)
                ->exists()
        );
    }

    public function test_a_redispatched_queue_job_that_fails_again_records_a_failed_attempt(): void
    {
        $uuid = (string) Str::uuid();
        $record = $this->queueIncident($uuid, RecoveryStatus::RecoveryPending, ['retry_count' => 1]);

        event(new JobFailed(
            'database',
            $this->queueJobMock($uuid),
            new RuntimeException('Connection could not be established with host smtp.hospital.local:587')
        ));

        $record->refresh();
        $this->assertSame(RecoveryStatus::RecoveryFailed, $record->status);
        $this->assertNotNull($record->last_attempt_error);
        $this->assertSame(RecoveryAttemptOutcome::Failed, $record->last_attempt_outcome);

        $audit = AuditLog::where('action', AuditAction::TriggeredRecoveryAction)
            ->where('target_name', $record->error_id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('failure', $audit->outcome);

        // A second failure of the same job does not open a duplicate incident.
        $this->assertSame(1, SystemRecoveryRecord::where('reference_id', $uuid)->count());
    }

    public function test_retrying_a_queue_incident_whose_job_is_gone_is_reported_as_not_executed(): void
    {
        $superAdmin = $this->superAdmin();
        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD);

        // The failed job row was already consumed, so there is nothing to re-dispatch.
        $record = $this->queueIncident((string) Str::uuid(), RecoveryStatus::Failed);

        $outcome = app(SmartRetryService::class)->retry($record, $superAdmin);
        $this->assertSame(RecoveryAttemptOutcome::Skipped, $outcome->outcome);

        $record->refresh();
        $this->assertSame(RecoveryStatus::NotRecoverable, $record->status);
        $this->assertSame(1, $record->retry_count);
        $this->assertSame(RecoveryAttemptOutcome::Skipped, $record->last_attempt_outcome);
        $this->assertStringContainsString('no longer in the failed jobs table', (string) $record->last_attempt_error);
    }

    // ---------------------------------------------------------------------
    // User-facing recovery modules
    // ---------------------------------------------------------------------
    public function test_recovery_ui_routes_and_dashboard_card_are_absent(): void
    {
        foreach ([
            'super-admin.recovery.index',
            'super-admin.recovery.health',
            'super-admin.recovery.rebuild-cache',
            'super-admin.recovery.show',
            'super-admin.recovery.retry',
            'super-admin.recovery.resolve',
        ] as $routeName) {
            $this->assertFalse(Route::has($routeName));
        }

        $superAdmin = $this->superAdmin();
        $this->queueIncident((string) Str::uuid(), RecoveryStatus::Failed);

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('super-admin.dashboard'))
            ->assertOk()
            ->assertDontSee('data-recovery-incident-card', false)
            ->assertDontSee('System incidents')
            ->assertDontSee('Recovery Center')
            ->assertDontSee('Health Telemetry');
    }

    // ---------------------------------------------------------------------
    // Data integrity
    // ---------------------------------------------------------------------

    /**
     * Deleting a user must not delete the incidents they caused or close, and must
     * not erase who was responsible: the snapshot is what preserves attribution.
     */
    public function test_an_incident_survives_the_deletion_of_the_operator_who_raised_it(): void
    {
        $operator = $this->inventoryManager();
        $this->actingAs($operator, AuthenticationContext::WEB_GUARD);

        $record = app(SafeExecutionService::class)->recordFailure(
            exception: new RuntimeException('The batch could not be committed.'),
            module: 'Imports',
            operation: 'data_import',
            isRetryable: false,
            failureType: RecoveryFailureType::Import,
            affectedResource: 'inventory items',
        );

        $this->assertSame($operator->id, $record->user_id);
        $this->assertStringContainsString('Pharmacy Custodian', (string) $record->user_snapshot);

        $record->forceFill([
            'status' => RecoveryStatus::Resolved,
            'resolved_by_user_id' => $operator->id,
            'resolved_at' => now(),
            'resolution_notes' => 'Confirmed with the pharmacy supervisor.',
        ])->save();

        $attempt = SystemRecoveryAttempt::create([
            'system_recovery_record_id' => $record->getKey(),
            'attempt_number' => 1,
            'outcome' => RecoveryAttemptOutcome::Skipped,
            'message' => 'No safe replay path for this batch.',
            'actor_user_id' => $operator->id,
            'actor_snapshot' => 'Pharmacy Custodian (EMP-0003, Inventory Manager)',
        ]);

        $operator->forceDelete();

        // The incident, its history, and the attribution all outlive the account.
        $record->refresh();
        $this->assertNull($record->user_id);
        $this->assertNull($record->resolved_by_user_id);
        $this->assertStringContainsString('Pharmacy Custodian', (string) $record->user_snapshot);
        $this->assertSame('Confirmed with the pharmacy supervisor.', $record->resolution_notes);
        $this->assertSame(RecoveryStatus::Resolved, $record->status);

        $attempt->refresh();
        $this->assertNull($attempt->actor_user_id);
        $this->assertSame('Pharmacy Custodian (EMP-0003, Inventory Manager)', $attempt->actor_snapshot);

        // Deleting an incident is what removes its attempt ledger, and only then.
        $this->assertSame(1, SystemRecoveryAttempt::count());
        $record->delete();
        $this->assertSame(0, SystemRecoveryAttempt::count());
    }

    public function test_incident_reference_ids_are_unique(): void
    {
        $ids = collect(range(1, 25))->map(fn (): string => app(SafeExecutionService::class)->recordFailure(
            exception: new RuntimeException('Repeated failure of the same shape.'),
            module: 'Imports',
            operation: 'data_import',
        )->error_id);

        $this->assertSame(25, $ids->unique()->count());
        $this->assertSame(25, SystemRecoveryRecord::count());
    }

    // ---------------------------------------------------------------------
    // Safe failure surfaces
    // ---------------------------------------------------------------------

    public function test_safe_execution_service_rolls_back_database_writes_on_exception(): void
    {
        $service = app(SafeExecutionService::class);

        $this->assertSame(0, InventoryItem::count());

        try {
            $service->executeTransaction(
                module: 'Inventory',
                operation: 'stock_movement',
                callback: function (): void {
                    InventoryItem::create([
                        'sku' => 'TEST-SKU-999',
                        'name' => 'Failing Item',
                        'unit' => 'piece',
                        'unit_cost' => 150.00,
                        'reorder_level' => 10,
                    ]);

                    throw new RuntimeException('Intentional simulated database transaction failure.');
                },
                context: ['test_context' => 'active']
            );

            $this->fail('SafeExecutionService should have rethrown a SafeOperationException.');
        } catch (SafeOperationException $e) {
            $this->assertStringContainsString('inventory movement could not be recorded', $e->getMessage());
            $this->assertStringStartsWith('REC-', $e->errorId);
        }

        // Assert 100% rollback occurred: 0 items persisted
        $this->assertSame(0, InventoryItem::count());

        $record = SystemRecoveryRecord::sole();
        $this->assertSame('Inventory', $record->module);
        $this->assertSame('stock_movement', $record->operation);
        $this->assertSame('automatic_rollback', $record->strategy_applied);
        $this->assertStringContainsString('Intentional simulated', $record->error_summary);

        // No handler was offered, so the incident is explicitly not recoverable
        // rather than presenting a retry that cannot run.
        $this->assertSame(RecoveryStatus::NotRecoverable, $record->status);
        $this->assertFalse($record->is_retryable);
        $this->assertFalse($record->canRetry());

        $auditLog = AuditLog::where('action', AuditAction::SystemOperationFailed)->first();
        $this->assertNotNull($auditLog);
        $this->assertSame('failure', $auditLog->outcome);
        $this->assertSame('System Recovery', $auditLog->module);
    }

    public function test_execute_with_fallback_runs_fallback_and_records_safe_default(): void
    {
        $result = app(SafeExecutionService::class)->executeWithFallback(
            module: 'Telemetry',
            operation: 'iot_reading',
            primary: function (): void {
                throw new RuntimeException('IoT sensor connection lost.');
            },
            fallback: fn (): array => ['temperature' => 4.0, 'is_fallback' => true],
            context: ['sensor_id' => 'VAULT-COLD-01']
        );

        $this->assertSame(['temperature' => 4.0, 'is_fallback' => true], $result);

        $record = SystemRecoveryRecord::where('module', 'Telemetry')->sole();
        $this->assertSame('safe_default', $record->strategy_applied);
        $this->assertSame(RecoveryStatus::NotRecoverable, $record->status);
        $this->assertStringContainsString('IoT sensor connection lost', $record->error_summary);
    }

    public function test_recorded_failures_never_store_credentials(): void
    {
        $record = app(SafeExecutionService::class)->recordFailure(
            exception: new RuntimeException('Connection failed for mysql://hims_app:s3cr3t-pa55@10.0.0.5:3306/hims while importing.'),
            module: 'Imports',
            operation: 'data_import',
            context: ['api_key' => 'sk-live-abcdef123456', 'target' => 'items'],
            isRetryable: true,
            retryHandler: RecoveryRetryHandler::Import,
            retryPayload: ['import_token' => 'e0d1c5b8-3a72-4f19-9b64-7d2e8a1c4f30', 'target' => 'items'],
        );

        $this->assertStringNotContainsString('s3cr3t-pa55', $record->error_summary);
        $this->assertStringNotContainsString('s3cr3t-pa55', $record->technical_details['message']);
        $this->assertSame('[REDACTED]', $record->technical_details['context']['api_key']);

        // The replay handle is an operational reference, not a credential, so it
        // survives redaction — without it the incident could never be replayed.
        $this->assertSame(
            'e0d1c5b8-3a72-4f19-9b64-7d2e8a1c4f30',
            $record->retry_payload['import_token']
        );
    }

    public function test_safe_operation_exception_renders_safe_500_view_with_reference_id(): void
    {
        Route::get('/_test/error-recovery/safe-crash', function () {
            throw new SafeOperationException(
                message: 'An unexpected transaction error occurred.',
                errorId: 'REC-TEST-SAFE-500',
                module: 'Inventory',
                operation: 'data_import'
            );
        });

        $response = $this->get('/_test/error-recovery/safe-crash');

        $response->assertStatus(500);
        $response->assertSee('System Error Intercepted');
        $response->assertSee('REC-TEST-SAFE-500');
        $response->assertDontSee('SQLSTATE');
        $response->assertDontSee('PDOException');
        $response->assertDontSee('Recovery Center');
        $response->assertDontSee('/super-admin/recovery', false);
    }

    public function test_safe_operation_json_request_returns_safe_json_payload(): void
    {
        Route::get('/_test/error-recovery/safe-json-crash', function () {
            throw new SafeOperationException(
                message: 'Database write error safely intercepted.',
                errorId: 'REC-JSON-001',
                module: 'Inventory',
                operation: 'stock_adjustment'
            );
        });

        $response = $this->getJson('/_test/error-recovery/safe-json-crash');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'error_id' => 'REC-JSON-001',
            'module' => 'Inventory',
            'message' => 'Database write error safely intercepted.',
        ]);
    }
}
