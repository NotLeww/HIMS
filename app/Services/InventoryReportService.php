<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\InventoryItem;
use App\Models\ItemBatch;
use App\Models\ItemStockLevel;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers behind /inventory/reports.
 *
 * Four questions, which is what the screen it feeds is organised around:
 * what do we hold and what is it worth, which items are in trouble, what has
 * procurement spent, and what has moved.
 *
 * Nothing here writes. Every figure is derived from the same tables the
 * operational screens are built from — `item_stock_levels` for balances,
 * `stock_movements` for activity, `purchase_orders` for spend — so a report
 * cannot drift from the records it summarises.
 *
 * Aggregation is left to the database with plain SUM/COUNT/GROUP BY, which
 * behaves the same on TiDB in production as on SQLite under test. Date
 * functions are deliberately avoided for that reason. `toBase()` skips model
 * hydration: these rows are totals, not entities, and an alias like `value`
 * would otherwise collide with a real attribute.
 */
class InventoryReportService
{
    /** Window the movement and spend sections cover unless asked otherwise. */
    public const DEFAULT_PERIOD_DAYS = 30;

    /** The periods the screen offers. Anything else is clamped into range. */
    public const PERIOD_OPTIONS = [
        7 => 'Last 7 days',
        30 => 'Last 30 days',
        90 => 'Last 90 days',
        365 => 'Last 12 months',
    ];

    /**
     * Every figure the screen needs, in one call.
     *
     * @return array<string, mixed>
     */
    public function build(int $days = self::DEFAULT_PERIOD_DAYS): array
    {
        $days = max(1, min(365, $days));
        $since = now()->subDays($days);

        $stockStatus = $this->stockStatus();
        $movementsByType = $this->movementsByType($since);

        return [
            'period' => ['days' => $days, 'from' => $since, 'to' => now()],
            'summary' => $this->summary($stockStatus),
            'stockStatus' => $stockStatus,
            'expiry' => $this->expiryExposure(),
            'valuationByCategory' => $this->valuationByCategory(),
            'stockByLocation' => $this->stockByLocation(),
            'spend' => $this->procurementSpend($since),
            'spendBySupplier' => $this->spendBySupplier($since),
            'movementsByType' => $movementsByType,
            'movementTotals' => $this->movementTotals($movementsByType),
            'topConsumedItems' => $this->topConsumedItems($since),
            'recentMovements' => $this->recentMovements($since),
        ];
    }

    /**
     * The headline tiles: catalogue size, units held, what it is worth, and
     * how many items are asking for attention.
     *
     * @param  array<string, array{items: int, units: int, value: float}>  $stockStatus
     * @return array<string, mixed>
     */
    public function summary(?array $stockStatus = null): array
    {
        $stockStatus ??= $this->stockStatus();

        $totals = InventoryItem::query()
            ->selectRaw('count(*) as items')
            ->selectRaw('coalesce(sum(quantity_on_hand), 0) as units')
            ->selectRaw('coalesce(sum(reserved_quantity), 0) as reserved')
            ->selectRaw('coalesce(sum(quantity_on_hand * coalesce(unit_cost, 0)), 0) as value')
            ->toBase()
            ->first();

        return [
            'items' => (int) ($totals->items ?? 0),
            'units_on_hand' => (int) ($totals->units ?? 0),
            'reserved_units' => (int) ($totals->reserved ?? 0),
            'stock_value' => (float) ($totals->value ?? 0),
            'needs_attention' => $stockStatus['low_stock']['items'] + $stockStatus['out_of_stock']['items'],
        ];
    }

    /**
     * Items bucketed into in stock / low stock / out of stock.
     *
     * Worked out from the quantities through the item's own predicates rather
     * than read off `inventory_items.status`. That column is a cache written
     * when stock moves through InventoryAutomationService, so an item created
     * by hand and never moved still reads whatever it was created with — the
     * report would then disagree with the item screen sitting next to it.
     *
     * @return array<string, array{items: int, units: int, value: float}>
     */
    public function stockStatus(): array
    {
        $buckets = [
            'in_stock' => ['items' => 0, 'units' => 0, 'value' => 0.0],
            'low_stock' => ['items' => 0, 'units' => 0, 'value' => 0.0],
            'out_of_stock' => ['items' => 0, 'units' => 0, 'value' => 0.0],
        ];

        InventoryItem::query()
            ->select(['id', 'quantity_on_hand', 'reorder_level', 'unit_cost'])
            ->each(function (InventoryItem $item) use (&$buckets) {
                $key = match (true) {
                    $item->isOutOfStock() => 'out_of_stock',
                    $item->isLowStock() => 'low_stock',
                    default => 'in_stock',
                };

                $buckets[$key]['items']++;
                $buckets[$key]['units'] += (int) $item->quantity_on_hand;
                $buckets[$key]['value'] += (int) $item->quantity_on_hand * (float) $item->unit_cost;
            });

        return $buckets;
    }

