<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AlertStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\PurchaseOrder;
use App\Models\StockAlert;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $totalSuppliers = Supplier::count();
        $activeSuppliers = Supplier::where('status', 'active')->count();
        $inactiveSuppliers = Supplier::where('status', 'inactive')->count();
        $totalItems = InventoryItem::count();
        $storageLocations = StorageLocation::count();
        $recentMovements = StockMovement::with(['item', 'fromLocation', 'toLocation'])
            ->latest('moved_at')
            ->latest('id')
            ->take(6)
            ->get();

        $pendingPurchaseOrders = PurchaseOrder::with(['supplier', 'item'])
            ->whereIn('status', ['draft', 'pending', 'submitted', 'approved'])
            ->latest('requested_at')
            ->take(5)
            ->get();

        $pendingPoCount = PurchaseOrder::whereIn('status', ['draft', 'pending', 'submitted', 'approved'])->count();

        // Batches inside their item's expiry alert window, or already expired.
        $expiringBatches = ItemBatch::with('item')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays(90))
            ->where('status', 'active')
            ->orderBy('expiry_date')
            ->take(5)
            ->get();

        return view('dashboard', array_merge($this->liveSnapshot(), compact(
            'totalSuppliers',
            'activeSuppliers',
            'inactiveSuppliers',
            'totalItems',
            'storageLocations',
            'recentMovements',
            'pendingPurchaseOrders',
            'pendingPoCount',
            'expiringBatches'
        )));
    }

    /**
     * The 30s poll behind the dashboard's live alert panel.
     *
     * Returns the alert table as rendered HTML rather than JSON rows so the
     * markup stays defined in exactly one Blade partial, plus the counters
     * that sit in the stat tiles above it.
     */
    public function live(): JsonResponse
    {
        $snapshot = $this->liveSnapshot();

        return response()->json([
            'alertsHtml' => view('inventory.partials.alerts-table', $snapshot)->render(),
            'openAlertCount' => $snapshot['openAlertCount'],
            'lowStockItems' => $snapshot['lowStockItems'],
            'outOfStockItems' => $snapshot['outOfStockItems'],
            'totalOnHand' => $snapshot['totalOnHand'],
            'totalInventoryValue' => $snapshot['totalInventoryValue'],
        ]);
    }

    /**
     * Everything on the dashboard that moves when stock moves.
     *
     * Shared by the full page render and the poll so the two can never
     * disagree about what "current" means.
     *
     * @return array<string, mixed>
     */
    private function liveSnapshot(): array
    {
        // Alerts still describing a live condition, worst severity first.
        $activeAlerts = StockAlert::with(['item', 'batch', 'location'])
            ->where('status', '!=', AlertStatus::Resolved)
            ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
            ->latest('created_at')
            ->take(6)
            ->get();

        return [
            'activeAlerts' => $activeAlerts,
            'openAlertCount' => StockAlert::where('status', AlertStatus::Open)->count(),
            'lowStockItems' => InventoryItem::whereIn('status', ['low_stock', 'out_of_stock'])->count(),
            'outOfStockItems' => InventoryItem::where('status', 'out_of_stock')->count(),
            'totalOnHand' => (int) InventoryItem::sum('quantity_on_hand'),
            'totalInventoryValue' => InventoryItem::get()->sum(
                fn ($item) => (float) $item->quantity_on_hand * (float) $item->unit_cost
            ),
        ];
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
