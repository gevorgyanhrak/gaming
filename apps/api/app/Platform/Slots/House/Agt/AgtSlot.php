<?php

namespace App\Platform\Slots\House\Agt;

use App\Platform\Slots\House\CreditedHouseSlot;
use App\Platform\Slots\House\HasClientLayout;
use Closure;
use RuntimeException;

/**
 * An AGT-style line slot (owner, 2026-11-24): the maths of one game from Slotopol (MIT, github.com/slotopol/server),
 * read from data/<slug>.json (converted by apps/web/play/tools/slotopol_agt.py: symbols, pay tables, lines, reel
 * strips at one RTP) and dealt by our server like every house slot. Rules, as Slotopol's scanners:
 *
 * - lines pay left to right per line bet (here: pay ÷ lines, in total bets);
 * - wild `none`; `substitute` — it stands in for the line's symbol and pays its own leading run if that pays more;
 *   `expanding` — a wild anywhere on reels 2–4 makes that whole reel wild for every line (it pays nothing itself);
 * - the scatter pays anywhere, 3 or more, times the total bet.
 *
 * Rounds: {grid: symbol names [reel][row], lineWins, scatterWin, expanded: reels, totalWin} (amounts in total bets).
 */
final class AgtSlot implements CreditedHouseSlot, HasClientLayout
{
    /** @var array{slug: string, title: string, cols: int, rows: int, lines: list<list<int>>, symbols: list<string>, line_pay: list<list<int|float>>, scatter: int|null, scatter_pay: list<float>, wild: int|null, wild_mode: string, rtp: float, reels: list<list<int>>} */
    private readonly array $game;

    /**
     * @param  array<string, mixed>|null  $data  the game's data (default: data/<slug>.json)
     */
    public function __construct(string $slug, ?array $data = null)
    {
        if ($data === null) {
            $path = __DIR__."/data/{$slug}.json";
            $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        }
        if (! is_array($data)) {
            throw new RuntimeException("No AGT slot data for [{$slug}].");
        }
        /** @var array{slug: string, title: string, cols: int, rows: int, lines: list<list<int>>, symbols: list<string>, line_pay: list<list<int|float>>, scatter: int|null, scatter_pay: list<float>, wild: int|null, wild_mode: string, rtp: float, reels: list<list<int>>} $data */
        $this->game = $data;
    }

    public function id(): string
    {
        return $this->game['slug'];
    }

    public function credit(): string
    {
        return 'Game maths: Slotopol (open source, MIT). Symbols and screen by this platform; every round is dealt on our server.';
    }

    /**
     * @return array{slug: string, title: string, cols: int, rows: int, lines: list<list<int>>, symbols: list<string>, line_pay: list<list<int|float>>, scatter: int|null, scatter_pay: list<float>, wild: int|null, wild_mode: string, rtp: float, reels: list<list<int>>}
     */
    public function data(): array
    {
        return $this->game;
    }

    /**
     * What the shared client (play/agt) draws from: the grid, lines, symbols and pay tables (no reel strips).
     *
     * @return array{title: string, cols: int, rows: int, lines: list<list<int>>, symbols: list<string>, line_pay: list<list<int|float>>, scatter: string|null, scatter_pay: list<float>, wild: string|null, wild_mode: string}
     */
    public function layout(): array
    {
        $g = $this->game;

        return [
            'title' => $g['title'],
            'cols' => $g['cols'],
            'rows' => $g['rows'],
            'lines' => $g['lines'],
            'symbols' => $g['symbols'],
            'line_pay' => $g['line_pay'],
            'scatter' => $g['scatter'] === null ? null : $g['symbols'][$g['scatter']],
            'scatter_pay' => $g['scatter_pay'],
            'wild' => $g['wild'] === null ? null : $g['symbols'][$g['wild']],
            'wild_mode' => $g['wild_mode'],
        ];
    }

    /** Every line at the top pay plus the top scatter pay, in total bets (an upper bound). */
    public function maxWin(): int
    {
        $line = max(array_map(fn (array $pays) => $pays === [] ? 0 : max($pays), $this->game['line_pay']));
        $scatter = $this->game['scatter_pay'] === [] ? 0 : max($this->game['scatter_pay']);

        return (int) ceil($line + $scatter);
    }

    public function play(Closure $random): array
    {
        $grid = [];
        foreach ($this->game['reels'] as $reel => $strip) {
            $stop = $random(count($strip));
            for ($row = 0; $row < $this->game['rows']; $row++) {
                $grid[$reel][$row] = $strip[($stop + $row) % count($strip)];
            }
        }
        $round = $this->evaluate($grid);

        return ['round' => $round, 'total_win' => $round['totalWin']];
    }

