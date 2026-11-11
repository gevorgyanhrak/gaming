<?php

namespace App\Gameplay\Contracts;

/**
 * Optional for games played in partnerships (blot, spades): when a player resigns, leaves or runs out of
 * time, their partners lose with them and only the other team wins.
 */
interface PlaysInTeams
{
    /**
     * The seats on $seat's team, $seat included.
     *
     * @return list<int>
     */
    public function teamOf(int $seat, int $seats): array;
}
