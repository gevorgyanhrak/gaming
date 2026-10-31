<?php

namespace App\Gameplay\Contracts;

/**
 * Optional for games played with chips that are the players' own Fishkas (poker). Instead of the
 * entry fee, each person buys in when they take a seat (up to buyIn(), at most their balance) and
 * starts with that many chips: the engine reads options['stacks'][seat] when it is set (bots get the
 * table's normal stack from the house). Leaving, or the end of the game, pays chipsOf() back.
 */
interface PlaysForChips
{
    /**
     * The most a player can bring to the table.
     *
     * @param  array<string, mixed>  $options  The mode's options.
     */
    public function buyIn(array $options): int;

    /**
     * The chips $seat takes away if they leave now or the game stops now: their stack, plus chips
     * they put into a hand that will never be played out.
     *
     * @param  array<string, mixed>  $state
     */
    public function chipsOf(array $state, int $seat): int;
}
