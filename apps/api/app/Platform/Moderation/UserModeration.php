<?php

namespace App\Platform\Moderation;

use App\Enums\UserStatus;
use App\Models\User;
use App\Platform\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bans, deactivations and role changes. Every action is audited; affected users are signed
 * out on their next request (EnsureAccountIsActive) and lose their API tokens.
 */
class UserModeration
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function ban(User $target, User $actor, string $reason, ?Carbon $expiresAt = null): void
    {
        $this->authorize($target, $actor);

        DB::transaction(function () use ($target, $actor, $reason, $expiresAt) {
            $target->bans()->create(['banned_by' => $actor->id, 'reason' => $reason, 'expires_at' => $expiresAt]);
            $target->forceFill(['status' => UserStatus::Banned])->save();
            $target->tokens()->delete();
        });

        $this->audit->record('user.banned', $target, [
            'reason' => $reason,
            'expires_at' => $expiresAt?->toIso8601String(),
        ], $actor);
    }

    public function unban(User $target, User $actor): void
    {
        $this->authorize($target, $actor);

        DB::transaction(function () use ($target, $actor) {
            $target->bans()->active()->update(['revoked_at' => now(), 'revoked_by' => $actor->id]);

            if ($target->status === UserStatus::Banned) {
                $target->forceFill(['status' => UserStatus::Active])->save();
            }
        });

        $this->audit->record('user.unbanned', $target, actor: $actor);
    }

    public function deactivate(User $target, User $actor): void
    {
        $this->authorize($target, $actor);

        $target->forceFill(['status' => UserStatus::Deactivated])->save();
        $target->tokens()->delete();

        $this->audit->record('user.deactivated', $target, actor: $actor);
    }

    public function activate(User $target, User $actor): void
    {
        $this->authorize($target, $actor);

        if ($target->status === UserStatus::Banned) {
            throw new ModerationException(__('platform.moderation.use_unban'));
        }

        $target->forceFill(['status' => UserStatus::Active])->save();

        $this->audit->record('user.activated', $target, actor: $actor);
    }

    /**
     * Replace the user's administrative roles. Only super admins may do this.
     *
     * @param  list<string>  $roles
     */
    public function syncRoles(User $target, User $actor, array $roles): void
    {
        if (! $actor->hasRole('super_admin')) {
            throw new ModerationException(__('platform.moderation.super_admin_only'));
        }

        if ($target->is($actor) && ! in_array('super_admin', $roles, true)) {
            throw new ModerationException(__('platform.moderation.keep_own_super_admin'));
        }

        $before = $target->getRoleNames()->sort()->values()->all();
        $target->syncRoles($roles);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->audit->record('user.roles_changed', $target, [
            'roles' => ['old' => $before, 'new' => collect($roles)->sort()->values()->all()],
        ], $actor);
    }

    /**
     * Reactivate users whose bans have all expired. Run by the scheduler.
     */
    public function liftExpiredBans(): int
    {
        $users = User::query()
            ->where('status', UserStatus::Banned)
            ->whereDoesntHave('bans', fn ($q) => $q->active())
            ->get();

        foreach ($users as $user) {
            $user->forceFill(['status' => UserStatus::Active])->save();
            $this->audit->record('user.ban_expired', $user);
        }

        return $users->count();
    }

    private function authorize(User $target, User $actor): void
    {
        if ($target->is($actor)) {
            throw new ModerationException(__('platform.moderation.not_yourself'));
        }

        // Staff accounts can only be moderated by super admins.
        if ($target->roles()->exists() && ! $actor->hasRole('super_admin')) {
            throw new ModerationException(__('platform.moderation.super_admin_only'));
        }
    }
}
