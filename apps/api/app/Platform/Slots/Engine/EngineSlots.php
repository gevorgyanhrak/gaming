<?php

namespace App\Platform\Slots\Engine;

use App\Models\SlotGame;
use App\Models\SlotProvider;
use Illuminate\Support\Str;

/**
 * The engine's games in our catalogue (ADR 0026): one `slot_providers` row ("slotopol") and a `slot_games` row per game
 * the engine lists, with its maker as `studio`. Synchronised from the engine itself (never a fixed list); an
 * administrator's changes to a game (on/off, bets, order, picture, name) are never overwritten.
 */
final class EngineSlots
{
    public const PROVIDER = 'slotopol';

    /** Bets per line a player can choose; a spin costs bet × lines, within the game's min and max. */
    public const LINE_BETS = [1, 2, 3, 5, 10, 20, 50, 100, 200, 500];

    public function __construct(private readonly SlotEngine $engine) {}

    public function provider(): SlotProvider
    {
        return SlotProvider::query()->firstOrCreate(
            ['slug' => self::PROVIDER],
            [
                'name' => 'Slotopol engine',
                'is_enabled' => true,
                'notes' => 'Slot maths from slotopol/server (MIT), run by our internal engine service; games keep their makers\' names by the owner\'s decision (ADR 0026).',
            ],
        );
    }

    /**
     * @return array{added: int, updated: int, total: int}
     *
     * @throws EngineUnavailable
     */
    public function sync(): array
    {
        $provider = $this->provider();
        $added = $updated = 0;
        $games = $this->engine->games();
        foreach ($games as $i => $g) {
            $meta = [
                'width' => $g['width'],
                'height' => $g['height'],
                'symbols' => $g['symbols'],
                'lines' => $g['lines'],
                'cost' => $g['cost'] ?? null,
                'ways' => $g['ways'] ?? 0,
                'bonuses' => $g['bonuses'] ?? 0,
                'year' => $g['year'] ?? null,
                'rtp' => $g['rtp'],
                'features' => $g['features'],
                // The RTP our spin path really paid in the engine's own measurement, and whether it matched the label.
                'measured' => $g['measured'] ?? null,
                'verified' => (bool) ($g['verified'] ?? false),
                'state' => $g['state'],
            ];
            $game = SlotGame::query()->where('slot_provider_id', $provider->id)->where('provider_game_id', $g['id'])->first();
            if ($game !== null) {
                // The engine's description only; what an administrator set stays.
                $game->forceFill(['studio' => $g['provider'], 'meta' => $meta])->save();
                $updated++;

                continue;
            }
            SlotGame::query()->create([
                'slot_provider_id' => $provider->id,
                'provider_game_id' => $g['id'],
                'slug' => $this->slug($g['id']),
                'name' => ['en' => $g['name'], 'ru' => $g['name'], 'hy' => $g['name']],
                'studio' => $g['provider'],
                'meta' => $meta,
                'min_bet' => 1,
                'max_bet' => 100000,
                // A round is a paid spin with all its free games and falls; the engine's own maths bounds it.
                'max_multiplier' => 100000,
                // Only games whose play was verified against their RTP start switched on (GAME_FEATURES_AUDIT.md).
                'is_enabled' => (bool) ($g['verified'] ?? false),
                'sort_order' => 1000 + $i,
            ]);
            $added++;
        }

        return ['added' => $added, 'updated' => $updated, 'total' => count($games)];
    }

    /** A slug unique in the catalogue: "novomatic/bookofradeluxe" → "novomatic-bookofradeluxe". */
    private function slug(string $id): string
    {
        $base = Str::limit(Str::slug(str_replace('/', '-', $id)), 56, '');
        $slug = $base;
        for ($n = 2; SlotGame::query()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
