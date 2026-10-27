<?php

namespace App\Policies;

use App\Models\GameMode;
use App\Models\User;

/**
 * Admin panel access to game modes. Super admins pass every check (Gate::before).
 */
class GameModePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, GameMode $model): bool
    {
        return $user->can('manage games');
    }

    public function create(User $user): bool
    {
        return $user->can('manage games');
    }

    public function update(User $user, GameMode $model): bool
    {
        return $user->can('manage games');
    }

    public function delete(User $user, GameMode $model): bool
    {
        return $user->can('manage games');
    }
}
