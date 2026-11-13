<?php

use App\Games\Blot\Bot\BidCalibration;

it('learns how often bids it was sure of were really made', function () {
    // 40 bids it gave 85%: only 30 made (75%).
    $notes = [...array_fill(0, 30, ['chance' => 0.85, 'made' => true]), ...array_fill(0, 10, ['chance' => 0.85, 'made' => false])];
    $table = BidCalibration::learn($notes);

    // (30 made + 10 × 0.85 of trust in its own estimate) / (40 + 10)
    expect($table->chance(0.85))->toBe((30 + 10 * 0.85) / 50)
        ->and($table->bands()[8])->toMatchArray(['n' => 40, 'made' => 30]);
});

it('keeps its own estimate where self-play saw nothing', function () {
    $table = BidCalibration::learn([['chance' => 0.95, 'made' => true]]);

    expect($table->chance(0.33))->toBe(0.33)->and($table->chance(1.0))->toBe((1 + 10 * 1.0) / 11);
});
