<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_procurement_lifecycle_timestamp_cannot_be_overridden_through_the_api(): void
    {
        config(['auth.device_security.enabled' => false]);
        Sanctum::actingAs(User::factory()->role(UserRole::InventoryManager)->create(), ['*']);

        $this->postJson('/api/v1/procurement-requests', [
            'request_number' => 'REQ-TIMESTAMP-TAMPER',
            'title' => 'Timestamp tampering attempt',
            'requested_at' => now()->subYears(5)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('requested_at');

        $this->assertDatabaseMissing('procurement_requests', [
            'request_number' => 'REQ-TIMESTAMP-TAMPER',
        ]);

        $item = InventoryItem::create([
            'name' => 'Timestamp Test Item',
            'sku' => 'TIMESTAMP-ITEM',
            'unit' => 'piece',
            'status' => 'active',
        ]);
        $procurementRequest = ProcurementRequest::create([
            'request_number' => 'REQ-SYSTEM-TIMESTAMP',
            'title' => 'System timestamp',
            'item_id' => $item->id,
            'requested_at' => now(),
        ]);
        $originalTimestamp = $procurementRequest->requested_at->toDateTimeString();

        $this->patchJson("/api/v1/procurement-requests/{$procurementRequest->id}", [
            'requested_at' => now()->addYears(5)->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('requested_at');

        $this->assertSame($originalTimestamp, $procurementRequest->fresh()->requested_at->toDateTimeString());
    }

    public function test_inventory_item_rest_contract(): void
    {
        $this->getJson('/api/v1/inventory-items')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->role(UserRole::Viewer)->create(), ['*']);
        $this->getJson('/api/v1/inventory-items')->assertOk();
        $this->postJson('/api/v1/inventory-items', ['sku' => 'DENIED-001', 'name' => 'Denied'])
            ->assertForbidden();
        $this->assertDatabaseMissing('inventory_items', ['sku' => 'DENIED-001']);

        Sanctum::actingAs(User::factory()->role(UserRole::InventoryManager)->create(), ['*']);
        $this->postJson('/api/v1/inventory-items', [])->assertUnprocessable()->assertJsonValidationErrors(['sku', 'name']);

        $itemId = $this->postJson('/api/v1/inventory-items', ['sku' => 'API-001', 'name' => 'API item'])
            ->assertCreated()
            ->json('data.id');

        $this->patchJson("/api/v1/inventory-items/{$itemId}", ['name' => 'Updated API item'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated API item');
        $this->getJson('/api/v1/inventory-items/999999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Not Found');
        $this->getJson('/api/v1/inventory-items/not-a-number')
            ->assertNotFound()
            ->assertJsonPath('message', 'Not Found');
        $this->deleteJson("/api/v1/inventory-items/{$itemId}")->assertMethodNotAllowed();

        $this->assertSame('Updated API item', InventoryItem::findOrFail($itemId)->name);
    }

    public function test_malformed_numeric_action_id_is_not_an_internal_error(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::InventoryManager)->create(), ['*']);

        $this->postJson('/api/v1/inventory/adjustments/not-a-number/authorize')
            ->assertNotFound();
    }

    public function test_stateful_session_cannot_crash_the_token_logout_endpoint(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(400)
            ->assertJsonPath('message', 'A bearer token is required for this endpoint.');
    }

    public function test_token_device_name_is_validated_before_persistence(): void
    {
        config(['auth.device_security.enabled' => false]);

        $user = User::factory()->role(UserRole::InventoryManager)->create(['password' => bcrypt('password')]);

        $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => str_repeat('x', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors(['device_name']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_bearer_token_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->role(UserRole::InventoryManager)->create();
        $token = $user->createToken('integration-test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_paginated_api_limits_requested_page_size(): void
    {
        Sanctum::actingAs(User::factory()->role(UserRole::Viewer)->create(), ['*']);

        $this->getJson('/api/v1/inventory-items?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_unexpected_json_failure_is_redacted(): void
    {
        $logPath = storage_path('logs/laravel.log');
        $logOffset = is_file($logPath) ? filesize($logPath) : 0;

        Route::middleware('api')->get('/_test/api-integration/crash', function () {
            throw new RuntimeException('SQLSTATE failure in C:\\private\\hims with a synthetic credential');
        });

        $response = $this->getJson('/_test/api-integration/crash')
            ->assertInternalServerError()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'An unexpected system error occurred. Operations were safely aborted.')
            ->assertJsonStructure(['error_id']);

        $response->assertDontSee('SQLSTATE')
            ->assertDontSee('C:\\private\\hims')
            ->assertDontSee('synthetic credential');

        Log::error('Synthetic structured context probe.', [
            'password' => 'never-log-this-password',
            'nested' => ['api_token' => 'never-log-this-token'],
        ]);

        clearstatcache(true, $logPath);
        $newLogContent = is_file($logPath)
            ? (string) file_get_contents($logPath, false, null, $logOffset)
            : '';

        $this->assertStringContainsString('Unhandled exception.', $newLogContent);
        $this->assertStringContainsString(RuntimeException::class, $newLogContent);
        $this->assertStringNotContainsString('synthetic credential', $newLogContent);
        $this->assertStringNotContainsString('C:\\private\\hims', $newLogContent);
        $this->assertStringNotContainsString('never-log-this-password', $newLogContent);
        $this->assertStringNotContainsString('never-log-this-token', $newLogContent);
        $this->assertStringContainsString('[REDACTED]', $newLogContent);
    }
}
