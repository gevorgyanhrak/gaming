<?php

namespace App\Games\Rummy\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Rummy\Engine\RummyEngine;

/**
 * Takes the discard when it completes a meld, puts down the longest
 * meld it can and lays off what fits, then throws away its most expensive loose card. Hard keeps cards
 * that pair up or nearly make runs and avoids discards that extend melds on the table. Easy throws away
 * at random.
 */
final class RummyBot implements BotStrategy
{
    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        $engine = new RummyEngine;
        $hand = $view['hand'];
        $byType = [];
        foreach ($legalActions as $action) {
            $byType[$action->type][] = $action;
        }

        if ($view['phase'] === 'draw') {
            $top = $view['discard_top'];
            $fromDiscard = collect($byType['draw'] ?? [])->first(fn (Action $a) => $a->payload['from'] === 'discard');
            $fromStock = collect($byType['draw'] ?? [])->first(fn (Action $a) => $a->payload['from'] === 'stock');
            if ($fromDiscard !== null && $top !== null) {
                $completes = $engine->possibleMelds([...$hand, $top]) !== $engine->possibleMelds($hand);
                // Only a card that completes a meld: taking ones that nearly fit makes bots trade the same
                // discards back and forth.
                if ($completes) {
                    return $fromDiscard;
                }
            }

            return $fromStock ?? $fromDiscard ?? $legalActions[0];
        }

        if (isset($byType['meld'])) {
            usort($byType['meld'], fn (Action $a, Action $b) => substr_count((string) $b->payload['cards'], ',') <=> substr_count((string) $a->payload['cards'], ','));

            return $byType['meld'][0];
        }
        if (isset($byType['layoff'])) {
            return $byType['layoff'][0];
        }

        $discards = $byType['discard'] ?? $legalActions;
        if ($difficulty === BotDifficulty::Easy) {
            return $discards[$random->int(0, count($discards) - 1)];
        }
        $best = null;
        $bestScore = PHP_INT_MIN;
        foreach ($discards as $action) {
            $card = (string) $action->payload['card'];
            $rest = $hand;
            unset($rest[array_search($card, $rest, true)]);
            $score = RummyEngine::value($card) * 2 - ($this->near(array_values($rest), $card) ? 15 : 0);
            if ($difficulty === BotDifficulty::Hard) {
                foreach ($view['melds'] as $meld) {
                    if (RummyEngine::meldKind([...$meld['cards'], $card]) !== null) {
                        $score -= 25; // would hand the next player a layoff
                    }
                }
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$action, $score];
            }
        }

        return $best ?? $discards[0];
    }

    /**
     * Whether $card pairs with a card of the same rank or sits next to one of its suit (a meld in the making).
     *
     * @param  list<string>  $hand
     */
    private function near(array $hand, string $card): bool
    {
        foreach ($hand as $other) {
            if ($other === $card) {
                continue;
            }
            if ($other[0] === $card[0]) {
                return true;
            }
            if ($other[1] === $card[1] && abs(RummyEngine::rank($other) - RummyEngine::rank($card)) <= 2) {
                return true;
            }
        }

        return false;
    }
}
