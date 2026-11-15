<?php

namespace App\Platform\Slots;

use App\Enums\FishkaTransactionType as Type;
use App\Models\SlotProvider;
use App\Models\SlotSession;
use App\Models\SlotTransaction;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Fishka\InsufficientFishkas;
use Illuminate\Support\Facades\DB;

/**
 * The only way a slot provider moves Fishkas: its signed server-to-server calls (balance, bet, win, rollback),
 * never the browser. Each call names an open session we issued for one player; every money call carries the
 * provider's transaction id, unique per provider — a repeat gets the stored answer and moves nothing. Calls run
 * under the player's row lock (lock order: user → wallet), so racing repeats wait and then find the first.
 *
 * Rules: bets only in an open session of a game that is on, within its limits, covered by the balance; a win
 * needs a bet in the same round, and a round never pays more than the game's max_multiplier × its bets; a
 * rollback gives back one unsettled bet once. Refusals are recorded (the admin's error list) and answered.
 */
final class SlotWallet
{
    public function __construct(private readonly FishkaWallets $wallets) {}

    /**
     * @param  array{action: string, session: string, tx_id?: string|null, round_id?: string|null, amount?: int|null, reference_tx_id?: string|null}  $call
     * @return array{status: string, error: string|null, balance: int|null, tx_id: string|null}
     */
    public function handle(SlotProvider $provider, array $call): array
    {
        $session = SlotSession::query()->with(['game', 'user'])
            ->where('token_hash', hash('sha256', $call['session']))
            ->first();
        if ($session === null || $session->game->slot_provider_id !== $provider->id) {
            return $this->refuseWithoutSession($provider, $call, 'bad_session');
        }

        if ($call['action'] === 'balance') {
            return $this->answer(SlotTransaction::OK, null, $this->wallets->wallet($session->user)->balance, null);
        }

        return DB::transaction(function () use ($provider, $session, $call) {
            User::query()->lockForUpdate()->findOrFail($session->user_id);

            $txId = (string) $call['tx_id'];
            $done = SlotTransaction::query()->where('slot_provider_id', $provider->id)->where('provider_tx_id', $txId)->first();
            if ($done !== null) {
                $same = $done->type === $call['action'] && $done->amount === (int) ($call['amount'] ?? 0)
                    && $done->slot_session_id === $session->id;

                return $same
                    ? $this->answer($done->status, $done->error, $this->balance($session), $txId)
                    : $this->answer(SlotTransaction::REFUSED, 'tx_conflict', $this->balance($session), $txId);
            }

            $row = [
                'slot_provider_id' => $provider->id,
                'provider_tx_id' => $txId,
                'slot_session_id' => $session->id,
                'user_id' => $session->user_id,
                'round_id' => $call['round_id'] ?? null,
                'type' => $call['action'],
                'amount' => (int) ($call['amount'] ?? 0),
                'reference_tx_id' => $call['reference_tx_id'] ?? null,
            ];

            try {
                $balance = match ($call['action']) {
                    'bet' => $this->bet($provider, $session, $row),
                    'win' => $this->win($provider, $session, $row),
                    'rollback' => $this->rollback($provider, $session, $row),
                    default => throw new SlotRefused('bad_action'),
                };
            } catch (SlotRefused $refused) {
                SlotTransaction::query()->create([...$row, 'status' => SlotTransaction::REFUSED, 'error' => $refused->reason]);

                return $this->answer(SlotTransaction::REFUSED, $refused->reason, $this->balance($session), $txId);
            }

            SlotTransaction::query()->create([...$row, 'status' => SlotTransaction::OK, 'balance_after' => $balance]);
            $session->forceFill(['last_played_at' => now()])->save();

            return $this->answer(SlotTransaction::OK, null, $balance, $txId);
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function bet(SlotProvider $provider, SlotSession $session, array $row): int
    {
        $game = $session->game;
        $amount = $row['amount'];
        if (! $session->isOpen()) {
            throw new SlotRefused('session_closed');
        }
        if (! $provider->is_enabled || ! $game->is_enabled) {
            throw new SlotRefused('game_off');
        }
        if ($row['round_id'] === null || $amount < $game->min_bet || $amount > $game->max_bet) {
            throw new SlotRefused('bet_limits');
        }

        try {
            $entry = $this->wallets->charge($session->user, $amount, Type::SlotBet, $this->meta($provider, $row, 'Slot bet'));
        } catch (InsufficientFishkas) {
            throw new SlotRefused('insufficient_fishkas');
        }

        return $entry === null ? $this->balance($session) : $entry->balance_after;
    }

    /**
     * A round's win (0 closes a lost round). Paid even after the session ended, since its bet was taken in it.
     *
     * @param  array<string, mixed>  $row
     */
    private function win(SlotProvider $provider, SlotSession $session, array $row): int
    {
        if ($row['round_id'] === null) {
            throw new SlotRefused('no_bet');
        }
        $round = SlotTransaction::query()
            ->where('slot_session_id', $session->id)
            ->where('round_id', $row['round_id'])
            ->where('status', SlotTransaction::OK)
            ->get();
        $rolledBack = $round->where('type', 'rollback')->pluck('reference_tx_id')->all();
        $bets = $round->where('type', 'bet')->reject(fn (SlotTransaction $t) => in_array($t->provider_tx_id, $rolledBack, true))->sum('amount');
        if ($bets === 0) {
            throw new SlotRefused('no_bet');
        }
        $paid = $round->where('type', 'win')->sum('amount');
        if ($paid + $row['amount'] > $bets * $session->game->max_multiplier) {
            throw new SlotRefused('win_over_limit');
        }

        if ($row['amount'] === 0) {
            return $this->balance($session);
        }
        $entry = $this->wallets->credit($session->user, $row['amount'], Type::SlotWin, $this->meta($provider, $row, 'Slot win'));

        return $entry === null ? $this->balance($session) : $entry->balance_after;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rollback(SlotProvider $provider, SlotSession $session, array $row): int
    {
        $bet = $row['reference_tx_id'] === null ? null : SlotTransaction::query()
            ->where('slot_provider_id', $provider->id)
            ->where('provider_tx_id', $row['reference_tx_id'])
            ->where('slot_session_id', $session->id)
            ->where('type', 'bet')
            ->where('status', SlotTransaction::OK)
            ->first();
        $settled = $bet !== null && SlotTransaction::query()
            ->where('slot_session_id', $session->id)
            ->where('status', SlotTransaction::OK)
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('type', 'win')->where('round_id', $bet->round_id))
                ->orWhere(fn ($q) => $q->where('type', 'rollback')->where('reference_tx_id', $bet->provider_tx_id)))
            ->exists();
        if ($bet === null || $settled) {
            throw new SlotRefused('not_rollbackable');
        }

        $row['amount'] = $bet->amount;
        $entry = $this->wallets->credit($session->user, $bet->amount, Type::SlotRefund, $this->meta($provider, $row, 'Slot bet returned'));

        return $entry === null ? $this->balance($session) : $entry->balance_after;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{description: string, reference_type: string, reference_id: string, idempotency_key: string}
     */
    private function meta(SlotProvider $provider, array $row, string $description): array
    {
        return [
            'description' => $description,
            'reference_type' => 'slot_round',
            'reference_id' => (string) $row['round_id'],
            'idempotency_key' => "slot:{$provider->id}:{$row['provider_tx_id']}",
        ];
    }

    private function balance(SlotSession $session): int
    {
        return $this->wallets->wallet($session->user)->balance;
    }

    /**
     * @param  array{action: string, session: string, tx_id?: string|null, round_id?: string|null, amount?: int|null, reference_tx_id?: string|null}  $call
     * @return array{status: string, error: string|null, balance: int|null, tx_id: string|null}
     */
    private function refuseWithoutSession(SlotProvider $provider, array $call, string $reason): array
    {
        $txId = $call['tx_id'] ?? null;
        if ($txId !== null && $call['action'] !== 'balance') {
            SlotTransaction::query()->firstOrCreate(
                ['slot_provider_id' => $provider->id, 'provider_tx_id' => $txId],
                ['type' => $call['action'], 'amount' => (int) ($call['amount'] ?? 0), 'round_id' => $call['round_id'] ?? null, 'status' => SlotTransaction::REFUSED, 'error' => $reason],
            );
        }

        return $this->answer(SlotTransaction::REFUSED, $reason, null, $txId);
    }

    /**
     * @return array{status: string, error: string|null, balance: int|null, tx_id: string|null}
     */
    private function answer(string $status, ?string $error, ?int $balance, ?string $txId): array
    {
        return ['status' => $status, 'error' => $error, 'balance' => $balance, 'tx_id' => $txId];
    }
}
