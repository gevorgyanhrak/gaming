<?php

namespace App\Platform\Levels;

/**
 * The virtual items a level can unlock (ADR 0018). Items are art, so they are defined here; which level
 * unlocks which item is data (`level_rewards`). A player owns an item when a reward at or below their
 * level lists it. Nothing here can be bought, sold or sent.
 */
final class LevelItems
{
    /** Avatar frames, shown around the avatar everywhere. */
    public const FRAMES = ['gold', 'legend'];

    /** Profile badges, shown next to the name. */
    public const BADGES = ['rising', 'premium', 'champion'];

    /**
     * Emoji packs for the table: extra emojis only their owners may send. None of them is in the free
     * catalog (App\Gameplay\Reactions\ReactionCatalog), or it would become locked.
     *
     * @var array<string, list<string>>
     */
    public const EMOJI_PACKS = [
        'royal' => ['🔱', '⚜️', '🦚', '🗡️'],
        'legend' => ['🐉', '🌋', '🌠', '☄️'],
    ];

    /**
     * Whether an item exists.
     */
    public static function exists(string $kind, string $key): bool
    {
        return match ($kind) {
            'frame' => in_array($key, self::FRAMES, true),
            'badge' => in_array($key, self::BADGES, true),
            'emoji_pack' => array_key_exists($key, self::EMOJI_PACKS),
            default => false,
        };
    }

    /**
     * Every item as "kind:key" => label, for the admin panel.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::FRAMES as $key) {
            $options["frame:{$key}"] = 'Avatar frame: '.ucfirst($key);
        }
        foreach (self::BADGES as $key) {
            $options["badge:{$key}"] = 'Badge: '.ucfirst($key);
        }
        foreach (self::EMOJI_PACKS as $key => $emojis) {
            $options["emoji_pack:{$key}"] = 'Emoji pack: '.ucfirst($key).' '.implode('', $emojis);
        }

        return $options;
    }
}
