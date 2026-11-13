<?php

namespace App\Games\Blot\Bot;

use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Bids by expected result, not by fixed rules: deals the 27 cards it can't see in ways that fit the bazar so
 * far (the partner's and the opponents' bids and passes), plays each deal out with every possible trump and
 * Boy (Simulator), and from what its pair took prices every contract it may bid — each number, each suit,
 * kaput — with the table's own scoring (BlotEngine::score). It bids the best one when it beats letting the
 * bazar stand by a margin; otherwise it passes.
 */
final class BidPlanner
{
    /** How much better than passing a bid must look (sai): the bazar may still go on against it. */
    private const MARGIN = 1.0;

    /**
     * A kaput only when nearly every deal takes all eight tricks: the look-ahead plays every seat by the quick
     * card rules, which lose tricks a strong defender wouldn't (the arena saw 1 kaput made in 11 without this).
     */
    private const KAPUT_SURE = 0.9;

    /** Numbers above the lowest legal one worth pricing. */
    private const SPAN = 14;

    /** The raw chance (share of sampled deals) that the last chosen bid is made: self-play records it. */
    public float $chance = 0.0;

    private readonly DealSampler $sampler;

    private readonly Simulator $simulator;

    /**
     * @param  BidCalibration|null  $calibration  what self-play taught about its own chances (null: raw estimates)
     */
    public function __construct(BlotBot $bot = new BlotBot, private readonly ?BidCalibration $calibration = null)
    {
        $this->sampler = new DealSampler($bot);
        $this->simulator = new Simulator($bot);
    }

    /**
     * @param  array<string, mixed>  $view
     * @param  array<string, Action>  $legal  by type (bid is a range, kaput by suit)
     * @param  list<Action>  $actions  all legal actions
     */
    public function choose(array $view, array $legal, array $actions, int $samples, RandomSource $random): Action
    {
        $plan = $this->plan($view, $legal, $actions, $samples, $random);
        $this->chance = $plan['chance'];

        return $plan['best'] !== null && $plan['value'] >= $plan['pass'] + self::MARGIN ? $plan['best'] : $legal['pass'];
    }

    /**
     * Every option's expected result for the pair (sai): the best bid and its value, and what passing gives.
     *
     * @param  array<string, mixed>  $view
     * @param  array<string, Action>  $legal
     * @param  list<Action>  $actions
     * @return array{best: Action|null, value: float, pass: float, chance: float, options: array<string, float>}
     */
    public function plan(array $view, array $legal, array $actions, int $samples, RandomSource $random): array
    {
        $seat = (int) $view['seat'];
        $team = $seat % 2;
        $memory = new CardMemory($view);
        $kaputs = array_values(array_filter($actions, fn (Action $a) => $a->type === 'kaput'));
        $range = $legal['bid'] ?? null;
        $suits = $range !== null ? BlotEngine::BID_SUITS : array_map(fn (Action $a) => (string) $a->payload['suit'], $kaputs);

        $deals = [];
        for ($n = 0; $n < $samples; $n++) {
            $deals[] = $this->sampler->sample($memory, $view, $random);
        }

        // What letting the bazar stand gives: the current contract played out, or nothing (a redeal).
        $pass = 0.0;
        if ($view['contract'] !== null) {
            foreach ($deals as $hands) {
                $pass += $this->simulator->value($this->simulator->finish($this->simulator->state($view, $hands), $random), $team);
            }
            $pass /= $samples;
        }

        $options = [];
        $best = null;
        $bestValue = -INF;
        $bestChance = 0.0;
        foreach ($suits as $suit) {
            // One play-out per deal with this trump; then every price of it from what the pair took.
            $outcomes = [];
            foreach ($deals as $hands) {
                $state = $this->simulator->state($view, $hands);
                $opening = $range !== null
                    ? new Action('bid', ['suit' => $suit, 'value' => (int) $range->payload['min']])
                    : new Action('kaput', ['suit' => $suit]);
                $final = $this->simulator->finish($this->simulator->act($state, $seat, $opening, $random), $random);
                $outcomes[] = $this->outcome($final['last_hand']);
            }

            $prices = [];
            if ($range !== null) {
                $top = min((int) $range->payload['max'], (int) $range->payload['min'] + self::SPAN);
                foreach (range((int) $range->payload['min'], $top) as $value) {
                    $prices[] = [new Action('bid', ['suit' => $suit, 'value' => $value]), ['team' => $team, 'value' => $value, 'suit' => $suit === 'N' ? null : $suit, 'kaput' => false, 'doubled' => 0]];
                }
            }
            $sweeps = count(array_filter($outcomes, fn (array $o) => $o['tricks'][$team] === 8)) / max(1, count($outcomes));
            foreach ($kaputs as $kaput) {
                if ($kaput->payload['suit'] === $suit && $sweeps >= self::KAPUT_SURE) {
                    $prices[] = [$kaput, ['team' => $team, 'value' => BlotEngine::KAPUT, 'suit' => $suit === 'N' ? null : $suit, 'kaput' => true, 'doubled' => 0]];
                }
            }
            foreach ($prices as [$action, $contract]) {
                $made = [];
                $failed = [];
                $ifFailed = 0.0;
                foreach ($outcomes as $o) {
                    $stopped = $contract['kaput'] && $o['tricks'][$team] < 8;
                    [$scored, $ok, , $noTricks, $bid, , $declSai] = BlotEngine::score($contract, $o['points'], $o['tricks'], $o['decl'], $o['belote'], $stopped);
                    $result = $scored[$team] - $scored[1 - $team] + ($noTricks ? -Simulator::MATCH : 0);
                    $ok ? $made[] = $result : $failed[] = $result;
                    // The same deal had the contract failed: the defenders take the bid, 16 and every declaration.
                    $ifFailed += -($bid + 16 + $declSai[0] + $declSai[1]) + intdiv((int) $o['belote'][$team], 10) - intdiv((int) $o['belote'][1 - $team], 10);
                }
                $chance = count($made) / max(1, count($outcomes));
                $value = (array_sum($made) + array_sum($failed)) / $samples;
                if ($this->calibration !== null && ! $contract['kaput'] && $made !== []) {
                    // What self-play says this chance really is: move that much weight between making and failing.
                    $failedMean = $failed !== [] ? array_sum($failed) / count($failed) : $ifFailed / count($outcomes);
                    $value += ($this->calibration->chance($chance) - $chance) * (array_sum($made) / count($made) - $failedMean);
                }
                $options[$action->type.':'.$suit.':'.($action->payload['value'] ?? 'K')] = $value;
                if ($value > $bestValue) {
                    [$best, $bestValue, $bestChance] = [$action, $value, $chance];
                }
            }
        }

        return ['best' => $best, 'value' => $best === null ? -INF : $bestValue, 'pass' => $pass, 'chance' => $bestChance, 'options' => $options];
    }

    /**
     * What each pair took in a played deal, for pricing contracts.
     *
     * @param  array<string, mixed>  $last
     * @return array{points: array<int, int|float>, tricks: array<int, int|float>, decl: array<int, int|float>, belote: array<int, int|float>}
     */
    private function outcome(array $last): array
    {
        $decl = [0, 0];
        foreach ($last['declarations'] as $declaration) {
            if ($declaration['counted']) {
                $decl[$declaration['seat'] % 2] += $declaration['points'];
            }
        }

        return [
            'points' => $last['card_points'],
            'tricks' => $last['tricks'],
            'decl' => $decl,
            'belote' => [$last['belote_sai'][0] * 10, $last['belote_sai'][1] * 10],
        ];
    }
}
