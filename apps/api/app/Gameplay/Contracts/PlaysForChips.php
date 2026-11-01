<?php

namespace App\Gameplay\Contracts;

/**
 * Optional for games played with chips that are the players' own Fishkas (poker). Instead of a bet,
 * each person brings their whole balance to the table when they take a seat and starts with that many
 * chips: the engine reads options['stacks'][seat] (bots get stacks like the people's, from the house).
 * Leaving, or the end of the game, pays chipsOf() back. A person whose chipsOf() reaches 0 is out:
 * their game ends as a loss, and the game ends when only bots are left.
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
     * they put into a hand that will never be played out. 0 only when the seat has lost everything.
     *
     * @param  array<string, mixed>  $state
     */
    public function chipsOf(array $state, int $seat): int;
}
