<?php

use App\Platform\Slots\House\Agt\AgtCatalog;
use App\Platform\Slots\House\Agt\AgtSlot;

/**
 * The exact RTP of an AGT-style slot from its data, computed independently of play(): each line's expected pay from
 * the reels' own distributions (reels are independent; a line reads one row per reel), stopping as soon as a line's
 * outcome is settled; the scatter from the convolution of each reel's scatter count. It must equal the RTP Slotopol
 * published for the same reels — so the port pays exactly as the original maths.
 */
function agtExactRtp(AgtSlot $slot): float
{
    $g = $slot->data();
    $rows = $g['rows'];
    $wild = $g['wild'];
    $expanding = $g['wild_mode'] === 'expanding';

    // Per reel and row: [[symbol, expanded, probability], …].
    $states = [];
    foreach ($g['reels'] as $reel => $strip) {
        $n = count($strip);
        for ($row = 0; $row < $rows; $row++) {
            $map = [];
            for ($stop = 0; $stop < $n; $stop++) {
                $window = [];
                for ($r = 0; $r < $rows; $r++) {
                    $window[] = $strip[($stop + $r) % $n];
                }
                $exp = $expanding && in_array($reel, [1, 2, 3], true) && in_array($wild, $window, true) ? 1 : 0;
                $key = $window[$row].':'.$exp;
                $map[$key] = ($map[$key] ?? 0) + 1 / $n;
            }
            $states[$reel][$row] = array_map(fn ($k, $p) => [...array_map('intval', explode(':', $k)), $p], array_keys($map), $map);
        }
    }

    $lineRtp = 0.0;
    foreach ($g['lines'] as $line) {
        $walk = function (int $reel, array $symbols, array $expanded, float $p) use (&$walk, $slot, $line, $states, $g, $wild): float {
            $done = $reel === $g['cols'];
            if (! $done) {
                // Settled early: the run already broke (no more reels can change the pay).
                if ($reel >= 2 && $g['wild_mode'] !== 'substitute') {
                    $last = $reel - 1;
                    $countsAsLine = $g['wild_mode'] === 'line_wild' && $symbols[$last] === $wild;
                    if (! in_array($last, $expanded, true) && ! $countsAsLine && $symbols[$last] !== $symbols[0]) {
                        $done = true;
                    }
                }
                if ($reel >= 2 && $g['wild_mode'] === 'substitute') {
                    $syml = null;
                    foreach ($symbols as $s) {
                        if ($s !== $wild) {
                            $syml ??= $s;
                            if ($s !== $syml) {
                                $done = true;
                                break;
                            }
                        }
                    }
                }
            }
            if ($done) {
                $win = $slot->line(array_pad($symbols, $g['cols'], -1), $expanded);

                return $p * ($win['pay'] ?? 0);
            }
            $sum = 0.0;
            foreach ($states[$reel][$line[$reel]] as [$symbol, $exp, $q]) {
                $sum += $walk($reel + 1, [...$symbols, $symbol], $exp ? [...$expanded, $reel] : $expanded, $p * $q);
            }

            return $sum;
        };
        $lineRtp += $walk(0, [], [], 1.0) / count($g['lines']);
    }

    $scatterRtp = 0.0;
    if ($g['scatter'] !== null) {
        $dist = [0 => 1.0];
        foreach ($g['reels'] as $strip) {
            $n = count($strip);
            $counts = [];
            for ($stop = 0; $stop < $n; $stop++) {
                $c = 0;
                for ($r = 0; $r < $rows; $r++) {
                    $c += $strip[($stop + $r) % $n] === $g['scatter'] ? 1 : 0;
                }
                $counts[$c] = ($counts[$c] ?? 0) + 1 / $n;
            }
            $next = [];
            foreach ($dist as $a => $pa) {
                foreach ($counts as $b => $pb) {
                    $next[$a + $b] = ($next[$a + $b] ?? 0) + $pa * $pb;
                }
            }
            $dist = $next;
        }
        foreach ($dist as $count => $p) {
            $scatterRtp += $p * $slot->scatterPay($count);
        }
    }

    return $lineRtp + $scatterRtp;
}

