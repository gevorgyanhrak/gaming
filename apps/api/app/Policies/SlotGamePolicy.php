<?php

namespace App\Policies;

use App\Models\SlotGame;
use App\Models\User;

/**
 * Admin panel access to slot Games. Super admins pass every check (Gate::before).
 */
class SlotGamePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, SlotGame $model): bool
    {
        return $user->can('manage games');
    }

    public function create(User $user): bool
    {
        return $user->can('manage games');
    }

    public function update(User $user, SlotGame $model): bool
    {
        return $user->can('manage games');
    }

    public function delete(User $user, SlotGame $model): bool
    {
        return $user->can('manage games');
    }
}
