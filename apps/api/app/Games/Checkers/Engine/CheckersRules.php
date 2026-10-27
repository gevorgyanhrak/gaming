<?php

namespace App\Games\Checkers\Engine;

/**
 * Russian draughts: positions, legal steps and their effects. Pure functions on the board; no turn
 * handling or draw rules (see CheckersEngine).
 *
 * Board: 64 squares, index = row * 8 + col, row 0 at the top. Pieces stand on dark squares
 * ((row + col) odd). "w"/"W" are seat 0's man/king (white, starts on rows 5..7 and moves up),
 * "b"/"B" seat 1's (black, rows 0..2, moves down); null is an empty square.
 *
 * A step is one move or one jump: ['from' => int, 'to' => int]. A capture with several jumps is a
 * series of steps by the same piece; jumped pieces stay on the board (and can't be jumped again)
 * until the series ends, then they are all removed (the "Turkish strike" rule).
 */
final class CheckersRules
{
    private const DIRECTIONS = [[-1, -1], [-1, 1], [1, -1], [1, 1]];

    /**
     * @return list<?string>
     */
    public static function startingBoard(): array
    {
        $board = array_fill(0, 64, null);
        for ($square = 0; $square < 64; $square++) {
            $row = intdiv($square, 8);
            if (self::isDark($square)) {
                $board[$square] = match (true) {
                    $row <= 2 => 'b',
                    $row >= 5 => 'w',
                    default => null,
                };
            }
        }

        return $board;
    }

    public static function isDark(int $square): bool
    {
        return (intdiv($square, 8) + $square % 8) % 2 === 1;
    }

    public static function owner(?string $piece): ?int
    {
        return match ($piece) {
            'w', 'W' => 0,
            'b', 'B' => 1,
            default => null,
        };
    }

    public static function isKing(?string $piece): bool
    {
        return $piece === 'W' || $piece === 'B';
    }

    /** The row where a seat's men are crowned. */
    public static function crownRow(int $seat): int
    {
        return $seat === 0 ? 0 : 7;
    }

    /**
     * Every legal first step for $seat: captures if any exist (capturing is mandatory), else moves.
     *
     * @param  list<?string>  $board
     * @return list<array{from: int, to: int}>
     */
    public static function steps(array $board, int $seat): array
    {
        $captures = [];
        $moves = [];
        foreach ($board as $square => $piece) {
            if (self::owner($piece) !== $seat) {
                continue;
            }
            array_push($captures, ...self::captureSteps($board, [], $square));
            if ($captures === []) {
                array_push($moves, ...self::quietSteps($board, $square));
            }
        }

        return $captures !== [] ? $captures : $moves;
    }

    /**
     * Jumps for the piece on $from. $captured: pieces already jumped in this series.
     *
     * A king lands on any empty square beyond the jumped piece, but if it can keep capturing from some
     * of them, it must land on one of those.
     *
     * @param  list<?string>  $board
     * @param  list<int>  $captured
     * @return list<array{from: int, to: int}>
     */
    public static function captureSteps(array $board, array $captured, int $from): array
    {
        $piece = $board[$from];
        $seat = self::owner($piece);
        $king = self::isKing($piece);
        $steps = [];

        foreach (self::DIRECTIONS as [$dr, $dc]) {
            $row = intdiv($from, 8) + $dr;
            $col = $from % 8 + $dc;
            while ($king && self::inside($row, $col) && $board[$row * 8 + $col] === null) {
                $row += $dr;
                $col += $dc;
            }
            if (! self::inside($row, $col)) {
                continue;
            }
            $jumped = $row * 8 + $col;
            $target = self::owner($board[$jumped]);
            if ($target === null || $target === $seat || in_array($jumped, $captured, true)) {
                continue;
            }

            $landings = [];
            for ($row += $dr, $col += $dc; self::inside($row, $col) && $board[$row * 8 + $col] === null; $row += $dr, $col += $dc) {
                $landings[] = $row * 8 + $col;
                if (! $king) {
                    break;
                }
            }

            if ($king && count($landings) > 1) {
                $continuing = array_values(array_filter($landings, function (int $to) use ($board, $captured, $from, $jumped, $piece) {
                    $after = $board;
                    $after[$from] = null;
                    $after[$to] = $piece;

                    return self::captureSteps($after, [...$captured, $jumped], $to) !== [];
                }));
                $landings = $continuing !== [] ? $continuing : $landings;
            }

            foreach ($landings as $to) {
                $steps[] = ['from' => $from, 'to' => $to];
            }
        }

        return $steps;
    }

