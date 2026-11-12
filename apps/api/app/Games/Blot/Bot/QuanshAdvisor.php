<?php

namespace App\Games\Blot\Bot;

use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotCards;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Whether a blot bot should say Quansh (contra) on the opponents' contract. Only the bot's own seat view is
 * used: its cards, the bazar so far, the scores. Partners' and opponents' cards are never seen; they are
 * inferred from the bazar, as a person would.
 *
 * Advanced and Expert deal the 24 unseen cards many times over, keeping deals that fit every bid (and, for
 * Expert, every pass), and play each one out with the real rules (BlotEngine: declarations,
 * blot, kaput stop, scoring, match-ending deals) once with Quansh and once without. They Quansh when the
 * expected gain in sai is clearly positive and the contract more likely fails than not. Expert also lets a
 * strong pair answer with Sur, and counts the match: a deal that ends it is worth MATCH sai either way.
 */
final class QuanshAdvisor
{
    /** What ending the match is worth in a deal's comparison (sai). */
    private const MATCH = 150;

    /** Shuffles tried for each sampled deal before settling for the best fit. */
    private const FIT_TRIES = 20;

    public function __construct(private readonly BlotBot $bot = new BlotBot) {}

    /**
     * @param  array<string, mixed>  $view  The bot's seat view, in the bazar with an opponents' contract.
     */
    public function shouldQuansh(array $view, QuanshSkill $skill, RandomSource $random): bool
    {
        if ($view['contract'] === null) {
            return false;
        }

        return match ($skill) {
            QuanshSkill::Beginner => $this->beginner($view, $random),
            QuanshSkill::Intermediate => $this->intermediate($view),
            QuanshSkill::Advanced, QuanshSkill::Expert => $this->judge($view, $skill, $random)['quansh'],
        };
    }

    /**
     * The sampled verdict: the chance the contract fails and the expected gain of Quansh over a pass (sai).
     *
     * @param  array<string, mixed>  $view
     * @return array{quansh: bool, fail: float, gain: float}
     */
    public function judge(array $view, QuanshSkill $skill, RandomSource $random, ?int $samples = null): array
    {
        $expert = $skill === QuanshSkill::Expert;
        $samples ??= $expert ? 160 : 64;
        $seat = (int) $view['seat'];
        $unknown = array_values(array_diff($this->deck(), $view['hand']));

        $weights = 0.0;
        $fails = 0.0;
        $gain = 0.0;
        for ($n = 0; $n < $samples; $n++) {
            $hands = $this->fitting($unknown, $view, $expert, $random);
            $weight = 1.0;
            [$pass, $made] = $this->playOut($view, $hands, quansh: false, expert: $expert, random: $random);
            [$quansh] = $this->playOut($view, $hands, quansh: true, expert: $expert, random: $random);
            $weights += $weight;
            $fails += $made ? 0 : $weight;
            $gain += $weight * ($quansh - $pass);
        }
        $fail = $fails / $weights;
        $gain /= $weights;
        [$margin, $least] = $expert ? [1.5, 0.5] : [3.0, 0.6];

        return ['quansh' => $gain >= $margin && $fail >= $least, 'fail' => $fail, 'gain' => $gain];
    }

    /**
     * Sees what Intermediate sees, but overlooks about a third of it, the way a new player does.
     *
     * @param  array<string, mixed>  $view
     */
    private function beginner(array $view, RandomSource $random): bool
    {
        return $this->intermediate($view) && $random->int(1, 3) > 1;
    }

    /**
     * Its own defence in their trump plus its partner's likely share, against the points the bid needs. A
     * kaput fails with one trick, so a sure trick (the trump jack, or an ace in Boy) is enough.
     *
     * @param  array<string, mixed>  $view
     */
    private function intermediate(array $view): bool
    {
        $contract = $view['contract'];
        $trump = $contract['suit'];
        $hand = $view['hand'];
        if ($contract['kaput']) {
            return in_array($trump === null ? 'A' : 'J'.$trump, $trump === null ? array_map(fn (string $c) => $c[0], $hand) : $hand, true);
        }
        $mine = $this->bot->strength($hand, $trump) - ($trump === null ? 20 : 30);
        $partner = $this->partnerBid($view);
        $share = $partner === null ? 25 : max(25, $partner * 10 - 30);
        $theirs = 162 - $mine - $share;
        $guards = $trump === null
            ? count(array_filter($hand, fn (string $c) => $c[0] === 'A' || $c[0] === 'T'))
            : count(array_filter($hand, fn (string $c) => $c[1] === $trump));

        return $guards >= 2 && $theirs < $contract['value'] * 10 - 15;
    }