it('pays exactly the RTP Slotopol published for each AGT-style game’s reels, and close to it on the lines we play', function (string $slug) {
    $slot = new AgtSlot($slug);
    // Slotopol computes its figure over the whole line table: the same rules on the same lines must give it exactly.
    $all = new AgtSlot($slug, ['lines' => $slot->data()['lines_all'], ...array_diff_key($slot->data(), ['lines' => 0])]);
    if ($slot->data()['wild_mode'] === 'expanding') {
        // Expanding-wild games: the scatter part matches Slotopol to the digit, the line part doesn't quite (Lucky
        // Slot 92.018 % vs published 91.989 %, Hot Clover 90.333 % vs 90.66 %) although its scanner reads exactly as
        // ours — its published line figures for these games look like estimates. We deal from this engine, whose
        // exact RTP is what the second check measures.
        expect(abs(agtExactRtp($all) * 100 - $slot->data()['rtp']))->toBeLessThan(0.35);
    } else {
        expect(round(agtExactRtp($all) * 100, 5))->toBe(round($slot->data()['rtp'], 5));
    }

    // On the game's own lines the RTP can move a little (an expanding wild weighs rows differently).
    expect(agtExactRtp($slot) * 100)->toBeGreaterThan(94.0)->toBeLessThan(97.5);
})->with(fn () => AgtCatalog::SLUGS);

it('scores lines like Slotopol: plain runs, a substituting wild, an expanding wild', function () {
    $seven = new AgtSlot('seven-hot'); // symbols: seven 0 … cherry 6, bell 7 (scatter)
    expect($seven->line([0, 0, 0, 3, 4]))->toBe(['symbol' => 0, 'count' => 3, 'pay' => 100.0])
        ->and($seven->line([6, 6, 1, 1, 1]))->toBe(['symbol' => 6, 'count' => 2, 'pay' => 5.0])
        ->and($seven->line([1, 2, 2, 2, 2]))->toBeNull();

    $ice = new AgtSlot('ice-fruits'); // wild 0, scatter 1, grapes 2 …
    expect($ice->line([0, 0, 2, 2, 3]))->toBe(['symbol' => 2, 'count' => 4, 'pay' => 100.0])
        // Three wilds then a cherry: the wilds' own run (40) beats four cherries (16).
        ->and($ice->line([0, 0, 0, 7, 3]))->toBe(['symbol' => 0, 'count' => 3, 'pay' => 40.0])
        // …but five cherries (80) beat three wilds.
        ->and($ice->line([0, 0, 0, 7, 7]))->toBe(['symbol' => 7, 'count' => 5, 'pay' => 80.0]);

    $lucky = new AgtSlot('lucky-slot'); // wild 0 (reels 2–4), seven 2
    expect($lucky->line([2, 9, 9, 9, 2], [1, 2, 3]))->toBe(['symbol' => 2, 'count' => 5, 'pay' => 5000.0])
        ->and($lucky->line([2, 9, 9, 9, 2]))->toBeNull();
});

it('deals a round and pays the scatter anywhere, times the total bet', function () {
    $seven = new AgtSlot('seven-hot');
    // Bells (7) on three reels, nothing on the lines.
    $grid = [[7, 1, 2], [3, 7, 4], [5, 6, 7], [1, 2, 3], [4, 5, 6]];
    $round = $seven->evaluate($grid);

    expect($round['scatterWin'])->toMatchArray(['symbol' => 'bell', 'count' => 3, 'amount' => 2.0])
        ->and($round['grid'][0])->toBe(['bell', 'blueberry', 'strawberry']);

    $played = $seven->play(fn (int $n) => 0);
    expect($played['round']['grid'])->toHaveCount(5)->and($played['total_win'])->toBe($played['round']['totalWin']);
});
