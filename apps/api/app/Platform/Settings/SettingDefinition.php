<?php

namespace App\Platform\Settings;

final readonly class SettingDefinition
{
    /**
     * @param  mixed  $default  Used while no administrator has set a value.
     * @param  bool  $public  Exposed to the SPA through GET /api/v1/config.
     * @param  list<mixed>  $rules  Validation rules for a new value.
     * @param  list<mixed>  $elementRules  Rules for each element of a StringList value.
     */
    public function __construct(
        public string $key,
        public SettingType $type,
        public mixed $default,
        public bool $public = false,
        public array $rules = [],
        public array $elementRules = [],
    ) {}

    public function group(): string
    {
        return explode('.', $this->key, 2)[0];
    }
}
