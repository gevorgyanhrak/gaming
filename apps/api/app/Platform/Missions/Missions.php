<?php

namespace App\Platform\Missions;

use App\Enums\FishkaTransactionType;
use App\Enums\XpSource;
use App\Models\User;
use App\Platform\Fishka\FishkaWallets;
use App\Platform\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Daily missions and weekly challenges (HayBet item 9, 2026-11-20). Three missions a day (two sets that alternate by
 * day) and two challenges a week, the same for everyone. Progress comes only from the server's records: the games
 * that earned the player XP in the period (finished, not abandoned, long enough, enough of their own moves — the
 * same rule as XP, so quick resigns don't count). A reward is claimed once per mission and period (a unique row and
 * a one-time ledger key). Days and weeks follow `fishka.timezone`, like the daily bonus.
 */
class Missions
{
    /** kind: play (games), win (games won), variety (different games played). */
    public const DAILY = [
        [
            ['key' => 'play_3', 'kind' => 'play', 'target' => 3, 'reward' => 300],
            ['key' => 'win_1', 'kind' => 'win', 'target' => 1, 'reward' => 400],
            ['key' => 'variety_2', 'kind' => 'variety', 'target' => 2, 'reward' => 300],
        ],
        [
            ['key' => 'play_5', 'kind' => 'play', 'target' => 5, 'reward' => 500],
            ['key' => 'win_2', 'kind' => 'win', 'target' => 2, 'reward' => 700],
            ['key' => 'variety_3', 'kind' => 'variety', 'target' => 3, 'reward' => 500],
        ],
    ];

    public const WEEKLY = [
        ['key' => 'week_play_20', 'kind' => 'play', 'target' => 20, 'reward' => 2500],
        ['key' => 'week_win_10', 'kind' => 'win', 'target' => 10, 'reward' => 4000],
    ];

    public function __construct(
        private readonly Settings $settings,
        private readonly FishkaWallets $wallets,
    ) {}

    /**
     * The player's missions now, with progress and whether each was claimed.
     *
     * @return list<array{key: string, period: 'day'|'week', kind: string, target: int, progress: int, reward: int, claimed: bool, ends_at: string}>
     */
    public function for(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $rows = [];
        foreach (['day', 'week'] as $period) {
            [$from, $to, $periodKey] = $this->window($period, $now);
            $stats = $this->stats($user->id, $from, $to);
            $claimed = DB::table('mission_claims')->where('user_id', $user->id)->where('period', $periodKey)->pluck('mission')->all();
            foreach ($this->catalog($period, $periodKey) as $mission) {
                $rows[] = [
                    'key' => $mission['key'],
                    'period' => $period,
                    'kind' => $mission['kind'],
                    'target' => $mission['target'],
                    'progress' => min($mission['target'], $stats[$mission['kind']]),
                    'reward' => $mission['reward'],
                    'claimed' => in_array($mission['key'], $claimed, true),
                    'ends_at' => $to->toIso8601String(),
                ];
            }
        }

        return $rows;
    }

    /**
     * Collects a finished mission's Fishkas (once). Refused when it isn't one of today's/this week's missions or
     * isn't done yet.
     *
     * @throws ValidationException
     */
    public function claim(User $user, string $key, ?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now();
        $mission = collect($this->for($user, $now))->firstWhere('key', $key);
        if ($mission === null || $mission['progress'] < $mission['target']) {
            throw ValidationException::withMessages(['mission' => __('platform.missions.not_done')]);
        }
        if ($mission['claimed']) {
            return;
        }
        [, , $periodKey] = $this->window($mission['period'], $now);

        DB::transaction(function () use ($user, $mission, $periodKey) {
            $inserted = DB::table('mission_claims')->insertOrIgnore([
                'user_id' => $user->id, 'mission' => $mission['key'], 'period' => $periodKey,
                'reward' => $mission['reward'], 'created_at' => now(),
            ]);
            if ($inserted === 0) {
                return;
            }
            $this->wallets->credit($user, $mission['reward'], FishkaTransactionType::MissionReward, [
                'idempotency_key' => "mission:{$user->id}:{$mission['key']}:{$periodKey}",
                'reference_type' => 'mission',
                'reference_id' => $mission['key'],
            ]);
        });
    }

    /**
     * The period's bounds (UTC) and its key.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function window(string $period, CarbonImmutable $now): array
    {
        $local = $now->setTimezone((string) $this->settings->get('fishka.timezone'));
        $from = $period === 'day' ? $local->startOfDay() : $local->startOfWeek();
        $to = $period === 'day' ? $from->addDay() : $from->addWeek();

        return [$from->utc(), $to->utc(), $period === 'day' ? $from->toDateString() : $from->format('o-\WW')];
    }

    /**
     * The period's missions; the day's set follows the local date (not the UTC start, which is the day before east of
     * UTC).
     *
     * @return list<array{key: string, kind: string, target: int, reward: int}>
     */
    private function catalog(string $period, string $periodKey): array
    {
        return $period === 'week' ? self::WEEKLY : self::DAILY[CarbonImmutable::parse($periodKey)->dayOfYear % count(self::DAILY)];
    }

    /**
     * Games that earned the player XP in the window: how many, how many won, how many different games.
     *
     * @return array{play: int, win: int, variety: int}
     */
    private function stats(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = DB::table('xp_transactions')
            ->join('game_rooms', 'game_rooms.public_id', '=', 'xp_transactions.reference_id')
            ->join('game_participants', fn ($join) => $join->on('game_participants.game_room_id', '=', 'game_rooms.id')
                ->where('game_participants.user_id', $userId))
            ->where('xp_transactions.user_id', $userId)
            ->where('xp_transactions.source', XpSource::Game->value)
            ->where('xp_transactions.reference_type', 'game_room')
            ->where('xp_transactions.created_at', '>=', $from)
            ->where('xp_transactions.created_at', '<', $to)
            ->selectRaw("COUNT(DISTINCT game_rooms.id) as play, COUNT(DISTINCT CASE WHEN game_participants.result = 'win' THEN game_rooms.id END) as win, COUNT(DISTINCT game_rooms.game_id) as variety")
            ->first();

        return ['play' => (int) ($row->play ?? 0), 'win' => (int) ($row->win ?? 0), 'variety' => (int) ($row->variety ?? 0)];
    }
}
