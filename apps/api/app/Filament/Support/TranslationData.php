<?php

namespace App\Filament\Support;

use App\Platform\Settings\SettingsCatalog;
use Illuminate\Database\Eloquent\Model;

/**
 * Converts form data for spatie/laravel-translatable attributes: all languages on fill, and empty
 * translations dropped on save so missing languages fall back to English.
 */
final class TranslationData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fill(Model $record, array $data): array
    {
        if (! method_exists($record, 'getTranslatableAttributes') || ! method_exists($record, 'getTranslations')) {
            return $data;
        }

        foreach ($record->getTranslatableAttributes() as $attribute) {
            $data[$attribute] = $record->getTranslations($attribute);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && array_intersect(array_keys($value), SettingsCatalog::AVAILABLE_LOCALES) !== []) {
                $data[$key] = array_filter($value, fn ($text) => filled($text));
            }
        }

        return $data;
    }
}
