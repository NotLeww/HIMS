<?php

namespace App\Console\Commands;

use App\Models\Models\InventoryItem;
use Illuminate\Console\Command;

class CheckInventoryAlerts extends Command
{
    protected $signature = 'inventory:check-alerts';

    protected $description = 'Check inventory items for low stock and out of stock conditions';

    public function handle(): int
    {
        $items = InventoryItem::query()->get();

        foreach ($items as $item) {
            $status = $this->resolveStatus($item);

            if ($item->status !== $status) {
                $item->status = $status;
                $item->save();
            }
        }

        $this->info('Inventory alerts checked successfully.');

        return self::SUCCESS;
    }

    private function resolveStatus(InventoryItem $item): string
    {
        if ((int) $item->quantity_on_hand <= 0) {
            return 'out_of_stock';
        }

        if ((int) $item->reorder_level > 0 && $item->quantity_on_hand <= (int) $item->reorder_level) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}
