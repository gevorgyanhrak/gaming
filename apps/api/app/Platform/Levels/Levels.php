<?php

namespace App\Platform\Levels;

use App\Enums\FishkaTransactionType;
use App\Enums\XpSource;
use App\Models\LevelReward;
use App\Models\LevelUp;
use App\Models\User;
use App\Models\XpTransaction;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * The only way XP and levels change (ADR 0018). XP is added under a lock on the user row, with an
 * idempotency key (one game, one day pays once); the level is recomputed from the total and never goes
 * down; every level reached writes one level-up with its reward, collected once from the popup.
 */
class Levels
{
    /** @var Collection<int, LevelReward>|null */
    private ?Collection $rewards = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly FishkaWallets $wallets,
    ) {}

    public function curve(): LevelCurve
    {
        return new LevelCurve(
            (int) $this->settings->get('levels.xp_base'),
            (int) $this->settings->get('levels.xp_step'),
            (int) $this->settings->get('levels.after_30_percent'),
            (int) $this->settings->get('levels.growth_percent'),
            (int) $this->settings->get('levels.max_level'),
        );
    }

    /**
     * Add XP to a player. Returns the ledger entry, or the first one for a repeated key; null for no XP.
     */
    public function award(int $userId, int $amount, XpSource $source, string $key, ?string $referenceType = null, ?string $referenceId = null): ?XpTransaction
    {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($userId, $amount, $source, $key, $referenceType, $referenceId) {
            /** @var User $user */
            $user = User::query()->lockForUpdate()->find($userId) ?? throw new RuntimeException("No user {$userId}.");

            // Checked under the lock: a repeat waits here and then finds the first.
            $done = XpTransaction::query()->where('idempotency_key', $key)->first();
            if ($done !== null) {
                return $done;
            }

            $before = $user->level;
            $xp = $user->xp + $amount;
            $after = max($before, $this->curve()->levelFor($xp));
            $user->forceFill(['xp' => $xp, 'level' => $after])->save();

            $entry = XpTransaction::query()->create([
                'user_id' => $userId,
                'amount' => $amount,
                'source' => $source,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'level_before' => $before,
                'level_after' => $after,
                'idempotency_key' => $key,
            ]);

            $now = now();
            for ($level = $before + 1; $level <= $after; $level++) {
                $reward = $this->rewardFor($level);
                LevelUp::query()->insertOrIgnore([
                    'user_id' => $userId,
                    'level' => $level,
                    'fishkas' => $reward['fishkas'],
                    'items' => $reward['items'] === [] ? null : json_encode($reward['items']),
                    'is_milestone' => $reward['is_milestone'],
                    'created_at' => $now,
                ]);
            }

            DB::afterCommit(function () use ($userId, $after, $xp, $amount) {
                try {
                    event(new LevelUpdated($userId, $after, $xp, $amount, $this->pendingCount($userId)));
                } catch (Throwable $e) {
                    Log::warning('Level broadcast failed.', ['user' => $userId, 'error' => $e->getMessage()]);
                }
            });

            return $entry;
        });
    }

    /**
     * Collect every pending level-up's reward, once. Returns the level-ups this call collected (none for a
     * repeat: the rows are locked and stamped, and each payout has its own key).
     *
     * @return Collection<int, LevelUp>
     */
    public function claim(User $user): Collection
    {
        return DB::transaction(function () use ($user) {
            User::query()->lockForUpdate()->find($user->id);
            $pending = LevelUp::query()
                ->where('user_id', $user->id)
                ->whereNull('claimed_at')
                ->orderBy('level')
                ->lockForUpdate()
                ->get();

            foreach ($pending as $levelUp) {
                $entry = $this->wallets->credit($user, $levelUp->fishkas, FishkaTransactionType::LevelReward, [
                    'description' => "Level {$levelUp->level} reward",
                    'reference_type' => 'level',
                    'reference_id' => (string) $levelUp->level,
                    'idempotency_key' => "level:{$user->id}:{$levelUp->level}",
                ]);
                $levelUp->forceFill(['claimed_at' => now(), 'fishka_transaction_id' => $entry?->id])->save();
            }

            return $pending;
        });
    }

    /**
     * The player's level and progress.
     *
     * @return array{level: int, xp: int, level_xp: int, next_level_xp: int|null, max_level: int}
     */
    public function progress(User $user): array
    {
        $curve = $this->curve();
        $atMax = $user->level >= $curve->maxLevel;

        return [
            'level' => $user->level,
            'xp' => $user->xp,
            'level_xp' => $curve->totalFor($user->level),
            'next_level_xp' => $atMax ? null : $curve->totalFor($user->level + 1),
            'max_level' => $curve->maxLevel,
        ];
    }

    /**
     * @return Collection<int, LevelUp>
     */
    public function pending(User $user): Collection
    {
        return LevelUp::query()->where('user_id', $user->id)->whereNull('claimed_at')->orderBy('level')->get();
    }

    public function pendingCount(int $userId): int
    {
        return LevelUp::query()->where('user_id', $userId)->whereNull('claimed_at')->count();
    }

    /**
     * What reaching $level gives: its configured reward, or the default number of Fishkas.
     *
     * @return array{fishkas: int, items: list<array{kind: string, key: string}>, is_milestone: bool}
     */
    public function rewardFor(int $level): array
    {
        $reward = $this->rewards()->get($level);

        return $reward === null
            ? ['fishkas' => (int) $this->settings->get('levels.reward_default'), 'items' => [], 'is_milestone' => false]
            : ['fishkas' => $reward->fishkas, 'items' => $this->validItems($reward->items), 'is_milestone' => $reward->is_milestone];
    }

    /**
     * The frame and badge a player at $level shows (the ones unlocked at the highest level).
     *
     * @return array{frame: string|null, badge: string|null}
     */
    public function looks(int $level): array
    {
        $looks = ['frame' => null, 'badge' => null];
        foreach ($this->rewards()->filter(fn (LevelReward $r) => $r->level <= $level)->sortBy('level') as $reward) {
            foreach ($this->validItems($reward->items) as $item) {
                if (in_array($item['kind'], ['frame', 'badge'], true)) {
                    $looks[$item['kind']] = $item['key'];
                }
            }
        }

        return $looks;
    }

    /**
     * The level each exclusive emoji unlocks at (packs no reward lists are left out).
     *
     * @return array<string, int>
     */
    public function emojiLevels(): array
    {
        $levels = [];
        foreach ($this->rewards()->sortBy('level') as $reward) {
            foreach ($this->validItems($reward->items) as $item) {
                if ($item['kind'] !== 'emoji_pack') {
                    continue;
                }
                foreach (LevelItems::EMOJI_PACKS[$item['key']] as $emoji) {
                    $levels[$emoji] ??= $reward->level;
                }
            }
        }

        return $levels;
    }

    /**
     * @return Collection<int, LevelReward>
     */
    private function rewards(): Collection
    {
        return $this->rewards ??= LevelReward::query()->get()->keyBy('level');
    }

    /**
     * The items of a reward that exist in the catalog (the column is admin-edited JSON).
     *
     * @param  array<mixed>|null  $items
     * @return list<array{kind: string, key: string}>
     */
    private function validItems(?array $items): array
    {
        $valid = [];
        foreach ($items ?? [] as $item) {
            if (is_array($item) && is_string($item['kind'] ?? null) && is_string($item['key'] ?? null) && LevelItems::exists($item['kind'], $item['key'])) {
                $valid[] = ['kind' => $item['kind'], 'key' => $item['key']];
            }
        }

        return $valid;
    }
}
