<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DemandPlanResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'plan_number' => $this->plan_number,
            'item' => $this->relationLoaded('item') && $this->item ? new InventoryItemResource($this->item) : null,
            'current_stock' => $this->current_stock,
            'historical_usage' => $this->historical_usage,
            'upcoming_need' => $this->upcoming_need,
            'reorder_point' => $this->reorder_point,
            'trigger_reason' => $this->trigger_reason,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
