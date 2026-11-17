<?php

namespace App\Platform\Slots\Engine;

use App\Models\SlotGame;
use App\Models\SlotSession;
use App\Platform\Slots\SlotAdapter;

/**
 * The engine provider (ADR 0026): its games run on our internal slot engine, so nothing outside calls the wallet
 * endpoint. Ready when the engine key is configured; the "secret" is derived from it and never leaves the server.
 */
final class EngineSlotAdapter implements SlotAdapter
{
    public function provider(): string
    {
        return EngineSlots::PROVIDER;
    }

    public function secret(): string
    {
        $key = (string) config('services.slot_engine.key');

        return strlen($key) >= 32 ? hash_hmac('sha256', 'engine-slots', $key) : '';
    }

    public function launchUrl(SlotSession $session, string $token, SlotGame $game, string $locale): string
    {
        return "/casino/slots/{$game->slug}";
    }
}
