<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Owns the rules that stop the user-management screen locking everyone out.
 *
 * Two failure modes are easy to reach by accident and impossible to undo from
 * the UI afterwards: an administrator removing their own access mid-session,
 * and the last remaining administrator being deactivated or demoted, which
 * leaves a system nobody can administer. Both are refused here rather than in
 * the controller so the API and any future console command inherit the guard.
 */
class UserAccountService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): User
    {
        $user = new User([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
            'role' => $attributes['role'],
            'status' => $attributes['status'] ?? UserStatus::Active->value,
            'employee_id' => $attributes['employee_id'] ?? null,
            'department' => $attributes['department'] ?? null,
            'phone' => $attributes['phone'] ?? null,
        ]);

        // An administrator created this account in person, so there is nobody
        // to send a confirmation link to. Set outside the fillable list on
        // purpose: email_verified_at must never be mass-assignable from a
        // request, so passing it to User::create() would be dropped silently.
        $user->email_verified_at = now();

        $user->save();

        return $user;
    }

    /**
     * Apply an edit, refusing changes that would remove the acting user's own
     * access or leave the system without an administrator.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(User $user, array $attributes, User $actor): User
    {
        return DB::transaction(function () use ($user, $attributes, $actor): User {
            $newRole = UserRole::from($attributes['role']);
            $newStatus = UserStatus::from($attributes['status']);

            $losesAdmin = $user->isAdministrator()
                && (! $newRole->isAdministrator() || ! $newStatus->isActive());

            if ($losesAdmin) {
                $this->assertNotSelf($user, $actor, 'You cannot remove your own administrator access.');
                $this->assertOtherAdministratorRemains($user);
            }

            $user->fill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'role' => $newRole,
                'status' => $newStatus,
                'employee_id' => $attributes['employee_id'] ?? null,
                'department' => $attributes['department'] ?? null,
                'phone' => $attributes['phone'] ?? null,
            ]);

            // Blank means "leave it alone" — the edit form does not echo the
            // existing password back, so an empty field is not a request to
            // clear it.
            if (! empty($attributes['password'])) {
                $user->password = $attributes['password'];
            }

            $user->save();

            return $user;
        });
    }

    /**
     * Flip an account between active and inactive.
     */
    public function toggleStatus(User $user, User $actor): User
    {
        return DB::transaction(function () use ($user, $actor): User {
            if ($user->isActive()) {
                $this->assertNotSelf($user, $actor, 'You cannot deactivate your own account.');

                if ($user->isAdministrator()) {
                    $this->assertOtherAdministratorRemains($user);
                }

                $user->status = UserStatus::Inactive;
            } else {
                $user->status = UserStatus::Active;
            }

            $user->save();

            return $user;
        });
    }

    public function resetPassword(User $user, string $password): User
    {
        $user->password = Hash::make($password);
        $user->save();

        return $user;
    }

    /**
     * Accounts are never deleted — see UserStatus for why. This exists so the
     * controller has one honest place to send a delete attempt.
     */
    public function deactivate(User $user, User $actor): User
    {
        $this->assertNotSelf($user, $actor, 'You cannot deactivate your own account.');

        if ($user->isAdministrator()) {
            $this->assertOtherAdministratorRemains($user);
        }

        $user->status = UserStatus::Inactive;
        $user->save();

        return $user;
    }

    private function assertNotSelf(User $user, User $actor, string $message): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['role' => [$message]]);
        }
    }

    /**
     * Refuse the change unless some other active administrator would survive it.
     */
    private function assertOtherAdministratorRemains(User $user): void
    {
        $remaining = User::query()
            ->administrators()
            ->active()
            ->whereKeyNot($user->getKey())
            ->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'role' => ['This is the only active administrator. Promote someone else first.'],
            ]);
        }
    }
}
