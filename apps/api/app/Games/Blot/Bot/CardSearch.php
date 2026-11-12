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

    public function __construct(BlotBot $bot = new BlotBot)
    {
        $this->sampler = new DealSampler($bot);
        $this->simulator = new Simulator($bot);
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
        $scores = $this->evaluate($view, $cards, $samples, $random);
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
                $totals[$card] += $this->simulator->value($this->simulator->finish($state, $random), $team);
            }
        }

        return array_map(fn (float $total) => $total / $samples, $totals);
    }
}
