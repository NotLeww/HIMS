<?php

namespace App\Enums;

use App\Models\InventoryItem;
use App\Models\MaterialRequisition;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\WarehouseTask;
use App\Support\AuthenticationPanel;

/**
 * Allowlisted notification destinations. Notification data never contains an
 * arbitrary URL, and access is checked again when a user follows the link.
 */
enum NotificationDestination: string
{
    case Dashboard = 'dashboard';
    case InventoryAlerts = 'inventory_alerts';
    case MaterialRequisition = 'material_requisition';
    case InventoryAdjustments = 'inventory_adjustments';
    case Procurement = 'procurement';
    case Import = 'import';
    case Profile = 'profile';
    case QualityControl = 'quality_control';
    case GoodsReceipt = 'goods_receipt';
    case WarehouseTask = 'warehouse_task';

    public function isAuthorizedFor(User $user): bool
    {
        return match ($this) {
            self::Dashboard => $user->isActive(),
            self::Profile => true,
            self::InventoryAlerts => $user->hasPermission(Permission::AcknowledgeAlerts),
            self::MaterialRequisition => $user->hasPermission(Permission::ApproveRequisition),
            self::InventoryAdjustments => $user->hasPermission(Permission::ApproveAdjustment),
            self::Procurement => $user->hasPermission(Permission::ViewProcurement),
            self::Import => $user->hasPermission(Permission::ManageItems)
                || $user->hasPermission(Permission::ManageLocations)
                || $user->hasPermission(Permission::ManageSuppliers),
            self::QualityControl => $user->hasPermission(Permission::InspectStock),
            self::GoodsReceipt => $user->hasPermission(Permission::ViewInventory)
                || $user->hasPermission(Permission::ReceivePurchaseOrder),
            self::WarehouseTask => $user->hasPermission(Permission::ViewWarehouseTasks),
        };
    }

    /** @param array<string, scalar|null> $parameters */
    public function isAvailable(array $parameters = []): bool
    {
        return match ($this) {
            self::InventoryAlerts => empty($parameters['item'])
                || InventoryItem::query()
                    ->whereKey((int) $parameters['item'])
                    ->when(! empty($parameters['batch']), fn ($query) => $query->whereHas(
                        'batches',
                        fn ($batches) => $batches->whereKey((int) $parameters['batch'])
                    ))
                    ->exists(),
            self::MaterialRequisition => MaterialRequisition::query()
                ->whereKey((int) ($parameters['requisition'] ?? 0))
                ->exists(),
            self::WarehouseTask => WarehouseTask::query()
                ->whereKey((int) ($parameters['task'] ?? 0))->exists(),
            self::Procurement => empty($parameters['purchase_order'])
                || PurchaseOrder::query()
                    ->visibleInPipeline()
                    ->whereKey((int) $parameters['purchase_order'])
                    ->exists(),
            default => true,
        };
    }

    /** @param array<string, scalar|null> $parameters */
    public function url(User $user, array $parameters = []): string
    {
        return match ($this) {
            self::Dashboard => route(AuthenticationPanel::forRole($user->role)->dashboardRoute()),
            self::InventoryAlerts => route('inventory.alerts', array_filter([
                'manage_item' => $parameters['item'] ?? null,
                'manage_batch' => $parameters['batch'] ?? null,
                'alert_type' => $parameters['alert_type'] ?? null,
            ], fn ($value): bool => $value !== null)),
            self::MaterialRequisition => route('inventory.requisitions.show', [
                'requisition' => (int) ($parameters['requisition'] ?? 0),
            ]),
            self::InventoryAdjustments => route('inventory.adjustments'),
            self::Procurement => $this->procurementUrl($parameters),
            self::Import => route('inventory.import.index'),
            self::Profile => route('profile.edit'),
            self::QualityControl => route('inventory.qc.index'),
            self::GoodsReceipt => ! empty($parameters['grn'])
                ? route('inventory.receiving.show', ['goodsReceiptNote' => (int) $parameters['grn']])
                : route('inventory.receiving.index'),
            self::WarehouseTask => route('inventory.warehouse-tasks.show', [
                'warehouseTask' => (int) ($parameters['task'] ?? 0),
            ]),
        };
    }

    /** @param array<string, scalar|null> $parameters */
    private function procurementUrl(array $parameters): string
    {
        $purchaseOrderId = (int) ($parameters['purchase_order'] ?? 0);
        $tab = $purchaseOrderId > 0 ? 'orders_revisions' : ($parameters['tab'] ?? null);
        $url = route('inventory.purchases', array_filter([
            'tab' => $tab,
            'po_id' => $purchaseOrderId > 0 ? $purchaseOrderId : null,
            'open_po' => $purchaseOrderId > 0 ? $purchaseOrderId : null,
            'po_search' => $purchaseOrderId > 0 ? null : ($parameters['po_search'] ?? null),
            'approval_search' => $parameters['approval_search'] ?? null,
        ]));

        return $purchaseOrderId > 0 ? $url.'#purchase-orders' : $url;
    }
}
