<?php

namespace App\Http\Requests\Slots;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An engine slot spin (ADR 0026): the bet per line in Fishkas, the lines (for games that let you choose), and the
 * client's one-time key for this spin (a retry sends it again). In free games both are ignored: the game keeps its own.
 */
class EngineSpinRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bet' => ['required', 'integer', 'min:1', 'max:100000'],
            'lines' => ['sometimes', 'integer', 'min:1', 'max:1024'],
            'key' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }
}
