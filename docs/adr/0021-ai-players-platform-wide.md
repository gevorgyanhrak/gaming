# 0021. AI players platform-wide: one level, people first, AI stands in for people

**Status:** Accepted (2026-11-12)

## Context
The owner asked for AI to work the same in every game (2026-11-12): "Play against AI" instead of a bot choice
with levels, matchmaking that prefers people, AI filling empty seats unnoticed, and AI taking over a seat when a
person drops out, so a table of several people isn't ruined by one of them. Each game keeps its own bot strategy.

## Decision
- **One level:** `Gameplay/Bots/AiPlayers` seats AI at the highest difficulty a game has bots for, for bot tables
  and matchmaking alike. The difficulty picker and the API's `bot_difficulty` are gone; rooms don't show a level.
- **People first:** `Matchmaker` matches people as before; once the oldest waiting player has waited the game's AI
  timeout (`bot_fallback_timeout_seconds`, or the settings default), everyone waiting sits down together and AI
  fills the empty seats (`fillWithAi`), in an ordinary matchmaking room. No offer, no countdown. Per game the admin
  chooses "fill with AI" or "keep waiting for people" (`bot_fallback_mode` `auto`/`off`; `offer` works as `auto`).
- **Never marked** (superseded by ADR 0022 — AI is now marked): nothing in a room shows who is AI (no pill, label or level); "Play against AI" stays as a mode
  people choose before a game.
- **AI stands in (`Gameplay/Lifecycle/AiTakeover`, `game_participants.ai_controlled`):** at tables of 3+ seats
  where another person plays,
  - a player who misses presence reports is *away*: AI plays their seat until they report again (or make a move);
    the seat and the result stay theirs; AI's moves are the server's (`auto:` ids), never counted for XP;
  - a player who presses Leave loses, and AI plays their seat to the end, so the game goes on (Blot no longer ends
    for the leaver's partner). Tables played for chips (poker, blackjack) keep their cash-out on Leave instead.
  Two-player games never hand a seat to AI: the one who stays wins. With no other person at the table the old rules
  apply (the game ends for the absent player).
- `GameScheduler` and `BotTurnJob` drive every seat where `playedByAi()` (AI players and held seats) with the game's
  own `BotStrategy`, through `GameActionService` like any move.

## Consequences
- Adding a game needs nothing new for this: its bot plays held seats as it plays its own.
- A held seat's moves are logged under the person's participant, marked `auto:`; replays are unchanged.
- Results at a table where someone left: the leaver's Loss is recorded at once; the others' results come from the
  game. A held seat that never comes back gets the game's result.
