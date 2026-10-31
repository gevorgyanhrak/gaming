<?php

namespace App\Platform\Moderation;

use App\Enums\UserStatus;
use App\Models\User;
use App\Platform\Audit\AuditLogger;
use App\Platform\Auth\AccountRules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use libphonenumber\PhoneNumberUtil;
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

    /**
     * Staff edit a player's account details. A changed email or phone has to be verified again. The
     * account keeps at least one way to sign in. Audited with the names of the changed fields.
     *
     * @param  array<string, mixed>  $data  username, email, phone, locale, display_name, country, bio
     *
     * @throws ValidationException|ModerationException
     */
    public function updateDetails(User $target, User $actor, array $data): void
    {
        if (! $target->is($actor)) {
            $this->authorize($target, $actor);
        }

        $data = AccountRules::normalize(array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value, $data));
        $validated = Validator::make($data, [
            'username' => AccountRules::username($target),
            'email' => filled($data['email'] ?? null) ? AccountRules::email($target) : ['nullable'],
            'phone' => filled($data['phone'] ?? null) ? AccountRules::phone($target) : ['nullable'],
            'locale' => ['nullable', Rule::in(config('platform.supported_locales'))],
            'display_name' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', Rule::in(PhoneNumberUtil::getInstance()->getSupportedRegions())],
            'bio' => ['nullable', 'string', 'max:500'],
        ])->after(function ($validator) use ($data) {
            if (blank($data['email'] ?? null) && blank($data['phone'] ?? null)) {
                $validator->errors()->add('email', __('platform.moderation.needs_login'));
            }
        })->validate();

        $account = ['username' => $validated['username'], 'email' => $validated['email'] ?? null, 'phone' => $validated['phone'] ?? null, 'locale' => $validated['locale'] ?? $target->locale];
        $profile = ['display_name' => $validated['display_name'] ?? null, 'country' => $validated['country'] ?? null, 'bio' => $validated['bio'] ?? null];
        $changed = [
            ...array_keys(array_filter($account, fn ($value, $key) => $target->{$key} !== $value, ARRAY_FILTER_USE_BOTH)),
            ...array_keys(array_filter($profile, fn ($value, $key) => $target->profile->{$key} !== $value, ARRAY_FILTER_USE_BOTH)),
        ];
        if ($changed === []) {
            return;
        }

        DB::transaction(function () use ($target, $account, $profile) {
            $target->forceFill([
                ...$account,
                'email_verified_at' => $account['email'] === $target->email ? $target->email_verified_at : null,
                'phone_verified_at' => $account['phone'] === $target->phone ? $target->phone_verified_at : null,
            ])->save();
            $target->profile()->updateOrCreate([], $profile);
        });

        $this->audit->record('user.updated', $target, ['fields' => $changed], $actor);
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
