# Slot engine — security audit (2026-11-18)

Scope: everything in `services/slot-engine` that came from https://github.com/slotopol/server (commit `db45081`),
our engine layer around it, and the platform code that talks to it (`apps/api/app/Platform/Slots/Engine`, its
controller, request, config, compose). Owner rules for this audit: no UI change, no gameplay change (the 322 enabled
games keep working exactly, the 25 disabled stay off), no new features; only review, removal of what gameplay doesn't
need, and hardening.

## Plan (written before any change)

1. **Inventory and baseline**
   - Diff our copy against a fresh upstream checkout of the same commit, file by file.
   - Record the gameplay baseline:
     - a SHA-256 of every gameplay source and data file;
     - golden scans: saved screens re-scored and compared win for win;
     - the catalogue (games, RTP sets, features);
     - the measured RTP file.
   - Take screenshots of the casino UI (slot hall and slot screen, desktop and 390 px).
2. **Read every kind of file**: Go (by import: `os`, `net`, `unsafe`, `exec`, `sql`, `syscall`), Lua, YAML, JSON,
   build files and dependencies. Then trace what the running service can reach: HTTP handlers, background goroutines,
   file system, environment, network and logging.
3. **Classify each finding** as needed for gameplay (keep), dead (remove), or hardening (change our layer only).
4. **Dependencies**: `go mod verify`, `govulncheck` (symbol-level), `go mod why` for each module; the base images.
5. **Platform side**:
   - the key is never sent to the browser or written to logs;
   - errors don't leak engine internals;
   - inputs are validated at both ends;
   - the engine is reachable only from inside the network.
6. **After each change**:
   - engine tests, golden replay and source hashes;
   - backend and frontend suites;
   - a live spin of all 322 enabled games through the API;
   - screenshots compared pixel by pixel.
7. **Report**: this file, `SLOT_ENGINE_REMOVED_CODE.md`, `SLOT_ENGINE_DEPENDENCY_AUDIT.md`,
   `SLOT_ENGINE_REGRESSION_REPORT.md`.

## What was inspected

- **Files:** 859 in `services/slot-engine`:
  - 572 Go (all 174 game packages, `game/`, `prng/`, `util/`, `config/`, our `engine/`);
  - 103 Lua;
  - 175 YAML reel and paytable files;
  - JSON (`measured.json`, test data), `go.mod`/`go.sum`, `Dockerfile`, `.gitignore`/`.gitattributes`.
- **No shell or build scripts remain:** the upstream `task/` folder was removed earlier.
- **Method:**
  - every import was listed and every file importing `os`, `net`, `unsafe`, `exec`, `sql` or `syscall` was read;
  - each HTTP route, background goroutine, file and environment access and log call was traced;
  - the symbols linked into the built binary were checked;
  - the engine container was inspected at runtime.
- **Baseline:** our copy differs from a fresh upstream checkout of `db45081` only in the parts already listed in
  `UPSTREAM.md` (removed folders, one Fairy Queen fix, our `engine/`, `main.go`, `Dockerfile`, `config/flags.go`).

## Findings

