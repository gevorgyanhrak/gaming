<?php

namespace App\Policies;

use App\Models\BotProfile;
use App\Models\User;

/**
 * Admin panel access to bot profiles. Super admins pass every check (Gate::before).
 */
class BotProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, BotProfile $model): bool
    {
        return $user->can('manage games');
    }

    public function create(User $user): bool
    {
        return $user->can('manage games');
    }

    public function update(User $user, BotProfile $model): bool
    {
        return $user->can('manage games');
    }

    public function delete(User $user, BotProfile $model): bool
    {
        return $user->can('manage games');
    }
}
