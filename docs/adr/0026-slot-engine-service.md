# ADR 0026 — Slot engine service from slotopol/server, with the makers' game names

- Status: accepted (2026-11-17)
- Builds on: ADR 0023 (slot wallet), ADR 0024 (house slots), ADR 0015 (Fishkas are play money)
- Changes: ADR 0024's "slotopol's own games carry commercial titles and brands, not copied"

## Context

The owner asked to integrate the complete slot gameplay of [slotopol/server](https://github.com/slotopol/server)
(MIT) into the casino, organised by the original makers, with our own users and wallet. The audit
(`docs/casino/SLOT_ENGINE_AUDIT.md`) found that its 353 games re-create commercial slots under their makers'
trademarks (Novomatic, NetEnt, IGT, Aristocrat, Playtech, Play'n GO, BetSoft, AGT, CT Interactive, Megajack); the
MIT licence covers the author's code, not those names or game designs. Asked, the owner chose to keep the makers'
names and game names ("Brands, as the brief says"). This ADR records that choice and its risk.

## Decision

- **A separate Go service** `services/slot-engine` (the upstream games, prng and loader unchanged except one marked bug
  fix; the users, clubs, wallet, SQL, JWT and e-mail parts removed — `UPSTREAM.md`) with a stateless internal API:
  `GET /v1/games`, `POST /v1/spin` (state in, next state and wins out), `GET /health`. It runs on the Docker network
  only (no published port), behind a shared key (`SLOT_ENGINE_KEY`, compared in constant time), as a non-root
  distroless image. It knows games, never people or Fishkas.
- **Our platform owns everything else**: provider row `slotopol` (`App\Platform\Slots\Engine`), one `slot_games` row
  per engine game with its maker in `studio` and the engine's description in `meta`, synchronised by
  `php artisan slots:engine-sync` (never a fixed list; admin changes kept). `EngineSpins` plays a spin under the
  player's row lock: a paid spin is a round (its key), charged bet × lines (or the game's own price for ways games)
  through `SlotWallet`; free games and cascade falls cost nothing and pay into that round; wins are whole Fishkas
  (rounded down); the game state is kept per player and game (`slot_engine_states`), so a reload continues free
  games; every spin is a `slot_spins` row; a repeated key replays without charging; an engine failure charges nothing.
- **Only verified games are switched on**: the engine measures the RTP each game really pays through this path
  (`engine/measured.json`); 322 of 347 are within 3 points of their label, 25 are off (`GAME_FEATURES_AUDIT.md`).
- **RTP** is an admin setting (`slots.engine_rtp`, default 95); each game uses its closest reel set (verified at 95).
- **Keno** stays out (owner, 2026-11-16).

## Consequences

- Trademark and game-design risk is the owner's accepted decision; the engine's code licence (MIT) is honoured in
  `services/slot-engine/LICENSE` and `docs/THIRD_PARTY_LICENSES.md`. Fishkas stay play money: no purchase, no cash-out.
- No art comes with the engine: the web app draws a generic slot screen (symbols by number and role) for every game.
- Progressive jackpots run with no fund (their jackpot part pays nothing) and the original double-up gamble is not
  offered.
