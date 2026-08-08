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
            // The window the forecast was taken over, so a consumer can tell
            // what the figures below actually mean.
            'analysis_days' => $this->analysis_days,
            'forecast_days' => $this->forecast_days,
            'lead_time_days' => $this->lead_time_days,

            'current_stock' => $this->current_stock,
            'historical_usage' => $this->historical_usage,
            'average_daily_usage' => $this->average_daily_usage,
            'upcoming_need' => $this->upcoming_need,
            'reorder_point' => $this->reorder_point,
            'safety_stock' => $this->safety_stock,
            'suggested_order_quantity' => $this->suggested_order_quantity,
            'days_of_cover' => $this->days_of_cover,
            'trend' => $this->trend?->value,
            'trigger_reason' => $this->trigger_reason,
            'status' => $this->status,

            'generated_at' => $this->generated_at,
            'generated_by' => $this->whenLoaded('generatedBy', fn () => [
                'id' => $this->generatedBy->id,
                'name' => $this->generatedBy->name,
            ]),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
