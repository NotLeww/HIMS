<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDemandPlanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'plan_number' => 'sometimes|required|string',
            'item_id' => 'sometimes|required|integer|exists:inventory_items,id',
            'current_stock' => 'nullable|numeric|min:0',
            'historical_usage' => 'nullable|numeric|min:0',
            'upcoming_need' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'trigger_reason' => 'nullable|string',
            'status' => 'nullable|string',
        ];
    }
}
