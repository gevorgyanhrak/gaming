<?php

use Illuminate\Support\Facades\Redis;

it('responds to the liveness probe', function () {
    $this->get('/up')->assertOk();
});

it('reports ready when the database and redis are reachable', function () {
    $this->getJson('/api/health/ready')
        ->assertOk()
        ->assertJsonPath('checks.database', 'ok')
        ->assertJsonPath('checks.redis', 'ok')
        ->assertJsonStructure(['status', 'checks' => ['database', 'redis', 'queue', 'websocket']]);
});

it('reports not ready when redis is unreachable', function () {
    config(['database.redis.default.port' => 1]);
    Redis::purge('default');

    $this->getJson('/api/health/ready')
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.redis', 'fail')
        ->assertJsonPath('checks.database', 'ok');
});

it('does not expose failure details', function () {
    config(['database.redis.default.port' => 1]);
    Redis::purge('default');

    $body = $this->getJson('/api/health/ready')->json();

    expect($body['checks'])->each->toBeIn(['ok', 'fail']);
});
