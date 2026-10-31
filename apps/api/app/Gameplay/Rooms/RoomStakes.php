<?php

namespace App\Gameplay\Rooms;

use App\Enums\FishkaTransactionType as Type;
use App\Gameplay\Contracts\PlaysForChips;
use App\Gameplay\GameRegistry;
use App\Models\GameMode;
use App\Models\GameParticipant;
use App\Models\GameRoom;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Fishka\InsufficientFishkas;
use App\Platform\Settings\Settings;

/**
 * What a seat costs in Fishkas. Most games charge the entry fee once, when a person takes a seat.
 * Games played for chips (PlaysForChips: poker) take a buy-in instead and pay the chips back when
 * the player leaves or the game ends. A fee or buy-in is given back if the game never starts. Bots
 * pay nothing; their chips come from the house. Every charge is keyed to the seat, so a repeated
 * request can't charge or pay twice.
 */
class RoomStakes
{
    public function __construct(
        private readonly GameRegistry $registry,
        private readonly FishkaWallets $wallets,
        private readonly Settings $settings,
    ) {}

    /** Fishkas a player needs to sit down: the entry fee, or 1 for a game played for chips. */
    public function minimum(GameMode $mode): int
    {
        return $this->chips($mode) !== null ? 1 : (int) $this->settings->get('fishka.entry_fee');
    }

    /**
     * @throws InsufficientFishkas
     */
    public function ensureCanAfford(User $user, GameMode $mode): void
    {
        $needed = $this->minimum($mode);
        $balance = $this->wallets->wallet($user)->balance;

        if ($needed > 0 && $balance < $needed) {
            throw new InsufficientFishkas($balance, $needed);
        }
    }

    public function canAfford(User $user, GameMode $mode): bool
    {
        return $this->wallets->wallet($user)->balance >= $this->minimum($mode);
    }

    /**
     * Charge a person for the seat they just took.
     *
     * @throws InsufficientFishkas
     */
    public function take(GameRoom $room, GameParticipant $participant, User $user): void
    {
        $engine = $this->chips($room->mode);
        $meta = [
            'reference_type' => 'game_room',
            'reference_id' => $room->public_id,
            'idempotency_key' => "stake:{$participant->id}",
        ];

        $entry = $engine !== null
            ? $this->wallets->chargeUpTo($user, $engine->buyIn($room->options ?? []), 1, Type::PokerBet, [...$meta, 'description' => "{$room->game->name}: buy-in"])
            : $this->wallets->charge($user, $this->minimum($room->mode), Type::GameEntryFee, [...$meta, 'description' => "{$room->game->name}: entry fee"]);

        $participant->forceFill(['stake' => $entry === null ? 0 : -$entry->amount])->save();
    }

    /** Give back the fee or buy-in of a seat in a game that never started. */
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
     * The game is over: everyone still seated cashes out.
     *
     * @param  array<string, mixed>  $state
     */
    public function settle(GameRoom $room, array $state): void
    {
        foreach ($room->participants as $participant) {
            $this->cashOut($room, $participant, $state);
        }
    }

    /**
     * Starting chips per seat for a game played for chips: each person's buy-in; null (the table's
     * normal stack) for bots. Null when the game doesn't play for chips.
     *
     * @return list<int|null>|null
     */
    public function stacks(GameRoom $room): ?array
    {
        if ($this->chips($room->mode) === null) {
            return null;
        }

        $bySeat = $room->participants->keyBy('seat');

        return array_map(
            fn (int $seat) => ($p = $bySeat->get($seat)) !== null && $p->user_id !== null ? $p->stake : null,
            range(0, $room->seats - 1),
        );
    }

    private function chips(GameMode $mode): ?PlaysForChips
    {
        $engine = $this->registry->get($mode->game->slug)->engine();

        return $engine instanceof PlaysForChips ? $engine : null;
    }
}
