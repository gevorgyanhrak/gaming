---
name: game-platform-development
description: How to add or change a game on game-platform (apps/api + apps/web) — engine, bot, definition, seeding, Fishka economy, rooms/matchmaking, board UI, tests. Use when implementing a new game or touching shared game infrastructure.
---

# Adding a game to game-platform

Read only what the step needs. Deeper references are the files named here; backend idioms are in the
`laravel` skill, project rules in `.claude/agents/gaming-agent.md` (and `CLAUDE.md`).

## The shape of a game (nothing else changes per game)
| Piece | Where | Model to copy |
|---|---|---|
| Engine (pure PHP: state in, action in, state out) | `apps/api/app/Games/<G>/Engine/<G>Engine.php` | 2-seat board: `Checkers`; cards/many seats: `Durak`; chips: `Poker`, `Blackjack` |
| Bot (`BotStrategy`, sees only `viewFor($seat)`) | `app/Games/<G>/Bot/<G>Bot.php` | `PokerBot` |
| Definition + ServiceProvider | `app/Games/<G>/<G>Definition.php`, register in `bootstrap/providers.php` | `PokerDefinition` |
| Catalog row, modes, bot profiles | `database/seeders/{GameSeeder,GameModeSeeder,BotSeeder}.php` (idempotent; `BotSeeder` adds profiles for games without any) | — |
| Board UI | `apps/web/src/games/<slug>/<G>Board.tsx` + `boards`/`traits` in `games/registry.ts` | `poker/PokerBoard.tsx` |
| Art | `features/catalog/components/GameArt.tsx` (SVG scene per slug) | — |
| i18n | `apps/web/src/shared/i18n/locales/{en,ru,hy}.json`, keys under `<slug>.*` | — |

## Engine contract (`app/Gameplay/Contracts`)
`GameEngine`: `initialState(GameSetup, RandomSource)`, `seatsToAct`, `legalActions`, `apply → Transition(state, events)`,
`viewFor(state, ?seat)` (hide secrets: hole cards, shoe, opponents' hands), `outcome → ?Outcome(winners, reason, scores)`.
Empty `winners` = draw for everyone. Randomness only via `RandomSource` (recorded; replays use `ReplayRandom`).
Optional: `ValidatesActions` (ranges, e.g. `{type:'bet',min,max}` → client sends `{type:'bet',amount}`),
`HandlesTimeouts` (default move), `DecidesTimeouts`, `HandlesDepartures` (3+ seat games go on),
`PausesBetweenRounds` (`pauseMs`/`resume`), `PlaysForChips` (`buyIn`, `chipsOf`), `PlaysInTeams` (`teamOf`: a leaver's
partners lose too). Validate range-action payloads independent of key order (only `Action::fromArray` sorts keys).
The platform owns turns, timers (`turn_seconds` in mode config), bots, persistence, broadcasting.
`TurnTimeoutJob` times out `seatsToAct[0]` only: keep one seat to act at a time.

## Fishka economy (never touch balances directly)
- Stake games (default): each person stakes the table bet on seating; winners share the pot (`RoomStakes::settle`).
- `PlaysForChips` games: the whole balance becomes chips; `chipsOf` is paid back on leave/end; a person at 0 is
  retired. `chipsOf` must include chips at risk in the current round, or a player who bets everything is "busted".
- Groups (`play_groups`, ADR 0016) give `options`: chips groups add `small_blind`, `big_blind`, `table_min_bet`, `table_max_bet`.
- All movement goes through `Platform/Fishka/FishkaWallets` (row lock, ledger, idempotency keys).

## Rooms, bots, matchmaking
`RoomController::store` (`type` bot|private; `opponents` 0 = solo when the mode's `min_players` is 1),
`RoomService` (seats, start), `Matchmaker` (fills to `max_players`, or seats whoever waited `table_wait_seconds`),
`BotSelector::pickMany`, `BotTurnJob` → `GameActionService` (the only write path). Results/stats/XP: `GameFinisher`.

## UI rules
Boards render `state.view` and call `onAction(one of state.legal_actions)` — never rules in TS.
Props: `BoardProps` in `games/registry.ts`. Shared cards: `games/cards` (`PlayingCard`, `CardBack`, cards "TH", "AS").
Tables: `.table-rim` + `.table-felt` (index.css), motion only `motion-safe:`/reduced-motion aware, i18n en/ru/hy,
phone first (390 px) and desktop. Lobby, matchmaking, waiting table, game-over panel are shared — don't rebuild them.

## Tests and done
- Unit: `apps/api/tests/Unit/Games/<G>EngineTest.php` with a scripted `RandomSource` (rules, legal moves, outcomes).
- Feature: `tests/Feature/Games/<G>TableTest.php` (API start, play through, Fishkas settle). Bot: plays a full game.
- Web: `games/<slug>/<G>Board.test.tsx` with `roomStateFixture`.
- Run `make lint-api test-api`, `make types`, and in `apps/web`: `npm run typecheck lint format:check test build`.
- Then `make restart` locally so workers load the new engine; seed with `make seed` (or `db:seed`).
