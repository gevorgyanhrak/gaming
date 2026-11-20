<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentOrderRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'package' => ['required', 'string', Rule::exists('chip_packages', 'slug')->where('is_enabled', true)],
            // The client's one-time key: the same key again returns the same order (a double tap, a retry).
            'key' => ['required', 'uuid'],
        ];
    }
}
