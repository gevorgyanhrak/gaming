<?php

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * Initial modes per game. Idempotent and non-destructive: existing modes keep admin edits.
 * `config` holds the options each game's engine will read once it ships.
 */
class GameModeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->modes() as $slug => $modes) {
            $game = Game::query()->where('slug', $slug)->first();

            foreach ($modes as $index => $mode) {
                $game?->modes()->firstOrCreate(
                    ['slug' => $mode['slug']],
                    [...$mode, 'sort_order' => ($index + 1) * 10],
                );
            }
        }
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function modes(): array
    {
        return [
            'nardi' => [[
                'slug' => 'classic',
                'name' => ['en' => 'Classic', 'ru' => 'Классика', 'hy' => 'Դասական'],
                'description' => ['en' => 'Short nardi, a single game.', 'ru' => 'Короткие нарды, одна партия.', 'hy' => 'Կարճ նարդի, մեկ խաղ։'],
                'min_players' => 2, 'max_players' => 2,
                'config' => ['variant' => 'short', 'points_to_win' => 1, 'turn_seconds' => 60],
            ]],
            'chess' => [
                [
                    'slug' => 'blitz',
                    'name' => ['en' => 'Blitz 5+3', 'ru' => 'Блиц 5+3', 'hy' => 'Բլից 5+3'],
                    'description' => ['en' => '5 minutes per player, 3 seconds added per move.', 'ru' => '5 минут на игрока, +3 секунды за ход.', 'hy' => 'Յուրաքանչյուր խաղացողին 5 րոպե, յուրաքանչյուր քայլին +3 վայրկյան։'],
                    'min_players' => 2, 'max_players' => 2,
                    'config' => ['initial_seconds' => 300, 'increment_seconds' => 3],
                ],
                [
                    'slug' => 'rapid',
                    'name' => ['en' => 'Rapid 10+5', 'ru' => 'Рапид 10+5', 'hy' => 'Արագ 10+5'],
                    'description' => ['en' => '10 minutes per player, 5 seconds added per move.', 'ru' => '10 минут на игрока, +5 секунд за ход.', 'hy' => 'Յուրաքանչյուր խաղացողին 10 րոպե, յուրաքանչյուր քայլին +5 վայրկյան։'],
                    'min_players' => 2, 'max_players' => 2,
                    'config' => ['initial_seconds' => 600, 'increment_seconds' => 5],
                ],
            ],
            'checkers' => [[
                'slug' => 'classic',
                'name' => ['en' => 'Russian draughts', 'ru' => 'Русские шашки', 'hy' => 'Ռուսական շաշկի'],
                'description' => null,
                'min_players' => 2, 'max_players' => 2,
                'config' => ['variant' => 'russian', 'turn_seconds' => 60],
            ]],
            'durak' => [[
                'slug' => 'podkidnoy',
                'name' => ['en' => 'Podkidnoy, 2 players', 'ru' => 'Подкидной, 2 игрока', 'hy' => 'Պոդկիդնոյ, 2 խաղացող'],
                'description' => null,
                'min_players' => 2, 'max_players' => 2,
                'config' => ['variant' => 'podkidnoy', 'deck_size' => 36, 'turn_seconds' => 45],
            ]],
            'poker' => [[
                'slug' => 'holdem',
                'name' => ['en' => "No-Limit Hold'em", 'ru' => 'Безлимитный холдем', 'hy' => 'Անսահմանափակ հոլդեմ'],
                'description' => ['en' => 'Up to 6 players. Play chips only.', 'ru' => 'До 6 игроков. Только игровые фишки.', 'hy' => 'Մինչև 6 խաղացող։ Միայն խաղային ժետոններ։'],
                'min_players' => 2, 'max_players' => 6,
                'config' => ['starting_chips' => 1000, 'small_blind' => 5, 'big_blind' => 10, 'turn_seconds' => 30],
            ]],
        ];
    }
}
