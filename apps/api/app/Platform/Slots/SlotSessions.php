<?php

namespace App\Platform\Slots;

use App\Models\SlotGame;
use App\Models\SlotSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Opens a slot game for a player: a session with a one-off token (only its hash is stored) that the provider's
 * wallet calls must name, and the address its adapter gives for the player's browser. One open session per player
 * and game; opening again closes the old one.
 */
final class SlotSessions
{
    public const HOURS = 4;

    public function __construct(private readonly SlotAdapters $adapters) {}

    /**
     * @return array{session: string, url: string}
     *
     * @throws ValidationException When the game isn't offered (off, or its provider has no adapter).
     */
    public function open(User $user, SlotGame $game, string $locale): array
    {
        $adapter = $this->adapters->for($game->provider->slug);
        if (! $game->is_enabled || ! $game->provider->is_enabled || $adapter === null || ! $this->adapters->ready($game->provider->slug)) {
            throw ValidationException::withMessages(['game' => __('platform.slots.unavailable')]);
        }

        $token = Str::random(48);
        $session = DB::transaction(function () use ($user, $game, $token) {
            SlotSession::query()->where('user_id', $user->id)->where('slot_game_id', $game->id)
                ->whereNull('closed_at')->update(['closed_at' => now()]);

            return SlotSession::query()->create([
                'user_id' => $user->id,
                'slot_game_id' => $game->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(self::HOURS),
            ]);
        });

        return ['session' => $session->public_id, 'url' => $adapter->launchUrl($session, $token, $game, $locale)];
    }
}
