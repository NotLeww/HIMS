<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Permission;
use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemCategory;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AiDemandForecastService;
use App\Services\DemandForecastService;
use App\Services\InventoryReportService;
use App\Support\MetricDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class InventoryController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly InventoryReportService $reports,
        private readonly AiDemandForecastService $aiForecasts,
    ) {}

    /**
     * The read-only screens, each gated on what it actually shows.
     *
     * index() and live() are deliberately left on plain `auth`: /dashboard is
     * where login redirects, so a 403 there would put a signed-in user in a
     * loop with no way out. It shows counts and alerts, nothing a member of
     * staff should not see, and the panels inside it are individually gated.
     *
     * @return array<int, Middleware|string>
     */
    public static function middleware(): array
    {
        return [
            'auth:web,admin,super_admin',
            new Middleware('can:'.Permission::ViewInventory->value, only: ['stock', 'alerts']),
            new Middleware('can:'.Permission::ViewReports->value, only: ['logistics']),
            new Middleware('can:'.Permission::ManageSuppliers->value, only: ['suppliers']),
            new Middleware('can:'.Permission::ManageProcurement->value, only: ['purchases']),
        ];
    }

    public function index(?Request $request = null): View
    {
        $request ??= request();

        $canViewSuppliers = $request->user()->can(Permission::ViewSuppliers->value);
        $canViewProcurementFinancials = $request->user()->can(Permission::ViewProcurementSensitiveData->value);
        $canViewForecasts = $request->user()->can(Permission::ViewReports->value);
        $aiForecast = $canViewForecasts ? $this->dashboardForecast($request->user()) : null;
        $forecastCategories = $canViewForecasts
            ? ItemCategory::query()->active()->orderBy('name')->get(['id', 'name'])
            : collect();

        $totalSuppliers = $canViewSuppliers ? Supplier::where('status', '!=', 'archived')->count() : null;
        $activeSuppliers = $canViewSuppliers ? Supplier::where('status', 'active')->count() : null;
        $inactiveSuppliers = $canViewSuppliers ? Supplier::where('status', 'inactive')->count() : null;
        $storageLocations = StorageLocation::count();
        $recentMovements = StockMovement::with(['item', 'fromLocation', 'toLocation'])
            ->latest('moved_at')
            ->latest('id')
            ->take(6)
            ->get();

        $pendingPurchaseOrders = $canViewProcurementFinancials
            ? PurchaseOrder::with(['supplier', 'item'])
                ->whereIn('status', PurchaseOrderStatus::openValues())
                ->latest('requested_at')
                ->latest('id')
                ->take(5)
                ->get()
            : collect();

        $pendingPoCount = $canViewProcurementFinancials
            ? PurchaseOrder::whereIn('status', PurchaseOrderStatus::openValues())->count()
            : null;

        return view('dashboard', array_merge($this->liveSnapshot($request), compact(
            'totalSuppliers',
            'activeSuppliers',
            'inactiveSuppliers',
            'storageLocations',
            'recentMovements',
            'pendingPurchaseOrders',
            'pendingPoCount',
            'aiForecast',
            'forecastCategories'
        )));
    }

    /**
     * The 30s poll behind the dashboard's live alert panel.
     *
     * Returns the compact alert summary as rendered HTML rather than JSON rows so the
     * markup stays defined in exactly one Blade partial, plus the counters
     * that sit in the stat tiles above it.
     */
    public function live(Request $request): JsonResponse
    {
        $snapshot = $this->liveSnapshot($request);

        return response()->json(array_filter([
            'alertsHtml' => view('inventory.partials.dashboard-alerts', $snapshot)->render(),
            'openAlertCount' => $snapshot['openAlertCount'],
            'expiringSoonCount' => $snapshot['expiringSoonCount'],
            'criticalExpiryCount' => $snapshot['criticalExpiryCount'],
            'totalItems' => $snapshot['totalItems'],
            'lowStockItems' => $snapshot['lowStockItems'],
            'outOfStockItems' => $snapshot['outOfStockItems'],
            'totalOnHand' => $snapshot['totalOnHand'],
            'totalInventoryValue' => $snapshot['totalInventoryValue'],
            'trackedItemDetails' => $snapshot['trackedItemDetails'],
            'attentionItemDetails' => $snapshot['attentionItemDetails'],
            'expiringBatchDetails' => $snapshot['expiringBatchDetails'],
            'inventoryValueDetails' => $snapshot['inventoryValueDetails'],
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * The forecast panel's data, filled in automatically on first view.
     *
     * /dashboard is where login lands, so waiting for somebody to press
     * Generate meant the forecast was missing on exactly the screen it is meant
     * to open on. Only the people who may generate a forecast start the model
     * call; a viewer who may read one but not generate it gets whatever is
     * already cached and the recorded-consumption table when nothing is.
     *
     * @return array<string, mixed>|null
     */
    private function dashboardForecast(User $user): ?array
    {
        $days = [DemandForecastService::DEFAULT_ANALYSIS_DAYS, DemandForecastService::DEFAULT_FORECAST_DAYS];

        $forecast = $user->can(Permission::GenerateForecasts->value)
            ? $this->aiForecasts->ensure($user, ...$days)
            : $this->aiForecasts->cached(...$days);

        return $forecast;
    }

    /**
     * Everything on the dashboard that moves when stock moves.
     *
     * Shared by the full page render and the poll so the two can never
     * disagree about what "current" means.
     *
     * @return array<string, mixed>
     */
    private function liveSnapshot(?Request $request = null): array
    {
        $request ??= request();
        $stockStatus = $this->reports->stockStatus();
        $summary = $this->reports->summary($stockStatus);
        $stockedExpiryBatches = fn ($query) => $query
            ->active()
            ->whereHas('item', fn ($item) => $item->where('status', '!=', 'archived'))
            ->whereHas('stockLevels', fn ($stock) => $stock->where('quantity', '>', 0));
        $expiringSoonCount = $stockedExpiryBatches(ItemBatch::query())
            ->expiringSoon()
            ->count();
        $criticalExpiryCount = $stockedExpiryBatches(ItemBatch::query())
            ->expiringSoon(ItemBatch::CRITICAL_EXPIRY_DAYS)
            ->count();
        $expiringBatches = $stockedExpiryBatches(ItemBatch::query())
            ->expiringSoon()
            ->with('item:id,name,sku')
            ->orderBy('expiry_date')
            ->take(5)
            ->get();

        // Use current balances, which are also the source of truth on the
        // inventory alerts page. Persisted alert rows can lag behind imports
        // or older data that predates real-time alert synchronization.
        $attentionItems = InventoryItem::query()
            ->where('status', '!=', 'archived')
            ->where(function ($query): void {
                $query->where('quantity_on_hand', '<=', 0)
                    ->orWhere(function ($lowStock): void {
                        $lowStock->where('reorder_level', '>', 0)
                            ->whereColumn('quantity_on_hand', '<=', 'reorder_level');
                    });
            })
            ->orderBy('quantity_on_hand')
            ->orderBy('name')
            ->take(5)
            ->get();

        $catalogItems = InventoryItem::query()
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->take(5)
            ->get(['id', 'name', 'sku', 'quantity_on_hand']);
        $canViewFinancials = $request->user()->can(Permission::ViewProcurementSensitiveData->value);
        $topValueItems = $canViewFinancials
            ? InventoryItem::query()
                ->where('status', '!=', 'archived')
                ->orderByRaw('(quantity_on_hand * unit_cost) desc')
                ->orderBy('name')
                ->take(5)
                ->get(['id', 'name', 'sku', 'quantity_on_hand', 'unit_cost'])
            : collect();

        $trackedItemDetails = MetricDetails::from(
            $catalogItems,
            $summary['items'],
            fn (InventoryItem $item): string => $item->name.' ('.$item->sku.') — '.number_format($item->quantity_on_hand).' on hand',
            'No active inventory items',
        );
        $attentionItemDetails = MetricDetails::from(
            $attentionItems,
            $summary['needs_attention'],
            fn (InventoryItem $item): string => $item->name.' — '.number_format($item->quantity_on_hand).' on hand',
            'No items currently need attention',
        );
        $expiringBatchDetails = MetricDetails::from(
            $expiringBatches,
            $expiringSoonCount,
            fn (ItemBatch $batch): string => ($batch->item?->name ?? 'Unknown item').' · '.$batch->batch_number.' — '.$batch->expiry_date?->format('M d, Y'),
            'No stocked batches expire within 90 days',
        );
        $inventoryValueDetails = $canViewFinancials
            ? MetricDetails::from(
                $topValueItems,
                $summary['items'],
                fn (InventoryItem $item): string => $item->name.' — ₱'.number_format($item->quantity_on_hand * $item->unit_cost, 2),
                'No inventory value recorded',
            )
            : [];

        return [
            'attentionItems' => $attentionItems,
            'trackedItemDetails' => $trackedItemDetails,
            'attentionItemDetails' => $attentionItemDetails,
            'expiringBatchDetails' => $expiringBatchDetails,
            'inventoryValueDetails' => $inventoryValueDetails,
            'openAlertCount' => $summary['needs_attention'],
            'expiringSoonCount' => $expiringSoonCount,
            'criticalExpiryCount' => $criticalExpiryCount,
            'totalItems' => $summary['items'],
            'lowStockItems' => $summary['needs_attention'],
            'outOfStockItems' => $stockStatus['out_of_stock']['items'],
            'totalOnHand' => $summary['units_on_hand'],
            'totalInventoryValue' => $canViewFinancials
                ? $summary['stock_value']
                : null,
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

    public function stock(): RedirectResponse
    {
        return redirect()->route('inventory.items');
    }

    public function alerts(): View
    {
        return view('inventory.alerts.index');
    }

    public function logistics(): View
    {
        return view('inventory.logistics.index');
    }
}
