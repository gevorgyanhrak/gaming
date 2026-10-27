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
            'bot_difficulty' => ['exclude_unless:type,bot', 'required', Rule::enum(BotDifficulty::class)],
            // Tables for more than two (poker): bots to play against, or seats in a private room.
            'opponents' => ['exclude_unless:type,bot', 'nullable', 'integer', 'min:1', 'max:9'],
            'seats' => ['exclude_unless:type,private', 'nullable', 'integer', 'min:2', 'max:10'],
        ];
    }
}
