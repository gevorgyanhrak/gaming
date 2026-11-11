<?php

namespace App\Gameplay\Engine;

/**
 * How a finished game ended. No winners means a draw.
 */
final readonly class Outcome
{
    /**
     * A seat nobody sits at: the house won (games against a dealer, or a player alone at the table who
     * left). Every player loses; it is not a draw.
     */
    public const HOUSE = -1;

    /**
     * @param  list<int>  $winners  Winning seats.
     * @param  string  $reason  Game-specific, e.g. "checkmate", "bear_off", "stalemate".
     * @param  array<int, int>  $scores  Optional points per seat (e.g. a mars counts double).
     */
    public function __construct(
        public array $winners,
        public string $reason,
        public array $scores = [],
    ) {}

    public function isDraw(): bool
    {
        return $this->winners === [];
    }
}
