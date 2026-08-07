<?php

namespace App\Http\Requests;

use App\Enums\MovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'item_id' => 'required|integer|exists:inventory_items,id',
            // Previously this allowed 'in'/'out', which are not MovementType
            // cases. Rows written that way threw a ValueError on the enum cast
            // the next time anything read the table. Bind the rule to the enum
            // so the two can never drift apart again.
            'movement_type' => ['required', Rule::enum(MovementType::class)],
            'quantity' => 'required|integer|min:1',
            'item_batch_id' => 'nullable|integer|exists:item_batches,id',
            'from_location_id' => 'nullable|integer|exists:storage_locations,id',
            'to_location_id' => 'nullable|integer|exists:storage_locations,id',
            'unit_cost' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:255',
        ];
    }
}
