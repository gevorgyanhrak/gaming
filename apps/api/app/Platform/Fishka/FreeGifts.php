<?php

namespace App\Platform\Fishka;

use App\Enums\FishkaTransactionType as Type;
use App\Models\FishkaTransaction;
use App\Models\User;
use App\Platform\Settings\Settings;
use Illuminate\Validation\ValidationException;

/**
 * Free Fishkas besides the daily bonus (owner, 2026-11-12): a gift every few hours, and a lucky wheel spun
 * once a day. Amounts, the interval and the wheel's prizes and odds are settings. Each is a ledger entry with
 * a one-time key (per time slot, per day), so a double click or a race can't pay twice. Fishkas are never
 * bought or sold; these are gifts.
 */
class FreeGifts
{
    public function __construct(
        private readonly FishkaWallets $wallets,
        private readonly Settings $settings,
    ) {}

    /**
     * @return array{amount: int, available: bool, next_at: int}
     */
    public function gift(User $user): array
    {
        $slot = $this->slot();
        $taken = $this->taken("gift:{$user->id}:{$slot}");

        return [
            'amount' => (int) $this->settings->get('fishka.gift_amount'),
            'available' => ! $taken,
            'next_at' => $taken ? ($slot + 1) * $this->interval() : now()->getTimestamp(),
        ];
    }

    /** Collect the gift of this time slot: the amount added, 0 if it was already taken. */
    public function claimGift(User $user): int
    {
        $amount = (int) $this->settings->get('fishka.gift_amount');
        $key = "gift:{$user->id}:{$this->slot()}";
        if ($amount <= 0 || $this->taken($key)) {
            return 0;
        }
        $entry = $this->wallets->credit($user, $amount, Type::FreeGift, ['description' => 'Free gift', 'idempotency_key' => $key]);

        return $entry !== null && $entry->wasRecentlyCreated ? $amount : 0;
    }

    /**
     * @return array{available: bool, next_at: int, prizes: list<int>}
     */
    public function wheel(User $user): array
    {
        $taken = $this->taken("wheel:{$user->id}:{$this->wallets->today()}");

        return [
            'available' => ! $taken,
            'next_at' => $taken ? now()->addDay()->startOfDay()->getTimestamp() : now()->getTimestamp(),
            'prizes' => array_column($this->prizes(), 0),
        ];
    }

    /**
     * Spin today's wheel: the prize's place on the wheel and its amount, chosen here by the prizes' odds.
     *
     * @return array{index: int, amount: int}
     */
    public function spin(User $user): array
    {
        $key = "wheel:{$user->id}:{$this->wallets->today()}";
        if ($this->taken($key)) {
            throw ValidationException::withMessages(['wheel' => __('platform.fishka.wheel_spun')]);
        }
        $prizes = $this->prizes();
        $pick = random_int(1, max(1, array_sum(array_column($prizes, 1))));
        $index = 0;
        foreach ($prizes as $i => [, $weight]) {
            $pick -= $weight;
            if ($pick <= 0) {
                $index = $i;
                break;
            }
        }
        $amount = $prizes[$index][0];
        $entry = $this->wallets->credit($user, $amount, Type::WheelPrize, ['description' => 'Lucky wheel', 'idempotency_key' => $key]);
        if ($entry === null || ! $entry->wasRecentlyCreated) {
            throw ValidationException::withMessages(['wheel' => __('platform.fishka.wheel_spun')]);
        }

        return ['index' => $index, 'amount' => $amount];
    }

    /**
     * @return list<array{0: int, 1: int}> amount and weight per slice
     */
    private function prizes(): array
    {
        $prizes = [];
        foreach ((array) $this->settings->get('fishka.wheel_prizes') as $entry) {
            [$amount, $weight] = array_map('intval', explode(':', (string) $entry) + [1 => '1']);
            $prizes[] = [max(0, $amount), max(0, $weight)];
        }

        return $prizes === [] ? [[100, 1]] : $prizes;
    }

    private function taken(string $key): bool
    {
        return FishkaTransaction::query()->where('idempotency_key', $key)->exists();
    }

    private function interval(): int
    {
        return max(1, (int) $this->settings->get('fishka.gift_interval_hours')) * 3600;
    }

    private function slot(): int
    {
        return intdiv(now()->getTimestamp(), $this->interval());
    }
}
