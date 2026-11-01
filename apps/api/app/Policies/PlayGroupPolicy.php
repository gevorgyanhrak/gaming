<?php

namespace App\Policies;

use App\Models\PlayGroup;
use App\Models\User;

/**
 * Admin panel access to play groups. Super admins pass every check (Gate::before).
 */
class PlayGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, PlayGroup $model): bool
    {
        return $user->can('manage games');
    }

    public function update(User $user, PlayGroup $model): bool
    {
        return $user->can('manage games');
    }

    // The ten groups are seeded by migration; games rely on them existing. Disable one instead.
    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, PlayGroup $model): bool
    {
        return false;
    }
}
