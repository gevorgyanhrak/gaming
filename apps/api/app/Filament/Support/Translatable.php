<?php

namespace App\Filament\Support;

use App\Platform\Settings\SettingsCatalog;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Form fields for a spatie/laravel-translatable attribute: one tab per available language.
 * English is the fallback language, so it is required when the field is required.
 */
final class Translatable
{
    public static function input(string $name, string $label, bool $required = true, int $maxLength = 255): Tabs
    {
        return self::tabs($name, $label, fn (string $path) => TextInput::make($path)->maxLength($maxLength), $required);
    }

    public static function textarea(string $name, string $label, bool $required = true, int $rows = 4, ?string $helperText = null): Tabs
    {
        return self::tabs(
            $name,
            $label,
            fn (string $path) => Textarea::make($path)->rows($rows)->helperText($helperText),
            $required,
        );
    }

    /**
     * @param  callable(string): Field  $field
     */
    private static function tabs(string $name, string $label, callable $field, bool $required): Tabs
    {
        return Tabs::make($label)
            ->label($label)
            ->tabs(array_map(
                fn (string $locale) => Tab::make(strtoupper($locale))->schema([
                    $field("{$name}.{$locale}")
                        ->label("{$label} ({$locale})")
                        ->required($required && $locale === 'en'),
                ]),
                SettingsCatalog::AVAILABLE_LOCALES,
            ))
            ->columnSpanFull();
    }
}
