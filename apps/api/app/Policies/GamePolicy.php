<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;

/**
 * Admin panel access to games. Super admins pass every check (Gate::before).
 */
class GamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, Game $model): bool
    {
        return $user->can('manage games');
    }

    public function update(User $user, Game $model): bool
    {
        return $user->can('manage games');
    }

    // Games are defined in code (engines); they are seeded, never created or deleted in the admin.
    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Game $model): bool
    {
        return false;
    }
}
