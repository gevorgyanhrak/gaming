<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One place on a leaderboard (Leaderboards::top()).
 *
 * @property array{rank: int, total: int, wins: int, player: array{username: string, name: string, avatar_url: string|null, level: int}, game: array{slug: string, name: string}|null} $resource
 */
class LeaderboardRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            /** @var int */
            'rank' => $row['rank'],
            /** @var int Fishkas won in the period (net results of games won). */
            'total' => $row['total'],
            /** @var int Games won in the period. */
            'wins' => $row['wins'],
            /** @var array{username: string, name: string, avatar_url: string|null, level: int} */
            'player' => $row['player'],
            /** @var array{slug: string, name: string}|null The board's game, or (overall) the one they won most in. */
            'game' => $row['game'],
        ];
    }
}
