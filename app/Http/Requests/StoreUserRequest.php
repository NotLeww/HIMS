<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::ManageUsers) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'status' => ['nullable', Rule::enum(UserStatus::class)],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'department' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'Pick the role this account should have.',
            'password.confirmed' => 'The two passwords do not match.',
        ];
    }
}
