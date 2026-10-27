<?php

namespace App\Games\Checkers\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Checkers\Engine\CheckersRules;

/**
 * Easy plays a random legal step. Medium searches two moves ahead and picks among the near-best;
 * hard searches six moves ahead with alpha-beta, following forced captures past the horizon.
 * Bots search whole turns (a capture series counts as one move) and play the first step of the best.
 */
final class CheckersBot implements BotStrategy
{
    private const WIN = 100_000;

    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        if ($difficulty === BotDifficulty::Easy || count($legalActions) === 1) {
            return $legalActions[$random->int(0, count($legalActions) - 1)];
        }

        $seat = $view['turn'];
        [$depth, $margin] = $difficulty === BotDifficulty::Hard ? [6, 0] : [2, 40];
        $scored = [];
        foreach (CheckersRules::turns($view['board'], $seat, $view['capturing'], $view['captured']) as $turn) {
            $scored[] = [$turn['first'], -$this->search($turn['board'], 1 - $seat, $depth - 1, -self::WIN * 2, self::WIN * 2)];
        }

        $best = max(array_column($scored, 1));
        $candidates = array_values(array_filter($scored, fn (array $entry) => $entry[1] >= $best - $margin));
        $first = $candidates[$random->int(0, count($candidates) - 1)][0];

        foreach ($legalActions as $action) {
            if ($action->equals(new Action('move', $first))) {
                return $action;
            }
        }

        return $legalActions[0];
    }

    /**
     * Negamax with alpha-beta; scores are from the point of view of $seat (to move).
     *
     * @param  list<?string>  $board
     */
    private function search(array $board, int $seat, int $depth, int $alpha, int $beta): int
    {
        $turns = CheckersRules::turns($board, $seat);
        if ($turns === []) {
            return -self::WIN - $depth;    // losing sooner is worse
        }
        // Past the horizon, keep going only while captures are forced (they change the material).
        $forced = $this->isCapture($board, $turns[0]['board']);
        if ($depth <= 0 && (! $forced || $depth <= -6)) {
            return $this->evaluate($board, $seat);
        }

        usort($turns, fn (array $a, array $b) => $this->evaluate($a['board'], $seat) <=> $this->evaluate($b['board'], $seat));
        foreach (array_reverse($turns) as $turn) {
            $alpha = max($alpha, -$this->search($turn['board'], 1 - $seat, $depth - 1, -$beta, -$alpha));
            if ($alpha >= $beta) {
                break;
            }
        }

        return $alpha;
    }

    /**
     * Material and position for $seat minus the same for the opponent.
     *
     * @param  list<?string>  $board
     */
    private function evaluate(array $board, int $seat): int
    {
        $score = 0;
        foreach ($board as $square => $piece) {
            $owner = CheckersRules::owner($piece);
            if ($owner === null) {
                continue;
            }
            $row = intdiv($square, 8);
            $col = $square % 8;
            if (CheckersRules::isKing($piece)) {
                $value = 300;
            } else {
                $advanced = $owner === 0 ? 7 - $row : $row;
                $value = 100 + $advanced * 4 + ($advanced === 0 ? 8 : 0);    // progress; guard the back row
            }
            $value += ($row >= 2 && $row <= 5 && $col >= 2 && $col <= 5) ? 6 : 0;
            $score += $owner === $seat ? $value : -$value;
        }

        return $score;
    }

    /**
     * @param  list<?string>  $before
     * @param  list<?string>  $after
     */
    private function isCapture(array $before, array $after): bool
    {
        return count(array_filter($before)) !== count(array_filter($after));
    }
}
