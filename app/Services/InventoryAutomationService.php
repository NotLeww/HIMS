<?php

namespace App\Services;

use App\Models\Models\InventoryItem;
use App\Models\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAutomationService
{
    public function recordMovement(array $validated, ?int $userId = null): StockMovement
    {
        return DB::transaction(function () use ($validated, $userId): StockMovement {
            $item = InventoryItem::findOrFail($validated['item_id']);
            $quantity = (int) $validated['quantity'];

            if ($validated['movement_type'] === 'stock_out') {
                if ($item->quantity_on_hand < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Insufficient stock on hand for this item.'],
                    ]);
                }

                $item->quantity_on_hand = max(0, $item->quantity_on_hand - $quantity);
            } elseif ($validated['movement_type'] === 'stock_in') {
                $item->quantity_on_hand += $quantity;
            } elseif ($validated['movement_type'] === 'transfer') {
                if ($item->quantity_on_hand < $quantity) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Insufficient stock on hand for this transfer.'],
                    ]);
                }

                $item->quantity_on_hand = max(0, $item->quantity_on_hand - $quantity);
            }

            $schemaBuilder = $item->getConnection()->getSchemaBuilder();
            if ($schemaBuilder->hasColumn($item->getTable(), 'total_value')) {
                $item->total_value = round($item->quantity_on_hand * (float) ($item->unit_cost ?? 0), 2);
            }

            if ($schemaBuilder->hasColumn($item->getTable(), 'unit_cost')) {
                $item->unit_cost = (float) ($item->unit_cost ?? 0);
            }

            $item->status = $this->resolveStatus($item);
            $item->save();

            return StockMovement::create([
                ...$validated,
                'user_id' => $userId ?? auth()->id(),
                'moved_at' => now(),
            ]);
        });
    }

    private function resolveStatus(InventoryItem $item): string
    {
        if ($item->quantity_on_hand <= 0) {
            return 'out_of_stock';
        }

        if ((int) $item->reorder_level > 0 && $item->quantity_on_hand <= (int) $item->reorder_level) {
            return 'low_stock';
        }

        return 'in_stock';
    }
}
