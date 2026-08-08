<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreDemandForecastPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::GenerateForecasts) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'analysis_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'forecast_days' => ['nullable', 'integer', 'min:7', 'max:180'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'analysis_days.min' => 'A week is the shortest window that produces a usable average.',
            'lead_time_days.max' => 'Lead times over 120 days are almost certainly a typo.',
        ];
    }
}
