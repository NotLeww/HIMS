<?php

namespace App\Observers;

use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\HimsNotificationService;

class PurchaseOrderObserver
{
    public function created(PurchaseOrder $purchaseOrder): void
    {
        $this->notifySupplierIfIssued($purchaseOrder);
    }

    public function updated(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->wasChanged('status')) {
            $this->notifySupplierIfIssued($purchaseOrder);
        }
    }

    private function notifySupplierIfIssued(PurchaseOrder $purchaseOrder): void
    {
        if (! in_array($purchaseOrder->statusEnum(), [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Dispatched], true)) {
            return;
        }

        $purchaseOrder->supplier?->users()
            ->where('status', 'active')
            ->eachById(fn ($user) => app(HimsNotificationService::class)->sendToUser(
                $user,
                "supplier-po-issued:{$purchaseOrder->id}:{$purchaseOrder->statusEnum()->value}",
                'Purchase order requires action',
                "{$purchaseOrder->po_number} is ready for supplier acknowledgement.",
                NotificationPriority::Info,
                NotificationDestination::Dashboard,
            ));
    }
}
