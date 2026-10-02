<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\PasswordHistory;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Support\AuthenticationContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ConfiguresAccountProvisioning;
use Tests\TestCase;

class DemoSeedTest extends TestCase
{
    use ConfiguresAccountProvisioning;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureAccountProvisioning();
    }

    public function test_default_seeder_creates_only_explicitly_configured_owner_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, User::role(UserRole::SuperAdministrator)->count());
        $owner = User::superAdministrators()->firstOrFail();
        $this->assertTrue($owner->isProtected());
        $this->assertTrue($owner->hasVerifiedEmail());
        $this->assertSame(1, PasswordHistory::query()->count());

        $this->post(route('super-admin.login.store'), [
            'email' => $owner->email,
            'password' => 'SyntheticSeedSuperAdmin123!',
        ])->assertRedirect(route('super-admin.dashboard', absolute: false));
        $this->assertAuthenticatedAs($owner, AuthenticationContext::SUPER_ADMIN_GUARD);

        foreach (UserRole::cases() as $role) {
            if ($role !== UserRole::SuperAdministrator) {
                $this->assertSame(0, User::role($role)->count());
            }
        }
    }

    public function test_default_seeder_does_not_create_synthetic_operational_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, InventoryItem::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, Supplier::query()->count());
        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_reseeding_preserves_accounts_without_adding_demo_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $account = User::superAdministrators()->firstOrFail();
        $account->forceFill(['department' => 'Locally Customized Department'])->save();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, PasswordHistory::query()->count());
        $this->assertSame('Locally Customized Department', $account->fresh()->department);
        $this->assertSame(0, InventoryItem::query()->count());
    }
}
