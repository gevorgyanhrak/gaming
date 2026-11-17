<?php

namespace App\Platform\Slots\Engine;

use App\Models\SlotEngineState;
use App\Models\SlotGame;
use App\Models\SlotSession;
use App\Models\SlotSpin;
use App\Models\SlotTransaction;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Settings\Settings;
use App\Platform\Slots\House\HouseSpinRefused;
use App\Platform\Slots\SlotWallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Plays engine slots for players (ADR 0026). The browser sends only "spin, bet per line, lines, key"; the engine
 * decides the screen and the wins; our wallet moves the Fishkas. A paid spin opens a round (its key) and is charged
 * bet × lines; the free games and cascade falls it brings cost nothing and pay into the same round, so the
 * provider wallet's win cap and idempotency hold. Everything happens in one transaction under the player's row
 * lock; an engine failure rolls it all back (nothing charged). The game state is kept per player and game, so a
 * reload or another device continues where the player was — free games included.
 */
final class EngineSpins
{
    public function __construct(
        private readonly SlotEngine $engine,
        private readonly SlotWallet $wallet,
        private readonly FishkaWallets $wallets,
        private readonly Settings $settings,
    ) {}

    /**
     * The game as the player finds it: their balance, the bets and lines they can choose, and the screen and free
     * games where they left it.
     *
     * @return array{balance: int, bets: list<int>, lines: array{min: int, max: int, fixed: bool, default: int}, unit: int, state: array<string, mixed>, free: bool}
     *
     * @throws HouseSpinRefused
     */
    public function open(User $user, SlotGame $game): array
    {
        $this->check($game);
        $saved = SlotEngineState::query()->where('user_id', $user->id)->where('slot_game_id', $game->id)->first();
        $state = $saved->state ?? (array) ($game->meta['state'] ?? []);

        return [
            'balance' => $this->wallets->wallet($user)->balance,
            ...$this->terms($game),
            'state' => $state,
            'free' => $saved?->round_key !== null,
        ];
    }

