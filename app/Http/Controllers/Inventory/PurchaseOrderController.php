<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Models\InventoryItem;
use App\Models\Models\PurchaseOrder;
use App\Models\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(): View
    {
        $purchaseOrders = PurchaseOrder::with(['supplier', 'item'])->latest('requested_at')->get();
        $suppliers = Supplier::where('status', 'active')->get();
        $items = InventoryItem::all();

        return view('inventory.purchases.index', compact('purchaseOrders', 'suppliers', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $quantity = (int) $validated['quantity'];
        $unitCost = (float) $validated['unit_cost'];

        PurchaseOrder::create([
            ...$validated,
            'po_number' => 'PO-'.now()->format('YmdHis'),
            'total_amount' => round($quantity * $unitCost, 2),
        ]);

        return redirect()->route('inventory.purchases')->with('success', 'Purchase order created successfully.');
    }

    public function receive(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status === 'received') {
            return redirect()->route('inventory.purchases')->with('info', 'This purchase order has already been received.');
        }

        $item = $purchaseOrder->item;
        $item->quantity_on_hand += $purchaseOrder->quantity;

        $schemaBuilder = $item->getConnection()->getSchemaBuilder();
        $hasTotalValueColumn = $schemaBuilder->hasColumn($item->getTable(), 'total_value');
        $hasUnitCostColumn = $schemaBuilder->hasColumn($item->getTable(), 'unit_cost');

        if ($hasTotalValueColumn) {
            $item->total_value = round($item->quantity_on_hand * (float) ($item->unit_cost ?? 0), 2);
        }

        if ($hasUnitCostColumn) {
            $item->unit_cost = (float) ($item->unit_cost ?? $purchaseOrder->unit_cost);
        }

        $item->save();

        $purchaseOrder->status = 'received';
        $purchaseOrder->received_at = now();
        $purchaseOrder->save();

        return redirect()->route('inventory.purchases')->with('success', 'Goods received and inventory updated.');
    }
}
