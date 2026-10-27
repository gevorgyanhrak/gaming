<?php

namespace App\Http\Requests\Me;

use App\Platform\Settings\Settings;
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
            'locale' => ['sometimes', 'nullable', 'string', Rule::in(app(Settings::class)->get('general.supported_locales'))],
            'preferences' => ['sometimes', 'array:sound'],
            'preferences.sound' => ['sometimes', 'boolean'],
        ];
    }
}
