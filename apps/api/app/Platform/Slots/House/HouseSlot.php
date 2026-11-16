<?php

namespace App\Platform\Slots\House;

use Closure;

/**
 * A slot game whose results our own server deals (ADR 0024): the maths of an open-source game ported to PHP, with
 * randomness injected. Its browser client only shows the round it is sent; it never decides anything.
 */
interface HouseSlot
{
    /** The game's id, as slot_games.provider_game_id and the client's folder under /play/. */
    public function id(): string;

    /** The biggest round, in total bets (slot_games.max_multiplier must allow it). */
    public function maxWin(): int;

    /**
     * One complete round for one bet: `round` is sent to the client as it is (the shape its client expects) and
     * `total_win` is the round's win in total bets.
     *
     * @param  Closure(int): int  $random  0…n−1
     * @return array{round: array<string, mixed>, total_win: float}
     */
    public function play(Closure $random): array;
}
