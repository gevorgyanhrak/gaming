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
            new SettingDefinition('reactions.enabled', SettingType::Boolean, true, public: true, rules: ['boolean']),

            // Fishkas (free play currency; never bought or cashed out)
            new SettingDefinition('fishka.registration_bonus', SettingType::Integer, 5000, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            new SettingDefinition('fishka.daily_bonus', SettingType::Integer, 1000, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            // The daily bonus grows with a streak of days in a row (day 1 is fishka.daily_bonus).
            new SettingDefinition('fishka.daily_bonus_day_2', SettingType::Integer, 1500, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            new SettingDefinition('fishka.daily_bonus_day_3', SettingType::Integer, 2000, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            new SettingDefinition('fishka.daily_bonus_day_4', SettingType::Integer, 2500, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            new SettingDefinition('fishka.daily_bonus_day_5', SettingType::Integer, 5000, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            new SettingDefinition('fishka.entry_fee', SettingType::Integer, 500, public: true, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            new SettingDefinition('fishka.refill_bonus', SettingType::Integer, 5000, public: true, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            new SettingDefinition('fishka.refill_cooldown_hours', SettingType::Integer, 24, public: true, rules: ['required', 'integer', 'min:0', 'max:720']),
            new SettingDefinition('fishka.timezone', SettingType::String, 'Asia/Yerevan', rules: ['required', 'timezone:all']),

            // Levels and XP (ADR 0018): what earns XP, the curve, and the reward for levels without a row.
            new SettingDefinition('levels.xp_game', SettingType::Integer, 30, rules: ['required', 'integer', 'min:0', 'max:100000']),
            new SettingDefinition('levels.xp_win', SettingType::Integer, 20, rules: ['required', 'integer', 'min:0', 'max:100000']),
            new SettingDefinition('levels.xp_draw', SettingType::Integer, 10, rules: ['required', 'integer', 'min:0', 'max:100000']),
            new SettingDefinition('levels.xp_daily', SettingType::Integer, 20, rules: ['required', 'integer', 'min:0', 'max:100000']),
            new SettingDefinition('levels.min_game_seconds', SettingType::Integer, 30, rules: ['required', 'integer', 'min:0', 'max:3600']),
            new SettingDefinition('levels.xp_base', SettingType::Integer, 80, rules: ['required', 'integer', 'min:1', 'max:1000000']),
            new SettingDefinition('levels.xp_step', SettingType::Integer, 20, rules: ['required', 'integer', 'min:0', 'max:1000000']),
            new SettingDefinition('levels.after_30_percent', SettingType::Integer, 150, rules: ['required', 'integer', 'min:100', 'max:1000']),
            new SettingDefinition('levels.growth_percent', SettingType::Integer, 10, rules: ['required', 'integer', 'min:1', 'max:100']),
            new SettingDefinition('levels.max_level', SettingType::Integer, 100, rules: ['required', 'integer', 'min:30', 'max:500']),
            new SettingDefinition('levels.reward_default', SettingType::Integer, 3000, rules: ['required', 'integer', 'min:0', 'max:1000000']),

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
