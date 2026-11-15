<?php

namespace App\Platform\Slots;

use App\Models\SlotGame;
use App\Models\SlotSession;

/**
 * What a slot provider's integration must give us (docs/research/casino-slots.md). The provider runs the game and
 * decides results on its own server; it takes bets and pays wins only through our signed wallet endpoint
 * (SlotWallet), never through the browser. No adapter ships yet: a provider is connected by the owner's decision.
 */
interface SlotAdapter
{
    /** The provider's slug, as in slot_providers. */
    public function provider(): string;

    /** The shared secret its wallet calls are signed with (HMAC-SHA256), from the environment; null = not set up. */
    public function secret(): ?string;

    /** The address the player's browser opens for this session (the token is the session's one-off key). */
    public function launchUrl(SlotSession $session, string $token, SlotGame $game, string $locale): string;
}
