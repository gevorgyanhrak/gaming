# Slot engine — where it comes from and what we changed

Source: https://github.com/slotopol/server, commit `db45081` ("independent EvD & Efs."), taken 2026-11-17.
Licence: MIT, © schwarzlichtbezirk 2023–2026 — kept unchanged in `LICENSE` (it must stay with the code).
The original `README.md` is kept as it was; it describes the full upstream server, not this service.

## Kept as it was (the games)

- `game/` — the algorithm registry, loader and maths; `game/slot/<maker>/<game>/` — 174 algorithms for 347 slot
  games: rules (`*_rule.go`), reels and paytables (`*_data.yaml`), RTP scanners (`*_scan.go`), Lua RTP worksheets.
- `prng/` — ChaCha20 (the spins' generator), PCG, wyrand, xoshiro256++.
- `util/` — except `batcher.go` (a database batch writer), `safemap.go` and the file-path/environment helpers in
  `strutil.go` (removed in the security audit, 2026-11-18: unused, see `docs/casino/SLOT_ENGINE_REMOVED_CODE.md`).
- `config/logger.go` — the log helpers the games call.

## Removed (platform parts our platform owns, or nothing needs)

`api/` (Gin HTTP API: sign-up/sign-in/JWT, e-mail codes, clubs, wallets, jackpot fund, host memory/disk reports),
`core/` (users, sessions with user-agent profiling, club "batbank" money, SQL storage via xorm), `config/config.go`
and `config/cfgpath.go` (database DSNs, JWT key, Brevo e-mail API key and endpoint), `appdata/` (SQLite schema,
new-user template), `cmd/` (CLI incl. the web server), `game/keno/` (keno: excluded by the owner), `generator/`
(offline Lua reel tools), `task/` (build scripts, JWT generator), `docs/`, the original `Dockerfile`,
`docker-compose.yml` (example MySQL password, published ports 8080/3306) and `.github/`. Dependencies dropped with
them: gin, golang-jwt, xorm and the MySQL/Postgres/SQLite drivers, uasurfer, go-disk-usage, viper, cobra.

## Added (ours, same MIT terms)

- `engine/` — `load.go` (reel data loading, from the original `core/startup.go`), `links.go` (the game list, from
  `core/links.go` without keno), `spin.go` (one stateless spin: the original `api/slot.go` steps without users, clubs
  or the bank), `server.go` (internal API with a shared key), `measure.go` + `measured.json` (the RTP each game pays
  through our path), tests.
- Security audit 2026-11-18 (`docs/casino/SLOT_ENGINE_SECURITY_AUDIT.md`): fixed error answers, input bounds,
  hashed key comparison, minimal `/health`, server limits; `engine/golden_test.go` + `engine/testdata/` lock every
  game's scoring and the catalogue.
- `main.go` (serve | list | measure), `config/flags.go`, `Dockerfile` (distroless, non-root, no published port).

## Patches to upstream game code

1. `game/slot/novomatic/fairyqueen/fairyqueen_rule.go` `SpinBon`: `ReelNumBon[g.ES]` → `ReelNumBon[g.ES-1]` — symbols
   are 1-based (as the game's own scanner uses them); the original indexed the wrong reel count and crashed when the
   13th symbol was drawn in free games.

## Known upstream behaviour (not changed)

- 25 games pay far from their labelled RTP when played (e.g. Power Stars ~103,000 %, Cash Farm ~65 %); the same
  happens with the game object kept in memory, as the original server did, so it is in the game code. They are
  switched off in our catalogue (see `docs/casino/GAME_FEATURES_AUDIT.md`).
- In cascade games (e.g. Gonzo's Quest) `Slotx.Apply` uses up a free game on every cascade fall, so they pay a few
  points less than their formula; within our 3-point tolerance they stay on.
- The original server rejected spins its club bank could not pay; there is no bank here (Fishkas are play money),
  wins are capped per round by the platform's slot wallet instead.
- Progressive jackpots run with a fund of 0 (no shared pool): their jackpot part pays nothing.
