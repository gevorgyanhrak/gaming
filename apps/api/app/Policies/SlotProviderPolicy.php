<?php

namespace App\Policies;

use App\Models\SlotProvider;
use App\Models\User;

/**
 * Admin panel access to slot Providers. Super admins pass every check (Gate::before).
 */
class SlotProviderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, SlotProvider $model): bool
    {
        return $user->can('manage games');
    }

    public function create(User $user): bool
    {
        return $user->can('manage games');
    }

    public function update(User $user, SlotProvider $model): bool
    {
        return $user->can('manage games');
    }

    public function delete(User $user, SlotProvider $model): bool
    {
        return $user->can('manage games');
    }
}
