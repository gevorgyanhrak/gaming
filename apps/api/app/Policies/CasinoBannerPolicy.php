<?php

namespace App\Policies;

use App\Models\CasinoBanner;
use App\Models\User;

/**
 * Admin panel access to casino banners. Super admins pass every check (Gate::before).
 */
class CasinoBannerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, CasinoBanner $model): bool
    {
        return $user->can('manage games');
    }

    public function create(User $user): bool
    {
        return $user->can('manage games');
    }

    public function update(User $user, CasinoBanner $model): bool
    {
        return $user->can('manage games');
    }

    public function delete(User $user, CasinoBanner $model): bool
    {
        return $user->can('manage games');
    }
}
