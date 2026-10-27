<?php

namespace App\Games\Chess\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Chess\Engine\ChessRules;

/**
 * Alpha-beta search with a capture-only quiescence search and piece-square tables (Tomasz
 * Michniewski's "simplified evaluation function"). Easy looks one move ahead and plays loosely,
 * medium two moves, hard three; ties are broken at random so games vary.
 */
final class ChessBot implements BotStrategy
{
    private const MATE = 100_000;

    private const VALUES = ['p' => 100, 'n' => 320, 'b' => 330, 'r' => 500, 'q' => 900, 'k' => 0];

    /** Rank 8 first, from white's point of view. */
    private const TABLES = [
        'p' => [0, 0, 0, 0, 0, 0, 0, 0, 50, 50, 50, 50, 50, 50, 50, 50, 10, 10, 20, 30, 30, 20, 10, 10, 5, 5, 10, 25, 25, 10, 5, 5, 0, 0, 0, 20, 20, 0, 0, 0, 5, -5, -10, 0, 0, -10, -5, 5, 5, 10, 10, -20, -20, 10, 10, 5, 0, 0, 0, 0, 0, 0, 0, 0],
        'n' => [-50, -40, -30, -30, -30, -30, -40, -50, -40, -20, 0, 0, 0, 0, -20, -40, -30, 0, 10, 15, 15, 10, 0, -30, -30, 5, 15, 20, 20, 15, 5, -30, -30, 0, 15, 20, 20, 15, 0, -30, -30, 5, 10, 15, 15, 10, 5, -30, -40, -20, 0, 5, 5, 0, -20, -40, -50, -40, -30, -30, -30, -30, -40, -50],
        'b' => [-20, -10, -10, -10, -10, -10, -10, -20, -10, 0, 0, 0, 0, 0, 0, -10, -10, 0, 5, 10, 10, 5, 0, -10, -10, 5, 5, 10, 10, 5, 5, -10, -10, 0, 10, 10, 10, 10, 0, -10, -10, 10, 10, 10, 10, 10, 10, -10, -10, 5, 0, 0, 0, 0, 5, -10, -20, -10, -10, -10, -10, -10, -10, -20],
        'r' => [0, 0, 0, 0, 0, 0, 0, 0, 5, 10, 10, 10, 10, 10, 10, 5, -5, 0, 0, 0, 0, 0, 0, -5, -5, 0, 0, 0, 0, 0, 0, -5, -5, 0, 0, 0, 0, 0, 0, -5, -5, 0, 0, 0, 0, 0, 0, -5, -5, 0, 0, 0, 0, 0, 0, -5, 0, 0, 0, 5, 5, 0, 0, 0],
        'q' => [-20, -10, -10, -5, -5, -10, -10, -20, -10, 0, 0, 0, 0, 0, 0, -10, -10, 0, 5, 5, 5, 5, 0, -10, -5, 0, 5, 5, 5, 5, 0, -5, 0, 0, 5, 5, 5, 5, 0, -5, -10, 5, 5, 5, 5, 5, 0, -10, -10, 0, 5, 0, 0, 0, 0, -10, -20, -10, -10, -5, -5, -10, -10, -20],
        'k' => [-30, -40, -40, -50, -50, -40, -40, -30, -30, -40, -40, -50, -50, -40, -40, -30, -30, -40, -40, -50, -50, -40, -40, -30, -30, -40, -40, -50, -50, -40, -40, -30, -20, -30, -30, -40, -40, -30, -30, -20, -10, -20, -20, -20, -20, -20, -20, -10, 20, 20, 0, 0, 0, 0, 20, 20, 20, 30, 10, 0, 0, 10, 30, 20],
        'k_end' => [-50, -40, -30, -20, -20, -30, -40, -50, -30, -20, -10, 0, 0, -10, -20, -30, -30, -10, 20, 30, 30, 20, -10, -30, -30, -10, 30, 40, 40, 30, -10, -30, -30, -10, 30, 40, 40, 30, -10, -30, -30, -10, 20, 30, 30, 20, -10, -30, -30, -30, 0, 0, 0, 0, -30, -30, -50, -30, -30, -30, -30, -30, -30, -50],
    ];

