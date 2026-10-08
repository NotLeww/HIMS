<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\ChainOfCustodyLog;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Logistics\ShipmentTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundShipmentPickupLocationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{actor: User, supplier: Supplier, otherSupplier: Supplier, pickup: StorageLocation, destination: StorageLocation, po: PurchaseOrder}
     */
    private function setupLogistics(): array
    {
        $actor = User::factory()->inventoryManager()->create();
        $supplier = Supplier::create([
            'name' => 'North Luzon Medical Supply',
            'contact_person' => 'Ana Reyes',
            'phone' => '09170001111',
            'address' => '10 Supplier Avenue, San Fernando, La Union',
            'delivery_address' => 'Warehouse 4, Poro Point, La Union',
            'status' => 'active',
        ]);
        $otherSupplier = Supplier::create([
            'name' => 'Different Medical Vendor',
            'address' => '88 Unrelated Road, Manila',
            'status' => 'active',
        ]);
        $pickup = StorageLocation::create([
            'name' => 'Main Warehouse',
            'code' => 'PICKUP-MAIN',
            'type' => 'warehouse',
            'status' => 'active',
            'is_dispatch_staging' => true,
        ]);
        $destination = StorageLocation::create([
            'name' => 'Operating Theatre Store',
            'code' => 'DEST-OTS',
            'type' => 'room',
            'status' => 'active',
            'is_receiving_staging' => true,
        ]);
        $item = InventoryItem::create([
            'name' => 'Sterile Pickup Test Item',
            'sku' => 'PICKUP-TEST-001',
            'unit' => 'box',
            'status' => 'active',
        ]);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-PICKUP-001',
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_cost' => 100,
            'total_amount' => 1000,
            'delivery_date' => today()->addDays(3),
            'status' => PurchaseOrderStatus::Approved->value,
            'requested_by_id' => $actor->id,
        ]);

        return compact('actor', 'supplier', 'otherSupplier', 'pickup', 'destination', 'po');
    }

    public function test_supplier_pickup_is_registered_persisted_displayed_and_audited_as_a_snapshot(): void
    {
        extract($this->setupLogistics());

        app(ShipmentTrackingService::class)->registerInboundShipment([
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'pickup_source' => 'supplier_address',
            'destination_storage_location_id' => $destination->id,
            'carrier_name' => 'Verified 3PL Carrier',
            'dispatch_date' => today()->toDateString(),
            'estimated_delivery_date' => today()->addDays(3)->toDateString(),
        ], $actor);

        $shipment = Shipment::query()->sole();
        $this->assertSame('supplier_address', $shipment->pickup_location_type);
        $this->assertSame('North Luzon Medical Supply - Registered Address', $shipment->pickup_location_name);
        $this->assertSame('10 Supplier Avenue, San Fernando, La Union', $shipment->origin_address);
        $this->assertSame('Ana Reyes', $shipment->pickup_contact_name);
        $this->assertSame('09170001111', $shipment->pickup_contact_number);
        $this->assertSame($destination->id, $shipment->destination_storage_location_id);
        $this->assertSame('Operating Theatre Store', $shipment->destination_facility);

        $audit = AuditLog::query()->where('action', AuditAction::ShipmentDispatched->value)->sole();
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertSame('North Luzon Medical Supply - Registered Address', $audit->new_values['pickup_location']);
        $this->assertSame('Operating Theatre Store', $audit->new_values['destination']);

        $custody = ChainOfCustodyLog::query()->sole();
        $this->assertSame('North Luzon Medical Supply - Registered Address', $custody->origin_location);
        $this->assertSame('Operating Theatre Store', $custody->destination_location);

        $supplier->update(['address' => 'A newly edited supplier address']);
        $shipment->refresh();
        $this->assertSame('10 Supplier Avenue, San Fernando, La Union', $shipment->origin_address);

        $this->actingAs($actor)->get(route('inventory.logistics.shipments'))
            ->assertOk()
            ->assertSee('North Luzon Medical Supply - Registered Address')
            ->assertSee('Address: 10 Supplier Avenue, San Fernando, La Union')
            ->assertSee('Operating Theatre Store');
    }

    public function test_duplicate_legacy_pickup_address_is_not_rendered_twice(): void
    {
        extract($this->setupLogistics());

        Shipment::create([
            'shipment_number' => 'SHP-LEGACY-DUPLICATE-PICKUP',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'carrier_name' => 'Legacy Carrier',
            'pickup_location_name' => 'Legacy Distribution Facility',
            'origin_address' => 'Legacy Distribution Facility',
            'destination_facility' => 'Main Receiving Dock',
            'status' => 'dispatched',
        ]);

        $this->actingAs($actor)->get(route('inventory.logistics.shipments', ['search' => 'SHP-LEGACY-DUPLICATE-PICKUP']))
            ->assertOk()
            ->assertSee('Pickup: Legacy Distribution Facility')
            ->assertDontSee('Address: Legacy Distribution Facility');
    }

    public function test_logistics_one_page_omits_inbound_registration_controls_and_endpoint(): void
    {
        extract($this->setupLogistics());

        $this->actingAs($actor)->get(route('inventory.logistics.shipments'))
            ->assertOk()
            ->assertDontSee('Register Inbound Shipment')
            ->assertDontSee('Register Inbound 3PL Shipment')
            ->assertDontSee('camera-scanner-shipment-sscc');

        $this->actingAs($actor)->post('/inventory/logistics/shipments')->assertMethodNotAllowed();

        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($script);
        $this->assertStringNotContainsString("Alpine.data('shipmentRegistration'", $script);
    }

    public function test_purchase_order_rejects_a_different_supplier_pickup(): void
    {
        extract($this->setupLogistics());

        $this->expectException(\InvalidArgumentException::class);
        app(ShipmentTrackingService::class)->registerInboundShipment([
            'purchase_order_id' => $po->id,
            'supplier_id' => $otherSupplier->id,
            'pickup_source' => 'supplier_address',
            'destination_storage_location_id' => $destination->id,
            'carrier_name' => 'Test Carrier',
        ], $actor);
    }

    public function test_direct_transfer_uses_distinct_active_internal_pickup_and_destination(): void
    {
        extract($this->setupLogistics());

        app(ShipmentTrackingService::class)->registerInboundShipment([
            'pickup_source' => 'internal',
            'pickup_storage_location_id' => $pickup->id,
            'destination_storage_location_id' => $destination->id,
            'carrier_name' => 'Hospital Fleet',
        ], $actor);

        $shipment = Shipment::query()->sole();
        $this->assertNull($shipment->purchase_order_id);
        $this->assertNull($shipment->supplier_id);
        $this->assertSame('internal', $shipment->pickup_location_type);
        $this->assertSame($pickup->id, $shipment->pickup_storage_location_id);
        $this->assertSame('Main Warehouse', $shipment->pickup_location_name);
        $this->assertSame('Operating Theatre Store', $shipment->destination_facility);
    }

    public function test_same_internal_pickup_and_destination_is_rejected(): void
    {
        extract($this->setupLogistics());

        $this->expectException(\InvalidArgumentException::class);
        app(ShipmentTrackingService::class)->registerInboundShipment([
            'pickup_source' => 'internal',
            'pickup_storage_location_id' => $pickup->id,
            'destination_storage_location_id' => $pickup->id,
            'carrier_name' => 'Hospital Fleet',
        ], $actor);
    }

    public function test_manual_pickup_is_saved_without_creating_master_data(): void
    {
        extract($this->setupLogistics());
        $supplierCount = Supplier::count();
        $locationCount = StorageLocation::count();

        app(ShipmentTrackingService::class)->registerInboundShipment([
            'pickup_source' => 'manual',
            'pickup_location_name' => 'Temporary 3PL Cross-Dock',
            'origin_address' => 'Pier 7, Port Area, Manila',
            'pickup_contact_name' => 'Dock Coordinator',
            'pickup_contact_number' => '09175550000',
            'destination_storage_location_id' => $destination->id,
            'carrier_name' => 'Port Transfer Logistics',
        ], $actor);

        $this->assertDatabaseHas('shipments', [
            'pickup_location_type' => 'manual',
            'pickup_location_name' => 'Temporary 3PL Cross-Dock',
            'origin_address' => 'Pier 7, Port Area, Manila',
            'pickup_contact_name' => 'Dock Coordinator',
        ]);
        $this->assertSame($supplierCount, Supplier::count());
        $this->assertSame($locationCount, StorageLocation::count());
    }

    public function test_service_rejects_delivery_before_dispatch(): void
    {
        extract($this->setupLogistics());

        $payload = [
            'pickup_source' => 'internal',
            'pickup_storage_location_id' => $pickup->id,
            'destination_storage_location_id' => $destination->id,
            'carrier_name' => 'Hospital Fleet',
            'dispatch_date' => today()->addDays(2)->toDateString(),
        ];

        $this->expectException(\InvalidArgumentException::class);
        app(ShipmentTrackingService::class)->registerInboundShipment([
            ...$payload,
            'estimated_delivery_date' => today()->addDay()->toDateString(),
        ], $actor);
    }

    public function test_service_rejects_inactive_pickup_locations(): void
    {
        extract($this->setupLogistics());
        $pickup->update(['status' => 'inactive']);

        $this->expectException(\InvalidArgumentException::class);
        app(ShipmentTrackingService::class)->registerInboundShipment([
            'pickup_source' => 'internal',
            'pickup_storage_location_id' => $pickup->id,
            'destination_storage_location_id' => $destination->id,
            'carrier_name' => 'Hospital Fleet',
        ], $actor);
    }

    public function test_pickup_is_searchable_visible_during_receiving_and_survives_dock_arrival(): void
    {
        extract($this->setupLogistics());

        $shipment = app(ShipmentTrackingService::class)->registerInboundShipment([
            'pickup_source' => 'manual',
            'pickup_location_name' => 'Harbor Medical Cross-Dock',
            'origin_address' => 'South Harbor, Manila',
            'destination_storage_location_id' => $destination->id,
            'carrier_name' => 'Harbor Logistics',
            'sscc' => '000123456700000015',
            'is_cold_chain' => true,
        ], $actor);

        $this->actingAs($actor)->get(route('inventory.logistics.shipments', ['search' => 'Harbor Medical']))
            ->assertOk()
            ->assertSee($shipment->shipment_number)
            ->assertSee('Harbor Medical Cross-Dock')
            ->assertSee('Operating Theatre Store')
            ->assertSee('0 0012345 670000001 5');

        $this->actingAs($actor)->post(route('inventory.logistics.shipments.dock-arrival', $shipment), [
            'actual_delivery_date' => today()->toDateString(),
            'temp_min' => 3.1,
            'temp_max' => 5.7,
            'package_condition' => 'seal_intact',
        ])->assertRedirect();

        $shipment->refresh();
        $this->assertSame('arrived_at_dock', $shipment->status);
        $this->assertSame('Harbor Medical Cross-Dock', $shipment->pickup_location_name);
        $this->assertSame('South Harbor, Manila', $shipment->origin_address);
        $this->assertSame('000123456700000015', $shipment->sscc);
        $this->assertTrue($shipment->is_cold_chain);
    }

    public function test_dock_arrival_rejects_future_and_pre_dispatch_dates(): void
    {
        extract($this->setupLogistics());

        $shipment = app(ShipmentTrackingService::class)->registerInboundShipment([
            'pickup_source' => 'manual',
            'pickup_location_name' => 'Validation Cross-Dock',
            'origin_address' => 'Port Area, Manila',
            'destination_storage_location_id' => $destination->id,
            'carrier_name' => 'Validation Logistics',
        ], $actor);

        $this->actingAs($actor)->post(route('inventory.logistics.shipments.dock-arrival', $shipment), [
            'actual_delivery_date' => today()->addDay()->toDateString(),
            'package_condition' => 'good_order',
        ])->assertSessionHasErrors('actual_delivery_date');

        $this->actingAs($actor)->post(route('inventory.logistics.shipments.dock-arrival', $shipment), [
            'actual_delivery_date' => $shipment->dispatch_date->copy()->subDay()->toDateString(),
            'package_condition' => 'good_order',
        ])->assertSessionHasErrors('actual_delivery_date');

        $this->assertNull($shipment->fresh()->actual_delivery_date);
    }

    public function test_legacy_shipment_without_pickup_renders_a_safe_state(): void
    {
        extract($this->setupLogistics());

        Shipment::create([
            'shipment_number' => 'SHP-LEGACY-NO-PICKUP',
            'purchase_order_id' => $po->id,
            'supplier_id' => $supplier->id,
            'carrier_name' => 'Legacy Carrier',
            'destination_facility' => 'Legacy Receiving Area',
            'status' => 'dispatched',
        ]);

        $this->actingAs($actor)->get(route('inventory.logistics.shipments', ['search' => 'SHP-LEGACY-NO-PICKUP']))
            ->assertOk()
            ->assertSee('Pickup: Not recorded')
            ->assertSee('Legacy Receiving Area');
    }
}
