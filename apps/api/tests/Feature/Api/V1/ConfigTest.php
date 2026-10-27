<?php

it('returns the public platform configuration', function () {
    config([
        'app.name' => 'Test Platform',
        'app.fallback_locale' => 'en',
        'platform.supported_locales' => ['en', 'ru', 'hy'],
    ]);

    $this->getJson('/api/v1/config')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'name' => 'Test Platform',
                'default_locale' => 'en',
                'supported_locales' => ['en', 'ru', 'hy'],
            ],
        ]);
});

it('allows credentialed requests from the SPA origin only', function () {
    config(['cors.allowed_origins' => ['http://localhost:5175']]);

    $this->getJson('/api/v1/config', ['Origin' => 'http://localhost:5175'])
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5175')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');

    // With a single allowed origin the header is static; what matters is that a foreign
    // origin is never echoed back, so browsers refuse to expose the response to it.
    $foreign = $this->getJson('/api/v1/config', ['Origin' => 'https://evil.example']);

    expect($foreign->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example');
});
