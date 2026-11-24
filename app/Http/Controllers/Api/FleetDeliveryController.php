<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaterialRequisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetDeliveryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $deliveries = MaterialRequisition::query()
            ->where('status', 'issued')
            ->whereNull('acknowledged_at')
            ->where('delivery_method', 'fleet')
            ->whereNotNull('destination_location')
            ->with($this->deliveryRelations())
            ->orderBy('issued_at')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        $deliveries->getCollection()->transform(fn (MaterialRequisition $requisition): array => $this->deliveryPayload($requisition));

        return response()->json($deliveries);
    }

    public function show(string $deliveryId): JsonResponse
    {
        $requisition = MaterialRequisition::query()
            ->where('requisition_number', $deliveryId)
            ->whereIn('status', ['issued', 'acknowledged'])
            ->where('delivery_method', 'fleet')
            ->whereNotNull('destination_location')
            ->with($this->deliveryRelations())
            ->firstOrFail();

        return response()->json(['data' => $this->deliveryPayload($requisition)]);
    }

    private function deliveryPayload(MaterialRequisition $requisition): array
    {
        return [
            'delivery_id' => $requisition->requisition_number,
            'status' => $requisition->acknowledged_at ? 'department_acknowledged' : 'ready_for_scheduling',
            'supply_status' => $requisition->status,
            'department' => $requisition->department,
            'urgency' => $requisition->urgency,
            'required_date' => $requisition->required_date?->toDateString(),
            'issued_at' => $requisition->issued_at?->toISOString(),
            'acknowledged_at' => $requisition->acknowledged_at?->toISOString(),
            'delivery_method' => $requisition->delivery_method,
            'pickup_contact' => null,
            'destination' => [
                'department' => $requisition->department,
                'location' => $requisition->destination_location,
                'address' => $requisition->destination_address,
                'contact' => $requisition->delivery_contact_name ? [
                    'name' => $requisition->delivery_contact_name,
                    'phone' => $requisition->delivery_contact_phone,
                    'role' => 'delivery_recipient',
                ] : null,
            ],
            'delivery_time_window' => $requisition->delivery_window_start && $requisition->delivery_window_end ? [
                'start' => substr((string) $requisition->delivery_window_start, 0, 5),
                'end' => substr((string) $requisition->delivery_window_end, 0, 5),
                'timezone' => config('app.timezone'),
            ] : null,
            'items' => $requisition->lines
                ->filter(fn ($line): bool => $line->issued_quantity > 0)
                ->map(fn ($line): array => [
                    'sku' => $line->item?->sku,
                    'name' => $line->item?->name,
                    'quantity' => $line->issued_quantity,
                    'unit' => $line->item?->unit,
                    'pickup_location' => $line->location?->fullPath(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function deliveryRelations(): array
    {
        return [
            'requestingUser:id,name,phone',
            'lines.item:id,sku,name,unit',
            'lines.location.parent.parent.parent.parent.parent',
        ];
    }
}
