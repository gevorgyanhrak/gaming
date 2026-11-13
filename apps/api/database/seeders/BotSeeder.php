<?php

namespace Database\Seeders;

use App\Enums\BotDifficulty;
use App\Models\BotGameProfile;
use App\Models\BotProfile;
use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * The AI players: 100 bots with Armenian names and handles, each at its own level — at the table they look
 * like any other player. Every bot plays every game at the strongest level (AI always plays at it, ADR 0021).
 * Administrators rename, add or disable bots.
 * Idempotent and non-destructive: existing bots and their game settings are left as they are.
 */
class BotSeeder extends Seeder
{
    /** The first fifteen are the original bots (their levels came from the migration's formula). */
    public const NAMES = [
        'Armen', 'Davit', 'Ani', 'Mariam', 'Hayk', 'Mikayel', 'Narek', 'Aram', 'Lilit', 'Tigran', 'Vardan', 'Levon',
        'Anahit', 'Gor', 'Sevak', 'Aren', 'Arsen', 'Artur', 'Ashot', 'Avet', 'Babken', 'Edgar', 'Erik', 'Gagik',
        'Grigor', 'Hovhannes', 'Karen', 'Khachik', 'Manuk', 'Mher', 'Norayr', 'Ruben', 'Samvel', 'Sargis', 'Smbat',
        'Suren', 'Vahagn', 'Vahe', 'Vazgen', 'Zaven', 'Gevorg', 'Hrant', 'Artak', 'Arman', 'Arsham', 'Seyran',
        'Tatul', 'Arpi', 'Astghik', 'Gayane', 'Hasmik', 'Karine', 'Lusine', 'Meline', 'Nare', 'Nune', 'Ruzanna',
        'Siranush', 'Tatevik', 'Zara', 'Shushan', 'Armine', 'Anush', 'Elen', 'Hripsime', 'Mane', 'Naira', 'Sona',
        'Syuzanna', 'Taguhi', 'Varduhi', 'Yeva', 'Zaruhi', 'Gor_777', 'Aramchik', 'Davo91', 'Lusik_K', 'Narekyan',
        'Tiko_88', 'Mane.S', 'Rafo_23', 'Hayko', 'Vahik07', 'Ashotik', 'Karo_Blot', 'Sev_Gor', 'Ano_96', 'Mushegh',
        'Nairi_K', 'Arsenchik', 'Levon_10', 'Tatev.A', 'Gugo', 'Erik_Yer', 'Zarik', 'Hrach_77', 'Ani.M', 'Vardges',
        'Sona_Gyumri', 'Taron',
    ];

    public function run(): void
    {
        $games = Game::query()->pluck('id');
        // Games no bot plays yet (added since the bots were seeded): every bot learns them.
        // Games with bots keep whatever the administrators set up.
        $newGames = Game::query()->whereNotIn('id', BotGameProfile::query()->select('game_id'))->pluck('id');

        foreach (self::NAMES as $name) {
            $bot = BotProfile::query()->firstOrCreate(['name' => $name], ['level' => self::level($name)]);

            foreach ($bot->wasRecentlyCreated ? $games : $newGames as $gameId) {
                $bot->gameProfiles()->firstOrCreate(['game_id' => $gameId], ['difficulty' => BotDifficulty::Hard]);
            }
        }
    }

    /**
     * A level that looks like a real player's, the same every time for a name: 3–85, most in the middle
     * (the sum of two draws), a few beginners and a few veterans.
     */
    public static function level(string $name): int
    {
        $hash = crc32($name);

        return 3 + ($hash % 42) + (intdiv($hash, 42) % 42);
    }
}
