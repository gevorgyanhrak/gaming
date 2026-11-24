<?php

namespace App\Platform\Slots\House;

/**
 * A house slot whose client draws its screen from the server's description (a shared client for a family of
 * games): sent with the session. Only what the screen needs — never the reel strips.
 */
interface HasClientLayout
{
    /** @return array<string, mixed> */
    public function layout(): array;
}
