<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(config('platform.supported_locales'))],
            'preferences' => ['sometimes', 'array:sound'],
            'preferences.sound' => ['sometimes', 'boolean'],
        ];
    }
}
