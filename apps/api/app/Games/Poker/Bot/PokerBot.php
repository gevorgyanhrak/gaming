<?php

namespace App\Games\Poker\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Poker\Engine\PokerHands;

/**
 * Easy calls a lot, folds at random and sometimes min-raises. Medium and hard estimate their chance
 * of winning against the players still in the hand (Monte Carlo over the unseen cards) and compare it
 * with the pot odds: raise strong hands, call when the price is right, otherwise check or fold. Hard
 * samples more, sizes bets to the pot and bluffs now and then.
 */
final class PokerBot implements BotStrategy
{
    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        $legal = [];
        foreach ($legalActions as $action) {
            $legal[$action->type] = $action;
        }
        $passive = $legal['check'] ?? $legal['call'];
        $raise = $legal['raise'] ?? null;
        $roll = $random->int(1, 100);

        if ($difficulty === BotDifficulty::Easy) {
            return match (true) {
                isset($legal['fold']) && $roll <= 25 => $legal['fold'],
                $raise !== null && $roll > 85 => $this->raiseTo($raise, (int) $raise->payload['min']),
                default => $passive,
            };
        }

        $hard = $difficulty === BotDifficulty::Hard;
        $seat = $this->mySeat($view);
        $opponents = count(array_filter(array_keys($view['folded']), fn (int $s) => $s !== $seat && ! $view['folded'][$s]));
        $equity = $this->equity($view['hand_cards'], $view['board'], max(1, $opponents), $hard ? 300 : 120, $random);

        $owe = $view['current_bet'] - $view['bets'][$seat];
        $pot = $view['pot'];
        $odds = $owe > 0 ? $owe / ($pot + $owe) : 0.0;
        // With more opponents, a hand needs to be stronger than its share to bet for value.
        $strong = 1.6 / ($opponents + 1) + 0.15;

        if ($raise !== null && ($equity >= $strong || ($hard && $owe === 0 && $roll <= 8))) {
            $size = $hard ? (int) round($view['current_bet'] + max($view['min_raise'], $pot * ($equity > 0.8 ? 1.0 : 0.6))) : (int) $raise->payload['min'];

            return $this->raiseTo($raise, $equity > 0.85 && $view['street'] === 'river' ? (int) $raise->payload['max'] : $size);
        }
        if ($owe === 0) {
            return $passive;
        }

        return $equity + ($hard ? 0.0 : 0.05) >= $odds ? $passive : ($legal['fold'] ?? $passive);
    }

    /**
     * Share of the pot won against $opponents random hands, over $samples random run-outs.
     *
     * @param  list<string>  $hole
     * @param  list<string>  $board
     */
    public function equity(array $hole, array $board, int $opponents, int $samples, RandomSource $random): float
    {
        $unseen = array_values(array_diff(PokerHands::deck(), $hole, $board));
        $need = 5 - count($board) + 2 * $opponents;
        $won = 0.0;

        for ($i = 0; $i < $samples; $i++) {
            // A partial shuffle: only the cards this sample needs.
            $cards = $unseen;
            for ($k = 0; $k < $need; $k++) {
                $j = $random->int($k, count($cards) - 1);
                [$cards[$k], $cards[$j]] = [$cards[$j], $cards[$k]];
            }
            $runout = [...$board, ...array_slice($cards, 0, 5 - count($board))];
            $mine = PokerHands::best([...$hole, ...$runout])[0];
            $best = $mine;
            $ties = 1;
            for ($o = 0; $o < $opponents; $o++) {
                $theirs = PokerHands::best([...array_slice($cards, 5 - count($board) + 2 * $o, 2), ...$runout])[0];
                if ($theirs > $best) {
                    $best = $theirs;
                    $ties = 0;
                } elseif ($theirs === $best && $best === $mine) {
                    $ties++;
                }
            }
            $won += $best === $mine && $ties > 0 ? 1 / $ties : 0;
        }

        return $won / $samples;
    }

    private function raiseTo(Action $range, int $amount): Action
    {
        return new Action('raise', ['amount' => max((int) $range->payload['min'], min((int) $range->payload['max'], $amount))]);
    }

    /**
     * @param  array<string, mixed>  $view
     */
    private function mySeat(array $view): int
    {
        return (int) $view['to_act'];
    }
}