    /**
     * The screen's wins.
     *
     * @param  array<int, array<int, int>>  $grid  symbol ids [reel][row]
     * @return array{grid: list<list<string>>, lineWins: list<array<string, mixed>>, scatterWin: array<string, mixed>|null, expanded: list<int>, totalWin: float}
     */
    public function evaluate(array $grid): array
    {
        $expanded = $this->expandedReels($grid);
        $lineWins = [];
        $lines = count($this->game['lines']);
        foreach ($this->game['lines'] as $index => $rows) {
            $symbols = [];
            foreach ($rows as $reel => $row) {
                $symbols[] = $grid[$reel][$row];
            }
            $win = $this->line($symbols, $expanded);
            if ($win !== null) {
                $positions = [];
                for ($reel = 0; $reel < $win['count']; $reel++) {
                    $positions[] = [$reel, $rows[$reel]];
                }
                $lineWins[] = [
                    'line' => $index,
                    'symbol' => $this->game['symbols'][$win['symbol']],
                    'count' => $win['count'],
                    'amount' => $win['pay'] / $lines,
                    'positions' => $positions,
                ];
            }
        }

        $scatterWin = null;
        $scatter = $this->game['scatter'];
        if ($scatter !== null) {
            $positions = [];
            foreach ($grid as $reel => $column) {
                foreach ($column as $row => $symbol) {
                    if ($symbol === $scatter) {
                        $positions[] = [$reel, $row];
                    }
                }
            }
            $pay = $this->scatterPay(count($positions));
            if ($pay > 0) {
                $scatterWin = ['symbol' => $this->game['symbols'][$scatter], 'count' => count($positions), 'amount' => $pay, 'positions' => $positions];
            }
        }

        return [
            'grid' => array_map(fn (array $column) => array_map(fn (int $s) => $this->game['symbols'][$s], array_values($column)), array_values($grid)),
            'lineWins' => $lineWins,
            'scatterWin' => $scatterWin,
            'expanded' => $expanded,
            'totalWin' => (float) (array_sum(array_column($lineWins, 'amount')) + ($scatterWin['amount'] ?? 0)),
        ];
    }

    /**
     * Reels a wild makes wild for every line (`expanding` games: reels 2–4).
     *
     * @param  array<int, array<int, int>>  $grid
     * @return list<int>
     */
    public function expandedReels(array $grid): array
    {
        if ($this->game['wild_mode'] !== 'expanding') {
            return [];
        }
        $expanded = [];
        foreach ([1, 2, 3] as $reel) {
            if (isset($grid[$reel]) && in_array($this->game['wild'], $grid[$reel], true)) {
                $expanded[] = $reel;
            }
        }

        return $expanded;
    }

    /**
     * One line, left to right: its paying symbol, run and pay in line bets, or null.
     *
     * @param  list<int>  $symbols
     * @param  list<int>  $expanded
     * @return array{symbol: int, count: int, pay: float}|null
     */
    public function line(array $symbols, array $expanded = []): ?array
    {
        $reels = count($symbols);
        $wild = $this->game['wild'];

        if ($this->game['wild_mode'] === 'substitute') {
            $numw = 0;
            $syml = null;
            $numl = $reels;
            foreach ($symbols as $x => $s) {
                if ($s === $wild) {
                    if ($syml === null) {
                        $numw = $x + 1;
                    }
                } elseif ($syml === null) {
                    $syml = $s;
                } elseif ($s !== $syml) {
                    $numl = $x;
                    break;
                }
            }
            $payw = $numw > 0 ? $this->linePay($wild, $numw) : 0.0;
            $payl = $syml !== null ? $this->linePay($syml, $numl) : 0.0;
            if ($payl > $payw) {
                return ['symbol' => (int) $syml, 'count' => $numl, 'pay' => $payl];
            }

            return $payw > 0 ? ['symbol' => (int) $wild, 'count' => $numw, 'pay' => $payw] : null;
        }

        $syml = $symbols[0];
        $numl = $reels;
        for ($x = 1; $x < $reels; $x++) {
            if (in_array($x, $expanded, true)) {
                continue;
            }
            if ($symbols[$x] !== $syml) {
                $numl = $x;
                break;
            }
        }
        $pay = $this->linePay($syml, $numl);

        return $pay > 0 ? ['symbol' => $syml, 'count' => $numl, 'pay' => $pay] : null;
    }

    public function linePay(?int $symbol, int $count): float
    {
        if ($symbol === null || $count < 1) {
            return 0.0;
        }

        return (float) ($this->game['line_pay'][$symbol][$count - 1] ?? 0);
    }

    public function scatterPay(int $count): float
    {
        if ($count < 1) {
            return 0.0;
        }

        return (float) ($this->game['scatter_pay'][min($count, count($this->game['scatter_pay'])) - 1] ?? 0);
    }
}
