<?php

namespace App\Platform\Fishka;

use App\Enums\FishkaTransactionType as Type;
use App\Models\FishkaTransaction;
use App\Models\FishkaWallet;
use App\Models\User;
use App\Platform\Audit\AuditLogger;
use App\Platform\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The only way a Fishka balance changes. Every change runs in a database transaction that locks the
 * wallet row, refuses to go below zero, writes one ledger entry and, after commit, tells the owner's
 * open tabs. One-time changes (bonuses, a game's fee, a cash-out) carry an idempotency key: a repeat
 * returns the first entry instead of paying or charging twice.
 *
 * Fishkas are free play currency: they are never bought, sold, cashed out or sent between users.
 */
class FishkaWallets
{
    public function __construct(
        private readonly Settings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The user's wallet, opened with the welcome bonus the first time it's needed (new accounts open
     * it at registration; accounts older than Fishkas open it on their next visit).
     */
    public function wallet(User $user): FishkaWallet
    {
        $wallet = FishkaWallet::query()->find($user->id);
        if ($wallet !== null) {
            return $wallet;
        }

        $this->open($user);

        return FishkaWallet::query()->findOrFail($user->id);
    }

    /**
     * Open the wallet and give the welcome bonus, once. Today counts as already rewarded, so the
     * first day brings the welcome bonus and no daily bonus on top.
     */
    public function open(User $user): void
    {
        DB::transaction(function () use ($user) {
            $now = now();
            $created = FishkaWallet::query()->insertOrIgnore([
                'user_id' => $user->id,
                'daily_bonus_on' => $this->today(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($created === 1) {
                $this->apply($user->id, (int) $this->settings->get('fishka.registration_bonus'), Type::RegistrationBonus, [
                    'idempotency_key' => "registration:{$user->id}",
                ]);
            }
        });
    }

    /** Days in the daily bonus's streak; the last one repeats while the streak holds. */
    public const DAILY_DAYS = 5;

    /**
     * Today's daily bonus (calendar days in the platform's Fishka time zone): which day of the streak it
     * is (days in a row raise it; a missed day starts again at day 1; the last day repeats), what each
     * day gives, and whether today's was already taken.
     *
     * @return array{day: int, claimed: bool, amount: int, days: list<array{day: int, amount: int}>}
     */
    public function dailyStatus(User $user): array
    {
        $wallet = $this->wallet($user);
        $claimed = $wallet->daily_bonus_on?->toDateString() === $this->today();
        $day = $claimed ? max(1, $wallet->daily_streak) : $this->nextDailyDay($wallet);
        $days = array_map(fn (int $d) => ['day' => $d, 'amount' => $this->dailyAmount($d)], range(1, self::DAILY_DAYS));

        return ['day' => $day, 'claimed' => $claimed, 'amount' => $this->dailyAmount($day), 'days' => $days];
    }

    /**
     * Take today's daily bonus: once a day, for the streak's day. Null when today's was already taken
     * (a repeat, or two at once: the wallet row is locked and the ledger key is per day).
     */
    public function claimDaily(User $user): ?FishkaTransaction
    {
        $today = $this->today();
        if ($this->wallet($user)->daily_bonus_on?->toDateString() === $today) {
            return null;
        }

        return DB::transaction(function () use ($user, $today) {
            $wallet = $this->lock($user->id);
            if ($wallet->daily_bonus_on?->toDateString() === $today) {
                return null;
            }
            $day = $this->nextDailyDay($wallet);
            $wallet->forceFill(['daily_bonus_on' => $today, 'daily_streak' => $day])->save();

            return $this->apply($user->id, $this->dailyAmount($day), Type::DailyLoginBonus, [
                'description' => "Daily bonus, day {$day}",
                'idempotency_key' => "daily:{$user->id}:{$today}",
            ]);
        });
    }

    /** The streak's day for a bonus taken today: one more after yesterday's, else day 1. */
    private function nextDailyDay(FishkaWallet $wallet): int
    {
        $yesterday = Carbon::parse($this->today())->subDay()->toDateString();

        return $wallet->daily_bonus_on?->toDateString() === $yesterday
            ? min(self::DAILY_DAYS, $wallet->daily_streak + 1)
            : 1;
    }

    private function dailyAmount(int $day): int
    {
        return (int) $this->settings->get($day === 1 ? 'fishka.daily_bonus' : "fishka.daily_bonus_day_{$day}");
    }

    /**
     * Whether the "out of Fishkas" bonus can be taken now: only while the balance can't pay the entry
     * fee, and not again before the cooldown has passed. `available_at` is when it next can be (null
     * when it's available now, or when the balance is still enough to play).
     *
     * @return array{available: bool, available_at: Carbon|null, amount: int}
     */
    public function refillStatus(User $user): array
    {
        return $this->refillStatusOf($this->wallet($user));
    }

    /**
     * Give the "out of Fishkas" bonus.
     *
     * @throws FishkaBonusUnavailable
     */
    public function refill(User $user): FishkaTransaction
    {
        $this->wallet($user);

        return DB::transaction(function () use ($user) {
            $wallet = $this->lock($user->id);
            $status = $this->refillStatusOf($wallet);
            if (! $status['available'] || $status['amount'] <= 0) {
                throw new FishkaBonusUnavailable($status['available_at']);
            }

            $now = now();
            $wallet->forceFill(['refilled_at' => $now])->save();

            /** @var FishkaTransaction */
            return $this->apply($user->id, $status['amount'], Type::InsufficientBalanceBonus, [
                'idempotency_key' => "refill:{$user->id}:{$now->getTimestamp()}",
            ]);
        });
    }

    /**
     * Take Fishkas (a game's fee, a poker buy-in).
     *
     * @param  array{description?: string, reference_type?: string, reference_id?: string, idempotency_key?: string}  $meta
     *
     * @throws InsufficientFishkas
     */
    public function charge(User $user, int $amount, Type $type, array $meta = []): ?FishkaTransaction
    {
        $this->wallet($user);

        return $this->apply($user->id, -$amount, $type, $meta);
    }

    /**
     * Take as many Fishkas as the balance allows, up to $max (a poker buy-in), deciding the amount under
     * the wallet lock.
     *
     * @param  array{description?: string, reference_type?: string, reference_id?: string, idempotency_key?: string}  $meta
     *
     * @throws InsufficientFishkas When the balance is below $min.
     */
    public function chargeUpTo(User $user, int $max, int $min, Type $type, array $meta = []): ?FishkaTransaction
    {
        $this->wallet($user);

        return DB::transaction(function () use ($user, $max, $min, $type, $meta) {
            $balance = $this->lock($user->id)->balance;
            if ($balance < $min) {
                throw new InsufficientFishkas($balance, $min);
            }

            return $this->apply($user->id, -min($balance, $max), $type, $meta);
        });
    }

    /**
     * Give Fishkas (a refund, a poker cash-out).
     *
     * @param  array{description?: string, reference_type?: string, reference_id?: string, idempotency_key?: string}  $meta
     */
    public function credit(User $user, int $amount, Type $type, array $meta = []): ?FishkaTransaction
    {
        $this->wallet($user);

        return $this->apply($user->id, $amount, $type, $meta);
    }

    /**
     * An administrator adds, deducts, or sets an exact balance. Recorded in the ledger (with the
     * administrator, balances before and after, and the reason) and in the audit log.
     *
     * @param  'credit'|'debit'|'set'  $mode
     *
     * @throws ValidationException
     */
    public function adjust(User $target, User $admin, string $mode, int $amount, string $reason): ?FishkaTransaction
    {
        if ($amount < 0 || trim($reason) === '') {
            throw ValidationException::withMessages(['amount' => __('platform.fishka.adjust_invalid')]);
        }
        $this->wallet($target);

        $entry = DB::transaction(function () use ($target, $admin, $mode, $amount, $reason) {
            $balance = $this->lock($target->id)->balance;
            $delta = match ($mode) {
                'credit' => $amount,
                'debit' => -$amount,
                'set' => $amount - $balance,
            };
            if ($balance + $delta < 0) {
                throw ValidationException::withMessages(['amount' => __('platform.fishka.adjust_below_zero', ['balance' => $balance])]);
            }

            return $this->apply($target->id, $delta, $delta > 0 ? Type::AdminCredit : Type::AdminDebit, [
                'description' => $mode === 'set' ? "Balance set to {$amount}" : null,
                'actor_id' => $admin->id,
                'reason' => $reason,
            ]);
        });

        if ($entry !== null) {
            $this->audit->record('fishka.adjusted', $target, [
                'mode' => $mode,
                'amount' => $entry->amount,
                'balance_before' => $entry->balance_before,
                'balance_after' => $entry->balance_after,
                'reason' => $reason,
                'transaction_id' => $entry->id,
            ], $admin);
        }

        return $entry;
    }

    /** Today's date where the platform's day begins. */
    public function today(): string
    {
        return Carbon::now((string) $this->settings->get('fishka.timezone'))->toDateString();
    }

    /**
     * @return array{available: bool, available_at: Carbon|null, amount: int}
     */
    private function refillStatusOf(FishkaWallet $wallet): array
    {
        $amount = (int) $this->settings->get('fishka.refill_bonus');
        if ($wallet->balance >= (int) $this->settings->get('fishka.entry_fee')) {
            return ['available' => false, 'available_at' => null, 'amount' => $amount];
        }

        $next = $wallet->refilled_at?->copy()->addHours((int) $this->settings->get('fishka.refill_cooldown_hours'));

        return $next !== null && $next->isFuture()
            ? ['available' => false, 'available_at' => $next, 'amount' => $amount]
            : ['available' => true, 'available_at' => null, 'amount' => $amount];
    }

    private function lock(int $userId): FishkaWallet
    {
        return FishkaWallet::query()->lockForUpdate()->find($userId)
            ?? throw new RuntimeException("User {$userId} has no Fishka wallet.");
    }

    /**
     * Change a balance by $delta and record it. Zero changes record nothing.
     *
     * @param  array{description?: string|null, reference_type?: string, reference_id?: string, idempotency_key?: string, actor_id?: int, reason?: string}  $meta
     *
     * @throws InsufficientFishkas
     */
    private function apply(int $userId, int $delta, Type $type, array $meta = []): ?FishkaTransaction
    {
        if ($delta === 0) {
            return null;
        }

        return DB::transaction(function () use ($userId, $delta, $type, $meta) {
            $wallet = $this->lock($userId);

            // Checked under the lock: a repeat of a one-time change waits here and then finds the first.
            $key = $meta['idempotency_key'] ?? null;
            if ($key !== null) {
                $done = FishkaTransaction::query()->where('idempotency_key', $key)->first();
                if ($done !== null) {
                    return $done;
                }
            }

            $before = $wallet->balance;
            $after = $before + $delta;
            if ($after < 0) {
                throw new InsufficientFishkas($before, -$delta);
            }

            $wallet->forceFill([
                'balance' => $after,
                'total_earned' => $wallet->total_earned + max(0, $delta),
                'total_spent' => $wallet->total_spent + max(0, -$delta),
            ])->save();

            $entry = FishkaTransaction::query()->create([
                ...$meta,
                'user_id' => $userId,
                'type' => $type,
                'amount' => $delta,
                'balance_before' => $before,
                'balance_after' => $after,
            ]);

            DB::afterCommit(function () use ($userId, $after, $delta) {
                try {
                    event(new WalletUpdated($userId, $after, $delta));
                } catch (Throwable $e) {
                    Log::warning('Wallet broadcast failed.', ['user' => $userId, 'error' => $e->getMessage()]);
                }
            });

            return $entry;
        });
    }
}
