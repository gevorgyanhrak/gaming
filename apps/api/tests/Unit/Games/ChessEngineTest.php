<?php

use App\Enums\BotDifficulty;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\Exceptions\IllegalAction;
use App\Gameplay\Random\SecureRandom;
use App\Games\Chess\Bot\ChessBot;
use App\Games\Chess\Engine\ChessEngine;
use App\Games\Chess\Engine\ChessRules;

function chessAt(string $fen): array
{
    return ChessEngine::stateFrom(ChessRules::fromFen($fen));
}

/** Play moves given as "e2e4" or "e7e8q". */
function chessPlay(array $state, string ...$moves): array
{
    foreach ($moves as $uci) {
        $move = ['from' => ChessRules::square(substr($uci, 0, 2)), 'to' => ChessRules::square(substr($uci, 2, 2))];
        if (strlen($uci) === 5) {
            $move['promotion'] = $uci[4];
        }
        $state = (new ChessEngine)->apply($state, $state['turn'], new Action('move', $move), new SecureRandom)->state;
    }

    return $state;
}

it('starts with white to move, twenty legal moves and a draw offer', function () {
    $engine = new ChessEngine;
    $state = $engine->initialState(new GameSetup(2), new SecureRandom);

    expect($engine->seatsToAct($state))->toBe([0])
        ->and(collect($engine->legalActions($state, 0))->where('type', 'move'))->toHaveCount(20)
        ->and($engine->legalActions($state, 1))->toBe([]);
});

it('records moves in algebraic notation and ends on checkmate', function () {
    $state = chessPlay((new ChessEngine)->initialState(new GameSetup(2), new SecureRandom), 'e2e4', 'e7e5', 'f1c4', 'b8c6', 'd1h5', 'g8f6', 'h5f7');

    expect($state['moves'])->toBe(['e4', 'e5', 'Bc4', 'Nc6', 'Qh5', 'Nf6', 'Qxf7#'])
        ->and($state['check'])->toBeTrue()
        ->and((new ChessEngine)->outcome($state))->toEqual(new Outcome([0], 'checkmate'))
        ->and((new ChessEngine)->seatsToAct($state))->toBe([]);
});

it('castles on both sides and updates castling rights', function () {
    $state = chessAt('r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1');
    $state = chessPlay($state, 'e1g1', 'e8c8');

    expect($state['board'][ChessRules::square('f1')])->toBe('R')
        ->and($state['board'][ChessRules::square('d8')])->toBe('r')
        ->and($state['moves'])->toBe(['O-O', 'O-O-O'])
        ->and($state['castling'])->toBe('');
});

it('does not castle out of, through or into check', function () {
    // The rook on f8 covers f1, so white can only castle queenside.
    $state = chessAt('4kr2/8/8/8/8/8/8/R3K2R w KQ - 0 1');
    $kingMoves = collect((new ChessEngine)->legalActions($state, 0))
        ->filter(fn (Action $a) => $a->type === 'move' && $a->payload['from'] === ChessRules::square('e1'))
        ->map(fn (Action $a) => ChessRules::name($a->payload['to']))
        ->sort()->values()->all();

    expect($kingMoves)->toBe(['c1', 'd1', 'd2', 'e2']);
});

it('captures en passant only right after the double step', function () {
    $state = chessAt('4k3/8/8/3pP3/8/8/8/4K3 w - d6 0 1');
    $state = chessPlay($state, 'e5d6');

    expect($state['moves'])->toBe(['exd6'])
        ->and($state['board'][ChessRules::square('d5')])->toBeNull()
        ->and($state['board'][ChessRules::square('d6')])->toBe('P');

    expect(fn () => chessPlay(chessAt('4k3/8/8/3pP3/8/8/8/4K3 w - - 0 1'), 'e5d6'))->toThrow(IllegalAction::class);
});

