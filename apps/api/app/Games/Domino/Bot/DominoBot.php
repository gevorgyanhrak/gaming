<?php

namespace App\Games\Domino\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Domino\Engine\DominoEngine;

/**
 * Easy plays any tile. Medium sheds its heaviest tile (doubles first, as they are hardest to place). Hard
 * also keeps its options open: it prefers moves that leave ends it holds more tiles for.
 */
final class DominoBot implements BotStrategy
{
    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        if ($difficulty === BotDifficulty::Easy) {
            return $legalActions[$random->int(0, count($legalActions) - 1)];
        }

        $best = null;
        $bestScore = PHP_INT_MIN;
        foreach ($legalActions as $action) {
            $score = $this->score($view, $action, $difficulty === BotDifficulty::Hard);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$action, $score];
            }
        }

        return $best ?? $legalActions[0];
    }

    /**
     * @param  array<string, mixed>  $view
     */
    private function score(array $view, Action $action, bool $lookAhead): int
    {
        $tile = (string) $action->payload['tile'];
        [$a, $b] = DominoEngine::pips($tile);
        $score = ($a + $b) * 2 + ($a === $b ? 6 : 0);
        if (! $lookAhead || $view['ends'] === null) {
            return $score;
        }

        // The ends after this move, and how many of the remaining tiles in hand would fit them.
        [$left, $right] = $view['ends'];
        $open = $action->payload['end'] === 'left' ? $left : $right;
        $newEnd = $a === $open ? $b : $a;
        $ends = $action->payload['end'] === 'left' ? [$newEnd, $right] : [$left, $newEnd];
        $rest = array_diff($view['hand'], [$tile]);
        foreach ($rest as $other) {
            [$x, $y] = DominoEngine::pips($other);
            if (in_array($x, $ends, true) || in_array($y, $ends, true)) {
                $score += 4;
            }
        }

        return $score;
    }
}
