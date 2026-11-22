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
            'pickup_contact' => null,
            'destination' => [
                'department' => $requisition->department,
                'location' => null,
                'address' => null,
                'contact' => $requisition->requestingUser ? [
                    'name' => $requisition->requestingUser->name,
                    'phone' => $requisition->requestingUser->phone,
                    'role' => 'requisition_requester',
                ] : null,
            ],
            'delivery_time_window' => null,
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
