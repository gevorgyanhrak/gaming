<?php

namespace App\Gameplay\Rooms;

use App\Enums\PlayGroupKind;
use App\Gameplay\Contracts\PlaysForChips;
use App\Gameplay\GameRegistry;
use App\Models\Game;
use App\Models\GameMode;
use App\Models\PlayGroup;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The central play-group configuration (admin-managed `play_groups`): which groups a game offers,
 * the terms a table is opened on, and the poker blinds of a group. Games never hard-code limits.
 */
class PlayGroups
{
    public function __construct(private readonly GameRegistry $registry) {}

    public function kindOf(GameMode $mode): PlayGroupKind
    {
        return $this->kindOfGame($mode->game);
    }

    public function kindOfGame(Game $game): PlayGroupKind
    {
        return $this->registry->has($game->slug) && $this->registry->get($game->slug)->engine() instanceof PlaysForChips
            ? PlayGroupKind::Chips
            : PlayGroupKind::Stake;
    }

    /**
     * The enabled groups offered for a game (none when it can't be played yet), lowest first.
     *
     * @return Collection<int, PlayGroup>
     */
    public function forGame(Game $game): Collection
    {
        if (! $this->registry->has($game->slug)) {
            return new Collection;
        }

        return PlayGroup::query()->where('kind', $this->kindOfGame($game))->where('is_enabled', true)->orderBy('position')->get();
    }

    /**
     * The enabled groups of a game, lowest first.
     *
     * @return Collection<int, PlayGroup>
     */
    public function forMode(GameMode $mode): Collection
    {
        return $this->forGame($mode->game);
    }

    /**
     * The terms a player asks to open (or queue for) a table on: the group (by slug, default the lowest)
     * and the bet. Stake games: matchmaking always plays the group's minimum; a bot game or a private
     * table may choose any bet within the group's range. Poker: no bet (the minimum is the big blind).
     *
     * @param  'bot'|'private'|'matchmaking'  $kind
     *
     * @throws ValidationException
     */
    public function terms(GameMode $mode, ?string $slug, ?int $bet, string $kind): TableTerms
    {
        $groups = $this->forMode($mode);
        $group = $slug === null ? $groups->first() : $groups->firstWhere('slug', $slug);
        if ($group === null) {
            throw ValidationException::withMessages(['group' => __('platform.play.group_unavailable')]);
        }

        if ($group->kind === PlayGroupKind::Chips) {
            return new TableTerms($group, 0);
        }

        $stake = $kind === 'matchmaking' || $bet === null ? $group->min_bet : $bet;
        if ($stake < $group->min_bet || ($group->max_bet !== null && $stake > $group->max_bet)) {
            throw ValidationException::withMessages(['bet' => __('platform.play.bet_range', [
                'min' => number_format($group->min_bet),
                'max' => number_format($group->max_bet ?? $group->min_bet),
            ])]);
        }

        return new TableTerms($group, $stake);
    }

    /**
     * Mode options a group adds to a table played for chips: poker blinds (big blind = the group's minimum
     * bet) and the table's bet limits (blackjack; no maximum when the group sets none).
     *
     * @return array<string, int>
     */
    public function options(PlayGroup $group): array
    {
        return $group->kind === PlayGroupKind::Chips
            ? array_filter([
                'small_blind' => max(1, intdiv($group->min_bet, 2)),
                'big_blind' => $group->min_bet,
                'table_min_bet' => $group->min_bet,
                'table_max_bet' => $group->max_bet,
            ], fn (?int $value) => $value !== null)
            : [];
    }
}
