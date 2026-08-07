<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $item = $this->route('inventory_item') ? $this->route('inventory_item') : null;
        $itemId = $item ? $item->id : null;

        return [
            'sku' => 'sometimes|required|string|unique:inventory_items,sku,'.$itemId,
            'name' => 'sometimes|required|string|max:255',
            'category' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'quantity_on_hand' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'warehouse_name' => 'nullable|string|max:255',
            'batch_number' => 'nullable|string|max:255',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }
}
