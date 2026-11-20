<?php

namespace App\Gameplay\Arena;

use App\Enums\BotDifficulty;
use App\Enums\ParticipantResult;
use App\Gameplay\Contracts\PausesBetweenRounds;
use App\Gameplay\Contracts\PlaysForChips;
use App\Gameplay\Engine\GameSetup;
use App\Gameplay\Engine\Outcome;
use App\Gameplay\GameRegistry;
use App\Gameplay\Random\SecureRandom;
use App\Gameplay\Rooms\PlayGroups;
use App\Models\Game;
use App\Models\GameMode;
use App\Platform\Rankings\Rankings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The AI arena (HayBet items 3–4, owner 2026-11-20: "AI earns real points"): whole games between AI players, played
 * on the server by the game's real engine and its real bot, each seat deciding from its own view and legal actions
 * only — the same as at a table, without a room, a clock or broadcasts. Each finished game is logged in
 * `ai_arena_games` and scored through Rankings like any other (once per seat), for the players' home game. A game
 * that doesn't finish within the step cap is dropped and scores nothing. Knows no game's rules.
 */
class AiArena
{
    /**
     * Arena games are played at most at this level: real games between real bots, cheap enough to run beside the
     * live tables (at the top level a Blot game takes about a minute of CPU).
     */
    public const MAX_DIFFICULTY = BotDifficulty::Medium;

    /** More moves than any real game needs; a game still going after this is dropped. */
    public const MAX_STEPS = 4000;

    public function __construct(
        private readonly GameRegistry $registry,
        private readonly PlayGroups $groups,
        private readonly Rankings $rankings,
    ) {}

    /**
     * Plays one game between the game's own AI players (its home bots). Null when it has too few of them, the
     * game has no engine, or the game didn't finish.
     *
     * @return array{public_id: string, bots: list<int>, winners: list<int>, moves: int}|null
     */
    public function play(Game $game): ?array
    {
        if (! $this->registry->has($game->slug)) {
            return null;
        }
        $mode = GameMode::query()->where('game_id', $game->id)->where('is_enabled', true)->orderBy('id')->first();
        if ($mode === null) {
            return null;
        }
        $seats = max(2, $mode->min_players);
        $bots = DB::table('bot_profiles')
            ->leftJoin('bot_game_profiles', fn ($join) => $join->on('bot_game_profiles.bot_profile_id', '=', 'bot_profiles.id')
                ->where('bot_game_profiles.game_id', $game->id))
            ->where('bot_profiles.home_game_id', $game->id)
            ->where('bot_profiles.is_enabled', true)
            ->inRandomOrder()->limit($seats)
            ->get(['bot_profiles.id', 'bot_game_profiles.difficulty']);
        if ($bots->count() < $seats) {
            return null;
        }

        $definition = $this->registry->get($game->slug);
        $engine = $definition->engine();
        $bot = $definition->bot();
        $random = new SecureRandom;
        $options = $mode->config ?? [];
        $group = $this->groups->forMode($mode)->first();
        if ($group !== null) {
            $options = [...$options, ...$this->groups->options($group)];
        }
        if ($engine instanceof PlaysForChips) {
            // Everyone starts with the same stack: the table's own buy-in for these options.
            $options['stacks'] = array_fill(0, $seats, $engine->buyIn($options));
        }

        $started = microtime(true);
        try {
            $state = $engine->initialState(new GameSetup($seats, $options), $random);
            $moves = 0;
            while (($outcome = $engine->outcome($state)) === null) {
                if ($moves++ >= self::MAX_STEPS) {
                    return null;
                }
                $toAct = $engine->seatsToAct($state);
                if ($toAct === []) {
                    if (! $engine instanceof PausesBetweenRounds || $engine->pauseMs($state) === null) {
                        return null;
                    }
                    $state = $engine->resume($state, $random)->state;

                    continue;
                }
                $seat = $toAct[0];
                $own = BotDifficulty::tryFrom((string) $bots[$seat]->difficulty) ?? self::MAX_DIFFICULTY;
                $difficulty = $own === BotDifficulty::Hard ? self::MAX_DIFFICULTY : $own;
                $action = $bot->chooseAction($engine->viewFor($state, $seat), $engine->legalActions($state, $seat), $difficulty, $random);
                $state = $engine->apply($state, $seat, $action, $random)->state;
            }
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $ids = $bots->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $publicId = (string) Str::ulid();
        DB::transaction(function () use ($game, $publicId, $ids, $outcome, $moves, $started) {
            DB::table('ai_arena_games')->insert([
                'public_id' => $publicId,
                'game_id' => $game->id,
                'bots' => json_encode($ids),
                'winners' => json_encode(array_values(array_filter($outcome->winners, fn (int $s) => $s !== Outcome::HOUSE))),
                'reason' => Str::limit($outcome->reason, 40, ''),
                'moves' => $moves,
                'ms' => (int) round((microtime(true) - $started) * 1000),
                'created_at' => now(),
            ]);
            foreach ($ids as $seat => $botId) {
                $result = match (true) {
                    $outcome->isDraw() => ParticipantResult::Draw,
                    in_array($seat, $outcome->winners, true) => ParticipantResult::Win,
                    default => ParticipantResult::Loss,
                };
                $this->rankings->recordArena($game, $publicId, $seat, $botId, $result);
            }
        });

        return ['public_id' => $publicId, 'bots' => $ids, 'winners' => $outcome->winners, 'moves' => $moves];
    }
}