it('promotes only with a chosen piece', function () {
    $state = chessAt('8/4P3/8/8/8/8/k7/4K3 w - - 0 1');

    expect(fn () => chessPlay($state, 'e7e8'))->toThrow(IllegalAction::class)
        ->and(chessPlay($state, 'e7e8n')['board'][ChessRules::square('e8')])->toBe('N')
        ->and(chessPlay($state, 'e7e8q')['moves'])->toBe(['e8=Q']);
});

it('disambiguates moves in notation', function () {
    expect(chessPlay(chessAt('4k3/8/8/8/8/8/8/1N2KN2 w - - 0 1'), 'b1d2')['moves'])->toBe(['Nbd2'])
        ->and(chessPlay(chessAt('4k3/8/8/R7/8/8/8/R3K3 w - - 0 1'), 'a1a3')['moves'])->toBe(['R1a3']);
});

it('draws by stalemate, insufficient material and the fifty-move rule', function () {
    expect(chessPlay(chessAt('k7/8/2Q5/8/8/8/8/7K w - - 0 1'), 'c6b6')['result'])->toBe(['winners' => [], 'reason' => 'stalemate'])
        ->and(chessPlay(chessAt('4k3/8/8/8/8/8/3r4/3NK3 w - - 0 1'), 'e1d2')['result'])->toBe(['winners' => [], 'reason' => 'insufficient_material'])
        ->and(chessPlay(chessAt('4k3/8/8/8/8/8/8/R3K3 w - - 99 80'), 'e1f1')['result'])->toBe(['winners' => [], 'reason' => 'fifty_moves']);
});

it('draws on the third repetition of a position', function () {
    $state = (new ChessEngine)->initialState(new GameSetup(2), new SecureRandom);
    $state = chessPlay($state, 'g1f3', 'g8f6', 'f3g1', 'f6g8', 'g1f3', 'g8f6', 'f3g1');
    expect($state['result'])->toBeNull();

    expect(chessPlay($state, 'f6g8')['result'])->toBe(['winners' => [], 'reason' => 'repetition']);
});

it('rejects moves into check and moves out of turn', function () {
    $state = chessAt('4k3/8/8/8/8/8/4r3/4K3 w - - 0 1');

    expect(fn () => chessPlay($state, 'e1d1'))->not->toThrow(IllegalAction::class)
        ->and(fn () => chessPlay($state, 'e1f2'))->toThrow(IllegalAction::class)
        ->and(fn () => (new ChessEngine)->apply($state, 1, new Action('move', ['from' => 4, 'to' => 5]), new SecureRandom))->toThrow(IllegalAction::class);
});

it('draws a timeout when the opponent could never checkmate', function () {
    $engine = new ChessEngine;
    $state = chessAt('4k3/8/8/8/8/8/8/R3K3 w - - 0 1');

    expect($engine->timeoutOutcome($state, 0))->toEqual(new Outcome([], 'timeout_insufficient_material'))
        ->and($engine->timeoutOutcome($state, 1))->toEqual(new Outcome([0], 'timeout'));
});

it('keeps the rules intact through random games', function () {
    $engine = new ChessEngine;
    $random = new SecureRandom;

    foreach (range(1, 8) as $game) {
        $state = $engine->initialState(new GameSetup(2), $random);
        for ($i = 0; $i < 400 && $engine->outcome($state) === null; $i++) {
            $legal = $engine->legalActions($state, $state['turn']);
            expect($legal)->not->toBeEmpty();
            $state = $engine->apply($state, $state['turn'], $legal[$random->int(0, count($legal) - 1)], $random)->state;

            $counts = array_count_values(array_filter($state['board']));
            expect($counts['K'] ?? 0)->toBe(1)->and($counts['k'] ?? 0)->toBe(1)
                ->and(ChessRules::inCheck($state['board'], 1 - $state['turn']))->toBeFalse();
        }
    }
});

