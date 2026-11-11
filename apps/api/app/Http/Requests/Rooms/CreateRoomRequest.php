<?php

namespace App\Http\Requests\Rooms;

use App\Enums\BotDifficulty;
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
            // Not needed alone at a table against the dealer (opponents: 0, modes for one player).
            'bot_difficulty' => ['exclude_unless:type,bot', 'required_unless:opponents,0', 'nullable', Rule::enum(BotDifficulty::class)],
            // Tables for more than two (poker): bots to play against, or seats in a private room.
            'opponents' => ['exclude_unless:type,bot', 'nullable', 'integer', 'min:0', 'max:9'],
            'seats' => ['exclude_unless:type,private', 'nullable', 'integer', 'min:2', 'max:10'],
            // The play group (default: the lowest) and, for stake games, the bet within its range.
            'group' => ['nullable', 'string', 'max:32'],
            'bet' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
