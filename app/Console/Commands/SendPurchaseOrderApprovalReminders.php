<?php

namespace App\Console\Commands;

use App\Services\Procurement\PurchaseOrderApprovalReminderService;
use Illuminate\Console\Command;

class SendPurchaseOrderApprovalReminders extends Command
{
    protected $signature = 'procurement:send-approval-reminders';

    protected $description = 'Notify current approvers about purchase orders near or past expected delivery';

    public function handle(PurchaseOrderApprovalReminderService $reminders): int
    {
        $sent = $reminders->send();

        $this->info("Purchase order approval reminders sent: {$sent}.");

        return self::SUCCESS;
    }
}
