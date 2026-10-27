<?php

it('returns the public platform configuration', function () {
    config([
        'app.name' => 'Test Platform',
        'app.fallback_locale' => 'en',
        'platform.supported_locales' => ['en', 'ru', 'hy'],
        'platform.registration.enabled' => true,
        'platform.registration.require_email_verification' => true,
        'platform.registration.require_phone_verification' => false,
    ]);

    $this->getJson('/api/v1/config')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'name' => 'Test Platform',
                'logo_url' => null,
                'favicon_url' => null,
                'default_locale' => 'en',
                'supported_locales' => ['en', 'ru', 'hy'],
                'maintenance' => ['enabled' => false, 'message' => null],
                'registration' => [
                    'enabled' => true,
                    'methods' => ['email', 'phone'],
                    'require_email_verification' => true,
                    'require_phone_verification' => false,
                    'requires_terms' => false,
                ],
                'pages' => [],
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
