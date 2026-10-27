<?php

namespace App\Gameplay\Rooms;

use App\Gameplay\GameRegistry;
use App\Models\GameMode;
use App\Models\GameParticipant;
use App\Models\User;
use App\Platform\Settings\Settings;
use Illuminate\Validation\ValidationException;

/**
 * Who may start which kind of game. Shared by bot games, private rooms and matchmaking.
 */
class PlayRules
{
    public function __construct(
        private readonly GameRegistry $registry,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  'bot'|'private'|'matchmaking'  $kind
     *
     * @throws ValidationException
     */
    public function ensureCanStart(User $user, GameMode $mode, string $kind): void
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
    }
}
