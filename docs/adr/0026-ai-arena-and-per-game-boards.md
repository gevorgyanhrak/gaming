# ADR 0026 — AI arena, home games and one points system for every game's board

- Status: accepted (2026-11-20)
- Context: HayBet brief items 3–4 (`docs/product/briefs/HAYBET-2026-11-20.md`): at least 10 leading players per game,
  much larger scores, no AI on two games' boards, many new names. The owner chose (2026-11-20) that AI players
  **earn real points by playing**, not invented scores.

## Decision

1. **One ranking system.** Every game ranks by points (`rankings.points_games` now lists all 11 games;
   `rankings.chips_games` is empty — it still works if an admin lists a game there). People and AI players share a
   board; AI rows stay marked AI (ADR 0022).
2. **Bigger unit.** A win is 100 points, a loss takes 50 (was 10 / 5). The migration multiplied stored totals and
   events by ten, so every standing is exactly what it was.
3. **Home game.** Each AI player has `bot_profiles.home_game_id`, the only game whose board it can be on. It scores
   only there (at people's tables too); points it earned elsewhere before are kept but never shown. The seeder spreads
   bots evenly (13–14 per game for 152 bots); admins change it in Bots.
4. **AI arena** (`Gameplay/Arena/AiArena`, game-agnostic). Whole AI-vs-AI games played on the server by each game's
   real engine and bot, every seat deciding from its own redacted view and legal actions — as at a table, without a
   room, clock or broadcast. Pauses between rounds are resumed; a game that doesn't finish within 4,000 steps is
   dropped. Each finished game is a row in `ai_arena_games` (bots, winners, reason, moves, time) and is scored through
   `Rankings` once per seat (the same unique `game_point_events` key a room uses). Bots play at most Medium in the
   arena (Hard Blot costs about a minute of CPU a game; Medium about 17 s, chess 2 s).
   `rankings:arena` runs every five minutes (`rankings.arena_games_per_hour`, default 12 per game; 0 = off) and can
   be run by hand (`--games=N --game=slug`).
5. Board rows carry `played` and `wins` (from `game_points`) and the UI shows "N games · X% wins".

## Consequences

- Boards fill truthfully over time; scores grow with play rather than starting at the brief's 45,000 (the owner
  accepted this when choosing real points). At the default rate a game's top AI gains a few hundred points a day.
- The arena adds steady background CPU (about 25 s every five minutes at the default rate).
- AI players at people's tables in a game that isn't their home game play normally but don't rank there.
