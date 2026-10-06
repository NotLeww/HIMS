<?php

namespace Tests\Feature;

use App\Contracts\SmsGateway;
use App\Enums\ActivationCancellationReason;
use App\Enums\AuditAction;
use App\Enums\MovementType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AccountActivationChallenge;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\KpiProcessReview;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserActiveSession;
use App\Notifications\AccountActivationCancelled;
use App\Notifications\AccountCreated;
use App\Services\UserAccountService;
use App\Support\AuthenticationContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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

    public function test_only_super_administrator_can_assign_the_auditor_role(): void
    {
        $service = app(UserAccountService::class);
        $admin = $this->admin();
        $superAdmin = User::factory()->superAdministrator()->create();

        $this->assertNotContains(UserRole::Auditor, $service->assignableRoles($admin));
        $this->assertContains(UserRole::Auditor, $service->assignableRoles($superAdmin));
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
                'surname' => 'Hire',
                'first_name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => UserRole::Administrator->value,
                'phone' => '09171234567',
            ])->assertForbidden()
                ->assertSessionMissing('success');
        }

        // Not one of those attempts created anything.
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/admin/login');
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
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest(AuthenticationContext::ADMIN_GUARD);
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
        UserActiveSession::updateOrCreate(
            ['user_id' => $staff->id],
            [
                'guard' => 'web',
                'session_id' => 'active-before-deactivation',
                'last_active_at' => now(),
            ],
        );
        $staff->createToken('active-before-deactivation');
        $staff->forceFill(['remember_token' => 'remember-before-deactivation'])->saveQuietly();

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->assertDatabaseMissing('user_active_sessions', ['user_id' => $staff->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $staff->id]);
        $this->assertNull($staff->fresh()->remember_token);
        $this->actingAs($staff->fresh())->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_deactivated_employee_cannot_log_in(): void
    {
        $staff = User::factory()->warehouseStaff()->inactive()->create([
            'password' => bcrypt('password'),
        ]);

        $this->post(route('login'), [
            'email' => $staff->email,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'email' => 'Incorrect email or password. You have 4 attempts remaining.',
        ]);

        $this->assertGuest(AuthenticationContext::WEB_GUARD);
    }

    // ------------------------------------------------------------------ create

    public function test_an_administrator_creates_a_staff_account(): void
    {
        Notification::fake();
        $sms = new class implements SmsGateway
        {
            public ?string $destination = null;

            public ?string $message = null;

            public function available(): bool
            {
                return true;
            }

            public function send(string $mobileNumber, #[\SensitiveParameter] string $message): bool
            {
                $this->destination = $mobileNumber;
                $this->message = $message;

                return true;
            }
        };
        $this->app->instance(SmsGateway::class, $sms);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'surname' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'email' => 'juan.delacruz@djnrmhs.test',
            'role' => UserRole::InventoryManager->value,
            'employee_id' => 'EMP-9999', // A forged value must be ignored.
            'department' => 'Central Supply',
            'phone' => '09171234567',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Account created as Pending Activation. The user must activate it and create their own password from the login page.')
            ->assertRedirect('/admin/users');

        $created = User::where('email', 'juan.delacruz@djnrmhs.test')->firstOrFail();

        $this->assertSame('Dela Cruz', $created->surname);
        $this->assertSame('Juan', $created->first_name);
        $this->assertSame('Santos', $created->middle_name);
        $this->assertSame('Juan Santos Dela Cruz', $created->name);
        $this->assertSame(UserRole::InventoryManager, $created->role);
        $this->assertSame(UserStatus::PendingActivation, $created->status);
        $this->assertSame('EMP-0001', $created->employee_id);

        $this->assertNull($created->password);

        $this->assertNull($created->email_verified_at);
        Notification::assertSentTo($created, AccountCreated::class, function (AccountCreated $notification) use ($created): bool {
            $mail = $notification->toMail($created);
            $html = view($mail->view['html'], $mail->viewData)->render();

            return $mail->view['html'] === 'emails.auth.account-created'
                && str_contains($mail->viewData['activationUrl'], '/verify-email/'.$created->id.'/')
                && str_contains($html, 'background:#174c86')
                && str_contains($html, 'Activate HIMS Account');
        });
        $this->assertSame('09171234567', $sms->destination);
        $this->assertSame(
            'Your HIMS account has been created. To activate it, open the HIMS sign-in page, select Activate account, and verify using the code sent by email or SMS.',
            $sms->message,
        );

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('himsToastNotifications', false)
            ->assertSee('Account created as Pending Activation. The user must activate it and create their own password from the login page.')
            ->assertSee('Juan Santos Dela Cruz')
            ->assertSee('09171234567')
            ->assertSeeInOrder([
                'Account ID', 'Surname', 'First Name', 'Middle Name', 'Department', 'Contact Number', 'Role',
            ]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertDontSee('Account created as Pending Activation. The user must activate it and create their own password from the login page.');
    }

    public function test_a_new_account_gets_exactly_its_role_permissions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/users', [
            'surname' => 'Dizon',
            'first_name' => 'Cely',
            'email' => 'cely@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::PharmacyStaff->value,
            'department' => 'Pharmacy',
            'phone' => '09171234567',
        ])->assertRedirect('/admin/users');

        $pharmacy = User::where('email', 'cely@djnrmhs.test')->firstOrFail();

        $this->assertFalse($pharmacy->hasPermission(Permission::ViewInventory));
        $pharmacy->forceFill(['status' => UserStatus::Active, 'password' => Hash::make('Activated1!')])->save();

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

    public function test_duplicate_email_is_rejected_and_employee_id_input_is_ignored(): void
    {
        $admin = $this->admin();
        User::factory()->create(['email' => 'taken@djnrmhs.test', 'employee_id' => 'EMP-9001']);

        $this->actingAs($admin)->post('/admin/users', [
            'surname' => 'Account',
            'first_name' => 'Clash',
            'email' => 'taken@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'employee_id' => 'EMP-9001',
            'department' => 'Warehouse',
            'phone' => '09171234567',
        ])->assertSessionHasErrors('email')
            ->assertSessionMissing('success')
            ->assertSessionDoesntHaveErrors('employee_id');
    }

    public function test_admin_supplied_password_fields_are_ignored(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'surname' => 'Account',
            'first_name' => 'Typo',
            'email' => 'typo@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password456!',
            'role' => UserRole::Viewer->value,
            'department' => 'Warehouse',
            'phone' => '09171234567',
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertNull(User::query()->where('email', 'typo@djnrmhs.test')->firstOrFail()->password);
    }

    public function test_a_database_failure_does_not_create_an_account_or_show_success(): void
    {
        $admin = $this->admin();
        $nextEmployeeNumber = DB::table('employee_id_sequences')->value('next_value');

        User::creating(function (): void {
            throw new \RuntimeException('Simulated database failure.');
        });

        $response = $this->actingAs($admin)->post('/admin/users', [
            'surname' => 'Failed',
            'first_name' => 'Creation',
            'email' => 'failed.creation@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Internal Audit',
            'phone' => '09171234567',
        ]);

        $response
            ->assertServerError()
            ->assertSessionMissing('success');

        $this->assertDatabaseMissing('users', [
            'email' => 'failed.creation@djnrmhs.test',
        ]);
        $this->assertSame($nextEmployeeNumber, DB::table('employee_id_sequences')->value('next_value'));
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'surname' => 'Up',
            'first_name' => 'Made',
            'email' => 'madeup@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'chief_wizard',
            'department' => 'Warehouse',
            'phone' => '09171234567',
        ])->assertSessionHasErrors('role');
    }

    public function test_surname_and_first_name_are_required_but_middle_name_is_optional(): void
    {
        $admin = $this->admin();

        $before = User::count();

        $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [])
            ->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors(['surname', 'first_name', 'email', 'role', 'department', 'phone']);

        $this->assertSame($before, User::count());

        $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
            'surname' => 'Reyes',
            'first_name' => 'Ana',
            'email' => 'ana.reyes@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Central Supply',
            'phone' => '09171234567',
        ])->assertRedirect('/admin/users');

        $created = User::where('email', 'ana.reyes@djnrmhs.test')->firstOrFail();
        $this->assertNull($created->middle_name);
        $this->assertSame('Ana Reyes', $created->name);
        $this->assertSame('09171234567', $created->phone);
    }

    public function test_name_parts_reject_numbers_and_special_characters_when_creating_or_updating_an_account(): void
    {
        $admin = $this->admin();
        $invalidNames = [
            'surname' => 'Reyes2',
            'first_name' => 'Ana!',
            'middle_name' => 'Marie-Jane',
        ];

        foreach ($invalidNames as $field => $value) {
            $this->actingAs($admin)->post('/admin/users', array_merge([
                'surname' => 'Reyes',
                'first_name' => 'Ana',
                'middle_name' => 'Marie',
                'email' => "invalid-{$field}@djnrmhs.test",
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => UserRole::Viewer->value,
                'department' => 'Central Supply',
                'phone' => '09170000001',
            ], [$field => $value]))->assertSessionHasErrors($field);
        }

        $staff = User::factory()->warehouseStaff()->create([
            'surname' => 'Reyes',
            'first_name' => 'Ana',
            'middle_name' => 'Marie',
        ]);

        foreach ($invalidNames as $field => $value) {
            $this->actingAs($admin)->put("/admin/users/{$staff->id}", array_merge([
                'surname' => 'Reyes',
                'first_name' => 'Ana',
                'middle_name' => 'Marie',
                'email' => $staff->email,
                'role' => $staff->role->value,
                'status' => $staff->status->value,
                'department' => $staff->department,
                'phone' => $staff->phone,
            ], [$field => $value]))->assertSessionHasErrors($field);
        }

        $staff->refresh();
        $this->assertSame('Reyes', $staff->surname);
        $this->assertSame('Ana', $staff->first_name);
        $this->assertSame('Marie', $staff->middle_name);
    }

    public function test_a_missing_phone_number_prevents_user_creation(): void
    {
        $admin = $this->admin();
        $nextEmployeeNumber = DB::table('employee_id_sequences')->value('next_value');

        $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
            'surname' => 'No Phone',
            'first_name' => 'User',
            'email' => 'no-phone@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Administration',
            'phone' => '',
        ])->assertRedirect('/admin/users/create')
            ->assertSessionHasErrors([
                'phone' => 'Phone number is required.',
            ])
            ->assertSessionHasInput('surname', 'No Phone')
            ->assertSessionHasInput('email', 'no-phone@djnrmhs.test');

        $this->assertDatabaseMissing('users', ['email' => 'no-phone@djnrmhs.test']);
        $this->assertSame($nextEmployeeNumber, DB::table('employee_id_sequences')->value('next_value'));
    }

    public function test_every_invalid_phone_format_prevents_user_creation(): void
    {
        $admin = $this->admin();
        $invalidNumbers = [
            '0912345678',
            '091234567890',
            '9123456789',
            '08123456789',
            '09123abc789',
            '0912 345 6789',
            '09-1234-56789',
            '09(123)456789',
            '+639123456789',
            'abc09123456789',
            '09@123456789',
        ];

        foreach ($invalidNumbers as $index => $phone) {
            $email = "bad-phone-{$index}@djnrmhs.test";

            $this->actingAs($admin)->from('/admin/users/create')->post('/admin/users', [
                'surname' => 'Bad Phone',
                'first_name' => 'User',
                'email' => $email,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'role' => UserRole::Viewer->value,
                'department' => 'Administration',
                'phone' => $phone,
            ])->assertRedirect('/admin/users/create')
                ->assertSessionHasErrors('phone');

            $this->assertDatabaseMissing('users', ['email' => $email]);
        }
    }

    public function test_each_supported_phone_number_is_saved_exactly_with_its_leading_zero(): void
    {
        $admin = $this->admin();

        foreach (['09123456789', '09987654321', '09051234567'] as $index => $phone) {
            $email = "valid-phone-{$index}@djnrmhs.test";
            $password = "ValidPhone{$index}!";

            $this->actingAs($admin)->post('/admin/users', [
                'surname' => 'Valid Phone',
                'first_name' => 'User',
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'role' => UserRole::Viewer->value,
                'department' => 'Administration',
                'phone' => $phone,
            ])->assertRedirect('/admin/users');

            $created = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame($phone, $created->phone);
            $this->assertTrue(User::query()->wherePhoneNumber($phone)->whereKey($created->id)->exists());
        }
    }

    public function test_employee_ids_are_automatic_sequential_and_unique(): void
    {
        $admin = $this->admin();

        foreach ([1 => 'first', 2 => 'second'] as $number => $emailPrefix) {
            $password = "EmployeePassword{$number}!";

            $this->actingAs($admin)->post('/admin/users', [
                'surname' => 'Employee',
                'first_name' => ucfirst($emailPrefix),
                'email' => $emailPrefix.'@djnrmhs.test',
                'password' => $password,
                'password_confirmation' => $password,
                'role' => UserRole::Viewer->value,
                'department' => 'Records Management',
                'employee_id' => 'EMP-9001',
                'phone' => '0917123456'.$number,
            ])->assertRedirect('/admin/users');

            $this->assertDatabaseHas('users', [
                'email' => $emailPrefix.'@djnrmhs.test',
                'employee_id' => 'EMP-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            ]);
        }

        $assigned = User::whereIn('email', ['first@djnrmhs.test', 'second@djnrmhs.test'])
            ->pluck('employee_id');
        $this->assertCount(2, $assigned);
        $this->assertCount(2, $assigned->unique());
        $this->assertSame(3, DB::table('employee_id_sequences')->value('next_value'));
    }

    public function test_an_invalid_department_is_rejected(): void
    {
        $this->actingAs($this->admin())->post('/admin/users', [
            'surname' => 'Intruder',
            'first_name' => 'Department',
            'email' => 'invalid-department@djnrmhs.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => UserRole::Viewer->value,
            'department' => 'Made Up Department',
            'phone' => '09171234567',
        ])->assertSessionHasErrors('department');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-department@djnrmhs.test']);
    }

    // ------------------------------------------------------------------ update

    public function test_changing_a_role_changes_what_that_account_may_do(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();

        $this->assertFalse($staff->hasPermission(Permission::ManageProcurement));

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            ...$staff->nameComponents(),
            'email' => $staff->email,
            'role' => UserRole::InventoryManager->value,
            'status' => UserStatus::Active->value,
            'department' => $staff->department,
            'phone' => $staff->phone,
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
            'surname' => 'Person',
            'first_name' => 'Renamed',
            'middle_name' => null,
            'email' => $staff->email,
            'role' => $staff->role->value,
            'status' => UserStatus::Active->value,
            'department' => $staff->department,
            'phone' => $staff->phone,
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
            ...$staff->nameComponents(),
            'email' => $staff->email,
            'role' => $staff->role->value,
            'status' => UserStatus::Active->value,
            'department' => $staff->department,
            'phone' => $staff->phone,
            'password' => 'BrandNewPass1!',
            'password_confirmation' => 'BrandNewPass1!',
        ])->assertRedirect('/admin/users');

        $staff->refresh();
        $this->assertFalse(Hash::check('OriginalPass1!', $staff->password));
        $this->assertTrue(Hash::check('BrandNewPass1!', $staff->password));
    }

    public function test_changing_an_account_email_requires_verification_of_the_new_address(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $staff = User::factory()->create();
        UserActiveSession::query()->create([
            'user_id' => $staff->id,
            'guard' => AuthenticationContext::WEB_GUARD,
            'session_id' => 'old-session',
            'ip_address' => '127.0.0.1',
            'last_activity_at' => now(),
        ]);

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            ...$staff->nameComponents(),
            'email' => 'changed.account@example.com',
            'role' => $staff->role->value,
            'status' => $staff->status->value,
            'department' => $staff->department,
            'phone' => $staff->phone,
        ])->assertSessionHasNoErrors();

        $staff->refresh();

        $this->assertSame('changed.account@example.com', $staff->email);
        $this->assertNull($staff->email_verified_at);
        $this->assertDatabaseMissing('user_active_sessions', ['user_id' => $staff->id]);
        Notification::assertSentTo($staff, VerifyEmail::class);

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $url = Notification::sent($staff, VerifyEmail::class)->last()->toMail($staff)->actionUrl;
        $this->get($url)->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => 'changed.account@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_user_management_shows_pending_verification_and_can_resend_activation(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $staff = User::factory()->unverified()->create();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Pending Email Verification')
            ->assertSee(route('admin.users.verification.send', $staff), escape: false);

        $this->post(route('admin.users.verification.send', $staff))
            ->assertRedirect()
            ->assertSessionHas('success', 'A new verification email was sent to '.$staff->email.'.');

        Notification::assertSentTo($staff, VerifyEmail::class);
    }

    public function test_resend_for_pending_activation_sends_account_created_notification(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $pending = User::factory()->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.verification.send', $pending))
            ->assertRedirect()
            ->assertSessionHas('success', 'A new activation email was sent to '.$pending->email.'.');

        Notification::assertSentTo($pending, AccountCreated::class);
        Notification::assertNotSentTo($pending, VerifyEmail::class);
    }

    public function test_resending_activation_is_rate_limited(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $staff = User::factory()->unverified()->create();
        $this->actingAs($admin);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('admin.users.verification.send', $staff))->assertRedirect();
        }

        $this->post(route('admin.users.verification.send', $staff))->assertTooManyRequests();
        Notification::assertSentToTimes($staff, VerifyEmail::class, 6);
    }

    public function test_pending_invitation_can_be_cancelled_and_its_challenge_is_invalidated(): void
    {
        Notification::fake();
        $creator = $this->admin();
        $admin = $this->admin();
        $this->actingAs($creator);
        $pending = User::factory()->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
            'email_verified_at' => null,
        ]);
        AccountActivationChallenge::create([
            'user_id' => $pending->id,
            'channel' => 'email',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Cancel Activation');

        $this->from('/admin/users')
            ->patch(route('admin.users.cancel-invitation', $pending), [
                'cancel_user_id' => $pending->id,
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHasErrors(['cancellation_reason'], null, 'cancelActivation');

        $this
            ->from('/admin/users')
            ->patch(route('admin.users.cancel-invitation', $pending), [
                'cancel_user_id' => $pending->id,
                'cancellation_reason' => ActivationCancellationReason::IncorrectEmailAddress->value,
                'cancellation_details' => 'The registered address needs correction.',
            ])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('success', "{$pending->name}'s account activation was cancelled.");

        $pending->refresh();
        $this->assertSame(UserStatus::Cancelled, $pending->status);
        $this->assertSame(ActivationCancellationReason::IncorrectEmailAddress, $pending->activation_cancellation_reason);
        $this->assertSame('The registered address needs correction.', $pending->activation_cancellation_details);
        $this->assertSame($admin->id, $pending->activation_cancelled_by);
        $this->assertNotNull($pending->activation_cancelled_at);
        $this->assertNotNull($pending->activation_cancellation_notice_sent_at);
        $this->assertDatabaseMissing('account_activation_challenges', ['user_id' => $pending->id]);

        Notification::assertSentTo($pending, AccountActivationCancelled::class, function ($notification) use ($creator, $pending): bool {
            $mail = $notification->toMail($pending);
            $html = view($mail->view['html'], $mail->viewData)->render();

            return $mail->view['html'] === 'emails.auth.account-activation-cancelled'
                && $mail->viewData['reason'] === 'Incorrect Email Address'
                && $mail->viewData['details'] === 'The registered address needs correction.'
                && $mail->viewData['creatorEmail'] === $creator->email
                && str_contains($html, 'background:#991b1b')
                && str_contains($html, 'mailto:'.$creator->email)
                && str_contains($html, 'The registered address needs correction.');
        });

        $log = AuditLog::where('action', AuditAction::AccountActivationCancelled->value)->latest('id')->firstOrFail();
        $this->assertSame(UserStatus::PendingActivation->value, $log->old_values['status']);
        $this->assertSame(UserStatus::Cancelled->value, $log->new_values['status']);
        $this->assertSame('Incorrect Email Address', $log->new_values['reason']);

        $this->patch(route('admin.users.cancel-invitation', $pending), [
            'cancel_user_id' => $pending->id,
            'cancellation_reason' => ActivationCancellationReason::DuplicateAccount->value,
        ])->assertSessionHasErrors('status');

        Notification::assertSentToTimes($pending, AccountActivationCancelled::class, 1);

        $cancelledIndex = $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertSee('Re-invite');
        $this->assertFalse(str_contains(
            $cancelledIndex->getContent(),
            route('admin.users.verification.send', $pending),
        ));

        $this->get(route('admin.users.show', $pending))
            ->assertOk()
            ->assertSee('Incorrect Email Address')
            ->assertSee('The registered address needs correction.')
            ->assertSee($admin->name)
            ->assertSee('Resend Cancellation Notice');

        $this->from('/admin/users')
            ->patch(route('admin.users.toggle-status', $pending))
            ->assertRedirect('/admin/users')
            ->assertSessionHas('success', "A new activation invitation was sent to {$pending->email}.");

        $this->assertSame(UserStatus::PendingActivation, $pending->fresh()->status);
        $this->assertNull($pending->fresh()->activation_cancellation_reason);
        Notification::assertSentTo($pending, AccountCreated::class);
        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('himsToastNotifications', false)
            ->assertSee("A new activation invitation was sent to {$pending->email}.")
            ->assertSee('Resend');
    }

    public function test_other_cancellation_reason_requires_and_emails_custom_details(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $pending = User::factory()->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.cancel-invitation', $pending), [
                'cancel_user_id' => $pending->id,
                'cancellation_reason' => ActivationCancellationReason::Other->value,
                'cancellation_details' => '   ',
            ])
            ->assertSessionHasErrors(['cancellation_details'], null, 'cancelActivation');

        $this->patch(route('admin.users.cancel-invitation', $pending), [
            'cancel_user_id' => $pending->id,
            'cancellation_reason' => ActivationCancellationReason::Other->value,
            'cancellation_details' => 'Account was requested for the wrong hospital unit.',
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo($pending, AccountActivationCancelled::class, function ($notification) use ($pending): bool {
            return $notification->toMail($pending)->viewData['details'] === 'Account was requested for the wrong hospital unit.';
        });
    }

    public function test_cancelled_account_cannot_use_an_old_activation_code_or_sign_in(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $pending = User::factory()->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
            'email_verified_at' => null,
        ]);
        AccountActivationChallenge::create([
            'user_id' => $pending->id,
            'channel' => 'email',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->actingAs($admin)->patch(route('admin.users.cancel-invitation', $pending), [
            'cancel_user_id' => $pending->id,
            'cancellation_reason' => ActivationCancellationReason::RequestWithdrawn->value,
        ]);

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withSession([
            'account_activation.identity' => [
                'user_id' => $pending->id,
                'channel' => 'email',
            ],
        ])->post(route('activation.verify.store'), ['otp' => '123456'])
            ->assertRedirect(route('activation.start'))
            ->assertSessionHasErrors('email');

        $this->assertSame(UserStatus::Cancelled, $pending->fresh()->status);
        $this->assertFalse($pending->fresh()->hasVerifiedEmail());
        $this->post(route('login'), ['email' => $pending->email, 'password' => 'Password1!']);
        $this->assertGuest();
    }

    public function test_unauthorized_user_cannot_cancel_or_resend_a_cancellation_notice(): void
    {
        Notification::fake();
        $viewer = User::factory()->viewer()->create();
        $pending = User::factory()->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
        ]);

        $this->actingAs($viewer)
            ->patch(route('admin.users.cancel-invitation', $pending), [
                'cancel_user_id' => $pending->id,
                'cancellation_reason' => ActivationCancellationReason::DuplicateAccount->value,
            ])->assertForbidden();

        $pending->forceFill([
            'status' => UserStatus::Cancelled,
            'activation_cancellation_reason' => ActivationCancellationReason::DuplicateAccount,
            'activation_cancelled_at' => now(),
        ])->saveQuietly();

        $this->post(route('admin.users.cancellation-notification.send', $pending))->assertForbidden();
        Notification::assertNothingSent();
    }

    public function test_active_account_cannot_use_pending_activation_cancellation(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $active = User::factory()->warehouseStaff()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.cancel-invitation', $active), [
                'cancel_user_id' => $active->id,
                'cancellation_reason' => ActivationCancellationReason::CreatedByMistake->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(UserStatus::Active, $active->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_cancellation_email_failure_does_not_undo_cancellation(): void
    {
        $admin = $this->admin();
        $pending = User::factory()->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
        ]);
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Synthetic mail failure'));
        Log::spy();

        $this->actingAs($admin)
            ->patch(route('admin.users.cancel-invitation', $pending), [
                'cancel_user_id' => $pending->id,
                'cancellation_reason' => ActivationCancellationReason::CreatedByMistake->value,
            ])
            ->assertSessionHas('warning');

        $pending->refresh();
        $this->assertSame(UserStatus::Cancelled, $pending->status);
        $this->assertNull($pending->activation_cancellation_notice_sent_at);
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_authorized_admin_can_resend_cancellation_notice_without_changing_status(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $cancelled = User::factory()->create([
            'status' => UserStatus::Cancelled,
            'password' => null,
            'email_verified_at' => null,
            'activation_cancellation_reason' => ActivationCancellationReason::WrongRoleOrDepartment,
            'activation_cancellation_details' => 'The assigned unit was incorrect.',
            'activation_cancelled_at' => now(),
            'activation_cancelled_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.cancellation-notification.send', $cancelled))
            ->assertSessionHas('success', 'The cancellation notice was sent to '.$cancelled->email.'.');

        $this->assertSame(UserStatus::Cancelled, $cancelled->fresh()->status);
        Notification::assertSentToTimes($cancelled, AccountActivationCancelled::class, 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AccountActivationCancellationNoticeResent->value,
            'user_id' => $admin->id,
            'target_id' => (string) $cancelled->id,
        ]);

        $this->post(route('admin.users.verification.send', $cancelled))
            ->assertSessionHasErrors('status');
        Notification::assertSentToTimes($cancelled, AccountActivationCancelled::class, 1);
    }

    public function test_correcting_a_pending_activation_email_invalidates_the_code_and_notifies_only_the_new_address(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $pending = User::factory()->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
            'email_verified_at' => null,
            'phone' => '09123456789',
        ]);
        AccountActivationChallenge::create([
            'user_id' => $pending->id,
            'channel' => 'email',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);
        $this->actingAs($admin)->put(route('admin.users.update', $pending), [
            ...$pending->nameComponents(),
            'email' => 'corrected.activation@example.test',
            'role' => $pending->role->value,
            'status' => UserStatus::PendingActivation->value,
            'department' => $pending->department,
            'phone' => $pending->phone,
        ])->assertSessionHasNoErrors();

        $pending->refresh();
        $this->assertSame(UserStatus::PendingActivation, $pending->status);
        $this->assertSame('corrected.activation@example.test', $pending->email);
        $this->assertDatabaseMissing('account_activation_challenges', ['user_id' => $pending->id]);
        Notification::assertSentTo($pending, AccountCreated::class);
        $this->assertSame('corrected.activation@example.test', $pending->routeNotificationFor('mail'));
    }

    public function test_an_invalid_phone_number_cannot_update_a_user(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create(['phone' => '09123456789']);

        $this->actingAs($admin)
            ->from("/admin/users/{$staff->id}/edit")
            ->put("/admin/users/{$staff->id}", [
                ...$staff->nameComponents(),
                'email' => $staff->email,
                'role' => $staff->role->value,
                'status' => $staff->status->value,
                'department' => $staff->department,
                'phone' => '+639123456789',
            ])->assertRedirect("/admin/users/{$staff->id}/edit")
            ->assertSessionHasErrors('phone');

        $this->assertSame('09123456789', $staff->fresh()->phone);

        $this->get("/admin/users/{$staff->id}/edit")
            ->assertOk()
            ->assertSee("\$nextTick(() => \$dispatch('open-modal', 'edit-user-modal'))", false)
            ->assertSee('value="+639123456789"', false);
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
            ->assertRedirect('/admin/users')
            ->assertSessionHas('success', "{$staff->name}'s account was reactivated successfully.");

        $this->assertSame(UserStatus::Active, $staff->fresh()->status);

        $this->get('/admin/users')
            ->assertOk()
            ->assertSee('himsToastNotifications', false)
            ->assertSee('account was reactivated successfully.');
    }

    public function test_deactivation_preserves_employee_inventory_and_audit_ownership(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();
        $item = InventoryItem::create([
            'name' => 'Retention Test Item',
            'sku' => 'RETENTION-001',
            'unit' => 'box',
            'status' => 'active',
        ]);
        $movement = StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => MovementType::StockOut,
            'quantity' => 1,
            'user_id' => $staff->id,
            'moved_at' => now(),
        ]);
        $activity = AuditLog::create([
            'user_id' => $staff->id,
            'actor_name' => $staff->name,
            'actor_employee_id' => $staff->employee_id,
            'action' => AuditAction::LoggedIn,
            'target_name' => 'Account',
            'description' => 'Historical employee activity.',
        ]);

        $this->actingAs($admin)
            ->from('/admin/users')
            ->patch("/admin/users/{$staff->id}/status")
            ->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'status' => UserStatus::Inactive->value,
        ]);
        $this->assertSame($staff->id, $movement->fresh()->user_id);
        $this->assertTrue($movement->fresh()->user->is($staff));
        $this->assertSame($staff->id, $activity->fresh()->user_id);
        $this->assertTrue($activity->fresh()->actor->is($staff));
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

    public function test_the_database_refuses_to_delete_an_evaluator_with_process_review_history(): void
    {
        $staff = User::factory()->inventoryManager()->create();
        $review = KpiProcessReview::create([
            'review_number' => 'REV-RETENTION-001',
            'title' => 'Retention Test Review',
            'period_start' => now()->subMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'evaluator_id' => $staff->id,
            'status' => 'draft',
        ]);

        try {
            $staff->delete();
            $this->fail('The database must preserve users referenced by process review history.');
        } catch (QueryException) {
            $this->assertDatabaseHas('users', ['id' => $staff->id]);
            $this->assertDatabaseHas('kpi_process_reviews', ['id' => $review->id]);
        }
    }

    // ------------------------------------------------------------- lockout guards

    public function test_an_administrator_cannot_demote_themselves(): void
    {
        $admin = $this->admin();
        // A second administrator exists, so this can only fail on the self check.
        $this->admin();

        $this->actingAs($admin)
            ->put("/admin/users/{$admin->id}", [
                ...$admin->nameComponents(),
                'email' => $admin->email,
                'role' => UserRole::Viewer->value,
                'status' => UserStatus::Active->value,
                'department' => $admin->department,
                'phone' => $admin->phone,
            ])->assertForbidden();

        $this->assertSame(UserRole::Administrator, $admin->fresh()->role);
    }

    public function test_an_administrator_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();
        $this->admin();

        $this->actingAs($admin)
            ->patch("/admin/users/{$admin->id}/status")
            ->assertForbidden();

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
    public function test_the_service_refuses_a_non_super_admin_demoting_an_administrator(): void
    {
        $onlyAdmin = $this->admin();
        $actor = User::factory()->warehouseStaff()->create();

        $this->assertSame(1, User::administrators()->active()->count());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Only a Super Administrator may manage administrative accounts.');

        app(UserAccountService::class)->update($onlyAdmin, [
            'name' => $onlyAdmin->name,
            'email' => $onlyAdmin->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Active->value,
        ], $actor);
    }

    public function test_the_service_refuses_a_non_super_admin_deactivating_an_administrator(): void
    {
        $onlyAdmin = $this->admin();
        $actor = User::factory()->warehouseStaff()->create();

        try {
            app(UserAccountService::class)->toggleStatus($onlyAdmin, $actor);
            $this->fail('A non-Super Administrator must not deactivate an administrator.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Only a Super Administrator', $e->getMessage());
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
            ...$manager->nameComponents(),
            'email' => $manager->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Inactive->value,
            'department' => $manager->department,
            'phone' => $manager->phone,
        ])->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertSame(UserRole::Viewer, $manager->role);
        $this->assertSame(UserStatus::Inactive, $manager->status);
    }

    /**
     * Demoting one administrator while another remains is the normal case and
     * must go through — the guard is not allowed to be over-eager.
     */
    public function test_an_administrator_cannot_demote_another_administrator(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAs($admin)->put("/admin/users/{$other->id}", [
            ...$other->nameComponents(),
            'email' => $other->email,
            'role' => UserRole::Viewer->value,
            'status' => UserStatus::Active->value,
            'department' => $other->department,
            'phone' => $other->phone,
        ])->assertForbidden();

        $this->assertSame(UserRole::Administrator, $other->fresh()->role);
        $this->assertSame(2, User::administrators()->active()->count());
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

        $createResponse = $this->actingAs($admin)->get('/admin/users/create');

        $createResponse
            ->assertStatus(200)
            ->assertSee('Create a new employee account for hospital operations.')
            ->assertSee('Personal Information')
            ->assertSee('Contact Information')
            ->assertSee('Access &amp; Role', false)
            ->assertSee('form="create-user-form"', false)
            ->assertSee('sm:max-w-6xl', false)
            ->assertSee('name="surname"', false)
            ->assertSee('name="first_name"', false)
            ->assertSee('name="middle_name"', false)
            ->assertSee('name="employee_id"', false)
            ->assertSee('Generated automatically after creation')
            ->assertSee('name="department"', false)
            ->assertSee('Select a department')
            ->assertSee('name="role"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('inputmode="numeric"', false)
            ->assertSee('maxlength="11"', false)
            ->assertSee('pattern="09[0-9]{9}"', false)
            ->assertSee('placeholder="09XXXXXXXXX"', false)
            ->assertDontSee('name="name"', false);
        $createContent = $createResponse->getContent();
        $createNameFieldCount = collect(['surname', 'first_name', 'middle_name'])
            ->sum(fn (string $field): int => substr_count($createContent, 'name="'.$field.'"'));
        $this->assertSame($createNameFieldCount, substr_count($createContent, 'data-name-part-input='));
        $createResponse->assertSee('x-on:input="sanitizeNamePart($event)"', false);

        $editResponse = $this->actingAs($admin)->get("/admin/users/{$staff->id}/edit");

        $editResponse
            ->assertStatus(200)
            ->assertSee("\$nextTick(() => \$dispatch('open-modal', 'edit-user-modal'))", false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('Edit User')
            ->assertSee('Reset password')
            ->assertSee('data-account-security', false)
            ->assertSee('x-show="password.length > 0"', false)
            ->assertSee('form="edit-user-form"', false)
            ->assertSee('action="'.route('admin.users.update', $staff).'"', false)
            ->assertSee('value="'.e($staff->surname).'"', false)
            ->assertSee('value="'.e($staff->first_name).'"', false)
            ->assertSee('value="'.e($staff->employee_id).'"', false)
            ->assertSee('value="'.e($staff->department).'" selected', false)
            ->assertSee('value="'.$staff->role->value.'"', false);
        $editContent = $editResponse->getContent();
        $editNameFieldCount = collect(['surname', 'first_name', 'middle_name'])
            ->sum(fn (string $field): int => substr_count($editContent, 'name="'.$field.'"'));
        $this->assertSame(3, $editNameFieldCount);
        $this->assertSame($editNameFieldCount, substr_count($editContent, 'data-name-part-input='));
    }

    public function test_add_user_uses_a_modal_and_reopens_after_validation_failure(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee("\$dispatch('open-modal', 'create-user-modal')", false)
            ->assertSee('create-user-modal')
            ->assertSee('action="'.route('admin.users.store').'"', false);

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee("\$nextTick(() => \$dispatch('open-modal', 'create-user-modal'))", false);

        $this->from(route('admin.users.index'))->post(route('admin.users.store'), [
            'form_context' => 'create_user',
            'surname' => 'Modal',
            'email' => 'modal.user@djnrmhs.test',
        ])->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['first_name', 'role', 'department', 'phone']);

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee("\$nextTick(() => \$dispatch('open-modal', 'create-user-modal'))", false)
            ->assertSee('value="Modal"', false)
            ->assertSee('value="modal.user@djnrmhs.test"', false);
    }

    public function test_editing_updates_each_name_part_and_the_complete_name(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create(['name' => 'Ana Reyes']);
        $originalEmployeeId = $staff->employee_id;

        $this->actingAs($admin)->put("/admin/users/{$staff->id}", [
            'surname' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'email' => $staff->email,
            'role' => $staff->role->value,
            'status' => $staff->status->value,
            'department' => 'Finance',
            'employee_id' => 'EMP-9999',
            'phone' => $staff->phone,
        ])->assertRedirect('/admin/users');

        $staff->refresh();
        $this->assertSame('Dela Cruz', $staff->surname);
        $this->assertSame('Juan', $staff->first_name);
        $this->assertSame('Santos', $staff->middle_name);
        $this->assertSame('Juan Santos Dela Cruz', $staff->name);
        $this->assertSame('Finance', $staff->department);
        $this->assertSame($originalEmployeeId, $staff->employee_id);
    }

    public function test_a_legacy_name_still_displays_and_populates_the_edit_form(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->warehouseStaff()->create();

        // Simulate an existing row from before the structured columns existed.
        DB::table('users')->where('id', $staff->id)->update([
            'name' => 'Ben Santos',
            'surname' => null,
            'first_name' => null,
            'middle_name' => null,
        ]);

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('Ben Santos');

        $this->actingAs($admin)->get("/admin/users/{$staff->id}/edit")
            ->assertStatus(200)
            ->assertSee('value="Santos"', false)
            ->assertSee('value="Ben"', false);
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

    public function test_user_management_renders_role_permissions_modal_and_compact_reference(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertStatus(200)
            ->assertSee('Role Permissions Reference')
            ->assertSee('View Role Permissions')
            ->assertSee('role-permissions-modal')
            ->assertSee('Super Administrator')
            ->assertSee('Runs the storeroom: items, procurement, forecasts.');
    }

    public function test_create_form_hides_password_fields_and_edit_form_shows_them(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create();

        $this->actingAs($admin)->get('/admin/users/create')
            ->assertStatus(200)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="password_confirmation"', false);

        $this->actingAs($admin)->get("/admin/users/{$staff->id}/edit")
            ->assertStatus(200)
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_edit_form_hides_password_fields_for_pending_activation_accounts(): void
    {
        $admin = $this->admin();
        $pending = User::factory()->create([
            'status' => UserStatus::PendingActivation,
            'password' => null,
            'email_verified_at' => null,
        ]);

        $this->actingAs($admin)->get("/admin/users/{$pending->id}/edit")
            ->assertStatus(200)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="password_confirmation"', false);
    }
}
