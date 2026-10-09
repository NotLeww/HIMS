<?php

namespace App\Services\Procurement;

use App\Enums\ApprovalStepStatus;
use App\Enums\NotificationDestination;
use App\Enums\NotificationPriority;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\PurchaseOrder;
use App\Services\HimsNotificationService;

class PurchaseOrderApprovalReminderService
{
    private const LEAD_DAYS = 3;

    public function __construct(private readonly HimsNotificationService $notifications) {}

    public function send(): int
    {
        $today = today();
        $sent = 0;

        PurchaseOrder::query()
            ->with(['approvalChain.steps', 'createdBy'])
            ->whereIn('status', ['submitted', 'pending', 'pending_approval'])
            ->whereNotNull('delivery_date')
            ->whereDate('delivery_date', '<=', $today->copy()->addDays(self::LEAD_DAYS))
            ->eachById(function (PurchaseOrder $purchaseOrder) use ($today, &$sent): void {
                $chain = $purchaseOrder->approvalChain;
                if ($chain && $chain->status !== 'pending') {
                    return;
                }

                $deliveryDate = $purchaseOrder->delivery_date->startOfDay();
                $overdue = $deliveryDate->lt($today);
                $stage = $overdue ? 'overdue' : 'due-soon';
                $priority = $overdue ? NotificationPriority::Critical : NotificationPriority::Warning;
                $title = $overdue ? 'Overdue PO approval' : 'PO approval due soon';
                $message = $overdue
                    ? "Purchase Order {$purchaseOrder->po_number} passed its expected delivery date of {$deliveryDate->toFormattedDateString()}. Update the date and approve or reject it."
                    : "Purchase Order {$purchaseOrder->po_number} is still awaiting approval before its expected delivery on {$deliveryDate->toFormattedDateString()}.";
                $dedupeKey = "purchase-order:{$purchaseOrder->id}:approval-reminder:{$stage}:{$deliveryDate->toDateString()}";
                $routeParameters = [
                    'purchase_order' => $purchaseOrder->id,
                    'po_search' => $purchaseOrder->po_number,
                ];
                $except = $purchaseOrder->createdBy;
                $step = $chain?->steps->first(
                    fn ($approvalStep) => $approvalStep->status === ApprovalStepStatus::Pending
                );
                $role = $step ? UserRole::tryFrom($step->required_role) : null;

                $sent += $role
                    ? $this->notifications->sendToRoles(
                        [$role],
                        $dedupeKey,
                        $title,
                        $message,
                        $priority,
                        NotificationDestination::Procurement,
                        $routeParameters,
                        $except,
                    )
                    : $this->notifications->sendToPermission(
                        Permission::ApprovePurchaseOrder,
                        $dedupeKey,
                        $title,
                        $message,
                        $priority,
                        NotificationDestination::Procurement,
                        $routeParameters,
                        $except,
                    );
            });

        return $sent;
    }
}
