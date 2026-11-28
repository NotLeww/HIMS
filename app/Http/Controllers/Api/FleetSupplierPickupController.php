<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\Logistics\ShipmentTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class FleetSupplierPickupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pickups = Shipment::query()
            ->where('status', 'created')
            ->whereNotNull('purchase_order_id')
            ->whereNotNull('supplier_id')
            ->with($this->pickupRelations())
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        $pickups->getCollection()->transform(fn (Shipment $shipment): array => $this->pickupPayload($shipment));

        return response()->json($pickups);
    }

    public function show(string $shipmentNumber): JsonResponse
    {
        $shipment = Shipment::query()
            ->where('shipment_number', $shipmentNumber)
            ->whereNotNull('purchase_order_id')
            ->whereNotNull('supplier_id')
            ->with($this->pickupRelations())
            ->firstOrFail();

        return response()->json(['data' => $this->pickupPayload($shipment)]);
    }

    public function updateStatus(Request $request, string $shipmentNumber, ShipmentTrackingService $tracking): JsonResponse
    {
        $status = $request->input('status');
        $rules = [
            'status' => ['required', 'in:picked_up,in_transit,arrived_at_dock'],
        ];

        if (in_array($status, ['picked_up', 'in_transit'], true)) {
            $rules += [
                'actual_pickup_date' => ['required', 'date', 'before_or_equal:today'],
                'carrier_name' => ['required', 'string', 'max:150'],
                'tracking_number' => ['nullable', 'string', 'max:100'],
                'waybill_number' => ['nullable', 'string', 'max:100'],
                'vehicle_plate_number' => ['nullable', 'string', 'max:50'],
                'driver_name' => ['nullable', 'string', 'max:150'],
                'driver_contact' => ['nullable', 'string', 'max:50'],
            ];
        } else {
            $rules += [
                'actual_delivery_date' => ['required', 'date', 'before_or_equal:today'],
                'package_condition' => ['required', 'in:good_order,damaged_packaging,tampered_seal,seal_intact'],
                'temp_min' => ['nullable', 'numeric', 'between:-80,80'],
                'temp_max' => ['nullable', 'numeric', 'between:-80,80', 'gte:temp_min'],
                'temp_logger_serial' => ['nullable', 'string', 'max:100'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ];
        }

        $validated = $request->validate($rules);
        unset($validated['status']);
        $shipment = Shipment::query()
            ->where('shipment_number', $shipmentNumber)
            ->whereNotNull('purchase_order_id')
            ->whereNotNull('supplier_id')
            ->firstOrFail();

        try {
            $requestedStatus = (string) $request->input('status');
            if (in_array($requestedStatus, ['picked_up', 'in_transit'], true)) {
                $shipment = $tracking->confirmFleetPickup($shipment, $validated);
            } elseif ($shipment->isDelivered()) {
                // Permit safe retries from Logistics2 without duplicate custody or audit entries.
                $shipment->refresh();
            } else {
                if ($shipment->status !== 'in_transit') {
                    return response()->json(['message' => 'Logistics2 must confirm pickup before reporting dock arrival.'], 409);
                }
                if ($shipment->dispatch_date
                    && $validated['actual_delivery_date'] < $shipment->dispatch_date->toDateString()) {
                    throw ValidationException::withMessages([
                        'actual_delivery_date' => ['The arrival date must be on or after the Logistics2 pickup date.'],
                    ]);
                }
                $shipment = $tracking->recordDockArrival($shipment, $validated, null, true);
            }
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => $this->pickupPayload($shipment->load($this->pickupRelations()))]);
    }

    private function pickupPayload(Shipment $shipment): array
    {
        $supplier = $shipment->supplier;

        return [
            'delivery_id' => $shipment->shipment_number,
            'delivery_type' => 'supplier_pickup',
            'asn_number' => $shipment->shipment_number,
            'purchase_order_number' => $shipment->purchaseOrder?->po_number,
            'status' => match ($shipment->status) {
                'created' => 'ready_for_pickup',
                'in_transit' => 'picked_up',
                'arrived_at_dock', 'received' => 'arrived_at_dock',
                default => $shipment->status,
            },
            'requested_at' => $shipment->created_at?->toISOString(),
            'actual_pickup_date' => $shipment->dispatch_date?->toDateString(),
            'expected_arrival_date' => $shipment->estimated_delivery_date?->toDateString(),
            'actual_delivery_date' => $shipment->actual_delivery_date?->toDateString(),
            'carrier' => $shipment->carrier_name,
            'tracking_number' => $shipment->tracking_number,
            'waybill_number' => $shipment->waybill_number,
            'driver' => $shipment->driver_name ? [
                'name' => $shipment->driver_name,
                'contact' => $shipment->driver_contact,
                'vehicle_plate_number' => $shipment->vehicle_plate_number,
            ] : null,
            'pickup' => [
                'location' => $shipment->pickup_location_name,
                'address' => $shipment->origin_address,
                'contact' => $shipment->pickup_contact_name ? [
                    'name' => $shipment->pickup_contact_name,
                    'phone' => $shipment->pickup_contact_number,
                ] : null,
            ],
            'destination' => [
                'location' => $shipment->destination_facility,
            ],
            'supplier' => [
                'name' => $supplier?->name,
            ],
            'items' => $shipment->lines->map(fn ($line): array => [
                'sku' => $line->purchaseOrderLine?->item?->sku,
                'name' => $line->purchaseOrderLine?->item?->name,
                'quantity' => $line->quantity,
                'unit' => $line->purchaseOrderLine?->purchase_unit ?: $line->purchaseOrderLine?->item?->unit,
                'pickup_location' => $shipment->pickup_location_name,
            ])->values()->all(),
        ];
    }

    private function pickupRelations(): array
    {
        return [
            'purchaseOrder:id,po_number',
            'supplier:id,name',
            'lines.purchaseOrderLine.item:id,sku,name,unit',
        ];
    }
}
