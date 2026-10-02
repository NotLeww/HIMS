<?php

namespace Tests\Feature;

use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_location_page_counts_pending_inbound_work_without_per_row_queries(): void
    {
        $user = User::factory()->warehouseStaff()->create();

        foreach (range(1, 8) as $index) {
            StorageLocation::create([
                'name' => "Performance Location {$index}",
                'code' => "PERF-{$index}",
                'type' => 'bin',
                'status' => 'active',
            ]);
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($user)
            ->get(route('inventory.warehousing.locations'))
            ->assertOk();

        $pendingInboundQueries = collect($queries)->filter(
            fn (string $sql) => str_contains($sql, 'stock_transfers')
                || str_contains($sql, 'warehouse_tasks')
        );

        $this->assertCount(1, $pendingInboundQueries);
    }

    public function test_camera_scanner_is_loaded_as_a_page_scoped_chunk(): void
    {
        $appScript = file_get_contents(resource_path('js/app.js'));
        $scannerComponent = file_get_contents(resource_path('views/components/ui/camera-scanner.blade.php'));

        $this->assertStringNotContainsString("from './scanner'", $appScript);
        $this->assertStringContainsString("await import('./scanner')", $appScript);
        $this->assertStringContainsString('data-hims-camera-scanner', $scannerComponent);
    }

    public function test_navigation_requests_are_not_held_behind_animation_frames(): void
    {
        $appScript = file_get_contents(resource_path('js/app.js'));
        $earlyNavigationScript = file_get_contents(resource_path('views/layouts/partials/navigation-loading-state.blade.php'));

        $this->assertStringContainsString('window.location.assign(url.href);', $appScript);
        $this->assertStringNotContainsString('requestAnimationFrame', $earlyNavigationScript);
    }
}
