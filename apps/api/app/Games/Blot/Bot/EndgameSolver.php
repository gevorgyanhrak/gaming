<?php

namespace App\Games\Blot\Bot;

use App\Games\Blot\Engine\BlotCards;
use App\Games\Blot\Engine\BlotEngine;

/**
 * Plays the last tricks of a sampled deal perfectly (research: docs/research/blot-ai — the Belot engine's
 * exact endgame, +59..+90 Elo there): once every hand has MAX_CARDS or fewer, alpha-beta over every legal
 * line, the pair to act maximising its result and the other pair minimising it, scored with the table's
 * own scoring (BlotEngine::score). Plays by the same rules as the engine (following suit, trumping, the
 * partner exception, any trump on a trump lead) and stops a kaput at its first lost trick, as the engine does.
 */
final class EndgameSolver
{
    public const MAX_CARDS = 3;

    private ?string $trump = null;

    /** @var array<string, mixed> */
    private array $contract = [];

    /** @var array{0: int, 1: int} */
    private array $decl = [0, 0];

    /** @var array{0: int, 1: int} */
    private array $belote = [0, 0];

    /** @param array<string, mixed> $state */
    public function applies(array $state): bool
    {
        return $state['phase'] === 'playing' && max(array_map('count', $state['hands'])) <= self::MAX_CARDS;
    }

    /**
     * The deal's result (sai, $team's score less the other pair's) with perfect play from here.
     *
     * @param  array<string, mixed>  $state  a full table state (all hands known)
     */
    public function solve(array $state, int $team): float
    {
        $this->trump = $state['contract']['suit'];
        $this->contract = $state['contract'];
        $this->decl = $this->countedDeclarations($state['declared'], (int) $state['leader']);
        $this->belote = [0, 0];
        if ($state['belote'] !== null) {
            $this->belote[$state['belote'] % 2] = 20;
        }
        $trick = array_map(fn (array $p) => [(int) $p['seat'], (string) $p['card']], $state['trick']);

        return $this->search($state['hands'], $trick, (int) $state['to_act'], $state['points'], $state['tricks'], $team, -INF, INF);
    }

    /**
     * @param  array<int, list<string>>  $hands
     * @param  list<array{0: int, 1: string}>  $trick
     * @param  array<int, int>  $points
     * @param  array<int, int>  $tricks
     */
    private function search(array $hands, array $trick, int $toAct, array $points, array $tricks, int $team, float $alpha, float $beta): float
    {
        if (count($trick) === 4) {
            $cards = array_column($trick, 1);
            $winner = $trick[BlotCards::winning($cards, $this->trump)][0];
            $last = $hands[$winner] === [];
            $points[$winner % 2] += array_sum(array_map(fn (string $c) => BlotCards::points($c, $this->trump), $cards)) + ($last ? 10 : 0);
            $tricks[$winner % 2]++;
            if ($this->contract['kaput'] && ! $last && $winner % 2 !== $this->contract['team']) {
                return $this->value($points, $tricks, $team, true);
            }
            if ($last) {
                return $this->value($points, $tricks, $team, false);
            }
            $trick = [];
            $toAct = $winner;
        }

        $maximise = $toAct % 2 === $team;
        $best = $maximise ? -INF : INF;
        foreach ($this->playable($hands[$toAct], $trick, $toAct) as $card) {
            $next = $hands;
            $next[$toAct] = array_values(array_diff($hands[$toAct], [$card]));
            $value = $this->search($next, [...$trick, [$toAct, $card]], ($toAct + 1) % 4, $points, $tricks, $team, $alpha, $beta);
            if ($maximise) {
                $best = max($best, $value);
                $alpha = max($alpha, $value);
            } else {
                $best = min($best, $value);
                $beta = min($beta, $value);
            }
            if ($alpha >= $beta) {
                break;
            }
        }

        return $best;
    }

    /**
     * The engine's rules of play (BlotEngine::playable).
     *
     * @param  list<string>  $hand
     * @param  list<array{0: int, 1: string}>  $trick
     * @return list<string>
     */
    private function playable(array $hand, array $trick, int $seat): array
    {
        if ($trick === []) {
            return $hand;
        }
        $played = array_column($trick, 1);
        $led = $played[0][1];
        $follow = array_values(array_filter($hand, fn (string $c) => $c[1] === $led));
        if ($follow !== []) {
            return $follow;
        }
        if ($this->trump === null) {
            return $hand;
        }
        if ($trick[BlotCards::winning($played, $this->trump)][0] % 2 === $seat % 2) {
            return $hand;
        }
        $trumps = array_values(array_filter($hand, fn (string $c) => $c[1] === $this->trump));
        if ($trumps === []) {
            return $hand;
        }
        $highest = max(array_map(fn (string $c) => BlotCards::strength($c, $led, $this->trump), $played));
        $beating = array_values(array_filter($trumps, fn (string $c) => BlotCards::strength($c, $led, $this->trump) > $highest));

        return $beating ?: $trumps;
    }

    /**
     * @param  array<int, int>  $points
     * @param  array<int, int>  $tricks
     */
    private function value(array $points, array $tricks, int $team, bool $stopped): float
    {
        [$scored, , , $noTricks] = BlotEngine::score($this->contract, $points, $tricks, $this->decl, $this->belote, $stopped);
        $value = $scored[$team] - $scored[1 - $team];
        if ($noTricks) {
            $value += $this->contract['team'] === $team ? -Simulator::MATCH : Simulator::MATCH;
        }

        return $value;
    }

    /**
     * Declarations that count: only the pair with the best one (engine's comparison by key).
     *
     * @param  array<int, list<array<string, mixed>>>  $declared
     * @return array{0: int, 1: int}
     */
    private function countedDeclarations(array $declared, int $leader): array
    {
        $best = null;
        foreach (range(0, 3) as $i) {
            // From the first leader round, as the engine compares them (the earlier wins a tie).
            $seat = ($leader + $i) % 4;
            foreach ($declared[$seat] ?? [] as $d) {
                if (isset($d['key']) && ($best === null || $d['key'] > $best['key'])) {
                    $best = ['key' => $d['key'], 'team' => $seat % 2];
                }
            }
        }
        $points = [0, 0];
        if ($best !== null) {
            foreach ($declared as $seat => $list) {
                if ($seat % 2 === $best['team']) {
                    $points[$best['team']] += array_sum(array_column($list, 'points'));
                }
            }
        }

        return $points;
    }
}
