<?php

namespace App\Games\Ludo\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Ludo\Engine\LudoEngine;

/**
 * Easy moves any token. Medium captures when it can, then brings tokens out, then runs its leading token.
 * Hard also gets tokens home, prefers safe squares and avoids stopping just in front of an opponent.
 */
final class LudoBot implements BotStrategy
{
    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        if (count($legalActions) === 1 || $difficulty === BotDifficulty::Easy) {
            return $legalActions[$random->int(0, count($legalActions) - 1)];
        }

        $best = $legalActions[0];
        $bestScore = PHP_INT_MIN;
        foreach ($legalActions as $action) {
            $score = $this->score($view, $action, $difficulty === BotDifficulty::Hard);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$action, $score];
            }
        }

        return $best;
    }

    /**
     * @param  array<string, mixed>  $view
     */
    private function score(array $view, Action $action, bool $careful): int
    {
        $seat = (int) $view['seat'];
        $colour = $view['colours'][$seat];
        $token = (int) $action->payload['token'];
        $from = $view['tokens'][$seat][$token];
        $to = (int) $action->payload['to'];
        $square = LudoEngine::square($colour, $to);
        $opponents = $this->opponentSquares($view, $seat);

        $score = $from; // run the leading token
        if ($square !== null && ! LudoEngine::isSafe($square) && in_array($square, $opponents, true)) {
            $score += 1000;
        }
        if ($from === -1) {
            $score += 500;
        }
        if (! $careful) {
            return $score;
        }
        if ($to === LudoEngine::HOME) {
            $score += 700;
        }
        if ($to > 50) {
            $score += 200; // safe in the home column
        }
        if ($square !== null && LudoEngine::isSafe($square)) {
            $score += 150;
        }
        // An opponent one to six squares behind could land on us next turn.
        if ($square !== null && ! LudoEngine::isSafe($square)) {
            foreach ($opponents as $theirs) {
                $gap = ($square - $theirs + LudoEngine::TRACK) % LudoEngine::TRACK;
                if ($gap >= 1 && $gap <= 6) {
                    $score -= 300;
                }
            }
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $view
     * @return list<int>
     */
    private function opponentSquares(array $view, int $seat): array
    {
        $squares = [];
        foreach ($view['tokens'] as $other => $tokens) {
            if ($other === $seat || $view['out'][$other]) {
                continue;
            }
            foreach ($tokens as $position) {
                $square = LudoEngine::square($view['colours'][$other], $position);
                if ($square !== null) {
                    $squares[] = $square;
                }
            }
        }

        return $squares;
    }
}
