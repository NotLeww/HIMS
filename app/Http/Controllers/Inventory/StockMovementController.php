<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Models\InventoryItem;
use App\Models\Models\StockMovement;
use App\Models\Models\StorageLocation;
use App\Services\InventoryAutomationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class StockMovementController extends Controller
{
    public function __construct(private readonly InventoryAutomationService $automationService)
    {
    }

    public function index(): View
    {
        $movements = StockMovement::with(['item', 'fromLocation', 'toLocation'])->latest('moved_at')->get();
        $items = InventoryItem::all();
        $locations = StorageLocation::all();

        return view('inventory.stock_movements.index', compact('movements', 'items', 'locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'movement_type' => ['required', 'in:stock_in,stock_out,transfer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'from_location_id' => ['nullable', 'exists:storage_locations,id'],
            'to_location_id' => ['nullable', 'exists:storage_locations,id'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->automationService->recordMovement($validated, auth()->id());
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()->route('inventory.stock-movements')->with('success', 'Stock movement recorded successfully.');
    }
}
