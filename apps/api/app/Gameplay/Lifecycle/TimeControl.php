<?php

namespace App\Gameplay\Lifecycle;

use App\Models\GameRoom;

/**
 * How long seats may take to act, from the room's mode options.
 *
 * By default every turn gets `turn_seconds`. Modes with `initial_seconds` use a chess clock instead:
 * each seat has a time bank that runs while it is to act, and `increment_seconds` is added each time
 * its turn passes. The clock is {remaining: ms per seat, since: ms timestamp of the last change}.
 */
final readonly class TimeControl
{
    /** A move that arrives this late after the clock ran out still counts (network lag). */
    public const GRACE_MS = 1000;

    private function __construct(
        private int $turnSeconds,
        private ?int $initialMs,
        private int $incrementMs,
    ) {}

    public static function for(GameRoom $room): self
    {
        $options = $room->options ?? [];

        return new self(
            $room->turnSeconds(),
            isset($options['initial_seconds']) ? (int) $options['initial_seconds'] * 1000 : null,
            (int) ($options['increment_seconds'] ?? 0) * 1000,
        );
    }

    /**
     * @return array{remaining: list<int>, since: int}|null
     */
    public function start(int $seats, int $nowMs): ?array
    {
        return $this->initialMs === null ? null : ['remaining' => array_fill(0, $seats, $this->initialMs), 'since' => $nowMs];
    }

    /**
     * Unix time by which the seats to act must move.
     *
     * @param  array{remaining: list<int>, since: int}|null  $clock
     * @param  list<int>  $seats
     */
    public function deadline(?array $clock, array $seats, int $nowMs): ?int
    {
        if ($seats === []) {
            return null;
        }
        if ($clock === null) {
            return intdiv($nowMs, 1000) + $this->turnSeconds;
        }

        $ends = array_map(fn (int $seat) => $clock['since'] + ($clock['remaining'][$seat] ?? 0), $seats);

        return (int) ceil(min($ends) / 1000);
    }

    /**
     * Time $seat would have left if it moved now; negative once it ran out.
     *
     * @param  array{remaining: list<int>, since: int}  $clock
     */
    public function left(array $clock, int $seat, int $nowMs): int
    {
        return ($clock['remaining'][$seat] ?? 0) - ($nowMs - $clock['since']);
    }

    /**
     * Charge $seat for the time it took, and add the increment if its turn passed.
     *
     * @param  array{remaining: list<int>, since: int}  $clock
     * @return array{remaining: list<int>, since: int}
     */
    public function afterMove(array $clock, int $seat, bool $turnPassed, int $nowMs): array
    {
        $clock['remaining'][$seat] = max(0, $this->left($clock, $seat, $nowMs)) + ($turnPassed ? $this->incrementMs : 0);
        $clock['since'] = $nowMs;

        return $clock;
    }

    /**
     * Remaining time per seat right now, for display (null without a chess clock).
     *
     * @param  array{remaining: list<int>, since: int}|null  $clock
     * @param  list<int>  $seatsToAct
     * @return list<int>|null
     */
    public static function remaining(?array $clock, array $seatsToAct, int $nowMs): ?array
    {
        if ($clock === null) {
            return null;
        }

        return array_map(
            fn (int $ms, int $seat) => in_array($seat, $seatsToAct, true) ? max(0, $ms - ($nowMs - $clock['since'])) : $ms,
            $clock['remaining'],
            array_keys($clock['remaining']),
        );
    }
}
