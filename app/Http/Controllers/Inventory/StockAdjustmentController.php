<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function index(): View
    {
        $items = InventoryItem::latest()->get();

        return view('inventory.adjustments.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'adjustment_type' => ['required', 'in:increase,decrease,correction'],
            'quantity' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $item = InventoryItem::findOrFail($validated['item_id']);
        $quantity = (int) $validated['quantity'];

        if ($validated['adjustment_type'] === 'increase') {
            $item->quantity_on_hand += $quantity;
        } elseif ($validated['adjustment_type'] === 'decrease') {
            $item->quantity_on_hand = max(0, $item->quantity_on_hand - $quantity);
        } else {
            $item->quantity_on_hand = $quantity;
        }

        $schemaBuilder = $item->getConnection()->getSchemaBuilder();
        if ($schemaBuilder->hasColumn($item->getTable(), 'total_value')) {
            $item->total_value = round($item->quantity_on_hand * (float) ($item->unit_cost ?? 0), 2);
        }

        if ($schemaBuilder->hasColumn($item->getTable(), 'unit_cost')) {
            $item->unit_cost = (float) ($item->unit_cost ?? 0);
        }

        $item->save();

        return redirect()->route('inventory.adjustments')->with('success', 'Stock adjustment applied successfully.');
    }
}
