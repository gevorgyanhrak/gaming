<?php

namespace App\Platform\Settings;

use Illuminate\Validation\Rule;
use InvalidArgumentException;
use libphonenumber\PhoneNumberUtil;

/**
 * Every platform setting administrators can change. Defaults come from configuration (and so
 * from the environment), which keeps tests and fresh installs working without any stored value.
 *
 * Secrets (API keys, provider credentials) are deliberately not settings: they live in the
 * environment or a secret manager, never in a database row editable from the admin panel.
 *
 * Adding a setting: add a definition here, a form field in App\Filament\Pages\ManageSettings and,
 * if the SPA needs it, mark it public and expose it in ConfigController.
 */
final class SettingsCatalog
{
    /** Languages the SPA ships translations for; supported locales must be a subset. */
    public const AVAILABLE_LOCALES = ['en', 'ru', 'hy'];

    /**
     * @return array<string, SettingDefinition>
     */
    public static function all(): array
    {
        $locale = ['string', Rule::in(self::AVAILABLE_LOCALES)];

        $definitions = [
            // General
            new SettingDefinition('general.platform_name', SettingType::String, config('app.name'), public: true, rules: ['required', 'string', 'max:60']),
            new SettingDefinition('general.logo', SettingType::File, null, public: true, rules: ['nullable', 'string', 'max:255']),
            new SettingDefinition('general.favicon', SettingType::File, null, public: true, rules: ['nullable', 'string', 'max:255']),
            new SettingDefinition('general.default_locale', SettingType::String, config('app.fallback_locale'), public: true, rules: ['required', ...$locale]),
            new SettingDefinition('general.supported_locales', SettingType::StringList, config('platform.supported_locales'), public: true, rules: ['required', 'array', 'min:1'], elementRules: $locale),

            // Access
            new SettingDefinition('access.maintenance_mode', SettingType::Boolean, false, public: true, rules: ['boolean']),
            new SettingDefinition('access.maintenance_message', SettingType::String, null, public: true, rules: ['nullable', 'string', 'max:300']),
            new SettingDefinition('access.registration_enabled', SettingType::Boolean, config('platform.registration.enabled'), public: true, rules: ['boolean']),
            new SettingDefinition('access.require_email_verification', SettingType::Boolean, config('platform.registration.require_email_verification'), public: true, rules: ['boolean']),
            new SettingDefinition('access.require_phone_verification', SettingType::Boolean, config('platform.registration.require_phone_verification'), public: true, rules: ['boolean']),
            new SettingDefinition('access.phone_allowed_countries', SettingType::StringList, config('platform.phone.allowed_countries'), rules: ['array'], elementRules: ['string', Rule::in(PhoneNumberUtil::getInstance()->getSupportedRegions())]),

            // Matchmaking (read by the gameplay infrastructure from Phase 5)
            new SettingDefinition('matchmaking.default_bot_timeout_seconds', SettingType::Integer, 30, rules: ['required', 'integer', 'min:5', 'max:600']),
            new SettingDefinition('matchmaking.abandon_after_seconds', SettingType::Integer, 15, public: true, rules: ['required', 'integer', 'min:0', 'max:600']),
            new SettingDefinition('matchmaking.max_concurrent_games', SettingType::Integer, 1, rules: ['required', 'integer', 'min:1', 'max:10']),

            // Security
            new SettingDefinition('security.session_lifetime_minutes', SettingType::Integer, (int) config('session.lifetime'), rules: ['required', 'integer', 'min:15', 'max:43200']),
        ];

        return collect($definitions)->keyBy('key')->all();
    }

    public static function get(string $key): SettingDefinition
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Unknown platform setting [{$key}].");
    }
}
