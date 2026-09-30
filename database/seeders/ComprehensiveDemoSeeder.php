<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ComprehensiveDemoSeeder extends Seeder
{
    use DemoEnvironmentOnly;

    public function run(): void
    {
        $this->assertDemoEnvironment();

        $this->call([
            SuperAdminSeeder::class,
            DemoUserSeeder::class,
            InventoryDemoSeeder::class,
            DemandForecastDemoSeeder::class,
            SupplierManagementDemoSeeder::class,
            ProcurementDemoSeeder::class,
            SmartWarehousingDemoSeeder::class,
            LogisticsDemoSeeder::class,
            ProcessReviewDemoSeeder::class,
            OperationalMetricsDemoSeeder::class,
            ErrorRecoveryDemoSeeder::class,
        ]);

        $this->command?->info('Comprehensive HIMS sample data is ready. See docs/demo-data.md for the account and module guide.');
    }
}