| # | Severity | Where | Finding | Action |
|---|---|---|---|---|
| 1 | Medium | `engine/server.go` (ours) | Error answers carried Go error text: JSON decode errors (fragments of the input), game faults ("runtime error: index out of range…"), rule errors. | Fixed answers (`bad request`, `unknown game`, `refused`, `internal error`, `unauthorized`); the cause is logged on the engine side only. |
| 2 | Low | `engine/server.go` (ours) | Key check `subtle.ConstantTimeCompare` on raw strings returns early when the lengths differ (leaks the key length by timing). | Both sides hashed with SHA-256 before the comparison. |
| 3 | Low | `engine/spin.go` (ours) | Input bounds left to the games: any string as game id, any bet/fund size, NaN/∞ through Go (JSON can't, but the API should not rely on it), any JSON type as state, trailing data after the body. | `validate()`: id `maker/game` lower-case letters and digits, 0 ≤ bet ≤ 10⁶, 0 ≤ lines ≤ 1024, 0 ≤ fund ≤ 10¹², finite numbers, state a JSON object; one JSON value per request. |
| 4 | Low | `engine/server.go` | `/health` (the only route without the key) told the number of games. | Answers only `{"ok":true}`. |
| 5 | Low | `main.go` (ours) | Default header limit (1 MB) and no idle timeout. | `MaxHeaderBytes` 16 KB, `IdleTimeout` 60 s (read/write timeouts were already set). |
| 6 | Medium | compose (ours) | The engine sat on the default network: no route was used, but outbound internet was possible, and it was reachable from every container. | Own `internal: true` network shared with the API only (proved: HTTPS to the internet fails from it); read-only root file system, all capabilities dropped, `no-new-privileges`, non-root distroless image, no published port. |
| 7 | Medium | game code (upstream) | Cash Farm's bonus can pay a **negative** amount (32 times in 20,000 spins; no other game, all 347 scanned). Cash Farm is one of the 25 disabled games. | Game code unchanged. The API now refuses any engine answer with a negative win or cost, or of the wrong shape, as a fault: nothing charged, logged. |
| 8 | Low | API (ours) | Engine failures were turned into "game unavailable" silently: operators could not see them. | Logged as warnings with the game id and reason only (no key, state or player data). |
| 9 | Low | API (ours) | The compose file's local development key would be accepted in production. | Refused when `APP_ENV=production` (`SlotEngine`). |
| 10 | Info | `util/strutil.go`, `util/safemap.go` (upstream) | Dead code in our build: file-path and environment helpers (`ExpandHomePath` reads the home directory, `Envfmt` reads environment variables) and a thread-safe map, used only by the removed config loader and user/club registries. Not linked into the binary, but an unnecessary source of OS access. | Removed (see `SLOT_ENGINE_REMOVED_CODE.md`); the helpers the games use (`ToID`, `ToLower`, `B2S`) untouched. |
| 11 | Info | `prng/chacha20.go` (upstream) | `StartBackgroundCycling` (GLI-19 state cycling for the CSPRNG) exists but is never started, by upstream either. Spins are still drawn from ChaCha20 seeded by `crypto/rand`. | Unchanged (changing the RNG was out of scope); listed under remaining risks as a possible step. |
| 12 | Info | `game/slot/*_scan.go`, `stat.go` (upstream) | RTP scanners print to stdout. They run only in `slot-engine measure`/tests, never in the server. | Kept (RTP calculation, owner rule). |
| 13 | Info | API `screen`/`state` (ours) | The player gets the engine's whole game state (reels as `grid`, plus e.g. cascade reel positions `seed`, expanding symbol, bonus counters). The reel strips are public in the source and the player makes no choice that this could inform (falls play on by themselves). | Kept as is: trimming it would change the API the screen uses; noted as an accepted low risk. |

**Nothing found of these kinds:**
- analytics, telemetry, advertising or tracking code;
- outgoing network calls (the only one, Brevo e-mail, left with the removed `api/` earlier);
- database access, command execution, plugins or `syscall` use;
- file reads at run time (the reels are embedded at build time; nothing in the server opens a file);
- secrets in the code (the one example password lived in the removed upstream `docker-compose.yml`).

**Unsafe code:** `unsafe` appears only in `util` (zero-copy string ↔ bytes) and `prng/chacha20_*` (a byte view of the
generator's own buffer); both are bounded and read-only on their own memory.

**Lua:** the 103 Lua files are RTP worksheets for authors. No Lua runtime is in the module graph, so they can't run;
they are kept as gameplay documentation.

## Platform side (checked, unchanged unless listed above)

- **Key handling:** the key lives only in server config (`config/services.php`, environment). It isn't in the web
  build, `/config` or any response; a test checks the responses.
- **Inputs:** players send only `bet`, `lines` and `key` (Form Request: integers in range, key pattern). The game
  state sent to the engine always comes from our database, never from the browser.
- **Limits:** `throttle:slot-spin` (120/min per user), the slot wallet's per-round win cap, and one transaction under
  the player's row lock.
- **Errors to players:** codes only (`slot_game_off`, `slot_bet_limits`, `insufficient_fishkas`), no engine detail.

## Outside this scope (reported, not changed)

`composer audit` lists 11 advisories in the platform's PHP framework, affecting 6 packages (laravel/framework 13.38 <
13.40: task output injection in concurrency, low; symfony/http-foundation, http-kernel, routing, mime, html-sanitizer:
trusted-proxy, host-in-URL, e-mail header injection and sanitizer bypasses), plus three abandoned `dragon-code/*`
packages. They concern the whole platform, not the slot engine. The fix is a framework update (`composer update
laravel/framework "symfony/*"`) with a full test run, best as a separate step. npm: 0 production vulnerabilities.

## Remaining risks

- The 25 disabled games still contain their faulty code (e.g. negative or huge pays). They stay off; the API also
  refuses negative answers. Switching one on in the admin would make it pay what `GAME_FEATURES_AUDIT.md` measured.
- The RNG's background cycling (finding 11) is off, as upstream ships it.
- Game state exposure (finding 13), accepted.
- Base images are pinned by tag (`golang:1.27-bookworm`, `distroless/static-debian12:nonroot`), not by digest.
- Trademark and game-design risk of the makers' names (ADR 0026), the owner's decision.
