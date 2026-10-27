<?php

namespace App\Policies;

use App\Models\User;

/**
 * Admin panel access to user accounts. Super admins pass every check (Gate::before).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('manage users');
    }

    // Accounts are created by registration and changed through moderation actions, not edited.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function moderate(User $user, User $model): bool
    {
        return $user->can('manage users') && ! $user->is($model);
    }

    public function manageRoles(User $user, User $model): bool
    {
        return $user->hasRole('super_admin');
    }
}
