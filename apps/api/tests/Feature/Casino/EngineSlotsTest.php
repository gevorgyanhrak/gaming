<?php

use App\Enums\FishkaTransactionType as Type;
use App\Models\FishkaTransaction;
use App\Models\SlotEngineState;
use App\Models\SlotGame;
use App\Models\SlotSpin;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Slots\Engine\EngineSlots;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 * Engine slots (ADR 0026) against a fake engine: the engine decides screens and wins, our wallet moves Fishkas.
 */

function engineGame(array $over = []): array
{
    return [
        'id' => 'novomatic/bookofradeluxe', 'name' => 'Book of Ra Deluxe', 'provider' => 'Novomatic', 'year' => 2008,
        'width' => 5, 'height' => 3, 'symbols' => 10, 'lines' => 10, 'cost' => 10, 'rtp' => [92.1, 95.112], 'measured' => 95.3,
        'verified' => true, 'features' => ['pays_left', 'line_select', 'free_games_retrigger', 'wild_scatters'],
        'state' => ['grid' => [[1, 2, 3], [4, 5, 6], [7, 8, 9], [1, 2, 3], [4, 5, 6]], 'sel' => 10, 'bet' => 1],
        ...$over,
    ];
}

/** A spin answer from the engine. */
function engineSpin(array $over = []): array
{
    return [
        'state' => ['grid' => [[3, 3, 3], [3, 1, 2], [3, 4, 5], [6, 7, 8], [9, 1, 2]], 'sel' => 10, 'bet' => 2],
        'wins' => [], 'cost' => 20, 'win' => 0, 'jack' => 0, 'free' => false, 'next' => false, 'fsr' => 0, 'total' => 0,
        ...$over,
    ];
}

beforeEach(function () {
    config(['services.slot_engine.key' => str_repeat('k', 40), 'services.slot_engine.url' => 'http://engine.test']);
    $this->spins = [];
    $this->sent = [];
    $this->engineDown = false;
    Http::fake([
        'engine.test/v1/games' => fn () => Http::response(['data' => [engineGame(), engineGame([
            'id' => 'novomatic/powerstars', 'name' => 'Power Stars', 'measured' => 102912.3, 'verified' => false,
            'features' => ['pays_left'],
        ])]]),
        'engine.test/v1/spin' => function (Request $request) {
            if ($this->engineDown) {
                return Http::response(['error' => 'boom'], 500);
            }
            $this->sent[] = $request->data();

            return Http::response(array_shift($this->spins) ?? engineSpin());
        },
    ]);
    app(EngineSlots::class)->sync();
    $this->game = SlotGame::query()->where('provider_game_id', 'novomatic/bookofradeluxe')->firstOrFail();
    $this->user = User::factory()->create();
    app(FishkaWallets::class)->open($this->user); // 5,000 welcome Fishkas
});

function engineBalance(User $user): int
{
    return app(FishkaWallets::class)->wallet($user)->balance;
}

it('adds the engine\'s games with their maker, switching on only those whose play was verified', function () {
    expect($this->game->studio)->toBe('Novomatic')
        ->and($this->game->is_enabled)->toBeTrue()
        ->and($this->game->meta['lines'])->toBe(10)
        ->and($this->game->meta['measured'])->toBe(95.3)
        ->and(SlotGame::query()->where('provider_game_id', 'novomatic/powerstars')->value('is_enabled'))->toBeFalse();

    // An administrator's change survives the next sync.
    $this->game->update(['is_enabled' => false, 'max_bet' => 500]);
    app(EngineSlots::class)->sync();
    expect($this->game->fresh()->is_enabled)->toBeFalse()->and($this->game->fresh()->max_bet)->toBe(500);

    $this->game->update(['is_enabled' => true]);
    $this->getJson('/api/v1/slots')->assertOk()
        ->assertJsonFragment(['slug' => 'novomatic-bookofradeluxe', 'studio' => 'Novomatic', 'kind' => 'engine']);
});

it('opens a game with the bets, the lines and the screen where the player left it', function () {
    $this->actingAs($this->user)->getJson('/api/v1/slots/novomatic-bookofradeluxe/engine')->assertOk()
        ->assertJsonPath('data.studio', 'Novomatic')
        ->assertJsonPath('data.reels', 5)
        ->assertJsonPath('data.lines', ['min' => 1, 'max' => 10, 'fixed' => false, 'default' => 10])
        ->assertJsonPath('data.bets.0', 1)
        ->assertJsonPath('data.state.grid.0', [1, 2, 3])
        ->assertJsonPath('data.free', false)
        ->assertJsonPath('data.balance', 5000);
});

it('charges bet × lines, pays the engine\'s win and records the spin', function () {
    $this->spins = [engineSpin(['win' => 37.5, 'wins' => [['pay' => 37.5, 'sym' => 3, 'num' => 3, 'li' => 1]]])];

    $this->actingAs($this->user)
        ->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', ['bet' => 2, 'lines' => 10, 'key' => 'spin-0001'])
        ->assertOk()
        ->assertJsonPath('data.bet', 20)
        ->assertJsonPath('data.win', 37) // whole Fishkas, rounded down
        ->assertJsonPath('data.balance', 5017)
        ->assertJsonPath('data.screen.grid.0', [3, 3, 3]);

    expect($this->sent[0])->toMatchArray(['game' => 'novomatic/bookofradeluxe', 'bet' => 2, 'sel' => 10, 'rtp' => 95.0])
        ->and(FishkaTransaction::query()->where('type', Type::SlotBet)->sum('amount'))->toEqual(-20)
        ->and(FishkaTransaction::query()->where('type', Type::SlotWin)->sum('amount'))->toEqual(37)
        ->and(SlotSpin::query()->count())->toBe(1);
});

