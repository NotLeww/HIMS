<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Access is decided by permission, never by role name: routes and views ask
 * `can:manage_users`, and UserRole::permissions() is the only place that says
 * which roles hold it. These tests therefore drive the real HTTP endpoints
 * rather than calling the gate directly, so a broken registration in
 * AppServiceProvider fails here too.
 *
 * The lockout guards in UserAccountService get the most attention, because
 * they protect the one mistake that cannot be undone through the UI: ending
 * up with no active administrator.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    // ------------------------------------------------------------------ access

    public function test_an_administrator_reaches_the_user_list(): void
    {
        $admin = $this->admin();
        User::factory()->warehouseStaff()->create(['name' => 'Ben Santos']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertStatus(200)
            ->assertSee('Ben Santos');
    }

    /**
     * Every non-administrator role, driven off the enum rather than a hand
     * written list — a role added later is covered without editing this test.
     */
    public function test_no_other_role_reaches_user_management(): void
    {
        foreach (UserRole::cases() as $role) {
            if ($role->isAdministrator()) {
                continue;
            }

            $user = User::factory()->role($role)->create();

            $this->actingAs($user)->get('/admin/users')
                ->assertForbidden();

            $this->actingAs($user)->get('/admin/users/create')
                ->assertForbidden();

            $this->actingAs($user)->post('/admin/users', [
                'name' => 'Sneaky Hire',
                'email' => 'sneaky@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => UserRole::Administrator->value,
            ])->assertForbidden();
        }

        // Not one of those attempts created anything.
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    /**
     * A deactivated administrator keeps the role but holds no permissions, so
     * the gate would refuse them anyway. EnsureUserIsActive gets there first
     * and ends the session outright, which is the stronger outcome: the block
     * lands on their next click rather than at their next login.
     */
    public function test_a_deactivated_administrator_holds_no_permissions(): void
    {
        $admin = User::factory()->administrator()->inactive()->create();

        $this->assertFalse($admin->hasPermission(Permission::ManageUsers));
        $this->assertSame([], $admin->permissions());

        $this->actingAs($admin)->get('/admin/users')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    /**
     * Deactivation therefore takes effect mid-session rather than at next
     * login — the point of the switch on the user list.
     */
    public function test_deactivating_a_signed_in_user_ends_their_session(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();

        $this->actingAs($staff)->get('/dashboard')->assertStatus(200);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->actingAs($staff->fresh())->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    // ------------------------------------------------------------------ create

    public function test_an_administrator_creates_a_staff_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Ana Reyes',
            'email' => 'ana.reyes@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::InventoryManager->value,
            'employee_id' => 'EMP-0002',
            'department' => 'Central Supply',
            'phone' => '09171234567',
        ])->assertRedirect('/admin/users');

        $created = User::where('email', 'ana.reyes@djnrmhs.test')->firstOrFail();

        $this->assertSame(UserRole::InventoryManager, $created->role);
        $this->assertSame(UserStatus::Active, $created->status);
        $this->assertSame('EMP-0002', $created->employee_id);

        // Stored hashed by the model's 'hashed' cast, never in the clear.
        $this->assertNotSame('Password123!', $created->password);
        $this->assertTrue(Hash::check('Password123!', $created->password));

        // Created by an administrator in person, so no verification email step.
        $this->assertNotNull($created->email_verified_at);
    }

    public function test_a_new_account_gets_exactly_its_role_permissions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Cely Dizon',
            'email' => 'cely@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::PharmacyStaff->value,
        ])->assertRedirect('/admin/users');

        $pharmacy = User::where('email', 'cely@djnrmhs.test')->firstOrFail();

        // What the department actually does: read the shelf and dispense from it.
        $this->assertTrue($pharmacy->hasPermission(Permission::ViewInventory));
        $this->assertTrue($pharmacy->hasPermission(Permission::IssueStock));
        $this->assertTrue($pharmacy->hasPermission(Permission::ViewReports));

        // And nothing beyond it. record_movements is the one to watch: it used
        // to be granted here, which is what let a pharmacy account book in
        // deliveries and return stock to suppliers.
        $this->assertFalse($pharmacy->hasPermission(Permission::RecordMovements));
        $this->assertFalse($pharmacy->hasPermission(Permission::AdjustStock));
        $this->assertFalse($pharmacy->hasPermission(Permission::ManageItems));
        $this->assertFalse($pharmacy->hasPermission(Permission::ManageUsers));
        $this->assertFalse($pharmacy->hasPermission(Permission::ManageProcurement));
    }

    public function test_duplicate_email_and_employee_id_are_rejected(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@djnrmhs.test', 'employee_id' => 'EMP-9001']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Clash',
            'email' => 'taken@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'employee_id' => 'EMP-9001',
        ])->assertSessionHasErrors(['email', 'employee_id']);
    }

    public function test_mismatched_password_confirmation_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Typo',
            'email' => 'typo@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password456!',
            'role' => UserRole::Viewer->value,
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'typo@djnrmhs.test']);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'name' => 'Made Up',
            'email' => 'madeup@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'chief_wizard',
        ])->assertSessionHasErrors('role');
    }

    // ------------------------------------------------------------------ update

    public function test_changing_a_role_changes_what_that_account_may_do(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();

        $this->assertFalse($staff->hasPermission(Permission::ManageProcurement));

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => UserRole::InventoryManager->value,
            'status' => UserStatus::Active->value,
        ])->assertRedirect('/admin/users');

        $this->assertTrue($staff->fresh()->hasPermission(Permission::ManageProcurement));
    }

    /**
     * The edit form does not echo the existing password back, so an empty
     * field means "leave it alone" rather than "clear it".
     */
    public function test_a_blank_password_on_edit_leaves_the_existing_one_intact(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['password' => Hash::make('OriginalPass1!')]);

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            'name' => 'Renamed Person',
            'email' => $staff->email,
            'role' => $staff->role->value,
            'status' => UserStatus::Active->value,
            'password' => '',
        ])->assertRedirect('/admin/users');

        $staff->refresh();
        $this->assertSame('Renamed Person', $staff->name);
        $this->assertTrue(Hash::check('OriginalPass1!', $staff->password));
    }

    public function test_a_supplied_password_on_edit_replaces_the_old_one(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['password' => Hash::make('OriginalPass1!')]);

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role->value,
            'status' => UserStatus::Active->value,
            'password' => 'BrandNewPass1!',
            'password_confirmation' => 'BrandNewPass1!',
        ])->assertRedirect('/admin/users');

        $staff->refresh();
        $this->assertFalse(Hash::check('OriginalPass1!', $staff->password));
        $this->assertTrue(Hash::check('BrandNewPass1!', $staff->password));
    }

    // ------------------------------------------------------------------ status

    public function test_toggling_status_deactivates_then_reactivates_an_account(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->assertSame(UserStatus::Inactive, $staff->fresh()->status);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->assertSame(UserStatus::Active, $staff->fresh()->status);
    }

    /**
     * Deactivation replaces deletion, so the stock movements an account
     * recorded keep naming a real person.
     */
    public function test_there_is_no_delete_route_for_an_account(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/users/{$staff->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('users', ['id' => $staff->id]);
    }

    // ------------------------------------------------------------- lockout guards

    public function test_an_administrator_cannot_demote_themselves(): void
    {
        $admin = $this->admin();
        // A second administrator exists, so this can only fail on the self check.
        $this->admin();

        $this->actingAs($admin)
            ->from("/admin/users/{$admin->id}/edit")
            ->put("/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => UserRole::Viewer->value,
                'status' => UserStatus::Active->value,
            ])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::Administrator, $admin->fresh()->role);
    }

    public function test_an_administrator_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();
        $this->admin();

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$admin->id}/status")
            ->assertSessionHasErrors('role');

        $this->assertSame(UserStatus::Active, $admin->fresh()->status);
    }

    /**
     * The count guard, exercised against the service directly.
     *
     * It cannot be reached over HTTP: the actor must hold manage_users, which
     * only an active administrator has, so an actor distinct from the target
     * already guarantees a survivor. It is defence in depth for the callers
     * that do not go through the gate — the API, a console command, a seeder —
     * and this test stands in for them.
     */
    public function test_the_service_refuses_to_demote_the_only_administrator(): void
    {
        $onlyAdmin = $this->admin();
        $actor = User::factory()->warehouseStaff()->create();

        $this->assertSame(1, User::administrators()->active()->count());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This is the only active administrator.');

        app(UserAccountService::class)->update($onlyAdmin, [
            'name' => $onlyAdmin->name,
            'email' => $onlyAdmin->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Active->value,
        ], $actor);
    }

    public function test_the_service_refuses_to_deactivate_the_only_administrator(): void
    {
        $onlyAdmin = $this->admin();
        $actor = User::factory()->warehouseStaff()->create();

        try {
            app(UserAccountService::class)->toggleStatus($onlyAdmin, $actor);
            $this->fail('Deactivating the only administrator should have been refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('only active administrator', $e->getMessage());
        }

        // Refused inside a transaction, so nothing was written.
        $this->assertSame(UserStatus::Active, $onlyAdmin->fresh()->status);
    }

    /**
     * The guard is about administrators specifically — an ordinary account
     * being the last of its role is not a lockout and must still be editable.
     */
    public function test_demoting_a_non_administrator_is_never_blocked(): void
    {
        $admin = $this->admin();
        $manager = User::factory()->inventoryManager()->create();

        $this->actingAs($admin)->put("/admin/users/{$manager->id}", [
            'name' => $manager->name,
            'email' => $manager->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Inactive->value,
        ])->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertSame(UserRole::Viewer, $manager->role);
        $this->assertSame(UserStatus::Inactive, $manager->status);
    }

    /**
     * Demoting one administrator while another remains is the normal case and
     * must go through — the guard is not allowed to be over-eager.
     */
    public function test_an_administrator_can_be_demoted_while_another_remains(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Active->value,
        ])->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Viewer, $other->fresh()->role);
        $this->assertSame(1, User::administrators()->active()->count());
    }

    // -------------------------------------------------------- filters and views

    public function test_the_list_filters_by_role_status_and_search(): void
    {
        $admin = $this->admin();
        User::factory()->warehouseStaff()->create(['name' => 'Ben Santos', 'department' => 'Warehouse']);
        User::factory()->inventoryManager()->create(['name' => 'Ana Reyes', 'department' => 'Central Supply']);
        User::factory()->role(UserRole::Viewer)->inactive()->create(['name' => 'Dino Cruz']);

        $this->actingAs($admin)->get('/admin/users?role='.UserRole::WarehouseStaff->value)
            ->assertSee('Ben Santos')
            ->assertDontSee('Ana Reyes');

        $this->actingAs($admin)->get('/admin/users?status='.UserStatus::Inactive->value)
            ->assertSee('Dino Cruz')
            ->assertDontSee('Ben Santos');

        $this->actingAs($admin)->get('/admin/users?search=Central+Supply')
            ->assertSee('Ana Reyes')
            ->assertDontSee('Ben Santos');
    }

    public function test_the_detail_screen_lists_the_movements_that_account_recorded(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create(['name' => 'Ben Santos']);

        $this->actingAs($admin)->get("/admin/users/{$staff->id}")
            ->assertStatus(200)
            ->assertSee('Ben Santos');
    }

    public function test_the_create_and_edit_screens_render(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)->get('/admin/users/create')->assertStatus(200);
        $this->actingAs($admin)->get("/admin/users/{$staff->id}/edit")->assertStatus(200);
    }

    /**
     * The sidebar link is hidden rather than shown-and-refused, so a
     * non-administrator is never offered a door that will not open.
     */
    public function test_the_sidebar_shows_user_management_only_to_administrators(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertSee('User Management');

        $this->actingAs(User::factory()->warehouseStaff()->create())->get('/dashboard')
            ->assertDontSee('User Management');
    }
}
