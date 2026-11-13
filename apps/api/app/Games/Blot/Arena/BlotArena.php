<?php

namespace App\Games\Blot\Arena;

use App\Enums\BotDifficulty;
use App\Gameplay\Contracts\BotStrategy;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Bot\BlotBot;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Measures blot strategies against each other on duplicate deals: every deal is played twice from the same
 * cards and dealer, once with strategy A in seats 0 and 2 and once with A in seats 1 and 3, so luck of the
 * cards cancels out. Each seat decides from its own view only, through the real engine. Reports sai won per
 * deal and how the bazar, Quansh, Sur and kaput went for each side.
 */
final class BlotArena
{
    /**
     * @param  callable(int): RandomSource  $randomFor  a repeatable random source per seed
     * @param  (callable(array<string, mixed>): void)|null  $record  receives a self-play note per deal: the raw
     *                                                               chance the final contract's bidder gave it, and whether it was made
     * @return array<string, array<string, float|int|string>> stats per side ('a', 'b')
     */
    public function run(BotStrategy $a, BotStrategy $b, int $deals, int $seed, callable $randomFor, BotDifficulty $difficulty = BotDifficulty::Hard, ?callable $progress = null, ?callable $record = null): array
    {
        $engine = new BlotEngine;
        $stats = ['a' => $this->blank(), 'b' => $this->blank()];

        $pairs = [];
        for ($d = 0; $d < $deals; $d++) {
            $before = $stats['a']['sai'];
            $start = $engine->initialState(new GameSetup(4, ['target' => 10000]), $randomFor($seed + $d));
            foreach ([0, 1] as $aTeam) {
                $strategies = [];
                foreach (range(0, 3) as $seat) {
                    $strategies[$seat] = $seat % 2 === $aTeam ? $a : $b;
                }
                $deal = $randomFor(($seed + $d) * 31 + 7);   // the same redeals in both runs
                $thinking = $randomFor(($seed + $d) * 131 + $aTeam);
                $this->play($engine, $start, $strategies, $aTeam, $deal, $thinking, $difficulty, $stats, $record);
            }
            // A's sai over the deal played both ways: one paired result.
            $pairs[] = $stats['a']['sai'] - $before;
            if ($progress !== null) {
                $progress($d + 1);
            }
        }
        // 95% interval of A's sai per deal, from the paired results (each pair is two deals).
        $n = count($pairs);
        $mean = $n > 0 ? array_sum($pairs) / $n : 0.0;
        $sd = $n > 1 ? sqrt(array_sum(array_map(fn (float|int $x) => ($x - $mean) ** 2, $pairs)) / ($n - 1)) : 0.0;
        $half = $n > 0 ? 1.96 * $sd / sqrt($n) / 2 : 0.0;
        $stats['a']['sai_per_deal_ci95'] = sprintf('%+.2f ± %.2f', $mean / 2, $half);
        $stats['b']['sai_per_deal_ci95'] = sprintf('%+.2f ± %.2f', -$mean / 2, $half);
        foreach (['a', 'b'] as $side) {
            $stats[$side]['sai_per_deal'] = round($stats[$side]['sai'] / max(1, $stats[$side]['deals']), 2);
            $stats[$side]['ms_per_decision'] = round($stats[$side]['ms'] / max(1, $stats[$side]['decisions']), 1);
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<int, BotStrategy>  $strategies
     * @param  array<string, array<string, float|int>>  $stats
     */
    private function play(BlotEngine $engine, array $state, array $strategies, int $aTeam, RandomSource $deal, RandomSource $thinking, BotDifficulty $difficulty, array &$stats, ?callable $record): void
    {
        $note = null;
        $side = fn (int $team) => $team === $aTeam ? 'a' : 'b';
        $steps = 0;
        while ($state['last_hand'] === null && $steps++ < 400) {
            if ($state['phase'] === 'dealing') {
                $state = $engine->resume($state, $deal)->state;

                continue;
            }
            $seat = (int) $state['to_act'];
            $t = microtime(true);
            $action = $strategies[$seat]->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $thinking);
            $who = $side($seat % 2);
            $stats[$who]['ms'] += (microtime(true) - $t) * 1000;
            $stats[$who]['decisions']++;
            if ($action->type === 'bid' || $action->type === 'kaput') {
                // The last bid is the contract: keep what its bidder thought of it.
                $chance = $strategies[$seat] instanceof BlotBot ? $strategies[$seat]->bidChance : null;
                $note = $chance === null ? null : ['chance' => round($chance, 4), 'kaput' => $action->type === 'kaput', 'no_trump' => ($action->payload['suit'] ?? null) === 'N', 'value' => $action->payload['value'] ?? null];
            }
            if ($action->type === 'contra') {
                $stats[$who]['quansh']++;
            } elseif ($action->type === 'recontra') {
                $stats[$who]['sur']++;
            }
            $state = $engine->apply($state, $seat, $action, $deal)->state;
        }
        $last = $state['last_hand'];
        if ($last === null) {
            return;
        }
        $contract = $last['contract'];
        $bidders = $side($contract['team']);
        $defenders = $bidders === 'a' ? 'b' : 'a';
        foreach ([0, 1] as $team) {
            $stats[$side($team)]['deals']++;
            $stats[$side($team)]['sai'] += $last['scored'][$team] - $last['scored'][1 - $team];
        }
        if ($record !== null && $note !== null) {
            $record([...$note, 'made' => (bool) $last['made'], 'doubled' => (int) $contract['doubled']]);
        }
        $stats[$bidders]['contracts']++;
        $stats[$bidders]['made'] += $last['made'] ? 1 : 0;
        if ($contract['kaput']) {
            $stats[$bidders]['kaput_bids']++;
            $stats[$bidders]['kaput_made'] += $last['made'] ? 1 : 0;
        }
        if ($contract['doubled'] >= 1) {
            $stats[$defenders]['quansh_right'] += $last['made'] ? 0 : 1;
        }
        if ($contract['doubled'] === 2) {
            $stats[$bidders]['sur_right'] += $last['made'] ? 1 : 0;
        }
    }

    /**
     * @return array<string, float|int>
     */
    private function blank(): array
    {
        return ['deals' => 0, 'sai' => 0, 'contracts' => 0, 'made' => 0, 'kaput_bids' => 0, 'kaput_made' => 0,
            'quansh' => 0, 'quansh_right' => 0, 'sur' => 0, 'sur_right' => 0, 'decisions' => 0, 'ms' => 0.0];
    }
}
