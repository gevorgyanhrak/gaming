<?php

namespace Tests\Fixtures\Slots;

use App\Models\SlotGame;
use App\Models\SlotSession;
use App\Platform\Slots\SlotAdapter;

/** A provider adapter for tests only: a fixed secret and a launch address that carries the token. */
final class FakeSlotAdapter implements SlotAdapter
{
    public const SECRET = 'test-slot-secret';

    public function provider(): string
    {
        return 'fakeslots';
    }

    public function secret(): ?string
    {
        return self::SECRET;
    }

    public function launchUrl(SlotSession $session, string $token, SlotGame $game, string $locale): string
    {
        return "https://slots.example.test/{$game->provider_game_id}?token={$token}&lang={$locale}";
    }
}
