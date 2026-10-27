<?php

return [

    // Cache store holding live room state and room locks. Tests use "array".
    'state_store' => env('GAME_STATE_STORE', 'game'),

    // Queue for time-critical game jobs (bot turns, turn timeouts).
    'queue' => env('GAME_QUEUE', 'game'),

    // Seconds a seat has to act when the game mode doesn't set "turn_seconds".
    'default_turn_seconds' => 60,

    // Bots wait a little before moving so games feel natural (milliseconds).
    'bot_delay_ms' => ['min' => 700, 'max' => 1800],

];
