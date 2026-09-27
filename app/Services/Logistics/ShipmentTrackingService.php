<?php

namespace App\Services\Logistics;

use App\Enums\AuditAction;
use App\Enums\SupplierStatus;
use App\Models\PurchaseOrder;
use App\Models\Shipment;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShipmentTrackingService
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected ChainOfCustodyService $custodyService
    ) {}

    /**
     * Generate unique Shipment Number: SHP-YYYY-XXXXX
     */
    public function generateShipmentNumber(): string
    {
        $year = date('Y');
        $prefix = "SHP-{$year}-";

        $latest = Shipment::where('shipment_number', 'like', "{$prefix}%")
            ->orderByDesc('id')
            ->value('shipment_number');

        $nextSeq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest, $matches)) {
            $nextSeq = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%05d', $prefix, $nextSeq);
    }

    /**
     * Validate GS1-128 Serial Shipping Container Code (SSCC-18).
     * Must be 18 digits with valid Modulo 10 check digit.
     */
    public function validateSscc(string $sscc): bool
    {
        $cleaned = trim($sscc);
        if (! preg_match('/^\d{18}$/', $cleaned)) {
            return false;
        }

        $digits = str_split($cleaned);
        $checkDigit = (int) array_pop($digits);

        $sum = 0;
        foreach (array_reverse($digits) as $posFromRight => $digit) {
            // Position from right to left (1-indexed odd/even multiplier: 3, 1, 3, 1...)
            $multiplier = (($posFromRight % 2) === 0) ? 3 : 1;
            $sum += ((int) $digit) * $multiplier;
        }

        $calculatedCheck = (10 - ($sum % 10)) % 10;

        return $calculatedCheck === $checkDigit;
    }

    /**
     * Register a new inbound shipment.
     */
    public function registerInboundShipment(array $data, User $actor): Shipment
    {
        if (blank($data['carrier_name'] ?? null)
            || (empty($data['destination_storage_location_id']) && blank($data['destination_facility'] ?? null))) {
            throw new InvalidArgumentException('Carrier and destination facility are required to register a shipment.');
        }

        if (! empty($data['sscc']) && ! $this->validateSscc($data['sscc'])) {
            throw new InvalidArgumentException("The provided SSCC [{$data['sscc']}] is not a valid 18-digit GS1 SSCC with check digit.");
        }

        return DB::transaction(function () use ($data, $actor) {
            $shipmentNumber = $this->generateShipmentNumber();

            $po = null;
            if (! empty($data['purchase_order_id'])) {
                $po = PurchaseOrder::find($data['purchase_order_id']);
                if (! $po) {
                    throw new InvalidArgumentException('The selected purchase order does not exist.');
                }
            }

            if ($po && ! empty($data['supplier_id']) && (int) $data['supplier_id'] !== (int) $po->supplier_id) {
                throw new InvalidArgumentException('The selected supplier does not match the linked purchase order.');
            }

            $supplier = $po?->supplier ?? (! empty($data['supplier_id']) ? Supplier::find($data['supplier_id']) : null);
            $pickup = $this->resolvePickup($data, $supplier);
            $destination = $this->resolveDestination($data);
            $dispatchDate = CarbonImmutable::parse($data['dispatch_date'] ?? now())->startOfDay();
            $estimatedDeliveryDate = filled($data['estimated_delivery_date'] ?? null)
                ? CarbonImmutable::parse($data['estimated_delivery_date'])->startOfDay()
                : ($po?->delivery_date ? CarbonImmutable::parse($po->delivery_date)->startOfDay() : null);

            if ($estimatedDeliveryDate
                && ($estimatedDeliveryDate->isBefore(today()) || ! $estimatedDeliveryDate->isAfter($dispatchDate))) {
                throw new InvalidArgumentException('Estimated delivery date must not be in the past and must be after the dispatch date.');
            }

            if ($pickup['storage_location_id'] !== null
                && $pickup['storage_location_id'] === $destination['storage_location_id']) {
                throw new InvalidArgumentException('Pickup and destination must be different physical locations.');
            }

            $shipment = Shipment::create([
                'shipment_number' => $shipmentNumber,
                'purchase_order_id' => $po?->id,
                'supplier_id' => $supplier?->id,
                'pickup_location_type' => $pickup['type'],
                'pickup_location_name' => $pickup['name'],
                'pickup_storage_location_id' => $pickup['storage_location_id'],
                'carrier_name' => $data['carrier_name'],
                'tracking_number' => $data['tracking_number'] ?? null,
                'waybill_number' => $data['waybill_number'] ?? null,
                'vehicle_plate_number' => $data['vehicle_plate_number'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'driver_contact' => $data['driver_contact'] ?? null,
                'sscc' => $data['sscc'] ?? null,
                'origin_address' => $pickup['address'],
                'pickup_contact_name' => $pickup['contact_name'],
                'pickup_contact_number' => $pickup['contact_number'],
                'destination_facility' => $destination['name'],
                'destination_storage_location_id' => $destination['storage_location_id'],
                'dispatch_date' => $dispatchDate->toDateString(),
                'estimated_delivery_date' => $estimatedDeliveryDate?->toDateString(),
                'status' => 'dispatched',
                'is_cold_chain' => (bool) ($data['is_cold_chain'] ?? false),
                'temp_logger_serial' => $data['temp_logger_serial'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create initial chain of custody record
            $this->custodyService->recordTransfer(
                trackable: $shipment,
                data: [
                    'event_type' => 'shipment_dispatched',
                    'releasing_party_name' => $shipment->driver_name ?? $shipment->carrier_name,
                    'receiving_user_id' => $actor->id,
                    'receiving_party_name' => $shipment->carrier_name,
                    'origin_location' => $shipment->pickup_location_name ?? $shipment->origin_address,
                    'destination_location' => $shipment->destination_facility,
                    'package_condition' => null,
                    'verification_method' => 'credential_auth',
                    'notes' => "Shipment {$shipmentNumber} dispatched via {$shipment->carrier_name}. SSCC: ".($shipment->sscc ?? 'N/A'),
                ],
                actor: $actor
            );

            $this->auditLogger->record(
                action: AuditAction::ShipmentDispatched,
                actor: $actor,
                target: $shipment,
                description: "Inbound shipment {$shipmentNumber} registered for PO ".($po?->po_number ?? 'Direct')." via {$shipment->carrier_name}.",
                newValues: [
                    'pickup_type' => $shipment->pickup_location_type,
                    'pickup_location' => $shipment->pickup_location_name,
                    'pickup_address' => $shipment->origin_address,
                    'destination' => $shipment->destination_facility,
                ],
            );

            return $shipment;
        });
    }

    /**
     * Record shipment arrival at hospital receiving dock and verify cold chain telemetry.
     */
    public function recordDockArrival(Shipment $shipment, array $dockData, User $receiver): Shipment
    {
        if ($shipment->isDelivered()) {
            throw new InvalidArgumentException("Shipment {$shipment->shipment_number} has already been received at dock.");
        }

        return DB::transaction(function () use ($shipment, $dockData, $receiver) {
            $isColdChain = $shipment->is_cold_chain;
            $tempMin = isset($dockData['temp_min']) ? (float) $dockData['temp_min'] : $shipment->temp_min;
            $tempMax = isset($dockData['temp_max']) ? (float) $dockData['temp_max'] : $shipment->temp_max;
            $tempExcursion = false;

            // Hospital cold-chain standard: 2.0°C to 8.0°C (WHO / FDA AO 2013-0027)
            if ($isColdChain && $tempMin !== null && $tempMax !== null) {
                if ($tempMin < 2.0 || $tempMax > 8.0) {
                    $tempExcursion = true;
                }
            }

            $shipment->update([
                'status' => 'arrived_at_dock',
                'actual_delivery_date' => $dockData['actual_delivery_date'] ?? now()->toDateString(),
                'temp_min' => $tempMin,
                'temp_max' => $tempMax,
                'temp_logger_serial' => $dockData['temp_logger_serial'] ?? $shipment->temp_logger_serial,
                'temp_excursion' => $tempExcursion,
                'notes' => filled($dockData['notes'] ?? null)
                    ? trim(implode("\n", array_filter([$shipment->notes, 'Arrival Notes: '.$dockData['notes']])))
                    : $shipment->notes,
            ]);

            // Chain of custody transfer to hospital receiving officer
            $this->custodyService->recordTransfer(
                trackable: $shipment,
                data: [
                    'event_type' => 'dock_arrival',
                    'releasing_party_name' => $shipment->driver_name ?? $shipment->carrier_name,
                    'receiving_user_id' => $receiver->id,
                    'receiving_party_name' => $receiver->name.' (Receiving Officer)',
                    'origin_location' => $shipment->pickup_location_name ?? $shipment->origin_address,
                    'destination_location' => $shipment->destination_facility,
                    'package_condition' => $tempExcursion ? 'cold_chain_excursion' : $dockData['package_condition'],
                    'verification_method' => 'credential_auth',
                    'notes' => 'Shipment arrived. Cold Chain: '.($isColdChain ? 'YES' : 'NO').
                             ($isColdChain ? " (Min: {$tempMin}°C, Max: {$tempMax}°C, Excursion: ".($tempExcursion ? 'DETECTED-QUARANTINE' : 'PASS').')' : ''),
                ],
                actor: $receiver
            );

            $this->auditLogger->record(
                action: AuditAction::ShipmentArrivedDock,
                actor: $receiver,
                target: $shipment,
                description: "Shipment {$shipment->shipment_number} arrived at receiving dock. Cold Chain Excursion: ".($tempExcursion ? 'YES' : 'NO')
            );

            return $shipment;
        });
    }

    /**
     * Update transit status (e.g. in_transit, custom hold).
     */
    public function updateStatus(Shipment $shipment, string $status, string $location, ?string $remarks, User $actor): Shipment
    {
        $oldStatus = $shipment->status;
        $shipment->update(['status' => $status]);

        $this->custodyService->recordTransfer(
            trackable: $shipment,
            data: [
                'event_type' => 'dock_receiving',
                'releasing_party_name' => $shipment->carrier_name,
                'receiving_party_name' => $shipment->carrier_name,
                'origin_location' => $location,
                'destination_location' => $location,
                'package_condition' => null,
                'verification_method' => 'credential_auth',
                'notes' => "Status changed from {$oldStatus} to {$status}. Remarks: {$remarks}",
            ],
            actor: $actor
        );

        $this->auditLogger->record(
            action: AuditAction::UpdatedShipmentStatus,
            actor: $actor,
            target: $shipment,
            description: "Shipment {$shipment->shipment_number} status updated to {$status}."
        );

        return $shipment;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: string, name: string, address: ?string, contact_name: ?string, contact_number: ?string, storage_location_id: ?int}
     */
    private function resolvePickup(array $data, ?Supplier $supplier): array
    {
        $type = (string) ($data['pickup_source'] ?? '');

        if ($type === 'supplier_address') {
            if (! $supplier || $supplier->status !== SupplierStatus::Active) {
                throw new InvalidArgumentException('Select an active supplier before choosing a supplier pickup location.');
            }

            $address = $supplier->address;
            if (blank($address)) {
                throw new InvalidArgumentException('The selected supplier pickup location has no recorded address.');
            }

            return [
                'type' => $type,
                'name' => $supplier->name.' - Registered Address',
                'address' => trim((string) $address),
                'contact_name' => $supplier->contact_person,
                'contact_number' => $supplier->phone,
                'storage_location_id' => null,
            ];
        }

        if ($type === 'internal') {
            $location = StorageLocation::query()->active()->find($data['pickup_storage_location_id'] ?? null);
            if (! $location) {
                throw new InvalidArgumentException('Select a valid active internal pickup location.');
            }

            return [
                'type' => 'internal',
                'name' => $location->fullPath(),
                'address' => null,
                'contact_name' => null,
                'contact_number' => null,
                'storage_location_id' => $location->id,
            ];
        }

        if ($type === 'manual') {
            $name = trim((string) ($data['pickup_location_name'] ?? ''));
            $address = trim((string) ($data['origin_address'] ?? ''));
            if ($name === '' || $address === '') {
                throw new InvalidArgumentException('Manual pickup location name and address are required.');
            }

            return [
                'type' => 'manual',
                'name' => $name,
                'address' => $address,
                'contact_name' => filled($data['pickup_contact_name'] ?? null) ? trim((string) $data['pickup_contact_name']) : null,
                'contact_number' => filled($data['pickup_contact_number'] ?? null) ? trim((string) $data['pickup_contact_number']) : null,
                'storage_location_id' => null,
            ];
        }

        throw new InvalidArgumentException('Pickup location is required to register a shipment.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, storage_location_id: ?int}
     */
    private function resolveDestination(array $data): array
    {
        if (! empty($data['destination_storage_location_id'])) {
            $location = StorageLocation::query()->active()->find($data['destination_storage_location_id']);
            if (! $location) {
                throw new InvalidArgumentException('Select a valid active destination facility.');
            }

            return ['name' => $location->fullPath(), 'storage_location_id' => $location->id];
        }

        if (filled($data['destination_facility'] ?? null)) {
            return ['name' => trim((string) $data['destination_facility']), 'storage_location_id' => null];
        }

        throw new InvalidArgumentException('Destination facility is required to register a shipment.');
    }
}
