<?php

namespace App\Http\Requests\Slots;

use Illuminate\Foundation\Http\FormRequest;

/** A house slot spin: the bet in Fishkas and the client's one-time key for this spin (a retry sends it again). */
class HouseSpinRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bet' => ['required', 'integer', 'min:1', 'max:1000000'],
            'key' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }
}
