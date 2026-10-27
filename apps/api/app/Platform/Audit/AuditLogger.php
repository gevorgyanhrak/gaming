<?php

namespace App\Platform\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Records administrative actions. Never store secrets or passwords in $changes.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(string $action, ?Model $subject = null, array $changes = [], ?User $actor = null): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::query()->create([
            'actor_id' => ($actor ?? $this->actor())?->id,
            'action' => $action,
            'subject_type' => $subject === null ? null : class_basename($subject),
            'subject_id' => is_int($subject?->getKey()) ? $subject->getKey() : null,
            'changes' => $changes === [] ? null : $changes,
            'ip' => $request?->ip(),
            'user_agent' => $request === null ? null : Str::limit((string) $request->userAgent(), 490, ''),
        ]);
    }

    /** The administrator performing the current request, if any. */
    public function actor(): ?User
    {
        $user = Auth::guard('admin')->user();

        return $user instanceof User ? $user : null;
    }
}
