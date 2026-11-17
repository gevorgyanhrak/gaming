# Slot engine — removed code

Everything from slotopol/server (`db45081`) that is not in `services/slot-engine`, and why. Removed in two steps: the
integration (2026-11-17, ADR 0026) and this security audit (2026-11-18). Nothing that plays a game was removed: all
174 algorithms, 175 reel/paytable files, 103 Lua RTP worksheets, the RNGs and the RTP scanners are byte-for-byte
upstream except one marked bug fix (Fairy Queen, `UPSTREAM.md`).

## Removed in the security audit (2026-11-18)

| What | Lines | Why | Was it needed for gameplay? |
|---|---:|---|---|
| `util/strutil.go`: `ToSlash`, `JoinPath`, `JoinFilePath`, `PathSeparator`, `PathName`, `ExpandHomePath`, `VarCharFirst`, `Envfmt` | 304 (3-line note added) | File-path and environment helpers of the removed configuration loader: `ExpandHomePath` reads the home directory, `Envfmt` reads environment variables; no remaining code calls them. | No — not referenced by any game or by our engine (checked by whole-word search and by the build). |
| `util/strutil_test.go`: examples of those helpers | 76 | Tested removed code. | No |
| `util/safemap.go` (`RWMap`) | 415 | Thread-safe map for the removed user/club registries. | No — unreferenced. |

Kept in `util`: `B2S`, `S2B`, `ToLower`, `ToUpper`, `ToKey`, `ToID`, `VarChar`, bit sets, Unix time — `ToID`/`ToLower`/
`B2S` are used by the game registry and filters; the others are small pure helpers tested in place.

## Removed at integration (2026-11-17), with the reason

| What | Why |
|---|---|
| `api/` (Gin HTTP API, 49 routes) | Sign-up/sign-in, JWT, e-mail codes, clubs, wallets, jackpot fund, `/memusage`, `/diskusage` — our platform owns users and money; host details must not be exposed. Replaced by `engine/server.go` (3 routes). |
| `core/` | Users, sessions with **user-agent profiling** (`uasurfer`), club "batbank" money, SQL storage (xorm). The two gameplay parts (embedded reel loading, game list) were rewritten into `engine/load.go` and `engine/links.go`. |
| `config/config.go`, `config/cfgpath.go` | Database DSNs, JWT key, **Brevo e-mail API key/endpoint** (the only outgoing network call), config-file search. `config/logger.go` kept (games log through it). |
| `appdata/` | SQLite schema, new-user template, server config. |
| `cmd/` | CLI including the web server; replaced by `main.go` (`serve`, `list`, `measure`). |
| `game/keno/` | Keno — excluded by the owner (2026-11-16). |
| `generator/` | Offline Lua reel-composition tools for authors. |
| `task/` | Build scripts and a JWT generator. |
| `docs/`, `.github/` | Upstream documentation and CI. |
| `Dockerfile`, `docker-compose.yml` | Replaced; the compose file had an example MySQL password and published 8080/3306. |
| `util/batcher.go` | Database batch writer (xorm). |
| Dependencies | gin, golang-jwt, xorm + MySQL/Postgres/SQLite drivers, uasurfer, go-disk-usage, viper, cobra (and their trees). |