    /**
     * @return array{round_id: string, bet: int, win: int, balance: int, replay: bool, screen: array<string, mixed>, wins: list<array{pay?: float, mp?: float, sym?: int, num?: int, li?: int, xy?: list<list<int>>, fs?: int, bid?: int, jid?: int}>, free: bool, next: bool, free_left: int, series_win: int, round: string}
     *
     * @throws HouseSpinRefused
     */
    public function spin(User $user, SlotGame $game, int $bet, int $lines, string $key): array
    {
        $this->check($game);

        return DB::transaction(function () use ($user, $game, $bet, $lines, $key) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $done = SlotSpin::query()->where('user_id', $user->id)->where('spin_key', $key)->first();
            if ($done !== null) {
                if ($done->slot_game_id !== $game->id) {
                    throw new HouseSpinRefused('key_conflict', $this->wallets->wallet($user)->balance);
                }

                return $this->answer($done, $this->wallets->wallet($user)->balance, true);
            }

            $saved = SlotEngineState::query()->where('user_id', $user->id)->where('slot_game_id', $game->id)
                ->lockForUpdate()->first();
            $state = $saved->state ?? (array) ($game->meta['state'] ?? []);
            $inRound = $saved?->round_key !== null;

            $sel = 0;
            $fixed = true;
            if (! $inRound) {
                $terms = $this->terms($game);
                // A game without line selection refuses any lines value, even its own: none is sent then.
                $fixed = $terms['lines']['fixed'];
                $sel = $terms['lines']['fixed'] || $lines < 1 ? $terms['lines']['default'] : $lines;
                $price = $bet * ($fixed ? $terms['unit'] : $sel);
                if (! in_array($bet, $terms['bets'], true) || (! $fixed && ($sel < $terms['lines']['min'] || $sel > $terms['lines']['max']))
                    || $price < $game->min_bet || $price > $game->max_bet) {
                    throw new HouseSpinRefused('bet_limits', $this->wallets->wallet($user)->balance);
                }
            }

            try {
                // In free games and falls the game keeps its bet and lines; it refuses changes itself.
                $out = $this->engine->spin($game->provider_game_id, $state, $inRound ? 0 : $bet, $inRound || $fixed ? 0 : $sel, $this->rtp());
            } catch (EngineRefused) {
                throw new HouseSpinRefused('bet_limits', $this->wallets->wallet($user)->balance);
            } catch (EngineUnavailable $e) {
                // For operators: which game and why (never the key, the state or the player's data).
                Log::warning('slot engine spin failed', ['game' => $game->provider_game_id, 'reason' => $e->getMessage()]);
                throw new HouseSpinRefused('game_off', $this->wallets->wallet($user)->balance);
            }

            $session = $this->session($user, $game);
            $tx = "u{$user->id}:{$key}";
            $cost = (int) round($out['cost']);
            $round = $out['free'] && $inRound ? (string) $saved->round_key : $key;
            $balance = $this->wallets->wallet($user)->balance;

            if (! $out['free']) {
                $paid = $this->wallet->handleSession($game->provider, $session, ['action' => 'bet', 'tx_id' => "{$tx}:bet", 'round_id' => $round, 'amount' => $cost]);
                if ($paid['status'] !== SlotTransaction::OK) {
                    throw new HouseSpinRefused((string) $paid['error'], $paid['balance']);
                }
                $balance = (int) $paid['balance'];
            }
            // Wins are whole Fishkas (rounded down); a free spin's win joins the round of the spin that brought it.
            $win = (int) floor($out['win'] + 1e-9);
            if ($win > 0) {
                $won = $this->wallet->handleSession($game->provider, $session, ['action' => 'win', 'tx_id' => "{$tx}:win", 'round_id' => $round, 'amount' => $win]);
                if ($won['status'] !== SlotTransaction::OK) {
                    throw new HouseSpinRefused((string) $won['error']);
                }
                $balance = (int) $won['balance'];
            }

            SlotEngineState::query()->updateOrCreate(
                ['user_id' => $user->id, 'slot_game_id' => $game->id],
                [
                    'state' => $out['state'],
                    'round_key' => $out['next'] ? $round : null,
                    'round_bet' => $out['next'] ? ($out['free'] ? (int) $saved?->round_bet : $cost) : 0,
                ],
            );

            $spin = SlotSpin::query()->create([
                'user_id' => $user->id,
                'slot_game_id' => $game->id,
                'slot_session_id' => $session->id,
                'spin_key' => $key,
                'bet' => $out['free'] ? 0 : $cost,
                'win' => $win,
                'result' => [
                    // The game's screen as the engine left it: reels (columns of symbol numbers) and what the game adds.
                    'screen' => $out['state'],
                    'wins' => $out['wins'],
                    'free' => $out['free'],
                    'next' => $out['next'],
                    'free_left' => $out['fsr'],
                    'series_win' => (int) floor($out['total'] + 1e-9),
                    'round' => $round,
                ],
            ]);

            return $this->answer($spin, $balance, false);
        });
    }

    /**
     * The bets a player may choose and the lines: per line where the game lets you pick lines (a spin costs bet ×
     * lines), else per spin at the game's own price (`cost` = what a spin costs at bet 1, e.g. 25 for a 243-ways game).
     *
     * @return array{bets: list<int>, lines: array{min: int, max: int, fixed: bool, default: int}, unit: int}
     */
    public function terms(SlotGame $game): array
    {
        $meta = $game->meta ?? [];
        // The game's own default lines; a state that doesn't carry them means all of the game's lines.
        $default = (int) ($meta['state']['sel'] ?? $meta['lines'] ?? 0);
        $selectable = in_array('line_select', (array) ($meta['features'] ?? []), true) && (int) ($meta['lines'] ?? 0) > 1;
        $unit = max(1, (int) round((float) ($meta['cost'] ?? max(1, $default))));
        $max = $selectable ? (int) $meta['lines'] : $default;

        return [
            'bets' => array_values(array_filter(
                EngineSlots::LINE_BETS,
                // The cheapest choice with this bet must reach the game's minimum, the default one stay under its maximum.
                fn (int $b) => $b * $unit <= $game->max_bet && $b * ($selectable ? 1 : $unit) >= $game->min_bet,
            )),
            'lines' => ['min' => $selectable ? 1 : $default, 'max' => $max, 'fixed' => ! $selectable, 'default' => $default],
            'unit' => $unit,
        ];
    }

    /** The RTP engine slots play at (admin setting); the engine uses each game's closest reel set. */
    private function rtp(): float
    {
        return (float) $this->settings->get('slots.engine_rtp');
    }

    /** @throws HouseSpinRefused */
    private function check(SlotGame $game): void
    {
        if ($game->provider->slug !== EngineSlots::PROVIDER || ! $game->is_enabled || ! $game->provider->is_enabled) {
            throw new HouseSpinRefused('game_off');
        }
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
     * @return array{round_id: string, bet: int, win: int, balance: int, replay: bool, screen: array<string, mixed>, wins: list<array{pay?: float, mp?: float, sym?: int, num?: int, li?: int, xy?: list<list<int>>, fs?: int, bid?: int, jid?: int}>, free: bool, next: bool, free_left: int, series_win: int, round: string}
     */
    private function answer(SlotSpin $spin, int $balance, bool $replay): array
    {
        /** @var array<string, mixed> $r */
        $r = $spin->result;

        return [
            'round_id' => $spin->public_id,
            'bet' => $spin->bet,
            'win' => $spin->win,
            'balance' => $balance,
            'replay' => $replay,
            /**
             * The game's screen as the engine left it: `grid` (reels, each a column of symbol numbers from the top;
             * 0 is an empty cell) and whatever the game adds (expanding symbol, multipliers, cascade level…).
             *
             * @var array<string, mixed>
             */
            'screen' => (array) ($r['screen'] ?? []),
            /**
             * The engine's win items: pay (Fishkas), mp (multiplier), sym, num (symbols), li (line, 0 for scatters),
             * xy (1-based [reel, row] positions), fs (free games won).
             *
             * @var list<array{pay?: float, mp?: float, sym?: int, num?: int, li?: int, xy?: list<list<int>>, fs?: int}>
             */
            'wins' => array_values((array) ($r['wins'] ?? [])),
            'free' => (bool) ($r['free'] ?? false),
            'next' => (bool) ($r['next'] ?? false),
            'free_left' => (int) ($r['free_left'] ?? 0),
            'series_win' => (int) ($r['series_win'] ?? 0),
            'round' => (string) ($r['round'] ?? ''),
        ];
    }
}
