<?php

use App\Models\User;
use App\Platform\Presence\DemoActivity;
use App\Platform\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;

/** Reverb, as far as the counter asks it: the "online" presence channel's unique users. */
function reverbSays(callable $info): void
{
    $pusher = new class($info)
    {
        public function __construct(private $info) {}

        public function getChannelInfo(string $channel, array $params): object
        {
            return ($this->info)($channel, $params);
        }
    };
    $connection = new class($pusher)
    {
        public function __construct(private object $pusher) {}

        public function getPusher(): object
        {
            return $this->pusher;
        }
    };
    Broadcast::shouldReceive('connection')->with('reverb')->andReturn($connection);
}

beforeEach(fn () => Cache::forget('online-players'));

it('counts the people connected now, from Reverb’s presence channel', function () {
    $asked = [];
    reverbSays(function (string $channel, array $params) use (&$asked) {
        $asked = [$channel, $params];

        return (object) ['occupied' => true, 'user_count' => 7];
    });

    $this->getJson('/api/v1/stats/online')->assertOk()->assertJsonPath('data.online', 7);
    expect($asked)->toBe(['presence-online', ['info' => 'user_count']]);
});

it('says nothing rather than a made-up number when Reverb can’t be asked', function () {
    reverbSays(fn () => throw new RuntimeException('connection refused'));
    // Recently active users don't stand in for connected ones.
    User::factory()->create(['last_active_at' => now()]);

    $this->getJson('/api/v1/stats/online')->assertOk()->assertJsonPath('data.online', null);
});

it('lets an active signed-in player join the online channel, with nothing but their id; not a banned one', function () {
    // Tests broadcast through the null driver, which approves everything: ask the channel's own rule.
    $rule = Broadcast::driver()->getChannels()->get('online');
    $active = User::factory()->create();
    $banned = User::factory()->banned()->create();

    expect($rule($active))->toBe(['id' => $active->id])
        ->and($rule($banned))->toBeFalse();
});

it('serves the live feeds under their own limit, apart from other public lookups', function () {
    reverbSays(fn () => (object) ['user_count' => 1]);
    foreach (range(1, 40) as $_) {
        $this->getJson('/api/v1/stats/online')->assertOk();
    }
    // 40 > the lookups limit (30): the feeds have their own.
    $this->getJson('/api/v1/wins')->assertOk();
});

it('returns the demo activity figure separately, never inside the real count', function () {
    reverbSays(fn () => (object) ['user_count' => 3]);
    $this->travelTo(CarbonImmutable::parse('2026-11-20 21:00', 'Asia/Yerevan'));

    $data = $this->getJson('/api/v1/stats/online')->assertOk()->json('data');

    expect($data['online'])->toBe(3)
        ->and($data['demo'])->toBe(DemoActivity::at(30000, now()));
});

it('moves the demo figure a little every minute, within ±14 % of its base, the same for everyone', function () {
    $start = CarbonImmutable::parse('2026-11-20 00:00', 'UTC');
    $previous = null;
    for ($m = 0; $m < 1440 * 3; $m += 1) {
        $value = DemoActivity::at(30000, $start->addMinutes($m));
        expect($value)->toBeGreaterThanOrEqual(25800)->toBeLessThanOrEqual(34200);
        if ($previous !== null) {
            expect(abs($value - $previous))->toBeLessThanOrEqual(80); // drifts, never jumps
        }
        $previous = $value;
    }
    expect(DemoActivity::at(30000, $start))->toBe(DemoActivity::at(30000, $start));
});

it('shows no demo figure when the admin turns it off', function () {
    reverbSays(fn () => (object) ['user_count' => 3]);
    app(Settings::class)->update(['online.demo_enabled' => false]);

    $this->getJson('/api/v1/stats/online')->assertOk()->assertJsonPath('data.demo', null);
});
