<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_supplier(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/inventory/suppliers', [
            'name' => 'Acme Medical Supplies',
            'contact_person' => 'Juan Cruz',
            'email' => 'juan@acme.com',
            'phone' => '09171234567',
            'address' => 'Makati City',
            'tax_number' => '123-456-789',
            'status' => 'active',
            'notes' => 'Priority vendor',
        ]);

        $response->assertRedirect('/inventory/suppliers');
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Acme Medical Supplies',
            'email' => 'juan@acme.com',
        ]);
    }
}
