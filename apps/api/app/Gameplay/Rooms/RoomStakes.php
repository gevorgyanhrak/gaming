<?php

namespace App\Gameplay\Rooms;

use App\Enums\FishkaTransactionType as Type;
use App\Enums\ParticipantResult;
use App\Enums\RoomStatus;
use App\Gameplay\Contracts\PlaysForChips;
use App\Gameplay\GameRegistry;
use App\Models\GameMode;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Models\PlayGroup;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Fishka\InsufficientFishkas;
use App\Platform\Settings\Settings;
use Illuminate\Validation\ValidationException;

/**
 * What a seat costs in Fishkas, and what a game pays out. Tables belong to a play group: its minimum
 * balance is only an entry check, never charged.
 *
 * - Stake games (most): each person stakes the table's bet when they take a seat; when the game ends the
 *   winners share the pot (bet × seats; bots' bets come from the house and their shares go back to it),
 *   a draw returns the stakes, a player who leaves loses theirs.
 * - Games played for chips (PlaysForChips: poker): each person brings their whole balance to the table as
 *   chips and takes their chips back when they leave or the game ends. Bots sit with stacks like the
 *   people's, from the house.
 * - Rooms from before groups existed pay the flat entry fee.
 *
 * A stake or buy-in is given back if the game never starts. Every charge and payout is keyed to the seat,
 * so a repeated request can't charge or pay twice.
 */
class RoomStakes
{
    public function __construct(
        private readonly GameRegistry $registry,
        private readonly FishkaWallets $wallets,
        private readonly Settings $settings,
    ) {}

    /**
     * Fishkas a player needs to sit down: the group's minimum balance, and at least the table's bet
     * (stake games) or 1 Fishka (poker). Without a group: the entry fee, or 1 for poker.
     */
    public function minimum(GameMode $mode, ?TableTerms $terms = null): int
    {
        $chips = $this->chips($mode) !== null;
        if ($terms === null) {
            return $chips ? 1 : (int) $this->settings->get('fishka.entry_fee');
        }

        return max($terms->group->min_balance, $chips ? 1 : $terms->stake);
    }

    /**
     * Not enough for the lowest group: "out of Fishkas" (the client offers the bonus). Enough to play,
     * but not in this group: a validation error naming what the group needs.
     *
     * @throws InsufficientFishkas|ValidationException
     */
    public function ensureCanAfford(User $user, GameMode $mode, ?TableTerms $terms = null): void
    {
        $needed = $this->minimum($mode, $terms);
        $balance = $this->wallets->wallet($user)->balance;
        if ($needed <= 0 || $balance >= $needed) {
            return;
        }

        $lowest = $terms === null ? null : PlayGroup::query()->where('kind', $terms->group->kind)->where('is_enabled', true)->orderBy('position')->first();
        if ($terms !== null && $lowest !== null && $lowest->id !== $terms->group->id) {
            $lowestNeeds = $this->minimum($mode, new TableTerms($lowest, $lowest->min_bet));
            if ($balance >= $lowestNeeds) {
                throw ValidationException::withMessages(['group' => __('platform.play.group_balance', ['needed' => number_format($needed)])]);
            }
            // Not even the lowest group: say what it takes to play at all.
            $needed = $lowestNeeds;
        }

        throw new InsufficientFishkas($balance, $needed);
    }

    public function canAfford(User $user, GameMode $mode, ?TableTerms $terms = null): bool
    {
        return $this->wallets->wallet($user)->balance >= $this->minimum($mode, $terms);
    }

    /**
     * Charge a person for the seat they just took.
     *
     * @throws InsufficientFishkas
     */
    public function take(GameRoom $room, GameParticipant $participant, User $user): void
    {
        $meta = [
            'reference_type' => 'game_room',
            'reference_id' => $room->public_id,
            'idempotency_key' => "stake:{$participant->id}",
        ];
        $game = $room->game->name;

        $entry = match (true) {
            $this->chips($room->mode) !== null => $this->wallets->chargeUpTo($user, PHP_INT_MAX, 1, Type::PokerBet, [...$meta, 'description' => "{$game}: buy-in"]),
            $room->stake > 0 => $this->wallets->charge($user, $room->stake, Type::GameStake, [...$meta, 'description' => "{$game}: bet"]),
            default => $this->wallets->charge($user, $this->minimum($room->mode), Type::GameEntryFee, [...$meta, 'description' => "{$game}: entry fee"]),
        };

        $participant->forceFill(['stake' => $entry === null ? 0 : -$entry->amount])->save();
    }

