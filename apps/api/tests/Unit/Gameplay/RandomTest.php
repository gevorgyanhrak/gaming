<?php

use App\Gameplay\Random\RecordingRandom;
use App\Gameplay\Random\ReplayRandom;

it('records every draw and replays them in order', function () {
    $recording = new RecordingRandom;
    $first = [$recording->int(1, 6), $recording->int(1, 6), $recording->int(0, 51)];

    $replay = new ReplayRandom($recording->draws());

    expect([$replay->int(1, 6), $replay->int(1, 6), $replay->int(0, 51)])->toBe($first);
});

it('refuses to replay beyond the recording or outside the requested range', function () {
    expect(fn () => (new ReplayRandom([]))->int(1, 6))->toThrow(LogicException::class);
    expect(fn () => (new ReplayRandom([9]))->int(1, 6))->toThrow(LogicException::class);
});
