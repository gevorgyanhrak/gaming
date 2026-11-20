<?php

namespace App\Console\Commands;

use App\Gameplay\Arena\AiArena;
use App\Models\Game;
use App\Platform\Settings\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Plays AI arena games (see AiArena): `--games` per game now, or (from the scheduler, no option) the share of
 * `rankings.arena_games_per_hour` for one five-minute slot.
 */
#[Signature('rankings:arena {--game= : one game slug} {--games= : games to play for each game}')]
#[Description('Play whole AI-vs-AI games on the server for the per-game boards')]
class ArenaCommand extends Command
{
    public function handle(AiArena $arena, Settings $settings): int
    {
        $count = $this->option('games') !== null
            ? max(0, (int) $this->option('games'))
            : (int) ceil((int) $settings->get('rankings.arena_games_per_hour') / 12);
        $games = Game::query()->where('is_enabled', true)
            ->when($this->option('game'), fn ($q, $slug) => $q->where('slug', $slug))
            ->orderBy('id')->get();

        foreach ($games as $game) {
            $played = 0;
            $dropped = 0;
            for ($i = 0; $i < $count; $i++) {
                $arena->play($game) === null ? $dropped++ : $played++;
            }
            if ($count > 0) {
                $this->line(sprintf('%-10s played %d, dropped %d', $game->slug, $played, $dropped));
            }
        }

        return self::SUCCESS;
    }
}
