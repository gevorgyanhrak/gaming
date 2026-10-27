<?php

namespace App\Games\Durak\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Durak\Engine\DurakCards;

/**
 * Easy plays any legal action. Medium leads its weakest card, beats with the cheapest card that does
 * and throws in only cheap cards until the deck runs out. Hard also keeps strong trumps: it picks up
 * cheap attacks rather than spend a high trump while the deck is long, and leads ranks it holds
 * several of.
 */
final class DurakBot implements BotStrategy
{
    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        if ($difficulty === BotDifficulty::Easy) {
            return $legalActions[$random->int(0, count($legalActions) - 1)];
        }

        $trump = $view['trump'];
        $hard = $difficulty === BotDifficulty::Hard;
        $byType = fn (string $type) => array_values(array_filter($legalActions, fn (Action $a) => $a->type === $type));
        $cheapest = function (array $actions) use ($trump, $view, $hard): Action {
            usort($actions, function (Action $a, Action $b) use ($trump, $view, $hard) {
                $score = function (Action $action) use ($trump, $view, $hard) {
                    $card = (string) $action->payload['card'];
                    $score = DurakCards::strength($card, $trump) * 10;
                    // Leading a rank you hold more of lets you throw in after it.
                    if ($hard && $view['table'] === []) {
                        $score -= 4 * (count(array_filter($view['hand'], fn (string $c) => $c[0] === $card[0])) - 1);
                    }

                    return $score;
                };

                return $score($a) <=> $score($b);
            });

            return $actions[0];
        };

        $defends = $byType('defend');
        if ($defends !== [] || $byType('take') !== []) {
            if ($defends === []) {
                return $byType('take')[0];
            }
            $best = $cheapest($defends);
            $card = (string) $best->payload['card'];
            $attack = $view['table'][count($view['table']) - 1]['attack'];
            $keepTrump = $hard && $view['deck_count'] > 8 && DurakCards::suit($card) === $trump
                && DurakCards::rank($card) >= 6 && DurakCards::rank($attack) < 4 && count($view['table']) === 1;

            return $keepTrump ? $byType('take')[0] : $best;
        }

        $attacks = $byType('attack');
        if ($view['table'] === []) {
            return $cheapest($attacks);
        }

        // Throw in cheap cards; once the deck is empty, anything that helps get rid of the hand.
        $throwable = array_values(array_filter($attacks, function (Action $a) use ($trump, $view) {
            $card = (string) $a->payload['card'];

            return $view['deck_count'] === 0 || (DurakCards::suit($card) !== $trump && DurakCards::rank($card) <= 5);
        }));

        return $throwable !== [] ? $cheapest($throwable) : $byType('done')[0];
    }
}
