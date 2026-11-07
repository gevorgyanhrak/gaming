<?php

namespace App\Platform\Levels;

/**
 * How much XP each level needs (ADR 0018). Below level 30 each level needs a fixed step more than the
 * one before (easy and quick); from 30 on, a jump and then a percentage more per level. Pure: built from
 * the admin settings by Levels::curve().
 */
final class LevelCurve
{
    /** Where the gentle part of the curve ends. */
    public const EASY_UNTIL = 30;

    /** @var array<int, int> Total XP needed to reach each level, filled lazily. */
    private array $totals = [1 => 0];

    public function __construct(
        public readonly int $base,
        public readonly int $step,
        public readonly int $after30Percent,
        public readonly int $growthPercent,
        public readonly int $maxLevel,
    ) {}

    /**
     * XP needed to go from $level to $level + 1.
     */
    public function toNext(int $level): int
    {
        if ($level < self::EASY_UNTIL) {
            return $this->base + $this->step * ($level - 1);
        }

        $last = $this->base + $this->step * (self::EASY_UNTIL - 1);

        return (int) ceil($last * $this->after30Percent / 100 * (1 + $this->growthPercent / 100) ** ($level - self::EASY_UNTIL));
    }

    /**
     * Total XP needed to reach $level (0 for level 1).
     */
    public function totalFor(int $level): int
    {
        $level = max(1, min($level, $this->maxLevel));
        for ($l = count($this->totals) + 1; $l <= $level; $l++) {
            $this->totals[$l] = $this->totals[$l - 1] + $this->toNext($l - 1);
        }

        return $this->totals[$level];
    }

    /**
     * The level a total of $xp reaches.
     */
    public function levelFor(int $xp): int
    {
        $level = 1;
        while ($level < $this->maxLevel && $xp >= $this->totalFor($level + 1)) {
            $level++;
        }

        return $level;
    }
}