    /**
     * Non-capturing moves for the piece on $from: men one square diagonally forward, kings any
     * distance in any diagonal direction.
     *
     * @param  list<?string>  $board
     * @return list<array{from: int, to: int}>
     */
    public static function quietSteps(array $board, int $from): array
    {
        $piece = $board[$from];
        $king = self::isKing($piece);
        $forward = self::owner($piece) === 0 ? -1 : 1;
        $steps = [];

        foreach (self::DIRECTIONS as [$dr, $dc]) {
            if (! $king && $dr !== $forward) {
                continue;
            }
            for ($row = intdiv($from, 8) + $dr, $col = $from % 8 + $dc; self::inside($row, $col) && $board[$row * 8 + $col] === null; $row += $dr, $col += $dc) {
                $steps[] = ['from' => $from, 'to' => $row * 8 + $col];
                if (! $king) {
                    break;
                }
            }
        }

        return $steps;
    }

    /**
     * Play a (legal) step. Returns the board, the pieces jumped so far, the jumped square (or null)
     * and whether the same piece must keep capturing. When the series ends, jumped pieces are removed.
     * A man reaching the crown row becomes a king at once, and keeps capturing as a king.
     *
     * @param  list<?string>  $board
     * @param  list<int>  $captured
     * @param  array{from: int, to: int}  $step
     * @return array{0: list<?string>, 1: list<int>, 2: ?int, 3: bool}
     */
    public static function applyStep(array $board, array $captured, array $step): array
    {
        ['from' => $from, 'to' => $to] = $step;
        $piece = $board[$from];
        $seat = self::owner($piece);
        $jumped = self::jumpedSquare($board, $from, $to);

        if (! self::isKing($piece) && intdiv($to, 8) === self::crownRow((int) $seat)) {
            $piece = strtoupper((string) $piece);
        }
        $board[$from] = null;
        $board[$to] = $piece;

        if ($jumped === null) {
            return [$board, [], null, false];
        }

        $captured[] = $jumped;
        if (self::captureSteps($board, $captured, $to) !== []) {
            return [$board, $captured, $jumped, true];
        }

        foreach ($captured as $square) {
            $board[$square] = null;
        }

        return [$board, [], $jumped, false];
    }

    /**
     * Every way $seat can finish its turn, each with its first step and the board after the turn.
     * Pass $capturing / $captured to continue a capture series already under way.
     *
     * @param  list<?string>  $board
     * @param  list<int>  $captured
     * @return list<array{first: array{from: int, to: int}, board: list<?string>}>
     */
    public static function turns(array $board, int $seat, ?int $capturing = null, array $captured = []): array
    {
        $steps = $capturing === null ? self::steps($board, $seat) : self::captureSteps($board, $captured, $capturing);
        $turns = [];

        foreach ($steps as $step) {
            [$after, $jumped, , $continues] = self::applyStep($board, $captured, $step);
            if (! $continues) {
                $turns[] = ['first' => $step, 'board' => $after];

                continue;
            }
            foreach (self::turns($after, $seat, $step['to'], $jumped) as $turn) {
                $turns[] = ['first' => $step, 'board' => $turn['board']];
            }
        }

        return $turns;
    }

    /**
     * @param  list<?string>  $board
     */
    public static function count(array $board, int $seat): int
    {
        return count(array_filter($board, fn (?string $piece) => self::owner($piece) === $seat));
    }

    /**
     * @param  list<?string>  $board
     */
    public static function key(array $board, int $turn): string
    {
        return implode('', array_map(fn (?string $piece) => $piece ?? '.', $board)).$turn;
    }

    /**
     * The opposing piece between $from and $to on their diagonal, if any.
     *
     * @param  list<?string>  $board
     */
    private static function jumpedSquare(array $board, int $from, int $to): ?int
    {
        $dr = intdiv($to, 8) <=> intdiv($from, 8);
        $dc = $to % 8 <=> $from % 8;
        for ($square = $from + $dr * 8 + $dc; $square !== $to; $square += $dr * 8 + $dc) {
            if ($board[$square] !== null) {
                return $square;
            }
        }

        return null;
    }

    private static function inside(int $row, int $col): bool
    {
        return $row >= 0 && $row < 8 && $col >= 0 && $col < 8;
    }
}
