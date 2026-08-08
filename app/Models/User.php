<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'employee_id',
        'department',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Stock movements this person recorded. The reason accounts are
     * deactivated instead of deleted — this history names them.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function acknowledgedAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class, 'acknowledged_by');
    }

    public function demandPlans(): HasMany
    {
        return $this->hasMany(DemandPlan::class, 'generated_by');
    }

    /**
     * Whether this account's role grants an ability.
     *
     * Inactive accounts hold no permissions at all, so a deactivated user who
     * still has a live session cannot act even before the next request logs
     * them out.
     */
    public function hasPermission(Permission|string $permission): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $permission = $permission instanceof Permission
            ? $permission
            : Permission::tryFrom($permission);

        return $permission !== null && $this->role->grants($permission);
    }

    public function hasRole(UserRole|string $role): bool
    {
        $role = $role instanceof UserRole ? $role : UserRole::tryFrom($role);

        return $role !== null && $this->role === $role;
    }

    public function isAdministrator(): bool
    {
        return $this->role->isAdministrator();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return $this->isActive() ? $this->role->permissions() : [];
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('') ?: '?';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active->value);
    }

    public function scopeRole(Builder $query, UserRole|string $role): Builder
    {
        return $query->where('role', $role instanceof UserRole ? $role->value : $role);
    }

    public function scopeAdministrators(Builder $query): Builder
    {
        return $query->role(UserRole::Administrator);
    }
}
