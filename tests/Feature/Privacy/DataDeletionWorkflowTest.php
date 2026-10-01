<?php

namespace Tests\Feature\Privacy;

use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\UserStatus;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\LoginApprovalRequest;
use App\Models\PrivacyRequest;
use App\Models\StockMovement;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserActiveSession;
use App\Models\UserAvatar;
use App\Models\WarehouseTask;
use App\Services\Privacy\PrivacyRequestService;
use App\Services\UserAccountService;
use App\Support\AuthenticationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DataDeletionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletion_request_requires_authentication_password_and_explicit_confirmation(): void
    {
        $this->post(route('privacy.requests.store'), ['request_type' => 'erasure'])
            ->assertRedirect(route('login'));

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('privacy.requests.store'), [
            'request_type' => 'erasure',
            'current_password' => 'wrong-password',
        ])->assertSessionHasErrors(['current_password', 'confirm_deletion']);

        $this->assertDatabaseCount('privacy_requests', 0);
    }

    public function test_user_submits_database_backed_deletion_request_only_for_themselves(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->post(route('privacy.requests.store'), [
            'request_type' => 'erasure',
            'details' => 'I no longer need this account.',
            'current_password' => 'password',
            'confirm_deletion' => '1',
            'user_id' => $other->id,
        ])->assertSessionHas('status');

        $request = PrivacyRequest::sole();
        $this->assertSame($user->id, $request->user_id);
        $this->assertSame(PrivacyRequest::TYPE_ERASURE_REVIEW, $request->request_type);
        $this->assertSame(PrivacyRequest::STATUS_PENDING, $request->status);
        $this->assertNotNull($request->created_at);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SubmittedPrivacyRequest->value,
            'target_id' => (string) $request->id,
        ]);

        $this->actingAs($user)->post(route('privacy.requests.store'), [
            'request_type' => 'erasure',
            'current_password' => 'password',
            'confirm_deletion' => '1',
        ])->assertSessionHasErrors('request_type');

        $this->assertDatabaseCount('privacy_requests', 1);
    }

    public function test_owner_can_cancel_only_a_pending_deletion_request(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $request = $this->deletionRequest($owner);

        $this->actingAs($other)->post(route('privacy.requests.cancel', $request))->assertForbidden();
        $this->assertSame(PrivacyRequest::STATUS_PENDING, $request->fresh()->status);

        $this->actingAs($owner)->post(route('privacy.requests.cancel', $request))->assertSessionHas('status');
        $this->assertSame(PrivacyRequest::STATUS_CANCELLED, $request->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CancelledPrivacyRequest->value,
            'target_id' => (string) $request->id,
        ]);

        $reviewed = $this->deletionRequest($owner, PrivacyRequest::STATUS_UNDER_REVIEW);
        $this->actingAs($owner)->post(route('privacy.requests.cancel', $reviewed))->assertSessionHasErrors('status');
        $this->assertSame(PrivacyRequest::STATUS_UNDER_REVIEW, $reviewed->fresh()->status);
    }

    public function test_only_authorized_super_admin_can_view_and_process_requests(): void
    {
        $user = User::factory()->create();
        $request = $this->deletionRequest($user);
        $ordinaryAdmin = User::factory()->administrator()->create();
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->actingAs($ordinaryAdmin)->get(route('admin.privacy.index', ['tab' => 'dsr']))->assertForbidden();
        $this->actingAs($ordinaryAdmin)->post(route('admin.privacy.requests.approve', $request))->assertForbidden();

        $this->actingAs($superAdmin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->get(route('super-admin.privacy.index', ['tab' => 'dsr']))
            ->assertOk()
            ->assertSee($request->ticket_number)
            ->assertSee('Approve &amp; Process Deletion', false);
    }

    public function test_processing_anonymizes_personal_data_revokes_access_and_preserves_history(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->warehouseStaff()->create([
            'name' => 'Deletion Test User',
            'email' => 'delete-me@example.test',
            'phone' => '09123456789',
            'avatar_path' => 'avatars/delete-me.jpg',
            'mfa_enabled' => true,
        ]);
        UserAvatar::create([
            'user_id' => $user->id,
            'mime_type' => 'image/jpeg',
            'content' => 'image',
        ]);
        $unrelated = User::factory()->create(['email' => 'keep-me@example.test']);
        Storage::disk('public')->put('avatars/delete-me.jpg', 'image');

        $item = InventoryItem::create(['name' => 'Retained Item', 'sku' => 'DELETE-RET-1', 'unit' => 'box', 'status' => 'active']);
        $movement = StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 1,
            'user_id' => $user->id,
            'moved_at' => now(),
        ]);
        $task = WarehouseTask::create([
            'task_number' => 'WT-DELETE-1',
            'task_type' => 'pick',
            'status' => 'completed',
            'assigned_to_id' => $user->id,
            'created_by_id' => $user->id,
        ]);
        $historicalAudit = AuditLog::create([
            'event_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'actor_name' => $user->name,
            'actor_employee_id' => $user->employee_id,
            'actor_role' => $user->role->value,
            'action' => AuditAction::RecordedStockMovement,
            'description' => 'Historical movement activity.',
        ]);

        $trusted = TrustedDevice::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'trusted-token'),
            'display_name' => 'Personal Laptop',
            'first_trusted_at' => now(),
            'last_used_at' => now(),
            'expires_at' => now()->addMonth(),
        ]);
        UserActiveSession::create([
            'user_id' => $user->id,
            'session_id' => 'active-session',
            'guard' => 'web',
            'trusted_device_id' => $trusted->id,
        ]);
        DB::table('sessions')->insert([
            'id' => 'active-session',
            'user_id' => $user->id,
            'payload' => 'synthetic',
            'last_activity' => now()->timestamp,
        ]);
        $approval = LoginApprovalRequest::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'challenge_token_hash' => hash('sha256', 'challenge'),
            'status' => LoginApprovalRequest::STATUS_PENDING,
            'requested_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'email_otp_hash' => hash('sha256', 'otp'),
        ]);
        $user->tokens()->create(['name' => 'mobile', 'token' => hash('sha256', 'api-token'), 'abilities' => ['*']]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'hims',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $conversation = AiChatConversation::create(['user_id' => $user->id, 'title' => 'Personal chat']);
        AiChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Personal convenience data',
            'attachment_path' => 'chat-attachments/private.txt',
        ]);
        Storage::disk('local')->put('chat-attachments/private.txt', 'private');

        $request = $this->deletionRequest($user, PrivacyRequest::STATUS_UNDER_REVIEW);

        $this->actingAs($admin, AuthenticationContext::SUPER_ADMIN_GUARD)
            ->post(route('super-admin.privacy.requests.approve', $request), [
                'resolution_notes' => 'Approved after identity and retention review.',
            ])->assertSessionHas('status');

        $deleted = $user->fresh();
        $this->assertSame(UserStatus::Inactive, $deleted->status);
        $this->assertSame('Deleted User', $deleted->name);
        $this->assertSame("deleted-user-{$user->id}@invalid.local", $deleted->email);
        $this->assertNull($deleted->employee_id);
        $this->assertNull($deleted->department);
        $this->assertNull($deleted->phone);
        $this->assertNull($deleted->avatar_path);
        $this->assertDatabaseMissing('user_avatars', ['user_id' => $user->id]);
        $this->assertFalse($deleted->mfa_enabled);

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('user_active_sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('trusted_devices', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $user->id]);
        $this->assertDatabaseMissing('ai_chat_conversations', ['user_id' => $user->id]);
        $this->assertSame(LoginApprovalRequest::STATUS_CANCELLED, $approval->fresh()->status);
        $this->assertNull($approval->fresh()->email_otp_hash);
        Storage::disk('public')->assertMissing('avatars/delete-me.jpg');
        Storage::disk('local')->assertMissing('chat-attachments/private.txt');

        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('warehouse_tasks', ['id' => $task->id, 'assigned_to_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $historicalAudit->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CompletedDataDeletion->value,
            'target_id' => (string) $request->id,
        ]);
        $this->assertDatabaseHas('privacy_requests', [
            'id' => $request->id,
            'status' => PrivacyRequest::STATUS_COMPLETED,
        ]);
        $this->assertSame('Data deletion request processed.', $request->fresh()->details);
        $this->assertSame('keep-me@example.test', $unrelated->fresh()->email);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));

        $this->assertFalse(Auth::guard('web')->attempt([
            'email' => 'delete-me@example.test',
            'password' => 'password',
        ]));
    }

    public function test_completed_request_is_idempotent(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create();
        $request = $this->deletionRequest($user);

        $service = app(PrivacyRequestService::class);
        $service->processDeletion($request, $admin);
        $service->processDeletion($request->fresh(), $admin);

        $this->assertSame(PrivacyRequest::STATUS_COMPLETED, $request->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', AuditAction::CompletedDataDeletion)->where('target_id', (string) $request->id)->count());
    }

    public function test_processing_failure_rolls_back_and_does_not_mark_request_completed(): void
    {
        $admin = User::factory()->superAdministrator()->create();
        $user = User::factory()->create(['email' => 'rollback@example.test']);
        $request = $this->deletionRequest($user);

        $this->mock(UserAccountService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deactivate')->once()->andThrow(new RuntimeException('Synthetic processing failure.'));
        });

        try {
            app(PrivacyRequestService::class)->processDeletion($request, $admin);
            $this->fail('The synthetic processing failure should be raised.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic processing failure.', $exception->getMessage());
        }

        $this->assertSame(PrivacyRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertSame('rollback@example.test', $user->fresh()->email);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::CompletedDataDeletion->value,
            'target_id' => (string) $request->id,
        ]);
    }

    private function deletionRequest(User $user, string $status = PrivacyRequest::STATUS_PENDING): PrivacyRequest
    {
        return PrivacyRequest::create([
            'user_id' => $user->id,
            'request_type' => PrivacyRequest::TYPE_ERASURE_REVIEW,
            'details' => 'Request deletion of eligible personal information.',
            'status' => $status,
        ]);
    }
}
