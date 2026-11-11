<?php

namespace App\Games\Blackjack\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blackjack\Engine\BlackjackCards;

/**
 * Hard plays basic strategy (multi-deck, dealer stands on soft 17, double after split). Medium mostly
 * does too but slips now and then; easy hits below 15 and never doubles or splits. Bots bet the table
 * minimum or a little more.
 */
final class BlackjackBot implements BotStrategy
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

        if (isset($legal['bet'])) {
            $min = (int) $legal['bet']->payload['min'];
            $max = (int) $legal['bet']->payload['max'];
            $times = $difficulty === BotDifficulty::Easy ? $random->int(1, 3) : $random->int(1, 2);

            return new Action('bet', ['amount' => max($min, min($max, $min * $times))]);
        }

        $hand = $view['hands'][$view['seat']][$view['active']];
        $simple = $difficulty === BotDifficulty::Easy || ($difficulty === BotDifficulty::Medium && $random->int(1, 100) <= 15);
        $choice = $simple
            ? (BlackjackCards::total($hand['cards'])[0] < 15 ? 'hit' : 'stand')
            : $this->basic($hand['cards'], BlackjackCards::value((string) $view['dealer'][0]), isset($legal['double']), isset($legal['split']));

        return $legal[$choice] ?? $legal['stand'];
    }

    /**
     * Basic strategy: split, double, hit or stand.
     *
     * @param  list<string>  $cards
     * @param  int  $up  The dealer's up card (ace = 11).
     */
    public function basic(array $cards, int $up, bool $canDouble, bool $canSplit): string
    {
        $in = fn (int ...$values) => in_array($up, $values, true);

        if ($canSplit) {
            $pair = BlackjackCards::value($cards[0]);
            $split = match ($pair) {
                11, 8 => true,
                2, 3, 7 => $up <= 7,
                6 => $up <= 6,
                9 => $up <= 9 && $up !== 7,
                4 => $in(5, 6),
                default => false,
            };
            if ($split) {
                return 'split';
            }
        }

        [$total, $soft] = BlackjackCards::total($cards);
        $double = fn (string $otherwise) => $canDouble ? 'double' : $otherwise;

        if ($soft) {
            return match (true) {
                $total >= 19 => 'stand',
                $total === 18 => $in(3, 4, 5, 6) ? $double('stand') : ($in(2, 7, 8) ? 'stand' : 'hit'),
                $total === 17 => $in(3, 4, 5, 6) ? $double('hit') : 'hit',
                $total >= 15 => $in(4, 5, 6) ? $double('hit') : 'hit',
                default => $in(5, 6) ? $double('hit') : 'hit',
            };
        }

        return match (true) {
            $total >= 17 => 'stand',
            $total >= 13 => $up <= 6 ? 'stand' : 'hit',
            $total === 12 => $in(4, 5, 6) ? 'stand' : 'hit',
            $total === 11 => $up <= 10 ? $double('hit') : 'hit',
            $total === 10 => $up <= 9 ? $double('hit') : 'hit',
            $total === 9 => $in(3, 4, 5, 6) ? $double('hit') : 'hit',
            default => 'hit',
        };
    }
}
