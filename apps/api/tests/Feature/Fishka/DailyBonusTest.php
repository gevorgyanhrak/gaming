<?php

use App\Enums\FishkaTransactionType as Type;
use App\Models\FishkaTransaction;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Settings\Settings;

beforeEach(function () {
    $this->travelTo(now()->setTimezone('Asia/Yerevan')->setTime(12, 0));
    $this->user = User::factory()->create();
    app(FishkaWallets::class)->open($this->user);
});

function collectDaily(): array
{
    return test()->actingAs(test()->user)->postJson('/api/v1/me/daily-bonus')->json('data');
}

it('raises the bonus with each day in a row, doubles on day 5 and keeps day 5', function () {
    $seen = [];
    foreach (range(1, 7) as $day) {
        $this->travel(1)->days();
        $first = collectDaily();
        $again = collectDaily();    // the same day: nothing more
        $seen[] = [$first['day'], $first['collected'], $again['collected']];
    }

    expect($seen)->toBe([[1, 1000, 0], [2, 1500, 0], [3, 2000, 0], [4, 2500, 0], [5, 5000, 0], [5, 5000, 0], [5, 5000, 0]])
        ->and(FishkaTransaction::query()->where('user_id', $this->user->id)->where('type', Type::DailyLoginBonus)->orderBy('id')->pluck('amount')->all())
        ->toBe([1000, 1500, 2000, 2500, 5000, 5000, 5000]);
});

it('starts again at day 1 after a missed day', function () {
    $this->travel(1)->days();
    collectDaily();
    $this->travel(1)->days();
    expect(collectDaily())->toMatchArray(['day' => 2, 'collected' => 1500]);

    $this->travel(2)->days();    // a day missed
    $this->getJson('/api/v1/me/daily-bonus')->assertJsonPath('data.day', 1)->assertJsonPath('data.claimed', false)->assertJsonPath('data.amount', 1000);
    expect(collectDaily())->toMatchArray(['day' => 1, 'collected' => 1000]);
});

it('shows today’s day, every day’s amount and whether it was collected', function () {
    $this->travel(1)->days();

    $this->actingAs($this->user)->getJson('/api/v1/me/daily-bonus')->assertOk()->assertExactJson(['data' => [
        'day' => 1, 'claimed' => false, 'amount' => 1000,
        'days' => [['day' => 1, 'amount' => 1000], ['day' => 2, 'amount' => 1500], ['day' => 3, 'amount' => 2000], ['day' => 4, 'amount' => 2500], ['day' => 5, 'amount' => 5000]],
    ]]);
    collectDaily();
    $this->getJson('/api/v1/me/daily-bonus')->assertJsonPath('data.claimed', true)->assertJsonPath('data.day', 1);
});

it('uses the amounts the administrator set', function () {
    app(Settings::class)->update(['fishka.daily_bonus' => 200, 'fishka.daily_bonus_day_2' => 300]);
    $this->travel(1)->days();
    expect(collectDaily()['collected'])->toBe(200);
    $this->travel(1)->days();
    expect(collectDaily()['collected'])->toBe(300);
});

it('gives the bonus once even when collected twice at the same moment', function () {
    $this->travel(1)->days();
    $wallets = app(FishkaWallets::class);

    $a = $wallets->claimDaily($this->user);
    $b = $wallets->claimDaily($this->user);

    expect($a?->amount)->toBe(1000)->and($b)->toBeNull()
        ->and(FishkaTransaction::query()->where('user_id', $this->user->id)->where('type', Type::DailyLoginBonus)->count())->toBe(1);
});

it('is only for the signed-in player', function () {
    auth()->logout();
    $this->postJson('/api/v1/me/daily-bonus')->assertUnauthorized();
    $this->getJson('/api/v1/me/daily-bonus')->assertUnauthorized();
});
