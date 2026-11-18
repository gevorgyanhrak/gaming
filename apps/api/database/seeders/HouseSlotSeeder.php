<?php

namespace Database\Seeders;

use App\Models\SlotGame;
use App\Models\SlotProvider;
use App\Platform\Slots\House\HouseSlots;
use Illuminate\Database\Seeder;

/**
 * The house slots (ADR 0024): the APEXSLOTS open-source games our server deals. Creates what is missing and never
 * changes what an administrator set (on/off, bets, order, names).
 */
class HouseSlotSeeder extends Seeder
{
    /** id => [name, sort order]; every game's code is in App\Platform\Slots\House\Games. */
    public const GAMES = [
        'stellar-sevens' => ['Stellar Sevens', 10],
        'nitro-hot' => ['Nitro Hot', 20],
        'scorching-hot' => ['Scorching Hot', 30],
        'tropic-escape' => ['Tropic Escape', 40],
        'pearl-reef' => ['Pearl Reef', 50],
        'discovery-voyage' => ['Discovery Voyage', 60],
        'golden-papyrus' => ['Golden Papyrus', 70],
        'house-of-ra' => ['House of Ra', 80],
        'samurai' => ['Samurai', 90],
        'the-dynasty' => ['The Dynasty', 100],
        'fairytale' => ['Fairytale', 110],
        'bumper-crop' => ['Bumper Crop', 120],
        // Art: "Jungle Slot Assets" by danitorres567 (OpenGameArt, CC0); maths ours (2026-11-18).
        'jungle-slot' => ['Jungle Slot', 5],
    ];

    public function run(HouseSlots $slots): void
    {
        $provider = SlotProvider::query()->firstOrCreate(
            ['slug' => HouseSlots::PROVIDER],
            [
                'name' => 'APEXSLOTS (open source)',
                'is_enabled' => true,
                'notes' => 'Open-source games (MIT code, CC BY 4.0 art by APEXSLOTS, maths from slotopol, MIT) dealt by our own server. See ADR 0024.',
            ],
        );

        foreach (self::GAMES as $id => [$name, $order]) {
            $engine = $slots->for($id);
            if ($engine === null) {
                continue;
            }
            SlotGame::query()->firstOrCreate(
                ['slot_provider_id' => $provider->id, 'provider_game_id' => $id],
                [
                    'slug' => $id,
                    'name' => ['en' => $name, 'ru' => $name, 'hy' => $name],
                    'min_bet' => 10,
                    'max_bet' => 1000,
                    'max_multiplier' => $engine->maxWin(),
                    'is_enabled' => true,
                    'sort_order' => $order,
                ],
            );
        }
    }
}
