<?php

namespace App\Games\Blot\Bot;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotEngine;

/**
 * The blot bot as it played before the look-ahead engine (kept as the baseline the arena measures against,
 * and for Easy bot profiles): bids on its hand's strength with a share for its partner, says Quansh holding
 * three top trumps, declares everything, and plays each trick by simple rules (BlotBot::card).
 */
final class ClassicBlotBot implements BotStrategy
{
    public function __construct(private readonly BlotBot $bot = new BlotBot) {}

    /**
     * @param  array<string, mixed>  $view
     */
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action
    {
        $legal = [];
        foreach ($legalActions as $action) {
            $legal[$action->type] = $action;
        }
        if ($view['phase'] === 'bidding') {
            return $this->bid($view, $legal, $difficulty, $random);
        }
        if ($view['phase'] === 'answer') {
            $contract = $view['contract'];
            $strong = $contract['suit'] !== null && $this->bot->strength($view['hand'], $contract['suit']) >= $contract['value'] * 10 + 30;

            return $difficulty === BotDifficulty::Hard && $strong ? $legal['recontra'] : $legal['pass'];
        }
        if (isset($legal['declare'])) {
            return $legal['declare'];
        }
        $plays = array_values(array_filter($legalActions, fn (Action $a) => $a->type === 'play'));
        if ($difficulty === BotDifficulty::Easy) {
            return $plays[$random->int(0, count($plays) - 1)];
        }
        $cards = array_map(fn (Action $a) => (string) $a->payload['card'], $plays);

        return new Action('play', ['card' => $this->bot->card($view, $cards, $difficulty)]);
    }

    /**
     * @param  array<string, mixed>  $view
     * @param  array<string, Action>  $legal
     */
    private function bid(array $view, array $legal, BotDifficulty $difficulty, RandomSource $random): Action
    {
        $best = null;
        foreach (BlotEngine::BID_SUITS as $suit) {
            $value = $this->bot->strength($view['hand'], $suit === 'N' ? null : $suit);
            if ($best === null || $value > $best[1]) {
                $best = [$suit, $value];
            }
        }
        [$suit, $value] = $best;
        $value += match ($difficulty) {
            BotDifficulty::Easy => -25,
            BotDifficulty::Medium => 0,
            BotDifficulty::Hard => 8,
        };
        $contract = $view['contract'];
        $seat = (int) $view['seat'];

        if (isset($legal['contra']) && $contract !== null && $contract['suit'] !== null && $difficulty !== BotDifficulty::Easy) {
            $trump = $contract['suit'];
            $top = count(array_intersect(['J'.$trump, '9'.$trump, 'A'.$trump], $view['hand']));
            if ($top >= 3 || ($top >= 2 && $contract['value'] >= 12)) {
                return $legal['contra'];
            }
        }

        $partnerHolds = $contract !== null && $contract['team'] === $seat % 2;
        $wanted = intdiv($value, 10) - ($partnerHolds ? 3 : 0);
        $bid = $legal['bid'] ?? null;
        if ($bid !== null && $wanted >= (int) $bid->payload['min'] && $random->int(1, 100) > ($difficulty === BotDifficulty::Easy ? 70 : 5)) {
            return new Action('bid', ['suit' => $suit, 'value' => min((int) $bid->payload['min'], BlotEngine::MAX_BID)]);
        }

        return $legal['pass'];
    }
}
