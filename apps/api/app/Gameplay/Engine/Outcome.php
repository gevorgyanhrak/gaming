<?php

namespace App\Gameplay\Engine;

/**
 * How a finished game ended. No winners means a draw.
 */
final readonly class Outcome
{
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
