<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Models\InventoryItem;
use App\Models\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    public function index(): View
    {
        $items = InventoryItem::with('supplier')->latest()->get();

        return view('inventory.items.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:255', 'unique:inventory_items'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'quantity_on_hand' => ['nullable', 'integer'],
            'reorder_level' => ['nullable', 'integer'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'warehouse_name' => ['nullable', 'string', 'max:255'],
        ]);

        InventoryItem::create($validated);

        return redirect()->route('inventory.items')->with('success', 'Inventory item created successfully.');
    }
}
