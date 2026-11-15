<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A win in the Big Wins feed: a person's (GameWins::present()) or an AI player's in a real game, marked
 * `is_ai` (GameWins::presentAi()).
 *
 * @property array{id: string, amount: int, won_at: string, celebrate: bool, is_ai: bool, player: array{username: string|null, name: string, avatar_url: string|null, level: int}, game: array{slug: string, name: string}} $resource
 */
class WinResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $win = $this->resource;

        return [
            /** @var string "p…" for a person's win, "a…" for an AI player's. */
            'id' => $win['id'],
            /** @var int Fishkas won: a person's net for that game from the ledger; an AI seat's pot share or chips gained. */
            'amount' => $win['amount'],
            /** @var string */
            'won_at' => $win['won_at'],
            /** @var bool At or above the celebration amount (admin setting wins.celebrate_at). */
            'celebrate' => $win['celebrate'],
            /** @var bool An AI player (shown marked AI; no profile). */
            'is_ai' => $win['is_ai'],
            /** @var array{username: string|null, name: string, avatar_url: string|null, level: int} */
            'player' => $win['player'],
            /** @var array{slug: string, name: string} */
            'game' => $win['game'],
        ];
    }
}