    /** Give back the stake or buy-in of a seat in a game that never started. */
    public function refund(GameRoom $room, GameParticipant $participant): void
    {
        $user = $participant->user;
        if ($user === null || $participant->stake <= 0) {
            return;
        }

        $this->wallets->credit($user, $participant->stake, Type::GameEntryRefund, [
            'description' => "{$room->game->name}: refund, the game did not start",
            'reference_type' => 'game_room',
            'reference_id' => $room->public_id,
            'idempotency_key' => "refund:{$participant->id}",
        ]);
    }

    /**
     * Pay a player's chips back into their wallet (a game played for chips): when they leave, and for
     * everyone still seated when the game ends. Once per seat.
     *
     * @param  array<string, mixed>  $state
     */
    public function cashOut(GameRoom $room, GameParticipant $participant, array $state): void
    {
        $engine = $this->chips($room->mode);
        $user = $participant->user;
        if ($engine === null || $user === null || $participant->stake <= 0) {
            return;
        }

        $this->wallets->credit($user, $engine->chipsOf($state, $participant->seat), Type::PokerWinning, [
            'description' => "{$room->game->name}: chips taken from the table",
            'reference_type' => 'game_room',
            'reference_id' => $room->public_id,
            'idempotency_key' => "cashout:{$participant->id}",
        ]);
    }

    /**
     * The game is over (call after the results are recorded): chip games cash everyone out; stake games
     * share the pot between the winners, or give the stakes back after a draw.
     *
     * @param  array<string, mixed>  $state
     */
    public function settle(GameRoom $room, array $state): void
    {
        if ($this->chips($room->mode) !== null) {
            foreach ($room->participants as $participant) {
                $this->cashOut($room, $participant, $state);
            }

            return;
        }
        if ($room->stake <= 0) {
            return;
        }

        $seated = $room->participants()->with('user')->get();
        $winners = $seated->filter(fn (GameParticipant $p) => $p->result === ParticipantResult::Win);
        $draw = $winners->isEmpty() && $seated->contains(fn (GameParticipant $p) => $p->result === ParticipantResult::Draw);
        $share = $winners->isEmpty() ? 0 : intdiv($room->stake * $room->seats, $winners->count());

        foreach ($draw ? $seated->filter(fn (GameParticipant $p) => $p->result === ParticipantResult::Draw) : $winners as $participant) {
            if ($participant->user === null) {
                continue;
            }
            $this->wallets->credit($participant->user, $draw ? $participant->stake : $share, Type::GamePayout, [
                'description' => $draw ? "{$room->game->name}: draw, bet returned" : "{$room->game->name}: winnings",
                'reference_type' => 'game_room',
                'reference_id' => $room->public_id,
                'idempotency_key' => "payout:{$participant->id}",
            ]);
        }
    }

    /**
     * Fishkas the player has at tables right now (games played for chips: their current chips, or the
     * buy-in at a table that hasn't started), so balances can be shown with them. Counting the waiting
     * table matters: a bot table is charged a moment before it starts, and the wallet must not look
     * empty in between.
     */
    public function atTable(User $user): int
    {
        $total = 0;
        $seats = GameParticipant::query()->where('user_id', $user->id)->where('is_active', true)->where('stake', '>', 0)
            ->whereHas('room', fn ($q) => $q->whereIn('status', [RoomStatus::Waiting, RoomStatus::Active]))
            ->with('room.game', 'room.mode')->get();

        foreach ($seats as $seat) {
            $engine = $this->chips($seat->room->mode);
            if ($engine !== null) {
                $total += $seat->room->status === RoomStatus::Waiting
                    ? $seat->stake
                    : $engine->chipsOf(app(RoomStates::class)->current($seat->room)->state, $seat->seat);
            }
        }

        return $total;
    }

    /**
     * Starting chips per seat for a game played for chips: each person's buy-in; bots get the people's
     * average (from the house), so a table is fair at any group. Null when the game doesn't play for chips.
     *
     * @return list<int|null>|null
     */
    public function stacks(GameRoom $room): ?array
    {
        if ($this->chips($room->mode) === null) {
            return null;
        }

        $bySeat = $room->participants->keyBy('seat');
        $people = $room->participants->whereNotNull('user_id')->pluck('stake');
        $bots = $people->isEmpty() ? null : max(1, (int) round($people->avg()));

        return array_map(
            fn (int $seat) => ($p = $bySeat->get($seat)) !== null && $p->user_id !== null ? $p->stake : $bots,
            range(0, $room->seats - 1),
        );
    }

    private function chips(GameMode $mode): ?PlaysForChips
    {
        $engine = $this->registry->get($mode->game->slug)->engine();

        return $engine instanceof PlaysForChips ? $engine : null;
    }
}
