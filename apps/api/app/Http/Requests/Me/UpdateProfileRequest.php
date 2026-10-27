<?php

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use libphonenumber\PhoneNumberUtil;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(collect($this->only(['display_name', 'bio', 'country']))
            ->map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'nullable', 'string', 'max:40'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            // ISO 3166-1 alpha-2, from libphonenumber's region list.
            'country' => ['sometimes', 'nullable', 'string', Rule::in(PhoneNumberUtil::getInstance()->getSupportedRegions())],
        ];
    }
}
