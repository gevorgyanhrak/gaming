<?php

namespace Database\Seeders;

use App\Enums\BotDifficulty;
use App\Enums\BotPersonality;
use App\Gameplay\GameRegistry;
use App\Models\BotGameProfile;
use App\Models\BotProfile;
use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * The AI players: 150 bots with Armenian names and handles, each at its own level — at the table they look
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
        // HayBet (owner, 2026-11-20): more players, mostly men, no repeated patterns.
        'Andranik', 'Hakob_M', 'Petros', 'Movses', 'Nerses_A', 'Garnik', 'Rafael', 'Robert_K', 'Albert', 'Ararat_Ar',
        'Haykaz', 'Kamo', 'Koryun', 'Mesrop', 'Mkhitar', 'Vachagan', 'Varuzhan', 'Zhirayr', 'Gurgen', 'Arshak',
        'Artashes', 'Garegin', 'Hamlet_V', 'Rubik', 'Aghasi', 'Ashkhen', 'Gohar', 'Marine_P', 'Shant', 'Sipan',
        'Tsolak', 'Valod', 'Vrezh', 'Yervand', 'Zohrab', 'Arayik', 'Benik', 'Garo_Yerevan', 'Kerob', 'Misak',
        'Nver', 'Paruyr', 'Rafik_77', 'Sasun', 'Tadevos', 'Vaspur', 'Yuri_Gyumri', 'Edik.S', 'Hovik_Vanadzor',
        'Lyova', 'Spartak_M', 'Arsen.Kh',
    ];

    /**
     * Some old names, too alike to their neighbours (owner: change only some), become new ones. Applied once: only
     * while the old name exists and the new one doesn't.
     */
    public const RENAMES = [
        'Gor_777' => 'Grigor_Ar', 'Aramchik' => 'Arayik_P', 'Davo91' => 'Davtyan_D', 'Narekyan' => 'Nerses_V',
        'Sev_Gor' => 'Sevan_Kh', 'Levon_10' => 'Leo_Karapetyan', 'Arsenchik' => 'Arsen_Hovs', 'Ani.M' => 'Anna_M',
        'Erik_Yer' => 'Edvard_Y', 'Ashotik' => 'Ashot_Petrosyan', 'Vahik07' => 'Vahram_07', 'Hayko' => 'Hayk_Sargsyan',
    ];

    public function run(): void
    {
        foreach (self::RENAMES as $old => $new) {
            if (! BotProfile::query()->where('name', $new)->exists()) {
                BotProfile::query()->where('name', $old)->update(['name' => $new]);
            }
        }

        $games = Game::query()->pluck('id');
        // Games no bot plays yet (added since the bots were seeded): every bot learns them.
        // Games with bots keep whatever the administrators set up.
        $newGames = Game::query()->whereNotIn('id', BotGameProfile::query()->select('game_id'))->pluck('id');

        foreach (array_map(fn (string $name) => self::RENAMES[$name] ?? $name, self::NAMES) as $name) {
            $bot = BotProfile::query()->firstOrCreate(['name' => $name], ['level' => self::level($name), 'personality' => BotPersonality::for($name)]);

            foreach ($bot->wasRecentlyCreated ? $games : $newGames as $gameId) {
                $bot->gameProfiles()->firstOrCreate(['game_id' => $gameId], ['difficulty' => BotDifficulty::Hard]);
            }
        }

        $this->assignHomeGames();
    }

    /**
     * Every AI player without a home game gets one (the one board it can appear on), spread evenly over the games
     * that have an engine: always the game with the fewest home players so far. Admins can change it later.
     */
    private function assignHomeGames(): void
    {
        $registry = app(GameRegistry::class);
        $games = Game::query()->orderBy('id')->pluck('slug', 'id')->filter(fn (string $slug) => $registry->has($slug));
        if ($games->isEmpty()) {
            return;
        }
        /** @var array<int, int> $count home players per game id */
        $count = $games->keys()->mapWithKeys(fn (int $id) => [$id => BotProfile::query()->where('home_game_id', $id)->count()])->all();
        foreach (BotProfile::query()->whereNull('home_game_id')->orderBy('id')->get() as $bot) {
            asort($count);
            $home = (int) array_key_first($count);
            $bot->forceFill(['home_game_id' => $home])->save();
            $bot->gameProfiles()->firstOrCreate(['game_id' => $home], ['difficulty' => BotDifficulty::Hard]);
            $count[$home]++;
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