it('has bots play legal moves, and above easy find a mate in one', function (BotDifficulty $difficulty) {
    $engine = new ChessEngine;
    $bot = new ChessBot;
    $random = new SecureRandom;

    $start = $engine->initialState(new GameSetup(2), $random);
    $legal = $engine->legalActions($start, 0);
    $choice = $bot->chooseAction($engine->viewFor($start, 0), $legal, $difficulty, $random);
    expect(collect($legal)->contains(fn (Action $a) => $a->equals($choice)))->toBeTrue();

    if ($difficulty === BotDifficulty::Easy) {
        return;    // easy only looks at the material after its own move
    }
    $mateInOne = chessAt('6k1/5ppp/8/8/8/8/5PPP/R5K1 w - - 0 1');
    $choice = $bot->chooseAction($engine->viewFor($mateInOne, 0), $engine->legalActions($mateInOne, 0), $difficulty, $random);
    expect(ChessRules::san($mateInOne, $choice->payload))->toBe('Ra8#');
})->with([BotDifficulty::Easy, BotDifficulty::Medium, BotDifficulty::Hard]);

it('has the medium bot beat the easy one', function () {
    $engine = new ChessEngine;
    $bot = new ChessBot;
    $random = new SecureRandom;
    $wins = 0;

    foreach ([0, 1] as $mediumSeat) {
        $state = $engine->initialState(new GameSetup(2), $random);
        for ($i = 0; $i < 300 && $engine->outcome($state) === null; $i++) {
            $level = $state['turn'] === $mediumSeat ? BotDifficulty::Medium : BotDifficulty::Easy;
            $action = $bot->chooseAction($engine->viewFor($state, $state['turn']), $engine->legalActions($state, $state['turn']), $level, $random);
            $state = $engine->apply($state, $state['turn'], $action, $random)->state;
        }
        $wins += $engine->outcome($state)?->winners === [$mediumSeat] ? 1 : 0;
    }

    expect($wins)->toBeGreaterThanOrEqual(1);
});

it('lets a player offer a draw on their turn and the opponent accept or decline it', function () {
    $engine = new ChessEngine;
    $state = $engine->initialState(new GameSetup(2), new SecureRandom);
    $types = fn (array $state) => collect($engine->legalActions($state, $state['turn']))->map->type->unique()->values()->all();

    expect($types($state))->toBe(['move', 'offer_draw']);

    $state = $engine->apply($state, 0, new Action('offer_draw'), new SecureRandom)->state;
    // Still white's move, and no second offer.
    expect($state['turn'])->toBe(0)->and($types($state))->toBe(['move']);

    $state = chessPlay($state, 'e2e4');
    expect($types($state))->toBe(['move', 'accept_draw']);

    // Black declines by moving; white's offer is gone.
    $declined = chessPlay($state, 'e7e5');
    expect($declined['draw_offer'])->toBeNull()->and($types($declined))->toBe(['move', 'offer_draw']);

    $accepted = $engine->apply($state, 1, new Action('accept_draw'), new SecureRandom)->state;
    expect($engine->outcome($accepted))->toEqual(new Outcome([], 'agreement'));
});

it('has bots accept a draw only when they are clearly worse', function () {
    $engine = new ChessEngine;
    $bot = new ChessBot;
    // Black, a queen down, is offered a draw; and white, a queen up, in the mirror position.
    $worse = [...chessAt('4k3/8/8/8/8/8/8/3QK3 b - - 0 1'), 'draw_offer' => 0];
    $better = [...chessAt('3qk3/8/8/8/8/8/8/4K3 b - - 0 1'), 'draw_offer' => 0];

    $pick = fn (array $state) => $bot->chooseAction($engine->viewFor($state, 1), $engine->legalActions($state, 1), BotDifficulty::Medium, new SecureRandom)->type;

    expect($pick($worse))->toBe('accept_draw')->and($pick($better))->toBe('move');
});
