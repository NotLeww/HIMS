<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemStockLevel;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Services\InventoryAutomationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockMovementController extends Controller
{
    public function __construct(private readonly InventoryAutomationService $automationService) {}

    public function index(): View
    {
        // A transfer writes its source and destination rows inside one
        // transaction, so moved_at ties are common; id breaks the tie and keeps
        // the newest movement at the top.
        $movements = StockMovement::with(['item', 'batch', 'fromLocation', 'toLocation', 'user', 'reference'])
            ->latest('moved_at')
            ->latest('id')
            ->get();

        $items = InventoryItem::orderBy('name')->get();
        $locations = StorageLocation::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        // Stock lives per item/location/batch, and the service rejects an
        // outbound movement from a location that holds none. Surfacing the
        // balances here is what makes the From Location choice informed.
        $availability = ItemStockLevel::with(['item', 'location'])
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('item_id');

        return view('inventory.stock_movements.index', compact(
            'movements', 'items', 'locations', 'suppliers', 'availability'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'movement_type' => ['required', 'in:stock_in,stock_out,transfer,issuance,return_to_supplier'],
            'quantity' => ['required', 'integer', 'min:1'],
            'from_location_id' => ['nullable', 'exists:storage_locations,id'],
            'to_location_id' => ['nullable', 'exists:storage_locations,id'],
            // Issuance and returns record their counterparty through the
            // movement's polymorphic reference, so no new columns are needed.
            'issued_to_location_id' => ['required_if:movement_type,issuance', 'nullable', 'exists:storage_locations,id'],
            'return_supplier_id' => ['required_if:movement_type,return_to_supplier', 'nullable', 'exists:suppliers,id'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'issued_to_location_id.required_if' => 'Select the department or ward the stock was issued to.',
            'return_supplier_id.required_if' => 'Select the supplier the stock is being returned to.',
        ]);

        // recordMovement throws ValidationException for a missing location or
        // insufficient stock at the source. Laravel turns that into a redirect
        // back with the errors and the old input, which the view renders.
        $movements = $this->automationService->recordMovement(
            $validated,
            auth()->id(),
            $this->resolveReference($validated)
        );

        $first = $movements->first();
        $message = $movements->count() > 1
            ? sprintf('Recorded %s across %d batches (earliest expiry first).', $first->movement_type->label(), $movements->count())
            : sprintf('%s of %d recorded.', $first->movement_type->label(), $first->quantity);

        return redirect()->route('inventory.stock-movements')->with('success', $message);
    }

    /**
     * The counterparty a movement points at, if its type has one.
     *
     * An issuance names the ward or department that received the stock; a
     * return names the supplier it went back to. Both ride the existing
     * `reference_type`/`reference_id` morph rather than dedicated columns.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveReference(array $validated): ?Model
    {
        return match ($validated['movement_type']) {
            'issuance' => StorageLocation::find($validated['issued_to_location_id'] ?? null),
            'return_to_supplier' => Supplier::find($validated['return_supplier_id'] ?? null),
            default => null,
        };
    }
}
