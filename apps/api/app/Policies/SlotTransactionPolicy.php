<?php

namespace App\Policies;

use App\Models\SlotTransaction;
use App\Models\User;

/**
 * Slot wallet calls are a read-only record in the admin panel: nobody edits or deletes them.
 * There are no write abilities, so even super admins (Gate::before covers permission names only) cannot.
 */
class SlotTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('manage games');
    }

    public function view(User $user, SlotTransaction $model): bool
    {
        return $user->can('manage games');
    }
}
