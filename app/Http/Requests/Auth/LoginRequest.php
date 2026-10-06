<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use App\Support\AuthenticationContext;

class LoginRequest extends RoleRestrictedLoginRequest
{
    protected function guard(): string
    {
        return AuthenticationContext::WEB_GUARD;
    }

    /**
     * @return array<int, string>
     */
    protected function allowedRoles(): array
    {
        if ($this->is('supplier/login')) {
            return collect(UserRole::cases())->filter->isSupplier()->map->value->all();
        }

        return collect(UserRole::cases())
            ->reject(fn (UserRole $role) => $role->isAdministrator() || $role->isSupplier())
            ->map->value
            ->all();
    }

    protected function throttlePrefix(): string
    {
        return 'staff';
    }
}
