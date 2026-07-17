<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Models\InventoryItem;
use App\Models\Models\ProcurementRequest;
use App\Models\Models\Supplier;
use App\Models\Models\SupplierQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcurementController extends Controller
{
    public function index(): View
    {
        $requests = ProcurementRequest::with('item')->latest('requested_at')->get();
        $quotes = SupplierQuote::with(['procurementRequest', 'supplier'])->latest()->get();
        $items = InventoryItem::all();
        $suppliers = Supplier::where('status', 'active')->get();

        return view('inventory.purchases.index', compact('requests', 'quotes', 'items', 'suppliers'));
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'item_id' => ['required', 'exists:inventory_items,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'approved_by' => ['nullable', 'string', 'max:255'],
            'approval_notes' => ['nullable', 'string', 'max:255'],
            'evaluation_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'evaluation_status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        ProcurementRequest::create([
            ...$validated,
            'request_number' => 'REQ-'.now()->format('YmdHis'),
        ]);

        return redirect()->route('inventory.purchases')->with('success', 'Procurement request created successfully.');
    }

    public function storeQuote(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'procurement_request_id' => ['required', 'exists:procurement_requests,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'quoted_price' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        SupplierQuote::create($validated);

        return redirect()->route('inventory.purchases')->with('success', 'Supplier quote submitted successfully.');
    }

    public function approve(Request $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $validated = $request->validate([
            'approved_by' => ['nullable', 'string', 'max:255'],
            'approval_notes' => ['nullable', 'string', 'max:255'],
            'evaluation_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'evaluation_status' => ['nullable', 'in:pending,approved,rejected'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
        ]);

        $procurementRequest->fill($validated);
        $procurementRequest->status = 'approved';
        $procurementRequest->save();

        return redirect()->route('inventory.purchases')->with('success', 'Procurement request approved.');
    }
}
