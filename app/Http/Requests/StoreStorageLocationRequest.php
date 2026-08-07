<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStorageLocationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'zone' => 'nullable|string|max:100',
            'capacity' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:active,inactive',
        ];
    }
}