    /**
     * @param  array<string, mixed>  $view
     * @param  list<Action>  $legalActions
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        [$depth, $margin] = match ($difficulty) {
            BotDifficulty::Easy => [1, 150],
            BotDifficulty::Medium => [2, 25],
            BotDifficulty::Hard => [3, 0],
        };

        $position = $view;
        $scored = [];
        foreach ($legalActions as $action) {
            $move = $action->payload;
            $scored[] = [$action, -$this->search(ChessRules::play($position, $move), $depth - 1, -self::MATE * 2, self::MATE * 2, 1)];
        }

        $best = max(array_column($scored, 1));
        $candidates = array_values(array_filter($scored, fn (array $entry) => $entry[1] >= $best - $margin));

        return $candidates[$random->int(0, count($candidates) - 1)][0];
    }

    /**
     * Negamax with alpha-beta; the score is for the side to move.
     *
     * @param  array<string, mixed>  $position
     */
    private function search(array $position, int $depth, int $alpha, int $beta, int $ply): int
    {
        if ($depth <= 0) {
            return $this->quiesce($position, $alpha, $beta, 4);
        }

        $any = false;
        foreach ($this->ordered($position, ChessRules::pseudoMoves($position)) as $move) {
            $next = ChessRules::play($position, $move);
            if (ChessRules::inCheck($next['board'], $position['turn'])) {
                continue;
            }
            $any = true;
            $alpha = max($alpha, -$this->search($next, $depth - 1, -$beta, -$alpha, $ply + 1));
            if ($alpha >= $beta) {
                return $alpha;
            }
        }

        if (! $any) {
            return ChessRules::inCheck($position['board'], $position['turn']) ? -self::MATE + $ply : 0;
        }

        return $alpha;
    }

    /**
     * Follow captures until the position is quiet, so the search doesn't stop mid-exchange.
     *
     * @param  array<string, mixed>  $position
     */
    private function quiesce(array $position, int $alpha, int $beta, int $depth): int
    {
        $standPat = $this->evaluate($position['board'], $position['turn']);
        if ($standPat >= $beta || $depth === 0) {
            return $standPat;
        }
        $alpha = max($alpha, $standPat);

        $captures = array_filter(ChessRules::pseudoMoves($position), fn (array $m) => $position['board'][$m['to']] !== null);
        foreach ($this->ordered($position, $captures) as $move) {
            $next = ChessRules::play($position, $move);
            if (ChessRules::inCheck($next['board'], $position['turn'])) {
                continue;
            }
            $alpha = max($alpha, -$this->quiesce($next, -$beta, -$alpha, $depth - 1));
            if ($alpha >= $beta) {
                break;
            }
        }

        return $alpha;
    }

    /**
     * Captures of valuable pieces by cheap ones first, then promotions, then the rest.
     *
     * @param  array<string, mixed>  $position
     * @param  array<int, array{from: int, to: int, promotion?: string}>  $moves
     * @return list<array{from: int, to: int, promotion?: string}>
     */
    private function ordered(array $position, array $moves): array
    {
        $board = $position['board'];
        $score = fn (array $m) => ($board[$m['to']] !== null ? 10 * self::VALUES[strtolower($board[$m['to']])] - self::VALUES[strtolower((string) $board[$m['from']])] : 0)
            + (isset($m['promotion']) ? self::VALUES[$m['promotion']] : 0);
        usort($moves, fn (array $a, array $b) => $score($b) <=> $score($a));

        return $moves;
    }

    /**
     * Material and piece placement for $seat minus the same for the opponent.
     *
     * @param  list<?string>  $board
     */
    private function evaluate(array $board, int $seat): int
    {
        $material = 0;
        foreach ($board as $piece) {
            if ($piece !== null && ! in_array(strtolower($piece), ['p', 'k'], true)) {
                $material += self::VALUES[strtolower($piece)];
            }
        }
        $kingTable = $material <= 2600 ? 'k_end' : 'k';

        $score = 0;
        foreach ($board as $square => $piece) {
            if ($piece === null) {
                continue;
            }
            $type = strtolower($piece);
            $white = ctype_upper($piece);
            $table = self::TABLES[$type === 'k' ? $kingTable : $type];
            $value = self::VALUES[$type] + $table[$white ? $square : $square ^ 56];
            $score += ($white ? 0 : 1) === $seat ? $value : -$value;
        }

        return $score;
    }
}
