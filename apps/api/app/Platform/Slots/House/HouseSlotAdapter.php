<?php

namespace App\Platform\Slots\House;

use App\Models\SlotGame;
use App\Models\SlotSession;
use App\Platform\Slots\SlotAdapter;

/**
 * The house provider (ADR 0024): its games run on our server, so nothing outside calls the wallet endpoint. Its
 * "secret" only marks it ready; it is derived from the app key and never leaves the server, and a signed call
 * from outside would still need a session token we never hand out for it.
 */
final class HouseSlotAdapter implements SlotAdapter
{
    public function provider(): string
    {
        return HouseSlots::PROVIDER;
    }

    public function secret(): string
    {
        return hash_hmac('sha256', 'house-slots', (string) config('app.key'));
    }

    public function launchUrl(SlotSession $session, string $token, SlotGame $game, string $locale): string
    {
        return "/play/{$game->provider_game_id}/";
    }
}
