<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Admin panel access to the audit log. Super admins pass every check (Gate::before).
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view audit log');
    }

    public function view(User $user, AuditLog $model): bool
    {
        return $user->can('view audit log');
    }

    // The audit log is append-only.
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $model): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $model): bool
    {
        return false;
    }
}
