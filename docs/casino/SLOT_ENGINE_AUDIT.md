# Slotopol server — source audit (2026-11-17)

Source: https://github.com/slotopol/server, commit `db45081` ("independent EvD & Efs."), cloned with `--depth 1` into
`services/slot-engine/`. **Outcome:** the owner chose option 3 (makers' names) — ADR 0026; the removals below were
done (`services/slot-engine/UPSTREAM.md`), the integration is in `SLOT_ENGINE_INTEGRATION.md`. 1,069 files, 9.7 MB.

## Licence

- Code: **MIT**, © schwarzlichtbezirk 2023–2026 (`LICENSE`). Compatible with our project; the notice must stay.
- **Flag — game identities are not covered by that licence.** Every game is a re-creation of a commercial slot,
  registered under the real product and maker names (`game/slot/**/*.go`, `AlgInfo.Aliases`), e.g.
  `{Prov: "Novomatic", Name: "Book of Ra Deluxe"}`, `{Prov: "NetEnt", Name: "Gonzo's Quest"}`,
  `{Prov: "Novomatic", Name: "Angry Birds"}`. The reel strips and paytables in `*_data.yaml` are presented as
  matching the originals (comments link to the commercial games). The MIT licence lets us use the author's *code*; it
  cannot grant rights to Novomatic's, NetEnt's, IGT's, Aristocrat's, Playtech's, Play'n GO's, BetSoft's, AGT's,
  CT Interactive's or Megajack's trademarks and game designs. Showing these games under those names and providers
  would present them as those companies' products. Decided by the owner: names kept (ADR 0026).
- The repository has no artwork: it is a server only. Every game would need our own symbols and screens.

## What the repository contains

| Part | Files | Gameplay? | Verdict |
| --- | --- | --- | --- |
| `game/slot/<provider>/<game>/` — algorithms, `*_data.yaml` reels and paytables, `*_scan.go` RTP scanners, `*.lua` RTP worksheets | 174 algorithm packages, 173 data files, 103 Lua scripts | yes | keep (the Lua files are offline RTP worksheets — the server never runs them) |
| `game/slot/*.go` — lines, grid, cascade, reels, parsheet, brute force / Monte Carlo scanners, stats | 9 | yes | keep |
| `game/*.go` — loader, filters, algorithm registry, math | 5 | yes | keep |
| `prng/` — ChaCha20 (reseeded on a ticker), PCG, wyrand, xoshiro256++ | 6 | yes (randomness) | keep |
| `util/` — bitsets, safe map, string helpers, batcher | 7 | partly | keep what the engine imports |
| `game/keno/` — 6 keno games | 6 | keno | **exclude** (owner 2026-11-16: no keno) |
| `api/` — Gin HTTP API, 49 routes: signup/signin/JWT, email codes, clubs, wallet (`/wallet/add`, `/cashin`), jackpot fund, `/memusage`, `/diskusage`, spins | 11 | only `/spin`-style calls | **remove**; our Laravel API stays the only public API |
| `core/` — sessions with user-agent parsing (`uasurfer`), club "batbank" money, SQL storage (xorm: SQLite/MySQL/Postgres), startup | 6 | no | **remove** (users, clubs, balances, spin log belong to our platform) |
| `config/` — viper config, JWT access key, **Brevo email API** key and endpoint | 3 | no | remove; replace by a minimal engine config |
| `appdata/` — SQLite schema, new-user template, server config | 3 | no | remove |
| `cmd/` — cobra CLI: `web`, `list`, `scan`, `data` | 5 | `list` / `scan` useful | keep `list` / `scan` for RTP checks; drop `web` |
| `generator/` — 163 Lua reel-composition and RTP scripts for authors | ~280 | offline tooling | keep out of the runtime (documented only) |
| `task/` — build scripts, `jwtgen` | 9 | no | drop (our Docker build replaces them) |
| `Dockerfile`, `docker-compose.yml` | 2 | no | replace; the compose file has an example MySQL password (`jB44t78dHv`) and publishes ports 8080 and 3306 |

## Network, telemetry, tracking

- **Outgoing requests:** one, `api/auth.go:362` — POSTs sign-up/verification e-mails to Brevo (`Cfg.BrevoEmailEndpoint`,
  needs `SLOTOPOL_BREVOAPIKEY`). Part of the user system being removed. No other `http.Get/Post/NewRequest`,
  `net.Dial` or `os/exec` in the Go code.
- **Telemetry / analytics / ads:** none sent anywhere. `core/session.go` parses each client's user agent (`uasurfer`)
  into the session record — local client profiling, removed with the session system. `/memusage` and `/diskusage`
  expose host details — removed with the API.
- URLs in the source are comments (links to the commercial games' review pages, YouTube videos, the ECMAScript spec).
- **Background work:** the web server's goroutine (`cmd/web.go`), the session/club flusher (`core/startup.go`) —
  both removed; ChaCha20's reseeding ticker (`prng/chacha20.go`) and the scanners' worker goroutines — engine, kept.

## Dependencies (go.mod, Go 1.27)

Engine needs: `go.yaml.in/yaml/v3` (reel data), `klauspost/cpuid` (PRNG selection), `go.uber.org/atomic`,
`golang.org/x/sync`, `spf13/cobra|pflag` (CLI). No Lua runtime: the Lua files are worked RTP sheets run by hand.
Only for the parts being removed: `gin-gonic/gin`, `golang-jwt/jwt`, `xorm`, `go-sql-driver/mysql`, `lib/pq`,
`mattn/go-sqlite3` (cgo), `LumenResearch/uasurfer`, `schwarzlichtbezirk/go-disk-usage`, `golang.org/x/crypto`,
`spf13/viper`.

## Counts (from the source, not the README)

353 game names on 174 slot algorithm packages (+6 keno): CT Interactive 149, Novomatic 86, AGT 65, NetEnt 22,
Playtech 8, Aristocrat 6, IGT 5, Megajack 4, Play'n GO 3, BetSoft 3, Slotopol 2 (both keno).

## How it would fit (once decided)

A Go service on the internal Docker network only (no published port), holding no users, money or sessions:
`POST /spin {algorithm, rtp, bet, lines, state}` → grid, wins, feature state. Laravel's existing house slot
provider (`Platform/Slots`, ADR 0023/0024: `HouseSpins`, `slot_spins`, unique transaction ids, win cap, rollback)
debits the bet, calls the engine, credits the win and records the spin; the free-games state lives in our
`slot_spins`, so a session survives a reload. Fishkas only, as everywhere.

## Decision needed from the owner

1. **Own names and art (recommended):** the engine's maths under our own game titles and symbols, no maker names or
   logos; provider shelves become our own themes. Residual risk: the reel strips and paytables still reproduce
   commercial games.
2. ~~Slotopol's own games only~~: its 2 own games (Fire Keno, Keno Centurion) are keno, which the owner excluded — so
   this option means no slots at all.
3. **As the brief asks (makers' names and game names):** not advised without licences from those companies.
4. **Stop here:** delete the clone.