    /**
     * Stock that is expired or heading that way, and what it is worth.
     *
     * Batches holding nothing are dropped: an expired batch with no units left
     * is a closed record, not money at risk, and listing it would bury the
     * ones that still matter.
     *
     * @return array<string, mixed>
     */
    public function expiryExposure(): array
    {
        $batches = ItemBatch::query()
            ->active()
            ->whereNotNull('expiry_date')
            ->with('item')
            ->withSum('stockLevels as units_on_hand', 'quantity')
            ->fefo()
            ->get()
            ->filter(fn (ItemBatch $batch) => (int) $batch->units_on_hand > 0);

        $expired = $batches->filter(fn (ItemBatch $batch) => $batch->isExpired());
        $expiringSoon = $batches->filter(fn (ItemBatch $batch) => $batch->isExpiringSoon());

        // Batch cost where the receipt recorded one, item cost otherwise.
        $value = fn (Collection $set) => (float) $set->sum(
            fn (ItemBatch $batch) => (int) $batch->units_on_hand
                * (float) ($batch->unit_cost ?? $batch->item?->unit_cost ?? 0)
        );

        return [
            'expired' => [
                'batches' => $expired->count(),
                'units' => (int) $expired->sum(fn (ItemBatch $batch) => (int) $batch->units_on_hand),
                'value' => $value($expired),
            ],
            'expiring_soon' => [
                'batches' => $expiringSoon->count(),
                'units' => (int) $expiringSoon->sum(fn (ItemBatch $batch) => (int) $batch->units_on_hand),
                'value' => $value($expiringSoon),
            ],
            'rows' => $expired->merge($expiringSoon)->take(10)->values(),
        ];
    }

    /**
     * What the catalogue is worth, broken down by category.
     *
     * @return Collection<int, object>
     */
    public function valuationByCategory(): Collection
    {
        return InventoryItem::query()
            ->leftJoin('item_categories', 'item_categories.id', '=', 'inventory_items.category_id')
            ->selectRaw("coalesce(item_categories.name, 'Uncategorised') as category")
            ->selectRaw('count(*) as items')
            ->selectRaw('coalesce(sum(inventory_items.quantity_on_hand), 0) as units')
            ->selectRaw('coalesce(sum(inventory_items.quantity_on_hand * coalesce(inventory_items.unit_cost, 0)), 0) as value')
            // Grouped on the column rather than the alias: every uncategorised
            // item has a null name, so they all fall into one bucket anyway.
            ->groupBy('item_categories.id', 'item_categories.name')
            ->orderByDesc('value')
            ->toBase()
            ->get();
    }

    /**
     * Where the stock physically is, and how full each place is.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function stockByLocation(): Collection
    {
        return ItemStockLevel::query()
            ->join('storage_locations', 'storage_locations.id', '=', 'item_stock_levels.storage_location_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'item_stock_levels.item_id')
            ->selectRaw('storage_locations.name as location')
            ->selectRaw('storage_locations.code as code')
            ->selectRaw('storage_locations.capacity as capacity')
            ->selectRaw('count(distinct item_stock_levels.item_id) as items')
            ->selectRaw('coalesce(sum(item_stock_levels.quantity), 0) as units')
            ->selectRaw('coalesce(sum(item_stock_levels.quantity * coalesce(inventory_items.unit_cost, 0)), 0) as value')
            ->groupBy('storage_locations.id', 'storage_locations.name', 'storage_locations.code', 'storage_locations.capacity')
            ->orderByDesc('units')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'location' => $row->location,
                'code' => $row->code,
                'items' => (int) $row->items,
                'units' => (int) $row->units,
                'value' => (float) $row->value,
                'capacity' => $row->capacity === null ? null : (int) $row->capacity,
                // Null where no capacity is configured — "unknown" is not 0%.
                'utilisation' => $row->capacity
                    ? round(((int) $row->units / (int) $row->capacity) * 100, 1)
                    : null,
            ]);
    }

    /**
     * Procurement spend: what was committed, what has landed, what is still out.
     *
     * Orders are dated by `requested_at` (when the commitment was made) and
     * receipts by `received_at` (when the goods arrived), so an order raised
     * last quarter and received this one counts once in each place — which is
     * what a spend report is supposed to show.
     *
     * Outstanding is deliberately not windowed: an order left open eight
     * months ago is exactly the one a report should surface.
     *
     * @return array<string, mixed>
     */
    public function procurementSpend(Carbon $since): array
    {
        $totals = fn ($query) => $query
            ->selectRaw('count(*) as orders')
            ->selectRaw('coalesce(sum(total_amount), 0) as value')
            ->toBase()
            ->first();

        $ordered = $totals(PurchaseOrder::query()->where('requested_at', '>=', $since));
        $received = $totals(PurchaseOrder::query()
            ->where('status', 'received')
            ->where('received_at', '>=', $since));
        $outstanding = $totals(PurchaseOrder::query()->whereNotIn('status', ['received', 'cancelled']));

        $orderCount = (int) ($ordered->orders ?? 0);

        return [
            'ordered' => ['orders' => $orderCount, 'value' => (float) ($ordered->value ?? 0)],
            'received' => [
                'orders' => (int) ($received->orders ?? 0),
                'value' => (float) ($received->value ?? 0),
            ],
            'outstanding' => [
                'orders' => (int) ($outstanding->orders ?? 0),
                'value' => (float) ($outstanding->value ?? 0),
            ],
            'average_order_value' => $orderCount > 0
                ? round((float) ($ordered->value ?? 0) / $orderCount, 2)
                : 0.0,
        ];
    }

