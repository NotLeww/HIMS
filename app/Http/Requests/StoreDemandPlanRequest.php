<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDemandPlanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'plan_number' => 'required|string|unique:demand_plans,plan_number',
            'item_id' => 'required|integer|exists:inventory_items,id',
            'current_stock' => 'nullable|numeric|min:0',
            'historical_usage' => 'nullable|numeric|min:0',
            'upcoming_need' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'trigger_reason' => 'nullable|string',
            'status' => 'nullable|string',
        ];
    }
}
