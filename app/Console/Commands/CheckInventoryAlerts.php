<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Services\InventoryAutomationService;
use App\Services\StockAlertService;
use Illuminate\Console\Command;

class CheckInventoryAlerts extends Command
{
    protected $signature = 'inventory:check-alerts';

    protected $description = 'Refresh cached stock totals and raise/resolve low-stock and expiry alerts';

    public function handle(StockAlertService $alerts, InventoryAutomationService $inventory): int
    {
        // Re-derive the cached rollups first so the alert sweep reads the
        // same numbers the dashboard shows.
        InventoryItem::query()->each(fn (InventoryItem $item) => $inventory->syncItemTotals($item));

        $result = $alerts->sweep();

        $this->info(sprintf(
            'Inventory alerts checked: %d raised, %d resolved.',
            $result['raised'],
            $result['resolved']
        ));

        return self::SUCCESS;
    }
}