    /**
     * Spend split by vendor, biggest first.
     *
     * @return Collection<int, object>
     */
    public function spendBySupplier(Carbon $since): Collection
    {
        return PurchaseOrder::query()
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.requested_at', '>=', $since)
            ->selectRaw("coalesce(suppliers.name, 'Unassigned') as supplier")
            ->selectRaw('count(*) as orders')
            ->selectRaw("coalesce(sum(case when purchase_orders.status = 'received' then 1 else 0 end), 0) as received_orders")
            ->selectRaw('coalesce(sum(purchase_orders.total_amount), 0) as value')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('value')
            ->limit(10)
            ->toBase()
            ->get();
    }

    /**
     * Activity in the window, one row per movement type.
     *
     * Every type is listed even when it did not occur. A type that simply
     * disappears reads as "not tracked"; a zero reads as "nothing happened",
     * which is the true statement.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function movementsByType(Carbon $since): Collection
    {
        $rows = StockMovement::query()
            ->where('moved_at', '>=', $since)
            ->selectRaw('movement_type')
            ->selectRaw('count(*) as movements')
            ->selectRaw('coalesce(sum(quantity), 0) as units')
            ->selectRaw('coalesce(sum(quantity * coalesce(unit_cost, 0)), 0) as value')
            ->groupBy('movement_type')
            ->toBase()
            ->get()
            ->keyBy('movement_type');

        return collect(MovementType::cases())->map(fn (MovementType $type) => [
            'type' => $type,
            'movements' => (int) ($rows[$type->value]->movements ?? 0),
            'units' => (int) ($rows[$type->value]->units ?? 0),
            'value' => (float) ($rows[$type->value]->value ?? 0),
        ]);
    }

    /**
     * The one-line reading of the movement table.
     *
     * Inbound counts stock_in only and outbound counts consumption only.
     * A transfer touches two locations without changing what the hospital
     * holds, so adding it to either side would report stock appearing or
     * leaving when none did.
     *
     * @param  Collection<int, array<string, mixed>>  $movementsByType
     * @return array<string, mixed>
     */
    public function movementTotals(Collection $movementsByType): array
    {
        $by = fn (array $types, string $key) => $movementsByType
            ->whereIn('type', $types)
            ->sum($key);

        $consumption = MovementType::consumptionCases();

        return [
            'movements' => (int) $movementsByType->sum('movements'),
            'units_in' => (int) $by([MovementType::StockIn], 'units'),
            'units_out' => (int) $by($consumption, 'units'),
            'consumption_value' => (float) $by($consumption, 'value'),
            'transfers' => (int) $by([MovementType::Transfer], 'movements'),
            'disposals' => (int) $by([MovementType::Disposal], 'units'),
        ];
    }

    /**
     * The items the hospital actually gets through, by units consumed.
     *
     * @return Collection<int, object>
     */
    public function topConsumedItems(Carbon $since): Collection
    {
        return StockMovement::query()
            ->join('inventory_items', 'inventory_items.id', '=', 'stock_movements.item_id')
            ->whereIn('stock_movements.movement_type', MovementType::consumptionValues())
            ->where('stock_movements.moved_at', '>=', $since)
            ->selectRaw('inventory_items.name as item')
            ->selectRaw('inventory_items.sku as sku')
            ->selectRaw('inventory_items.unit as unit')
            ->selectRaw('inventory_items.quantity_on_hand as on_hand')
            ->selectRaw('count(*) as movements')
            ->selectRaw('coalesce(sum(stock_movements.quantity), 0) as units')
            // The cost recorded on the movement, falling back to the item's
            // current cost for rows written before that column was populated.
            ->selectRaw('coalesce(sum(stock_movements.quantity * coalesce(stock_movements.unit_cost, inventory_items.unit_cost, 0)), 0) as value')
            ->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.sku', 'inventory_items.unit', 'inventory_items.quantity_on_hand')
            ->orderByDesc('units')
            ->limit(10)
            ->toBase()
            ->get();
    }

    /**
     * The movement ledger for the window, newest first.
     *
     * Hydrated as models rather than a raw aggregate: the view needs the
     * movement_type cast, the location names and the polymorphic reference
     * that carries the ward or vendor on an issuance or a return.
     *
     * @return Collection<int, StockMovement>
     */
    public function recentMovements(Carbon $since, int $limit = 15): Collection
    {
        return StockMovement::query()
            ->with(['item', 'fromLocation', 'toLocation', 'user', 'reference'])
            ->where('moved_at', '>=', $since)
            ->latest('moved_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
