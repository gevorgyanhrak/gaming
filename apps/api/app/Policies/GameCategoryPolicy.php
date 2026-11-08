<?php

namespace App\Policies;

use App\Models\GameCategory;
use App\Models\User;

/**
 * Admin panel access to game categories. Super admins pass every check (Gate::before).
 */
class GameCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, GameCategory $model): bool
    {
        return $user->can('manage games');
    }

    public function create(User $user): bool
    {
        return $user->can('manage games');
    }

    public function update(User $user, GameCategory $model): bool
    {
        return $user->can('manage games');
    }

    public function delete(User $user, GameCategory $model): bool
    {
        return $user->can('manage games');
    }
}
