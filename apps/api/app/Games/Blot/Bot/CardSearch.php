<?php

namespace App\Games\Blot\Bot;

use App\Gameplay\Engine\Action;
use App\Gameplay\Random\RandomSource;
use App\Games\Blot\Engine\BlotCards;

/**
 * Chooses the card to play by looking ahead (Monte Carlo over the hidden cards): deal the cards it can't
 * see in ways that fit everything it knows (CardMemory, DealSampler), play every legal card on each of
 * those deals to the end of the deal (Simulator), and keep the card with the best average result for its
 * pair — the whole deal, not just this trick: points, the last trick, the contract made or failed,
 * multipliers, kaput. Ties go to the card that gives away the least.
 */
final class CardSearch
{
    private readonly DealSampler $sampler;

    private readonly Simulator $simulator;

    private readonly EndgameSolver $endgame;

    /**
     * @param  bool  $exact  solve the last tricks exactly (EndgameSolver)
     * @param  bool  $merge  test only one of cards that are worth the same (same suit, nothing between, same points)
     */
    public function __construct(BlotBot $bot = new BlotBot, private readonly bool $exact = true, private readonly bool $merge = true)
    {
        $this->sampler = new DealSampler($bot);
        $this->simulator = new Simulator($bot);
        $this->endgame = new EndgameSolver;
    }

    /**
     * @param  array<string, mixed>  $view
     * @param  list<string>  $cards  the legal cards
     */
    public function choose(array $view, array $cards, int $samples, RandomSource $random): string
    {
        if (count($cards) === 1) {
            return $cards[0];
        }
        $candidates = $this->merge ? $this->distinct($view, $cards) : $cards;
        if (count($candidates) === 1) {
            return $candidates[0];
        }
        $scores = $this->evaluate($view, $candidates, $samples, $random);
        $trump = $view['trump'];
        $cost = fn (string $c) => BlotCards::points($c, $trump) + ($trump !== null && $c[1] === $trump ? 15 : 0);
        uksort($scores, fn (string $a, string $b) => [$scores[$b], $cost($a)] <=> [$scores[$a], $cost($b)]);

        return (string) array_key_first($scores);
    }

    /**
     * Each legal card's average result for the pair (sai), over $samples deals.
     *
     * @param  array<string, mixed>  $view
     * @param  list<string>  $cards
     * @return array<string, float>
     */
    public function evaluate(array $view, array $cards, int $samples, RandomSource $random): array
    {
        $memory = new CardMemory($view);
        $team = $memory->seat % 2;
        $totals = array_fill_keys($cards, 0.0);
        for ($n = 0; $n < $samples; $n++) {
            $hands = $this->sampler->sample($memory, $view, $random);
            $base = $this->simulator->state($view, $hands, $this->sampler->original($hands, $view));
            foreach ($cards as $card) {
                $state = $this->simulator->act($base, $memory->seat, new Action('play', ['card' => $card]), $random);
                $totals[$card] += $this->exact && $this->endgame->applies($state)
                    ? $this->endgame->solve($state, $team)
                    : $this->simulator->value($this->simulator->finish($state, $random), $team);
            }
        }

        return array_map(fn (float $total) => $total / $samples, $totals);
    }

    /**
     * One card for each group that plays the same: cards of a suit with no card anyone else could hold
     * between them in rank and the same points (e.g. 7 and 8 with nothing out between). The lowest of each.
     *
     * @param  array<string, mixed>  $view
     * @param  list<string>  $cards
     * @return list<string>
     */
    public function distinct(array $view, array $cards): array
    {
        $trump = $view['trump'];
        $memory = new CardMemory($view);
        $out = array_merge($memory->unseen(), ...$memory->known);
        $keep = [];
        foreach (BlotCards::SUITS as $suit) {
            $order = $trump === $suit ? '78QKTA9J' : '789JQKTA';
            $mine = array_values(array_filter($cards, fn (string $c) => $c[1] === $suit));
            usort($mine, fn (string $a, string $b) => strpos($order, $a[0]) <=> strpos($order, $b[0]));
            $previous = null;
            foreach ($mine as $card) {
                if ($previous !== null && BlotCards::points($card, $trump) === BlotCards::points($previous, $trump)) {
                    $between = array_filter($out, fn (string $c) => $c[1] === $suit
                        && strpos($order, $c[0]) > strpos($order, $previous[0]) && strpos($order, $c[0]) < strpos($order, $card[0]));
                    if ($between === []) {
                        $previous = $card;

                        continue;   // plays the same as the lower one, already kept
                    }
                }
                $keep[] = $card;
                $previous = $card;
            }
        }

        return $keep;
    }
}
