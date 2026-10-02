<?php

namespace App\Services\Inventory;

use App\Enums\AuditAction;
use App\Enums\RequisitionStatus;
use App\Models\CostCenter;
use App\Models\InventoryItem;
use App\Models\ProcurementCategory;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReplenishmentDaemon
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Safety Stock formula:
     * SS = Z * sqrt( Avg_Lead_Time * Variance_Demand + (Avg_Demand^2) * Variance_Lead_Time )
     * where Z = 1.645 (95% service level factor).
     */
    public function calculateSafetyStock(InventoryItem $item, ?float $serviceFactor = null): ?int
    {
        $leadTime = (float) $item->lead_time_days;
        $annualDemand = (float) $item->annual_demand;
        if ($leadTime <= 0 || $annualDemand <= 0) {
            return null;
        }

        $serviceFactor ??= (float) config('inventory.replenishment.service_factor');
        $avgDailyDemand = $annualDemand / 365.0;

        // Variability parameters (assuming standard deviation ~25% of mean if unrecorded)
        $stdDevDemand = max(1.0, $avgDailyDemand * (float) config('inventory.replenishment.demand_variability_rate'));
        $varianceDemand = pow($stdDevDemand, 2);

        $stdDevLeadTime = max(1.0, $leadTime * (float) config('inventory.replenishment.lead_time_variability_rate'));
        $varianceLeadTime = pow($stdDevLeadTime, 2);

        $term1 = $leadTime * $varianceDemand;
        $term2 = pow($avgDailyDemand, 2) * $varianceLeadTime;

        $ss = (int) ceil($serviceFactor * sqrt($term1 + $term2));

        return max(1, $ss);
    }

    /**
     * Dynamic Reorder Point formula:
     * ROP = (Avg_Daily_Demand * Avg_Lead_Time) + SS
     */
    public function calculateReorderPoint(InventoryItem $item): ?int
    {
        $leadTime = (float) $item->lead_time_days;
        $annualDemand = (float) $item->annual_demand;
        if ($leadTime <= 0 || $annualDemand <= 0) {
            return null;
        }

        $avgDailyDemand = $annualDemand / 365.0;

        $ss = $item->safety_stock > 0 ? (float) $item->safety_stock : $this->calculateSafetyStock($item);
        if ($ss === null) {
            return null;
        }

        $rop = (int) ceil(($avgDailyDemand * $leadTime) + $ss);

        return max(1, $rop);
    }

    /**
     * Economic Order Quantity formula:
     * EOQ = sqrt( (2 * Annual_Demand * PO_Order_Cost) / Annual_Holding_Cost )
     */
    public function calculateEconomicOrderQuantity(InventoryItem $item): ?int
    {
        $annualDemand = (float) $item->annual_demand;
        $unitCost = (float) $item->unit_cost;
        if ($annualDemand <= 0 || $unitCost <= 0) {
            return null;
        }

        $orderCost = (float) config('inventory.replenishment.order_cost');
        $holdingCostRate = (float) config('inventory.replenishment.holding_cost_rate');
        $annualHoldingCost = max(1.00, $unitCost * $holdingCostRate);

        $eoq = (int) ceil(sqrt((2.0 * $annualDemand * $orderCost) / $annualHoldingCost));

        return max(1, $eoq);
    }

    public function evaluateAndTriggerReplenishment(InventoryItem $item, ?User $actor = null): ?PurchaseRequest
    {
        return $this->evaluateAndReplenish($item, $actor);
    }

    /**
     * Check if an item requires replenishment and instantiate a draft Purchase Request if breached.
     */
    public function evaluateAndReplenish(InventoryItem $item, ?User $actor = null): ?PurchaseRequest
    {
        return DB::transaction(function () use ($item, $actor) {
            $lockedItem = InventoryItem::lockForUpdate()->findOrFail($item->id);

            $rop = $lockedItem->reorder_point > 0 ? $lockedItem->reorder_point : $this->calculateReorderPoint($lockedItem);
            $eoq = $lockedItem->economic_order_quantity > 0 ? $lockedItem->economic_order_quantity : $this->calculateEconomicOrderQuantity($lockedItem);

            if ($rop === null || $eoq === null) {
                throw new DomainException('Complete the item reorder point, EOQ, or their required demand, lead-time, and cost inputs before evaluating replenishment.');
            }

            $atp = $lockedItem->availableToPromise();

            // On-Order: open quantities from active unfulfilled Purchase Orders
            $onOrder = (int) PurchaseOrderLine::query()
                ->where('item_id', $lockedItem->id)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereNotIn('status', ['received', 'cancelled', 'rejected']))
                ->selectRaw('coalesce(sum(ordered_quantity - received_quantity), 0) as open_qty')
                ->value('open_qty');

            // Replenishment condition: (ATP + On-Order) <= ROP
            if (($atp + $onOrder) > $rop) {
                return null;
            }

            // Prevent duplicate open PRs for the same item
            $existingPr = PurchaseRequest::query()
                ->whereIn('status', [RequisitionStatus::Draft->value, RequisitionStatus::PendingApproval->value])
                ->whereHas('lines', fn ($q) => $q->where('item_id', $lockedItem->id))
                ->exists();

            if ($existingPr) {
                return null;
            }

            if (! $actor) {
                throw new DomainException('An authenticated requester is required to create a replenishment request.');
            }
            if (blank($lockedItem->unit) || (float) $lockedItem->unit_cost <= 0 || (int) $lockedItem->lead_time_days <= 0) {
                throw new DomainException('Complete the item unit, unit cost, and lead time before creating a replenishment request.');
            }

            $costCenter = CostCenter::resolveForDepartment($actor->department);
            if (! $costCenter) {
                throw new DomainException("No active cost center is mapped to the requester's department.");
            }

            $itemCategory = $lockedItem->category;
            $category = $itemCategory
                ? ProcurementCategory::query()
                    ->where('is_active', true)
                    ->where(fn ($query) => $query
                        ->where('code', $itemCategory->code)
                        ->orWhere('name', $itemCategory->name))
                    ->first()
                : null;
            if (! $category) {
                throw new DomainException('No active procurement category is mapped to the item category.');
            }

            $prNumber = 'PR-AUTO-'.now()->format('Ymd').'-'.Str::upper(Str::ulid());
            $totalEst = round($eoq * (float) $lockedItem->unit_cost, 2);

            $pr = PurchaseRequest::create([
                'pr_number' => $prNumber,
                'title' => "Automated Replenishment: {$lockedItem->name} (ROP Breached)",
                'description' => "Triggered automatically when ATP ({$atp}) + On-Order ({$onOrder}) <= ROP ({$rop}). Recommended EOQ: {$eoq} units.",
                'requester_id' => $actor->id,
                'cost_center_id' => $costCenter->id,
                'procurement_category_id' => $category->id,
                'procurement_method' => 'shopping',
                'total_estimated_amount' => $totalEst,
                'currency' => 'PHP',
                'priority' => ($atp <= 0) ? 'high' : 'medium',
                'status' => RequisitionStatus::Draft->value,
                'is_emergency' => false,
            ]);

            PurchaseRequestLine::create([
                'purchase_request_id' => $pr->id,
                'item_id' => $lockedItem->id,
                'line_number' => 1,
                'item_description' => $lockedItem->name.' ('.$lockedItem->sku.')',
                'quantity' => $eoq,
                'uom' => $lockedItem->unit,
                'estimated_unit_price' => $lockedItem->unit_cost,
                'estimated_total_price' => $totalEst,
                'need_by_date' => now()->addDays($lockedItem->lead_time_days),
                'is_contracted_catalog' => false,
            ]);

            $this->auditLogger->record(
                AuditAction::CreatedPurchaseRequest,
                actor: $actor,
                target: $pr,
                description: "Instantiated draft Purchase Request {$pr->pr_number} for {$lockedItem->name} (EOQ: {$eoq})",
                newValues: [
                    'pr_number' => $pr->pr_number,
                    'item_id' => $lockedItem->id,
                    'atp' => $atp,
                    'on_order' => $onOrder,
                    'rop' => $rop,
                    'eoq' => $eoq,
                ],
                source: 'user',
            );

            return $pr;
        });
    }
}
