<?php

use App\Models\User;
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
