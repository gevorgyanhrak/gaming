<?php

use App\Enums\BotDifficulty;
use App\Enums\BotPersonality;
use App\Gameplay\Engine\Action;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Random\SecureRandom;
use App\Games\Blackjack\Bot\BlackjackBot;
use App\Games\Blackjack\Engine\BlackjackEngine;
use App\Games\Poker\Bot\PokerBot;

it('gives each AI player a stable personality by its name, and all four exist', function () {
    expect(BotPersonality::for('Aram'))->toBe(BotPersonality::for('Aram'));
    $seen = collect(range(1, 200))->map(fn (int $i) => BotPersonality::for("bot{$i}"))->unique();
    expect($seen)->toHaveCount(4);
});

it('paces a cautious player slower and a bold one faster', function () {
    expect(BotPersonality::Cautious->tempo())->toBeGreaterThan(BotPersonality::Steady->tempo())
        ->and(BotPersonality::Bold->tempo())->toBeLessThan(BotPersonality::Steady->tempo())
        ->and(BotPersonality::Tricky->jitter())->toBeGreaterThan(BotPersonality::Steady->jitter());
});

it('bets blackjack in its style: cautious the minimum, bold two to four times it', function () {
    $engine = new BlackjackEngine;
    $state = $engine->initialState(new GameSetup(1, ['starting_chips' => 1000, 'table_min_bet' => 10, 'table_max_bet' => 500]), new SecureRandom);
    $view = $engine->viewFor($state, 0);
    $legal = $engine->legalActions($state, 0);
    $bet = fn (BotPersonality $p) => (new BlackjackBot)->inStyle($p)->chooseAction($view, $legal, BotDifficulty::Hard, new SecureRandom)->payload['amount'];

    foreach (range(1, 30) as $_) {
        expect($bet(BotPersonality::Cautious))->toBe(10)
            ->and($bet(BotPersonality::Bold))->toBeGreaterThanOrEqual(20)->toBeLessThanOrEqual(40);
    }
});

it('bluffs poker more when tricky than when cautious, with the same weak hand', function () {
    // Heads-up on the flop, nothing owed, 7-2 off on a dry board: only a bluff raises.
    $view = [
        'to_act' => 1, 'hand_cards' => ['7C', '2D'], 'board' => ['KS', '9H', '4S'], 'folded' => [0 => false, 1 => false],
        'current_bet' => 0, 'bets' => [0 => 0, 1 => 0], 'pot' => 40, 'min_raise' => 10, 'street' => 'flop',
    ];
    $legal = [new Action('check'), new Action('raise', ['min' => 10, 'max' => 990]), new Action('fold')];
    $raises = function (BotPersonality $p) use ($view, $legal): int {
        $bot = (new PokerBot)->inStyle($p);

        return collect(range(1, 150))->filter(fn () => $bot->chooseAction($view, $legal, BotDifficulty::Hard, new SecureRandom)->type === 'raise')->count();
    };

    expect($raises(BotPersonality::Tricky))->toBeGreaterThan($raises(BotPersonality::Cautious));
});
