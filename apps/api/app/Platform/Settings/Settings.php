<?php

namespace App\Platform\Settings;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Reads and updates platform settings (see SettingsCatalog).
 *
 * Stored overrides are cached until the next update.
 */
class Settings
{
    private const CACHE_KEY = 'platform_settings';

    /** @var array<string, string|null>|null */
    private ?array $stored = null;

    public function get(string $key): mixed
    {
        $definition = SettingsCatalog::get($key);
        $stored = $this->stored();

        return array_key_exists($key, $stored)
            ? $this->decode($definition, $stored[$key])
            : $definition->default;
    }

    /**
     * Current value of every setting, keyed by setting key.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return collect(SettingsCatalog::all())->map(fn (SettingDefinition $d) => $this->get($d->key))->all();
    }

    /** Public URL of a File setting, or null when none is set. */
    public function fileUrl(string $key): ?string
    {
        $path = $this->get($key);

        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Validate and store new values. Unchanged values are not written.
     *
     * @param  array<string, mixed>  $values  Keyed by setting key.
     * @return array<string, array{old: mixed, new: mixed}> What changed.
     *
     * @throws ValidationException
     */
    public function update(array $values, ?User $actor = null): array
    {
        $this->validate($values);

        $changes = [];
        foreach ($values as $key => $value) {
            $definition = SettingsCatalog::get($key);
            $value = $this->cast($definition, $value);
            $current = $this->get($key);

            if ($value !== $current) {
                $changes[$key] = ['old' => $current, 'new' => $value];

                $values[$key] = $value;
            } else {
                unset($values[$key]);
            }
        }

        if ($changes === []) {
            return [];
        }

        DB::transaction(function () use ($values, $actor) {
            foreach ($values as $key => $value) {
                PlatformSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => json_encode($value), 'updated_by' => $actor?->id],
                );
            }
        });

        $this->flush();

        return $changes;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->stored = null;
    }

    /**
     * @param  array<string, mixed>  $values
     *
     * @throws ValidationException
     */
    private function validate(array $values): void
    {
        $rules = [];
        foreach (array_keys($values) as $key) {
            $definition = SettingsCatalog::get($key);
            $rules[$key] = $definition->rules;
            if ($definition->elementRules !== []) {
                $rules["{$key}.*"] = $definition->elementRules;
            }
        }

        // Setting keys contain dots, which the validator reads as nesting: validate the nested form.
        $validator = Validator::make(Arr::undot($values), $rules);

        $validator->after(function ($validator) use ($values) {
            $default = $values['general.default_locale'] ?? $this->get('general.default_locale');
            $supported = $values['general.supported_locales'] ?? $this->get('general.supported_locales');

            if (is_array($supported) && ! in_array($default, $supported, true)) {
                $validator->errors()->add('general.default_locale', __('platform.settings.default_locale_supported'));
            }
        });

        $validator->validate();
    }

    private function cast(SettingDefinition $definition, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $definition->type === SettingType::StringList ? [] : ($definition->type === SettingType::Boolean ? false : null);
        }

        return match ($definition->type) {
            SettingType::Boolean => (bool) $value,
            SettingType::Integer => (int) $value,
            SettingType::String, SettingType::File => (string) $value,
            SettingType::StringList => array_values(array_map('strval', (array) $value)),
        };
    }

    private function decode(SettingDefinition $definition, ?string $raw): mixed
    {
        if ($raw === null) {
            return $definition->default;
        }

        return $this->cast($definition, json_decode($raw, true));
    }

    /**
     * @return array<string, string|null>
     */
    private function stored(): array
    {
        /** @var array<string, string|null> */
        return $this->stored ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => PlatformSetting::query()->pluck('value', 'key')->all(),
        );
    }
}
