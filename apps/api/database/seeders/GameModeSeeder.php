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
                    [...$mode, 'sort_order' => $mode['sort_order'] ?? ($index + 1) * 10],
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
            'nardi' => [
                [
                    'slug' => 'classic',
                    'name' => ['en' => 'Short nardi', 'ru' => 'Короткие нарды', 'hy' => 'Կարճ նարդի'],
                    'description' => ['en' => 'Short nardi, a single game.', 'ru' => 'Короткие нарды, одна партия.', 'hy' => 'Կարճ նարդի, մեկ խաղ։'],
                    'min_players' => 2, 'max_players' => 2,
                    'config' => ['variant' => 'short', 'points_to_win' => 1, 'turn_seconds' => 60],
                ],
                [
                    // Listed first: the variant most players here know.
                    'slug' => 'long',
                    'sort_order' => 5,
                    'name' => ['en' => 'Long nardi', 'ru' => 'Длинные нарды', 'hy' => 'Երկար նարդի'],
                    'description' => ['en' => 'Long nardi: all fifteen start on the head, no hitting, a single game.', 'ru' => 'Длинные нарды: все пятнадцать с головы, без боя, одна партия.', 'hy' => 'Երկար նարդի. բոլոր տասնհինգը գլխից, առանց հարվածի, մեկ խաղ։'],
                    'min_players' => 2, 'max_players' => 2,
                    'config' => ['variant' => 'long', 'points_to_win' => 1, 'turn_seconds' => 60],
                ],
            ],
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
                'name' => ['en' => 'Podkidnoy', 'ru' => 'Подкидной', 'hy' => 'Պոդկիդնոյ'],
                'description' => ['en' => '2 to 6 players.', 'ru' => 'От 2 до 6 игроков.', 'hy' => '2-ից 6 խաղացող։'],
                'min_players' => 2, 'max_players' => 6,
                'config' => ['variant' => 'podkidnoy', 'deck_size' => 36, 'turn_seconds' => 45],
            ]],
            'poker' => [[
                'slug' => 'holdem',
                'name' => ['en' => "No-Limit Hold'em", 'ru' => 'Безлимитный холдем', 'hy' => 'Անսահմանափակ հոլդեմ'],
                'description' => ['en' => 'Up to 6 players. Play chips only.', 'ru' => 'До 6 игроков. Только игровые фишки.', 'hy' => 'Մինչև 6 խաղացող։ Միայն խաղային ժետոններ։'],
                'min_players' => 2, 'max_players' => 6,
                'config' => ['starting_chips' => 1000, 'small_blind' => 5, 'big_blind' => 10, 'turn_seconds' => 30, 'table_wait_seconds' => 20],
            ]],
            'blackjack' => [[
                'slug' => 'classic',
                'name' => ['en' => 'Classic blackjack', 'ru' => 'Классический блэкджек', 'hy' => 'Դասական բլեքջեք'],
                'description' => ['en' => '1 to 5 players against the dealer, 10 rounds. Play chips only.', 'ru' => 'От 1 до 5 игроков против дилера, 10 раундов. Только игровые фишки.', 'hy' => '1-ից 5 խաղացող դիլերի դեմ, 10 ռաունդ։ Միայն խաղային ֆիշկաներ։'],
                'min_players' => 1, 'max_players' => 5,
                'config' => ['rounds' => 10, 'decks' => 6, 'dealer_hits_soft_17' => false, 'max_bet_multiple' => 100, 'turn_seconds' => 30, 'table_wait_seconds' => 15, 'round_pause_ms' => 3500],
            ]],
            'blot' => [[
                'slug' => 'bazar',
                'name' => ['en' => 'Bazar blot', 'ru' => 'Базар-блот', 'hy' => 'Բազար բլոտ'],
                'description' => ['en' => 'Four players in two teams, to 301.', 'ru' => 'Четыре игрока, две команды, до 301.', 'hy' => 'Չորս խաղացող, երկու թիմ, մինչև 301։'],
                'min_players' => 4, 'max_players' => 4,
                'config' => ['target' => 301, 'turn_seconds' => 30, 'hand_pause_ms' => 5000],
            ]],
            'domino' => [[
                'slug' => 'block',
                'name' => ['en' => 'Block domino', 'ru' => 'Блок-домино', 'hy' => 'Բլոկ դոմինո'],
                'description' => ['en' => '2 to 4 players, no drawing, to 100.', 'ru' => 'От 2 до 4 игроков, без добора, до 100.', 'hy' => '2-ից 4 խաղացող, առանց վերցնելու, մինչև 100։'],
                'min_players' => 2, 'max_players' => 4,
                'config' => ['target' => 100, 'turn_seconds' => 30, 'round_pause_ms' => 4000, 'table_wait_seconds' => 20],
            ]],
            'ludo' => [[
                'slug' => 'classic',
                'name' => ['en' => 'Classic ludo', 'ru' => 'Классическое лудо', 'hy' => 'Դասական լուդո'],
                'description' => ['en' => '2 to 4 players, four tokens each.', 'ru' => 'От 2 до 4 игроков, по четыре фишки.', 'hy' => '2-ից 4 խաղացող, չորսական խաղաքար։'],
                'min_players' => 2, 'max_players' => 4,
                'config' => ['turn_seconds' => 20, 'table_wait_seconds' => 20],
            ]],
        ];
    }
}
