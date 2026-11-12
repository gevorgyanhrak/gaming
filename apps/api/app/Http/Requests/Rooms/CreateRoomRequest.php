<?php

namespace App\Http\Requests\Rooms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRoomRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'game_mode_id' => ['required', 'integer', Rule::exists('game_modes', 'id')],
            // Matchmaking rooms are created by the matchmaker, not by players.
            'type' => ['required', Rule::in(['bot', 'private'])],
            // AI opponents (always at their highest level; 0 alone against a dealer), or seats in a private room.
            'opponents' => ['exclude_unless:type,bot', 'nullable', 'integer', 'min:0', 'max:9'],
            'seats' => ['exclude_unless:type,private', 'nullable', 'integer', 'min:2', 'max:10'],
            // The play group (default: the lowest) and, for stake games, the bet within its range.
            'group' => ['nullable', 'string', 'max:32'],
            'bet' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
