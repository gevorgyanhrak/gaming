# Slot engine — regression report (security audit, 2026-11-18)

Goal: the same casino UI and gameplay after the cleanup. All results below were measured on the local stack after
the last change (engine rebuilt and running on the internal network).

## Files changed by the audit

| File | Change |
|---|---|
| `services/slot-engine/engine/server.go` | Fixed public error messages, cause logged; SHA-256 before constant-time key compare; `/health` = `{"ok":true}`; single JSON value per request. |
| `services/slot-engine/engine/spin.go` | `validate()` input bounds (`ErrBadInput`). |
| `services/slot-engine/main.go` | `MaxHeaderBytes`, `IdleTimeout`. |
| `services/slot-engine/util/strutil.go`, `strutil_test.go` | Unused path/environment helpers and their examples removed (+3-line note). |
| `services/slot-engine/util/safemap.go` | Deleted (unused). |
| `services/slot-engine/engine/engine_test.go` | Strict consistency check for verified games (Cash Farm, disabled, can pay below zero). |
| `services/slot-engine/engine/hardening_test.go` | New: input bounds, fixed answers, key of any length, methods. |
| `services/slot-engine/engine/golden_test.go`, `engine/testdata/{golden,catalogue}.json` | New: gameplay lock (see below). |
| `apps/api/app/Platform/Slots/Engine/SlotEngine.php` | Rejects malformed or negative engine answers; refuses the development key in production. |
| `apps/api/app/Platform/Slots/Engine/EngineSpins.php` | Logs engine failures (game id and reason only). |
| `apps/api/tests/Feature/Casino/EngineSlotsTest.php` | +2 tests (negative/malformed answers; key never leaves the server, dev key refused in production). |
| `compose.yaml` | Engine on an `internal` network with the API only; read-only, no capabilities, no-new-privileges. |
| Frontend | **No file changed** (`git status apps/web` empty). |

## Gameplay unchanged

| Check | Result |
|---|---|
| SHA-256 of every file in `game/`, `prng/`, `config/` (828 game files incl. 175 reel/paytable YAML and 103 Lua) before vs after | identical; only `util/strutil.go`, `util/strutil_test.go`, `util/safemap.go` differ (removals above) |
| `engine/measured.json` (measured RTP of every game) | identical (`9211c982…3262`) |
| Golden replay: 347 games × 6 saved screens re-scored by each game's scanner | 2,082 / 2,082 identical wins |
| Catalogue (games, makers, grid, lines/ways, price, RTP sets, features) | identical to the snapshot |
| `go test ./engine` — every game spins 60× through saved states, game rules, RTP of the spin path (Sizzling Hot, Book of Ra Deluxe, 100 Jokers), key, no network outside the server, golden, hardening | PASS (also 30 repeated runs of the per-game test) |
| `go test ./util` | PASS |
| Negative-pay scan, all 347 games × 20,000 spins | only Cash Farm (disabled); no enabled game |

## Platform unchanged

| Check | Result |
|---|---|
| Backend suite `make test-api` | **722 passed** (190,508 assertions) |
| `make lint-api` (Pint + Larastan level 6) | no errors |
| Frontend: typecheck, lint, format, `vitest` (61 files), build | **301 passed**, all clean |
| Live, player1 through the API: one spin of every enabled game | **322 / 322** answered; balance = previous − bet + win on every spin |
| Live: cascade (Gonzo's Quest) | 2 falls in 14 spins, each free (bet 0) |
| Live: free games (Book of Ra Deluxe, 1 line) | triggered on spin 29: 10 free games, all bet 0, all in the triggering spin's round, won 15; balance exact throughout |
| Live: a disabled game (Power Stars) | refused, `422 slot_game_off` |
| History (`GET /me/slots/recent`) | lists the games just played, newest first |
| Enabled / disabled games | 322 offered (`GET /slots`), the 25 unchanged off |
| RTP setting / bet ladder / pricing | unchanged (no edits to settings, `EngineSlots::LINE_BETS`, `terms()`) |

## UI unchanged

Screenshots of the slot hall, the hall filtered to NetEnt, Book of Ra Deluxe and Gonzo's Quest, at 1440×900 and
390×844 (full page, reduced motion), taken before any change and after the last one: **8 / 8 identical, 0 pixels
different**. (Two pre-change runs of the phone hall pages differed in page height from each other — the
"Played recently" row loading — so the comparison used the first run, which the after-run matched exactly.)

## Isolation, verified at run time

- Engine network `internal=true`: HTTPS to the internet from it fails; the engine answers `401` there without the key.
- Container: read-only root file system, `cap_drop: ALL`, `no-new-privileges`, user `nonroot`, no published port;
  `localhost:8090` is not reachable from the host.
- The API reaches the engine (`/health` ok, 347 games listed).
