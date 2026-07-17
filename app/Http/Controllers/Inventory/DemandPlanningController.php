<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Models\DemandPlan;
use App\Models\Models\InventoryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemandPlanningController extends Controller
{
    public function index(): View
    {
        $plans = DemandPlan::with('item')->latest()->get();
        $items = InventoryItem::all();

        return view('inventory.purchases.index', compact('plans', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'exists:inventory_items,id'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'historical_usage' => ['required', 'integer', 'min:0'],
            'upcoming_need' => ['required', 'integer', 'min:0'],
            'reorder_point' => ['required', 'integer', 'min:0'],
            'trigger_reason' => ['nullable', 'string', 'max:255'],
        ]);

        DemandPlan::create([
            ...$validated,
            'plan_number' => 'PLAN-'.now()->format('YmdHis'),
            'status' => 'draft',
        ]);

        return redirect()->route('inventory.purchases')->with('success', 'Demand plan created successfully.');
    }
}
