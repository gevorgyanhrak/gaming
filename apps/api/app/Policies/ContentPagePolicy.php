<?php

namespace App\Policies;

use App\Models\ContentPage;
use App\Models\User;

/**
 * Admin panel access to content pages. Super admins pass every check (Gate::before).
 */
class ContentPagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage settings');
    }

    public function view(User $user, ContentPage $model): bool
    {
        return $user->can('manage settings');
    }

    public function create(User $user): bool
    {
        return $user->can('manage settings');
    }

    public function update(User $user, ContentPage $model): bool
    {
        return $user->can('manage settings');
    }

    public function delete(User $user, ContentPage $model): bool
    {
        return $user->can('manage settings') && ! in_array($model->slug, [ContentPage::TERMS, ContentPage::PRIVACY], true);
    }
}
