<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\LevelUp;
use App\Models\User;
use App\Platform\Levels\Levels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    /**
     * The player's level and progress (ADR 0018): total XP, the XP where this level began and where the
     * next one begins (null at the top level), the frame and badge they show, the next level's reward,
     * and the level-ups whose rewards wait to be collected (reached while away, too).
     */
    public function show(Request $request, Levels $levels): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->status($user, $levels)]);
    }

    /**
     * Collect every pending level-up's reward: 201 with `collected` (the Fishkas added) and the levels
     * collected; a repeat collects nothing (200, `collected` 0).
     */
    public function claim(Request $request, Levels $levels): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $claimed = $levels->claim($user);

        $data = [
            ...$this->status($user->refresh(), $levels),
            /** @var int The Fishkas this request added (0 for a repeat). */
            'collected' => (int) $claimed->sum('fishkas'),
            /** @var list<int> The levels whose rewards this request collected. */
            'claimed' => $claimed->pluck('level')->values()->all(),
        ];

        return response()->json(['data' => $data], $claimed->isEmpty() ? 200 : 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function status(User $user, Levels $levels): array
    {
        $progress = $levels->progress($user);
        $next = $progress['next_level_xp'] === null ? null : ['level' => $user->level + 1, ...$levels->rewardFor($user->level + 1)];

        return [
            /** @var int */
            'level' => $progress['level'],
            /** @var int Total XP earned. */
            'xp' => $progress['xp'],
            /** @var int Total XP where the current level began. */
            'level_xp' => $progress['level_xp'],
            /** @var int|null Total XP where the next level begins; null at the top level. */
            'next_level_xp' => $progress['next_level_xp'],
            /** @var int */
            'max_level' => $progress['max_level'],
            ...$levels->looks($user->level),
            /** @var array{level: int, fishkas: int, items: list<array{kind: string, key: string}>, is_milestone: bool}|null */
            'next_reward' => $next,
            /** @var list<array{level: int, fishkas: int, items: list<array{kind: string, key: string}>, is_milestone: bool}> */
            'pending' => $levels->pending($user)->map(fn (LevelUp $up) => [
                'level' => $up->level,
                'fishkas' => $up->fishkas,
                'items' => array_map(fn (array $item) => ['kind' => $item['kind'], 'key' => $item['key']], $up->items ?? []),
                'is_milestone' => $up->is_milestone,
            ])->values()->all(),
        ];
    }
}
