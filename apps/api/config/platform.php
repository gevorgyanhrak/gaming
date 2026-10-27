<?php

/*
| Platform behaviour that administrators will manage from the admin panel in
| Phase 4 (platform_settings). Until then, values come from the environment.
*/

return [

    /*
    | Locales the API can respond in. The request locale is negotiated from the
    | Accept-Language header; anything unsupported falls back to app.fallback_locale.
    */
    'supported_locales' => array_values(array_filter(
        explode(',', (string) env('APP_SUPPORTED_LOCALES', 'en')),
    )),

    'registration' => [
        'enabled' => (bool) env('REGISTRATION_ENABLED', true),
        // Whether a contact must be verified before playing (enforced on gameplay routes from Phase 5).
        'require_email_verification' => (bool) env('REQUIRE_EMAIL_VERIFICATION', true),
        'require_phone_verification' => (bool) env('REQUIRE_PHONE_VERIFICATION', true),
        // Lower-cased names nobody may register.
        'reserved_usernames' => [
            'admin', 'administrator', 'root', 'system', 'support', 'help', 'moderator', 'mod',
            'staff', 'official', 'bot', 'null', 'undefined', 'api', 'me', 'settings',
        ],
    ],

    'phone' => [
        // ISO 3166-1 alpha-2 codes allowed for phone registration (SMS cost and fraud control).
        // Empty means any country. Example: "AM,GE,RU".
        'allowed_countries' => array_values(array_filter(
            explode(',', strtoupper((string) env('PHONE_ALLOWED_COUNTRIES', ''))),
        )),
    ],

    'verification' => [
        'code_ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
        'max_sends_per_hour' => 5,
    ],

    'avatars' => [
        'size' => 256,
        'max_upload_kb' => 2048,
    ],

    'admin' => [
        // Serve the admin panel on its own host (e.g. admin.example.com). Null: same host as the API.
        'domain' => env('ADMIN_DOMAIN') ?: null,
        // Require two-factor authentication (authenticator app) for admin sign-in.
        'mfa_required' => (bool) env('ADMIN_MFA_REQUIRED', true),
    ],

    // last_active_at is written at most once per interval per user.
    'last_active_interval_minutes' => 5,

];
