<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\Inventory\ReplenishmentDaemon;
use App\Services\Logistics\ShipmentTrackingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OperationalDataSourceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_replenishment_does_not_fabricate_requester_or_master_data(): void
    {
        $item = InventoryItem::create([
            'name' => 'Database-backed replenishment item',
            'sku' => 'DB-ROP-001',
            'unit' => 'box',
            'quantity_on_hand' => 0,
            'reorder_point' => 10,
            'economic_order_quantity' => 20,
            'annual_demand' => 100,
            'lead_time_days' => 5,
            'unit_cost' => 25,
            'status' => 'active',
        ]);

        try {
            app(ReplenishmentDaemon::class)->evaluateAndReplenish($item);
            $this->fail('Expected replenishment without an authenticated requester to fail.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('authenticated requester', $exception->getMessage());
        }

        $this->assertDatabaseCount('purchase_requests', 0);
        $this->assertDatabaseCount('procurement_categories', 0);
        $this->assertDatabaseCount('cost_centers', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_shipment_registration_does_not_substitute_a_fake_destination(): void
    {
        $actor = User::factory()->inventoryManager()->create();

        try {
            app(ShipmentTrackingService::class)->registerInboundShipment([
                'carrier_name' => 'Recorded Carrier',
            ], $actor);
            $this->fail('Expected a missing shipment destination to fail.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('destination facility', $exception->getMessage());
        }

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_storage_location_purpose_is_persisted_instead_of_inferred_from_code(): void
    {
        $location = StorageLocation::create([
            'code' => 'CUSTOM-TRANSIT-BUFFER',
            'name' => 'Configured Transfer Buffer',
            'status' => 'active',
            'is_in_transit' => true,
        ]);

        $this->assertTrue($location->fresh()->is_in_transit);
        $this->assertSame($location->id, StorageLocation::where('is_in_transit', true)->value('id'));
    }
}