it('plays free games without charging, pays them into the paid spin\'s round, and keeps them across a reload', function () {
    $this->spins = [
        engineSpin(['next' => true, 'fsr' => 10, 'wins' => [['sym' => 10, 'num' => 3, 'fs' => 10]]]),
        engineSpin(['free' => true, 'cost' => 0, 'next' => true, 'fsr' => 9, 'win' => 200, 'total' => 200]),
    ];
    $spin = fn (string $key) => $this->actingAs($this->user)
        ->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', ['bet' => 2, 'lines' => 10, 'key' => $key]);

    $spin('spin-0001')->assertOk()->assertJsonPath('data.next', true)->assertJsonPath('data.free_left', 10);
    // A reload: the game opens in its free games.
    $this->actingAs($this->user)->getJson('/api/v1/slots/novomatic-bookofradeluxe/engine')->assertJsonPath('data.free', true);
    // In free games the bet sent is ignored: the game keeps its own.
    $spin('spin-0002')->assertOk()->assertJsonPath('data.bet', 0)->assertJsonPath('data.win', 200)->assertJsonPath('data.round', 'spin-0001');

    expect($this->sent[1])->not->toHaveKey('bet')->not->toHaveKey('sel')
        ->and($this->sent[1]['state'])->toEqual(engineSpin()['state'])
        ->and(engineBalance($this->user))->toBe(5000 - 20 + 200)
        ->and(SlotEngineState::query()->first()->round_key)->toBe('spin-0001');
});

it('repeats a spin key without charging or spinning again', function () {
    $this->spins = [engineSpin(['win' => 50])];
    $body = ['bet' => 2, 'lines' => 10, 'key' => 'spin-0001'];
    $this->actingAs($this->user)->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', $body)->assertOk();
    $this->actingAs($this->user)->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', $body)->assertOk()
        ->assertJsonPath('data.replay', true)->assertJsonPath('data.win', 50);

    expect(count($this->sent))->toBe(1)->and(engineBalance($this->user))->toBe(5030);
});

it('charges nothing when the engine fails or the player cannot pay', function () {
    $this->engineDown = true;
    $this->actingAs($this->user)
        ->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', ['bet' => 2, 'lines' => 10, 'key' => 'spin-0001'])
        ->assertStatus(422)->assertJsonPath('code', 'slot_game_off');
    expect(engineBalance($this->user))->toBe(5000)->and(SlotSpin::query()->count())->toBe(0);

    $this->engineDown = false;
    $this->spins = [engineSpin(['cost' => 5000 * 10])];
    $this->game->update(['max_bet' => 100000]);
    $this->actingAs($this->user)
        ->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', ['bet' => 500, 'lines' => 10, 'key' => 'spin-0002'])
        ->assertStatus(422)->assertJsonPath('code', 'insufficient_fishkas');
    expect(engineBalance($this->user))->toBe(5000)->and(SlotEngineState::query()->count())->toBe(0);
});

it('refuses bets off the ladder, lines out of the game\'s set, and games that are off', function () {
    $spin = fn (array $body) => $this->actingAs($this->user)
        ->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', [...$body, 'key' => 'spin-'.uniqid()]);
    $spin(['bet' => 7, 'lines' => 10])->assertStatus(422)->assertJsonPath('code', 'slot_bet_limits');
    $spin(['bet' => 2, 'lines' => 11])->assertStatus(422)->assertJsonPath('code', 'slot_bet_limits');
    $this->game->update(['is_enabled' => false]);
    $spin(['bet' => 2, 'lines' => 10])->assertStatus(422)->assertJsonPath('code', 'slot_game_off');

    expect($this->sent)->toBe([])->and(engineBalance($this->user))->toBe(5000);
});

it('sends no lines for a game whose lines are fixed', function () {
    $this->game->update(['meta' => [...$this->game->meta, 'features' => ['pays_left'], 'state' => ['sel' => 20, 'bet' => 1]]]);
    $this->actingAs($this->user)
        ->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', ['bet' => 1, 'lines' => 3, 'key' => 'spin-0001'])
        ->assertOk();
    expect($this->sent[0])->not->toHaveKey('sel')->toMatchArray(['bet' => 1]);
});

it('prices a ways game per spin at the game\'s own cost', function () {
    $this->game->update(['meta' => [...$this->game->meta, 'lines' => 0, 'ways' => 243, 'cost' => 25, 'features' => ['pays_left'], 'state' => ['sel' => 0, 'bet' => 1]]]);
    $this->spins = [engineSpin(['cost' => 50])];
    $this->actingAs($this->user)->getJson('/api/v1/slots/novomatic-bookofradeluxe/engine')
        ->assertJsonPath('data.unit', 25)->assertJsonPath('data.lines.fixed', true);
    $this->actingAs($this->user)
        ->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', ['bet' => 2, 'key' => 'spin-0001'])
        ->assertOk()->assertJsonPath('data.bet', 50);
    expect($this->sent[0])->not->toHaveKey('sel');
});

it('needs a signed-in player', function () {
    $this->postJson('/api/v1/slots/novomatic-bookofradeluxe/engine/spin', ['bet' => 2, 'key' => 'spin-0001'])->assertUnauthorized();
    $this->getJson('/api/v1/slots/novomatic-bookofradeluxe/engine')->assertUnauthorized();
});
