<?php

namespace App\Platform\Slots\House\Agt;

/**
 * The AGT-style house slots (owner, 2026-11-24), one data file each in data/. Slug => title; the page and art live
 * in apps/web/play/<slug>/ (the shared client in play/agt/).
 */
final class AgtCatalog
{
    public const GAMES = [
        'seven-hot' => 'Seven Hot',
        'ice-fruits' => 'Ice Fruits',
        'lucky-slot' => 'Lucky Slot',
    ];

    public const SLUGS = ['seven-hot', 'ice-fruits', 'lucky-slot'];

    /** @return list<AgtSlot> */
    public static function slots(): array
    {
        return array_map(fn (string $slug) => new AgtSlot($slug), self::SLUGS);
    }
}
