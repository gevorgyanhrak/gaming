<?php

namespace Tests\Fixtures\Blot;

use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Games\Blot\Bot\BlotBot;
use App\Games\Blot\Bot\QuanshAdvisor;
use App\Games\Blot\Bot\QuanshSkill;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Measures how blot bots judge Quansh over many random deals. In each one the player left of the dealer
 * opens the bazar on their best suit, honestly (as their hand is worth) or overbidding by 2–6, and the bot
 * on their left decides from its own seat view. The truth comes from playing the actual deal out both ways.
 *
 * - obvious: a careful player in the bot's seat would see it (a 400-sample Expert judgement: the contract
 *   fails 65%+ of the time and Quansh gains 4+ sai on average) and it was right (the contract failed);
 * - wrong: the bot said Quansh and the contract was made;
 * - gain: what the bot's Quanshes won (or lost) on the real cards, against `best`, the most anyone could
 *   win knowing every card (Quansh exactly when it pays).
 */
final class QuanshLab
{
    /**
     * @param  list<QuanshSkill>  $skills
     * @return array<string, array{deals: int, obvious: int, missed: int, calls: int, wrong: int, gain: int, best: int}>
     */
    public static function run(int $deals, int $seed, array $skills): array
    {
        // Deals from their own stream, so every skill and setting meets the same deals.
        $random = new SeededRandom($seed);
        $thinking = new SeededRandom($seed * 7 + 1);
        $engine = new BlotEngine;
        $bot = new BlotBot;
        $advisor = new QuanshAdvisor;
        $stats = [];
        foreach ($skills as $skill) {
            $stats[$skill->value] = ['deals' => 0, 'obvious' => 0, 'missed' => 0, 'calls' => 0, 'wrong' => 0, 'gain' => 0, 'best' => 0];
        }

        for ($d = 0; $d < $deals; $d++) {
            $state = $engine->initialState(new GameSetup(4), $random);
            $state = $engine->resume($state, $random)->state;
            $first = (int) $state['to_act'];
            $hand = $state['hands'][$first];
            $best = null;
            foreach (BlotEngine::BID_SUITS as $suit) {
                $value = $bot->strength($hand, $suit === 'N' ? null : $suit);
                if ($best === null || $value > $best[1]) {
                    $best = [$suit, $value];
                }
            }
            $overbid = $random->int(1, 10) <= 4 ? $random->int(2, 6) : 0;
            $value = max(BlotEngine::MIN_BID, min(BlotEngine::MAX_BID, intdiv($best[1], 10) + $overbid));
            $state = $engine->apply($state, $first, new Action('bid', ['value' => $value, 'suit' => $best[0]]), $random)->state;

            $seat = (int) $state['to_act'];
            $view = $engine->viewFor($state, $seat);
            [$pass, $made] = $advisor->playOut($view, $state['hands'], quansh: false, expert: false, random: $random);
            [$quansh] = $advisor->playOut($view, $state['hands'], quansh: true, expert: false, random: $random);
            $gain = $quansh - $pass;
            $careful = $advisor->judge($view, QuanshSkill::Expert, $thinking, samples: 400);
            $obvious = ! $made && $careful['fail'] >= 0.65 && $careful['gain'] >= 4;

            foreach ($skills as $skill) {
                $calls = $advisor->shouldQuansh($view, $skill, $thinking);
                $row = &$stats[$skill->value];
                $row['deals']++;
                $row['obvious'] += $obvious ? 1 : 0;
                $row['missed'] += $obvious && ! $calls ? 1 : 0;
                $row['calls'] += $calls ? 1 : 0;
                $row['wrong'] += $calls && $made ? 1 : 0;
                $row['gain'] += $calls ? $gain : 0;
                $row['best'] += max(0, $gain);
                unset($row);
            }
        }

        return $stats;
    }
}
