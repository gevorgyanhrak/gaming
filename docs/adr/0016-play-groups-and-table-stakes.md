# 0016. Play groups and table stakes

**Status:** Accepted (2026-11-01)

## Context
The owner asked for five levels ("groups") per game with entry requirements by Fishka balance, minimum
(and for most games maximum) bets, separate tables and matchmaking, configurable centrally. Poker must use
the player's whole balance; other games bet within a range and the winner takes the pot.

## Decision
- One `play_groups` table, two kinds: `chips` (poker; the group's minimum bet is the big blind) and `stake`
  (every other game; `min_bet`–`max_bet`). The migration seeds the owner's ten groups; staff edit limits or
  close a group in the admin panel (audited). The kind of a game comes from its engine (`PlaysForChips`).
- `App\Gameplay\Rooms\PlayGroups` resolves a request to `TableTerms` (group + each player's bet): the lowest
  group by default, matchmaking always at the group's minimum, bot/private tables any bet in range. Rooms
  store `play_group_id` and `stake`; the group's table options (poker blinds) are merged into the room's
  options so replays match.
- Entry: `RoomStakes::ensureCanAfford` requires the group's minimum balance (never charged) and the bet.
  Below the lowest group → `insufficient_fishkas` (the bonus popup); otherwise a validation error on `group`.
- Money: poker buys in with the whole balance (bots get the people's average from the house) and cashes out
  as before. Stake games stake the bet per seat (`game_stake`); at the end the winners share bet × seats
  (`game_payout`, Durak: everyone but the loser), a draw returns the bets, leavers lose theirs; bots' bets
  and shares are the house's. Keys per seat prevent double charges and payouts.
- Matchmaking queues are per mode and group ("pool" = `mode:group`).

## Consequences
- Supersedes the flat 500 entry fee for new tables (rooms without a group still use it).
- Winning against bots creates Fishkas; losing to them removes Fishkas (owner's earlier choice).
