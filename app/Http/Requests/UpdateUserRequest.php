<?php

namespace App\Http\Requests;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            // Left blank on the edit form when the password is not changing.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'employee_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('users', 'employee_id')->ignore($userId),
            ],
            'department' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
