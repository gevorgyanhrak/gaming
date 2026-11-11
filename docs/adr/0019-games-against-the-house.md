# 0019. Games against the house (Blackjack)

**Status:** Accepted (2026-11-11)

## Context
The owner asked for ten new games (2026-11-11), Blackjack first: single player against the dealer, and
tables with more players where possible, betting play Fishkas. Until now every game was played between seats
(min two players), an outcome without winners was a draw, and a player alone at a table didn't exist.

## Decision
- **The dealer is the engine, not a seat.** `BlackjackEngine` plays the dealer's hand by fixed rules inside
  `apply()`; seats are only players (1–5). Bots at the table are other players against the same dealer.
- **Chips, like poker.** Blackjack implements `PlaysForChips`: each person brings their whole balance as chips
  (`RoomStakes` buy-in), bets per round within the play group's limits (`table_min_bet` = the group's minimum
  bet, `table_max_bet` = its maximum or `max_bet_multiple` × the minimum), and is paid `chipsOf()` back when
  leaving or at the end. `chipsOf()` counts chips at stake in the current round, so betting everything is not
  "busted". The house pays winnings; there is no pot between players.
- **A game has an end.** A table plays `rounds` rounds (mode config, 10), pausing between them
  (`PausesBetweenRounds`). Seats ahead of their buy-in win; when nobody is ahead and someone is behind, the
  winner is `Outcome::HOUSE` (seat −1, nobody sits there): everyone loses; nobody ahead or behind is a draw.
- **Alone at a table.** Modes may have `min_players` 1. A bot room with `opponents: 0` seats only the player
  (no bot difficulty needed). Private tables still need two seats. Leaving a table where nobody else plays
  is a loss (`Outcome::HOUSE`), not a draw, for every game.
- **Bot profiles for new games.** `BotSeeder` gives the sample bots every game no bot plays yet, so a new game
  has bots after `db:seed`; games with bots keep what administrators set.

## Consequences
- Leaving mid-round forfeits that round's bets (they are on the table); a bet placed before the deal is returned.
- The play group's `min_bet` is the blackjack minimum bet and the poker big blind (same chips groups).
- Insurance and surrender are not offered (simpler, and not needed for free play); can be added as actions later.