    /**
     * The partner's highest bid in this bazar, if they made one.
     *
     * @param  array<string, mixed>  $view
     */
    private function partnerBid(array $view): ?int
    {
        $partner = ((int) $view['seat'] + 2) % 4;
        $best = null;
        foreach ($view['bids'] as $bid) {
            if ($bid['seat'] === $partner && $bid['type'] === 'bid') {
                $best = max($best ?? 0, (int) $bid['value']);
            }
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private function deck(): array
    {
        $cards = [];
        foreach (BlotCards::SUITS as $suit) {
            foreach (str_split('789TJQKA') as $rank) {
                $cards[] = $rank.$suit;
            }
        }

        return $cards;
    }

    /**
     * A deal that fits the bazar (rejection sampling): a shuffle is kept with the chance that its players
     * would have bid and passed as they did; after FIT_TRIES shuffles the best fit so far is kept.
     *
     * @param  list<string>  $unknown
     * @param  array<string, mixed>  $view
     * @return array<int, list<string>>
     */
    private function fitting(array $unknown, array $view, bool $readPasses, RandomSource $random): array
    {
        $best = null;
        $bestFit = -1.0;
        for ($try = 0; $try < self::FIT_TRIES; $try++) {
            $hands = $this->sample($unknown, $view, $random);
            $fit = $this->likelihood($hands, $view, $readPasses);
            if ($random->int(1, 1000) <= (int) round($fit * 1000)) {
                return $hands;
            }
            if ($fit > $bestFit) {
                [$best, $bestFit] = [$hands, $fit];
            }
        }

        return $best ?? $this->sample($unknown, $view, $random);
    }

    /**
     * One way the unseen cards could lie. The contract holder's hand is drawn first, leaning towards trumps
     * and aces the higher they bid (a 25, a kaput, leans hard), then the rest is shuffled to the other seats.
     *
     * @param  list<string>  $unknown
     * @param  array<string, mixed>  $view
     * @return array<int, list<string>>
     */
    private function sample(array $unknown, array $view, RandomSource $random): array
    {
        $seat = (int) $view['seat'];
        $contract = $view['contract'];
        $holder = $contract !== null && $contract['seat'] !== $seat ? (int) $contract['seat'] : null;
        $hands = [$seat => $view['hand']];

        if ($holder !== null) {
            $lean = max(0, min(2 * 6, $contract['value'] - BlotEngine::MIN_BID)) / 6;   // 0 … 2
            $trump = $contract['suit'];
            $weights = array_map(fn (string $c) => (int) round(10 * (1 + $lean * match (true) {
                $trump !== null && $c[1] === $trump => 4,
                $c[0] === 'A' => 2,
                $c[0] === 'T' => 1,
                default => 0,
            })), $unknown);
            $drawn = [];
            for ($k = 0; $k < 8; $k++) {
                $pick = $random->int(1, array_sum($weights));
                foreach ($weights as $i => $w) {
                    $pick -= $w;
                    if ($pick <= 0) {
                        $drawn[] = $unknown[$i];
                        array_splice($unknown, $i, 1);
                        array_splice($weights, $i, 1);
                        break;
                    }
                }
            }
            $hands[$holder] = $drawn;
        }

        for ($i = count($unknown) - 1; $i > 0; $i--) {
            $j = $random->int(0, $i);
            [$unknown[$i], $unknown[$j]] = [$unknown[$j], $unknown[$i]];
        }
        $k = 0;
        foreach (range(0, 3) as $s) {
            if (! isset($hands[$s])) {
                $hands[$s] = array_slice($unknown, $k * 8, 8);
                $k++;
            }
        }
        ksort($hands);

        return $hands;
    }

    /**
     * How well a deal fits the bazar: a bidder's hand is usually worth about their bid (with a partner's
     * share), and someone who passed seldom held a hand that could comfortably bid. Never zero: people do
     * overbid and underbid, which is exactly what a Quansh punishes.
     *
     * @param  array<int, list<string>>  $hands
     * @param  array<string, mixed>  $view
     */
    private function likelihood(array $hands, array $view, bool $readPasses): float
    {
        $seat = (int) $view['seat'];
        $weight = 1.0;
        $lowest = BlotEngine::MIN_BID;
        foreach ($view['bids'] as $bid) {
            $who = (int) $bid['seat'];
            $isBid = $bid['type'] === 'bid' || $bid['type'] === 'kaput';
            $value = $bid['type'] === 'kaput' ? BlotEngine::KAPUT : (int) ($bid['value'] ?? 0);
            if ($who !== $seat && $isBid) {
                $suit = $bid['suit'] === 'N' ? null : (string) $bid['suit'];
                $weight *= max(0.05, $this->sigmoid(($this->bot->strength($hands[$who], $suit) - ($value * 10 - 20)) / 15));
            } elseif ($who !== $seat && $bid['type'] === 'pass' && $readPasses) {
                $best = max(array_map(fn (string $s) => $this->bot->strength($hands[$who], $s === 'N' ? null : $s), BlotEngine::BID_SUITS));
                $weight *= max(0.1, 1 - 0.85 * $this->sigmoid(($best - ($lowest * 10 + 20)) / 15));
            }
            if ($isBid) {
                $lowest = $value + 1;
            }
        }

        return $weight;
    }

    private function sigmoid(float $x): float
    {
        return 1 / (1 + exp(-$x));
    }

    /**
     * Plays the deal out and returns the bot's pair's score less the other pair's (with MATCH for a deal that
     * ends the match), and whether the contract was made.
     *
     * @param  array<string, mixed>  $view
     * @param  array<int, list<string>>  $hands
     * @return array{0: int, 1: bool}
     */
    public function playOut(array $view, array $hands, bool $quansh, bool $expert, RandomSource $random): array
    {
        $state = $this->finalState($view, $hands, $random, $quansh, $expert);
        $us = (int) $view['seat'] % 2;
        $scored = $state['last_hand']['scored'];
        $value = $scored[$us] - $scored[1 - $us];
        $result = $state['result'];
        // The rules end the match on a reverse kaput or a made contra'd kaput; Expert also watches the target.
        if ($result !== null && ($expert || $result['reason'] !== 'target_reached')) {
            $value += $result['team'] === $us ? self::MATCH : -self::MATCH;
        }

        return [$value, (bool) $state['last_hand']['made']];
    }

    /**
     * The deal played out from this bazar with the real rules: everyone passes (or the bot says Quansh and
     * the pair answers), all four declare what they hold and play it as a bot would. Public for the analysis
     * in the tests.
     *
     * @param  array<string, mixed>  $view
     * @param  array<int, list<string>>  $hands
     * @return array<string, mixed>
     */
    public function finalState(array $view, array $hands, RandomSource $random, bool $quansh = false, bool $expert = false): array
    {
        $engine = new BlotEngine;
        $seat = (int) $view['seat'];
        $state = [
            ...$view,
            'hands' => $hands,
            'available' => [[], [], [], []],
            'declared_index' => [[], [], [], []],
            'pause_ms' => 0,
            'deal_ms' => 0,
        ];
        $act = fn (array $state, int $who, Action $action) => $engine->apply($state, $who, $action, $random)->state;

        if ($quansh) {
            $state = $act($state, $seat, new Action('contra'));
            while ($state['phase'] === 'answer') {
                $who = (int) $state['to_act'];
                // Expert expects a pair that is clearly strong to answer with Sur.
                $sur = $expert && $this->bot->strength($state['hands'][$who], $state['contract']['suit']) >= $state['contract']['value'] * 10 + 30;
                $state = $act($state, $who, new Action($sur ? 'recontra' : 'pass'));
            }
        } else {
            while ($state['phase'] === 'bidding') {
                $state = $act($state, (int) $state['to_act'], new Action('pass'));
            }
        }

        while ($state['phase'] === 'playing') {
            $who = (int) $state['to_act'];
            $cards = [];
            $declare = null;
            foreach ($engine->legalActions($state, $who) as $action) {
                if ($action->type === 'declare') {
                    $declare ??= $action;
                } else {
                    $cards[] = (string) $action->payload['card'];
                }
            }
            if ($declare !== null) {
                $state = $act($state, $who, $declare);

                continue;
            }
            $card = $this->bot->card(
                ['trump' => $state['contract']['suit'], 'seat' => $who, 'trick' => $state['trick'], 'contract' => $state['contract']],
                $cards,
            );
            $state = $act($state, $who, new Action('play', ['card' => $card]));
        }

        return $state;
    }
}
