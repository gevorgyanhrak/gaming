<?php

namespace App\Platform\Presence;

use App\Platform\Settings\Settings;
use Carbon\CarbonInterface;

/**
 * A simulated activity figure the owner chose to show next to the real online count (HayBet brief, 2026-11-20:
 * "if simulated activity is necessary, clearly distinguish it from verified real users"). It is never added to the
 * real count and every place that shows it labels it as a demo. Settings: online.demo_enabled, online.demo_base.
 *
 * The value is a pure function of the minute: a daily rhythm (busiest in the evening, Yerevan time)
 * plus two slow drifts, so it moves a little every minute, never jumps, and every visitor sees the same number.
 */
class DemoActivity
{
    public function __construct(private readonly Settings $settings) {}

    public function value(CarbonInterface $now): ?int
    {
        if (! $this->settings->get('online.demo_enabled')) {
            return null;
        }

        return self::at((int) $this->settings->get('online.demo_base'), $now);
    }

    public static function at(int $base, CarbonInterface $now): int
    {
        $minute = intdiv($now->getTimestamp(), 60);
        $local = $now->copy()->setTimezone('Asia/Yerevan');
        $dayShare = ($local->hour * 60 + $local->minute) / 1440;
        // ±10 % over the day: busiest at 21:00, quietest at 09:00.
        $daily = 0.10 * cos(2 * M_PI * ($dayShare - 21 / 24));
        $drift = 0.025 * sin($minute / 47.0) + 0.012 * sin($minute / 11.3 + 1.7);

        return max(0, (int) round($base * (1 + $daily + $drift)));
    }
}
