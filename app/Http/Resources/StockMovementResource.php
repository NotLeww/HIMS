<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'item' => $this->relationLoaded('item') && $this->item ? new InventoryItemResource($this->item) : null,
            'movement_type' => $this->movement_type,
            'quantity' => $this->quantity,
            'from_location' => $this->whenLoaded('fromLocation') ? [
                'id' => $this->fromLocation->id,
                'name' => $this->fromLocation->name,
            ] : null,
            'to_location' => $this->whenLoaded('toLocation') ? [
                'id' => $this->toLocation->id,
                'name' => $this->toLocation->name,
            ] : null,
            'remarks' => $this->remarks,
            'moved_at' => $this->moved_at,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
