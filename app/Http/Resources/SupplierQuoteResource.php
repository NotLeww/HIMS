<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SupplierQuoteResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'procurement_request' => $this->relationLoaded('procurementRequest') && $this->procurementRequest ? [
                'id' => $this->procurementRequest->id,
                'request_number' => $this->procurementRequest->request_number,
            ] : null,
            'supplier' => $this->relationLoaded('supplier') && $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
            ] : null,
            'quoted_price' => $this->quoted_price,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
