<?php

namespace App\Platform\Slots\House;

use App\Models\SlotGame;
use App\Models\SlotSession;
use App\Models\SlotSpin;
use App\Models\SlotTransaction;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Slots\SlotWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Deals house slot rounds (ADR 0024). The browser sends only "spin, bet X, key K"; the server checks the game and
 * the bet, deals the round with PHP's CSPRNG, and moves the Fishkas through SlotWallet (bet, then win, as a
 * provider would), all in one transaction under the player's row lock. The key is the player's one-time ticket:
 * the same key again returns the round already dealt and charges nothing.
 */
final class HouseSpins
{
    /** The bets a house slot offers, in Fishkas, within the game's min and max. */
    public const BETS = [10, 20, 50, 100, 200, 500, 1000, 2000, 5000, 10000];

    public function __construct(
        private readonly HouseSlots $slots,
        private readonly SlotWallet $wallet,
        private readonly FishkaWallets $wallets,
    ) {}

    /**
     * The game as a player opens it: their balance and the bets they can choose.
     *
     * @return array{balance: int, bets: list<int>, layout?: array<string, mixed>}
     *
     * @throws HouseSpinRefused
     */
    public function open(User $user, SlotGame $game): array
    {
        $engine = $this->engine($game);

        return [
            'balance' => $this->wallets->wallet($user)->balance,
            'bets' => $this->bets($game),
            // A shared client draws the game from this (HasClientLayout); others have their own screens.
            ...($engine instanceof HasClientLayout ? ['layout' => $engine->layout()] : []),
        ];
    }

    /**
     * @return array{round_id: string, round: array<string, mixed>, bet: int, win: int, balance: int, replay: bool}
     *
     * @throws HouseSpinRefused
     */
    public function spin(User $user, SlotGame $game, int $bet, string $key): array
    {
        $engine = $this->engine($game);
        if (! in_array($bet, $this->bets($game), true)) {
            throw new HouseSpinRefused('bet_limits', $this->wallets->wallet($user)->balance);
        }

        return DB::transaction(function () use ($user, $game, $engine, $bet, $key) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $done = SlotSpin::query()->where('user_id', $user->id)->where('spin_key', $key)->first();
            if ($done !== null) {
                if ($done->slot_game_id !== $game->id || $done->bet !== $bet) {
                    throw new HouseSpinRefused('key_conflict', $this->wallets->wallet($user)->balance);
                }

                return $this->answer($done, $this->wallets->wallet($user)->balance, true);
            }

            $session = $this->session($user, $game);
            $dealt = $engine->play(fn (int $n): int => random_int(0, $n - 1));
            $win = (int) floor($bet * $dealt['total_win'] + 1e-9);
            $tx = "u{$user->id}:{$key}";

            $paid = $this->wallet->handleSession($game->provider, $session, ['action' => 'bet', 'tx_id' => "{$tx}:bet", 'round_id' => $key, 'amount' => $bet]);
            if ($paid['status'] !== SlotTransaction::OK) {
                throw new HouseSpinRefused((string) $paid['error'], $paid['balance']);
            }
            $won = $this->wallet->handleSession($game->provider, $session, ['action' => 'win', 'tx_id' => "{$tx}:win", 'round_id' => $key, 'amount' => $win]);
            if ($won['status'] !== SlotTransaction::OK) {
                // Only a misconfigured game (max_multiplier below the engine's max win) gets here: undo the bet too.
                throw new HouseSpinRefused((string) $won['error']);
            }

            $spin = SlotSpin::query()->create([
                'user_id' => $user->id,
                'slot_game_id' => $game->id,
                'slot_session_id' => $session->id,
                'spin_key' => $key,
                'bet' => $bet,
                'win' => $win,
                'result' => $dealt['round'],
            ]);

            return $this->answer($spin, (int) $won['balance'], false);
        });
    }

    /** @throws HouseSpinRefused */
    private function engine(SlotGame $game): HouseSlot
    {
        $engine = $game->provider->slug === HouseSlots::PROVIDER ? $this->slots->for($game->provider_game_id) : null;
        if ($engine === null || ! $game->is_enabled || ! $game->provider->is_enabled) {
            throw new HouseSpinRefused('game_off');
        }

        return $engine;
    }

    /** @return list<int> */
    private function bets(SlotGame $game): array
    {
        return array_values(array_filter(self::BETS, fn (int $b) => $b >= $game->min_bet && $b <= $game->max_bet));
    }

    /** The player's open session for the game, or a new one (its token is never handed out: no outside calls). */
    private function session(User $user, SlotGame $game): SlotSession
    {
        $open = SlotSession::query()->where('user_id', $user->id)->where('slot_game_id', $game->id)
            ->whereNull('closed_at')->where('expires_at', '>', now())->latest('id')->first();

        return $open ?? SlotSession::query()->create([
            'user_id' => $user->id,
            'slot_game_id' => $game->id,
            'token_hash' => hash('sha256', Str::random(48)),
            'expires_at' => now()->addHours(4),
        ]);
    }

    /**
     * @return array{round_id: string, round: array<string, mixed>, bet: int, win: int, balance: int, replay: bool}
     */
    private function answer(SlotSpin $spin, int $balance, bool $replay): array
    {
        return ['round_id' => $spin->public_id, 'round' => $spin->result, 'bet' => $spin->bet, 'win' => $spin->win, 'balance' => $balance, 'replay' => $replay];
    }
}
