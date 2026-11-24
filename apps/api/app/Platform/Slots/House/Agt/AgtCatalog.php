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
        'gems' => 'Gems',
        'gems-50' => '50 Gems',
        'hot-clover' => 'Hot Clover',
        'jokers' => 'Jokers',
        'jokers-100' => '100 Jokers',
        'ice-fruits-6' => 'Ice Fruits 6 Reels',
        'cherry-hot' => 'Cherry Hot',
        'valentines-day' => "Valentine's Day",
    ];

    public const SLUGS = ['seven-hot', 'ice-fruits', 'lucky-slot', 'gems', 'gems-50', 'hot-clover', 'jokers', 'jokers-100', 'ice-fruits-6', 'cherry-hot', 'valentines-day'];

    /** @return list<AgtSlot> */
    public static function slots(): array
    {
        return array_map(fn (string $slug) => new AgtSlot($slug), self::SLUGS);
    }
}
