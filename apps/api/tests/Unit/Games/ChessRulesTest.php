<?php

use App\Games\Chess\Engine\ChessRules;

function chessPerft(array $position, int $depth): int
{
    $moves = ChessRules::legalMoves($position);
    if ($depth === 1) {
        return count($moves);
    }

    return array_sum(array_map(fn (array $move) => chessPerft(ChessRules::play($position, $move), $depth - 1), $moves));
}

// Reference counts from https://www.chessprogramming.org/Perft_Results
it('generates exactly the known number of positions', function (string $fen, array $counts) {
    $position = ChessRules::fromFen($fen);

    foreach ($counts as $depth => $count) {
        expect(chessPerft($position, $depth + 1))->toBe($count);
    }
})->with([
    'start' => [ChessRules::START, [20, 400, 8902, 197281]],
    'kiwipete' => ['r3k2r/p1ppqpb1/bn2pnp1/3PN3/1p2P3/2N2Q1p/PPPBBPPP/R3K2R w KQkq - 0 1', [48, 2039, 97862]],
    'position 3' => ['8/2p5/3p4/KP5r/1R3p1k/8/4P1P1/8 w - - 0 1', [14, 191, 2812, 43238]],
    'position 4' => ['r3k2r/Pppp1ppp/1b3nbN/nP6/BBP1P3/q4N2/Pp1P2PP/R2Q1RK1 w kq - 0 1', [6, 264, 9467]],
    'position 5' => ['rnbq1k1r/pp1Pbppp/2p5/8/2B5/8/PPP1NnPP/RNBQK2R w KQ - 1 8', [44, 1486, 62379]],
]);
