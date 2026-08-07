<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStockMovementRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'item_id' => 'sometimes|required|integer|exists:inventory_items,id',
            'movement_type' => 'sometimes|required|string|in:in,out,transfer,adjustment',
            'quantity' => 'sometimes|required|numeric|min:0.01',
            'from_location_id' => 'nullable|integer|exists:storage_locations,id',
            'to_location_id' => 'nullable|integer|exists:storage_locations,id',
            'remarks' => 'nullable|string',
            'moved_at' => 'nullable|date',
            'user_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
