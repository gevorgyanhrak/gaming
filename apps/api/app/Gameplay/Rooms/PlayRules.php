<?php

namespace App\Gameplay\Rooms;

use App\Gameplay\GameRegistry;
use App\Models\GameMode;
use App\Models\GameParticipant;
use App\Models\User;
use App\Platform\Fishka\InsufficientFishkas;
use App\Platform\Settings\Settings;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Who may start which kind of game. Shared by bot games, private rooms and matchmaking.
 */
class PlayRules
{
    public function __construct(
        private readonly GameRegistry $registry,
        private readonly Settings $settings,
        private readonly RoomStakes $stakes,
    ) {}

    /**
     * @param  'bot'|'private'|'matchmaking'  $kind
     *
     * @throws ValidationException|InsufficientFishkas
     */
    public function ensureCanStart(User $user, GameMode $mode, string $kind, ?TableTerms $terms = null): void
    {
        $game = $mode->game;
        $playable = $game->is_enabled && $game->is_visible && $mode->is_enabled && $this->registry->has($game->slug);

        $allowed = $playable && match ($kind) {
            'bot' => $game->bots_enabled && $mode->bots_allowed,
            'private' => $mode->private_rooms_enabled,
            'matchmaking' => $mode->matchmaking_enabled,
        };

        if (! $allowed) {
            throw ValidationException::withMessages(['game_mode_id' => __('platform.play.unavailable')]);
        }

        $active = GameParticipant::query()->where('user_id', $user->id)->where('is_active', true)->count();
        if ($active >= (int) $this->settings->get('matchmaking.max_concurrent_games')) {
            throw ValidationException::withMessages(['game_mode_id' => __('platform.play.too_many_games')]);
        }

        $this->stakes->ensureCanAfford($user, $mode, $terms);
    }

    /**
     * Run $start (the checks above, then creating or joining the game) for one request of this player
     * at a time. Rapid repeated clicks then queue up: the next one finds the game already started and
     * fails the "games at once" check, instead of starting and paying for another game.
     *
     * @template T
     *
     * @param  Closure(): T  $start
     * @param  int  $wait  Seconds to wait for the player's previous start.
     * @return T
     */
    public function exclusively(User $user, Closure $start, int $wait = 10): mixed
    {
        try {
            return Cache::lock("play:start:{$user->id}", 15)->block($wait, $start);
        } catch (LockTimeoutException) {
            // Another start of theirs is still running: the same answer as a second game.
            throw ValidationException::withMessages(['game_mode_id' => __('platform.play.too_many_games')]);
        }
    }
}
