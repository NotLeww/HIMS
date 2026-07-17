<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Models\InventoryItem;
use App\Models\Models\StockMovement;
use App\Models\Models\StorageLocation;
use App\Models\Models\Supplier;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $totalSuppliers = Supplier::count();
        $activeSuppliers = Supplier::where('status', 'active')->count();
        $inactiveSuppliers = Supplier::where('status', 'inactive')->count();
        $totalItems = InventoryItem::count();
        $lowStockItems = InventoryItem::whereIn('status', ['low_stock', 'out_of_stock'])->count();
        $outOfStockItems = InventoryItem::where('status', 'out_of_stock')->count();
        $totalOnHand = InventoryItem::sum('quantity_on_hand');
        $totalInventoryValue = InventoryItem::get()->sum(function ($item) {
            return (float) $item->quantity_on_hand * (float) $item->unit_cost;
        });
        $storageLocations = StorageLocation::count();
        $recentMovements = StockMovement::with(['item', 'fromLocation', 'toLocation'])
            ->latest('moved_at')
            ->take(6)
            ->get();

        return view('dashboard', compact(
            'totalSuppliers',
            'activeSuppliers',
            'inactiveSuppliers',
            'totalItems',
            'lowStockItems',
            'outOfStockItems',
            'totalOnHand',
            'totalInventoryValue',
            'storageLocations',
            'recentMovements'
        ));
    }

    public function suppliers(): View
    {
        return view('inventory.suppliers.index');
    }

    public function purchases(): View
    {
        return view('inventory.purchases.index');
    }

    public function stock(): View
    {
        return view('inventory.stock.index');
    }

    public function alerts(): View
    {
        return view('inventory.alerts.index');
    }

    public function reports(): View
    {
        return view('inventory.reports.index');
    }

    public function logistics(): View
    {
        return view('inventory.logistics.index');
    }
}
