# Architecture

This document is the approved Phase 1 plan, kept current as the project evolves.
Individual decisions and their reasoning live in [`adr/`](adr/README.md).

## 1. System overview

```
                 ┌────────────── Browser (React SPA) ───────────────┐
                 │  REST (TanStack Query)        WebSocket (Echo)   │
                 └───────┬─────────────────────────────┬────────────┘
   static CDN ◀── web    │                             │
                 ┌───────▼────────┐            ┌───────▼────────┐
                 │  api (Laravel) │──publish──▶│ reverb (WS)    │  scales via Redis pub/sub
                 │  stateless × N │            │ long-running   │
                 └──┬──────┬──────┘            └────────────────┘
                    │      │ dispatch
          ┌─────────▼┐  ┌──▼──────────────────────────────┐   ┌───────────┐
          │  MySQL   │  │ horizon workers                  │   │ scheduler │ (1 instance)
          │ durable  │  │ queues: game-high (bots, timers) │   └───────────┘
          └──────────┘  │ notifications (SMS/email), stats │
                        └──────────────┬──────────────────┘
          ┌────────────────────────────▼───────────────────────┐
          │ Redis A (noeviction, AOF): game state, locks,        │
          │          matchmaking queues, presence, Horizon       │
          │ Redis B (allkeys-lru):  cache, sessions, rate limits │
          └─────────────────────────────────────────────────────┘
```

Locally a single Redis runs with `noeviction`. **In production, game state and cache must be
separate Redis instances**: cache eviction must never be able to remove live game state.

### Core principles

| Principle | Consequence |
|---|---|
| The server is the only rules authority | Clients send intents; the backend validates every move and owns all state and outcomes. The server returns `legal_actions`; no game rules are implemented in TypeScript. |
| Commands over HTTP, events over WebSocket | `POST /rooms/{id}/actions` reuses Laravel auth, validation, policies, rate limiting and idempotency. Reverb only pushes updates. All five games are turn-based, so no tick loop is needed. |
| Pure game engines | `app/Games/*/Engine` has no Laravel, DB or Redis dependency: deterministic, exhaustively testable, replayable. |
| Hot state in Redis, durable move log in MySQL | One `game_moves` row per accepted move is the history, the replay source and the crash-recovery source. Presence, clocks, heartbeats and queues never touch the database. |
| Bots use the human write path | `BotTurnJob` → `GameActionService`, given only the bot seat's redacted view. |
| Shared infrastructure, game-specific engines | Auth, rooms, matchmaking, timers, history and stats are written once; a new game adds an engine, bot, definition and UI. |

## 2. Technology (pinned in Phase 2, October 2026)

| Layer | Choice |
|---|---|
| Backend | PHP 8.5, Laravel 13, Sanctum 4 (SPA cookie auth), Reverb 1.x, Horizon 5, Scramble (OpenAPI), spatie/laravel-translatable |
| Backend quality | Pest 5, Larastan 3 (level 6), Pint |
| Frontend | React 19, TypeScript 6.0 (strict), Vite 8, React Router 8, TanStack Query 5, Tailwind CSS 4, i18next + react-i18next |
| Frontend quality | ESLint 10 + typescript-eslint (type-checked), Prettier, Vitest 5, Testing Library, MSW 3 |
| Data | MySQL 8.4 LTS (InnoDB, utf8mb4), Redis 7.4 |
| Local infra | Docker Compose: nginx + php-fpm, reverb, horizon, scheduler, mysql, redis, mailpit |

Version notes:
- TypeScript is held at 6.0 because typescript-eslint does not support TypeScript 7 (the native port) yet.
- `openapi-typescript` requires TypeScript 5, so type generation runs in an isolated `npx` environment (`npm run api:types`) instead of being a project dependency.
- Added in Phase 3: zod, react-hook-form, spatie/laravel-permission, propaganistas/laravel-phone, laravel-lang (translations, dev only).
- Added later, when first needed: Zustand, laravel-echo/pusher-js (Phase 5), Filament (Phase 4).

## 3. Repository layout

```
game-platform/
├── apps/api/        Laravel API          (see "Backend structure")
├── apps/web/        React SPA            (see "Frontend structure")
├── docker/          php (dev/prod image), nginx, mysql init
├── docs/            this file, ADRs
├── compose.yaml     local development stack
├── Makefile         developer commands
└── .github/workflows/  per-app CI (GitHub Actions: api.yml, web.yml)
```

### Backend structure (`apps/api/app`)

```
Http/Controllers/Api/V1/   thin controllers (Auth, Me, Games, Matchmaking, Rooms)
Http/Requests|Resources|Middleware
Models/  Policies/
Platform/                  Auth (verification, SMS gateway), Settings, Audit, Presence
Gameplay/                  shared game infrastructure; knows no rules
  Contracts/               GameEngine, BotStrategy, GameDefinition, RandomSource
  Registry/  Rooms/  Actions/GameActionService.php  Matchmaking/  Lifecycle/  Bots/  Broadcasting/
Games/{Nardi,Chess,Checkers,Durak,Poker}/
  {Game}Definition.php  Engine/  Bot/  {Game}ServiceProvider.php
Filament/                  admin panel (Phase 4)
```

Engine contract (implemented in Phase 5):

```php
interface GameEngine {   // app/Gameplay/Contracts — state is a JSON-serializable array
    public function initialState(GameSetup $setup, RandomSource $random): array;
    public function seatsToAct(array $state): array;
    public function legalActions(array $state, int $seat): array;      // list<Action>
    public function apply(array $state, int $seat, Action $action, RandomSource $random): Transition;
    public function viewFor(array $state, ?int $seat): array;           // redacts hidden information
    public function outcome(array $state): ?Outcome;
}

interface BotStrategy {
    public function chooseAction(array $view, array $legalActions, BotDifficulty $difficulty, RandomSource $random): Action;
}
```

Optional engine contracts (same folder): `HandlesTimeouts` (a default move instead of losing on time),
`DecidesTimeouts` (what running out of time means), `HandlesDepartures` (a leaver is taken out and the
game goes on), `ValidatesActions` (range actions such as poker raises) and `PausesBetweenRounds`
(a break in which nobody acts, e.g. poker shows a hand's result before the next deal: while
`pauseMs()` isn't null and no seat is to act, `GameScheduler` queues `ResumeRoundJob`, which calls
`resume()` through `GameActionService::resume`; the step is logged as a `resume` move with no
participant and replayed like any other; the room's `deadline` is the end of the pause).

### Frontend structure (`apps/web/src`)

```
app/        providers, routes, layouts, QueryClient
pages/      route components composing features
features/   catalog/, platform/, (auth/, profile/, matchmaking/, rooms/ ...)
games/      registry + per-game boards (Phase 6+)
shared/     api/ (client, errors, generated OpenAPI types), i18n/, ui/, config/
test/       MSW server, handlers/fixtures, render helpers
```

Server state lives in TanStack Query, including the current user (`['me']`). Client stores (Zustand,
added when the first game UI needs one) hold only ephemeral UI state, never a copy of authoritative
game state. Sign-in and account routes are lazy-loaded; `RequireAuth`/`RequireGuest` layout routes
own all auth redirects (including where a user goes right after signing in).

## 4. Database (MySQL 8.4)

Conventions: utf8mb4, case-insensitive collation (usernames and emails are unique regardless of
case; emails are also lowercased before storage), `DATETIME` columns in UTC (not `TIMESTAMP`,
which ends in 2038), BIGINT surrogate keys, ULID `public_id` where an id is exposed in URLs or
channel names, CHECK constraints added through `DB::statement` in migrations.

| Table | Purpose / key constraints | Phase |
|---|---|---|
| `games` | slug UNIQUE; translatable name/description/rules (JSON); player counts with CHECK; `is_enabled` (playable) and `is_visible` (listed) | 2 ✅ |
| `users` | username UNIQUE, email UNIQUE NULL, phone (E.164) UNIQUE NULL, nullable password (future social login), verification timestamps, status, locale, last_active_at. CHECK email or phone present | 3 |
| `user_profiles` | display name, avatar, bio, preferences JSON | 3 |
| `user_bans` | ban history (by, reason, expires, revoked); `users.status` is the fast copy | 4 ✅ |
| `verification_codes` | channel, destination, purpose, code hash, attempts, expiry | 3 |
| roles / permissions | spatie/laravel-permission | 3 |
| `platform_settings` | key → JSON value overrides; keys, types, defaults, validation and public visibility are defined in code (`SettingsCatalog`); no secrets | 4 ✅ |
| `content_pages` | privacy, terms and other pages (translatable, publish flag) | 4 ✅ |
| `audit_logs` | actor, action, subject, before/after changes, ip, user agent (append-only) | 4 ✅ |
| `game_modes` | per game: slug, translatable name, player counts, config JSON, matchmaking/private/bot flags. UNIQUE(game_id, slug) | 4 ✅ |
| `bot_profiles`, `bot_game_profiles` | unique bot names, avatars; which games and difficulties each bot plays | 4 ✅ |
| `game_rooms` | public ULID, type, status, invite code, `state_version`, final state, end reason | 5 |
| `game_participants` | seat, user **or** bot (CHECK exactly one), result, placement. UNIQUE(room, seat), UNIQUE(room, user). FKs on user/bot use RESTRICT (MySQL forbids cascades on CHECK columns) | 5 |
| `game_moves` | append-only: seq, type, payload JSON, `client_action_id`. **UNIQUE(room, seq) and UNIQUE(room, client_action_id)** | 5 |
| `user_game_stats` | per user and game: played/wins/losses/draws/streaks, rating column (unused in MVP) | 5 |

Deliberately absent: a `game_results` table (outcome belongs to the room, per-player results to
participants), presence tables (Redis only), per-move state snapshots (Redis plus replay).
Long-term growth of `game_moves` is handled by archiving finished games' moves, since MySQL
partitioned tables cannot have foreign keys.

Critical writes use `DB::transaction(..., attempts: 3)` to retry InnoDB deadlocks.

## 5. API (`/api/v1`)

| Group | Endpoints | Phase |
|---|---|---|
| Infra | `GET /up` (liveness), `GET /api/health/ready` (readiness: DB and Redis decide the status; queue and WebSocket are reported) | 2 ✅ |
| Public | `GET /config`, `GET /games`, `GET /games/{slug}` | 2 ✅ |
| Public | `GET /pages/{slug}` ✅, `GET /users/{username}` ✅, `GET /stats/online` | 3–5 |
| Auth | `POST /auth/register`, `/login`, `/logout`, `/verification/send`, `/verification/verify`, `/password/forgot`, `/password/reset`; `GET /auth/username-available` | 3 |
| Me | `GET /me`, `PATCH /me/profile`, `POST\|DELETE /me/avatar`, `PATCH /me/settings`, `PUT /me/password`, `POST /me/contact`, `GET /me/games`, `GET /me/stats`, `GET /me/active-room` | 3–5 |
| Matchmaking | `POST /matchmaking/tickets`, `GET\|DELETE /matchmaking/tickets/current`, `POST /matchmaking/tickets/current/accept-bot` | 5 |
| Rooms | `POST /rooms`, `POST /rooms/join`, `GET /rooms/{id}`, `GET /rooms/{id}/state`, `POST /rooms/{id}/actions` (`GET /rooms/{id}/moves` is planned, backlog TASK-021) | 5 |

Responses use Laravel API Resources (`{ data: ... }`); errors are `{ message, errors? }`, with a
generic body for 404. The OpenAPI spec is served at `/docs/api.json` (UI at `/docs/api`, local
environment only) and drives the frontend's generated types.

Broadcast channels: `private-user.{id}` (room updates with that player's own redacted view,
matchmaking, account events) and `presence-room.{room}` (who is connected; no game state).

## 6. Authentication

- Register with **either** email or phone (E.164); verification by 6-digit code on both channels (hashed, 10-minute expiry, 5 attempts, resend cooldown, per-destination/IP/account limits).
- SMS through an `SmsGateway` interface (log driver locally). SMS fraud protection: country allowlist, CAPTCHA before sending, prefix limits.
- Passwords: `Password::defaults()` with `uncompromised()`, Argon2id. Password reset by email link or phone OTP. Responses never reveal whether an account exists.
- Sanctum SPA cookies (HttpOnly, SameSite=Lax, CSRF via `XSRF-TOKEN`), session regenerated on login and password change. CORS and stateful domains locked to the SPA origin.
- Bans and deactivation: middleware check, session and token revocation, channel authorization denied, live sockets kicked.
- Roles: `super_admin`, `admin`, `moderator`, `user`; policies everywhere; admin MFA.

## 7. Real-time gameplay

Action pipeline: policy → rate limit → Redis room lock → load state (rebuild from `game_moves`
if missing) → idempotency (`client_action_id`) → version check (`expected_version`, else 409) →
engine validation (else 422) → DB transaction (insert move, finish room) → write Redis → unlock →
broadcast per-seat views → schedule turn-timeout and bot jobs (each carrying the `version` it was
created for, so stale jobs do nothing).

- **The turn clock is the authority for abandonment**; presence only drives the UI.
- **Reconnect:** `GET /me/active-room` → `GET /rooms/{id}/state` → resubscribe. Every event carries `version`; a gap triggers a snapshot refetch.
- **Hidden information:** never sent on shared channels. Poker shuffles use a CSPRNG Fisher–Yates; deck order is stored encrypted.

## 8. Matchmaking and bots

Tickets live in the game store per mode; every queue change happens under that mode's lock, so a
ticket is matched exactly once (a human match and a bot acceptance can't both win). Rooms are
formed from the mode's `min_players` oldest tickets; filling larger tables (poker) comes with that
game. `app:matchmaking-sweep` runs every 5 seconds: drops tickets without a heartbeat for 45 s and
applies the bot fallback per game (`auto` / `offer` / `off`, game timeout or platform default). Bots are `bot_profiles` with
per-game difficulty, act after a configurable human-like delay, and always carry a visible "Bot"
badge.

## 9. Admin panel

Filament 5 at `/admin` (optionally `ADMIN_DOMAIN`), on the **`admin` session guard**: same `users`
table, separate login state from the SPA's `web` guard (ADR 0014). Access: `User::canAccessPanel`
(active, has an email, `access admin` permission). Two-factor authentication with an authenticator
app, required unless `ADMIN_MFA_REQUIRED=false` (local development only).

| Module | Contents | Permission |
|---|---|---|
| Dashboard | user counts (cached 60 s), recent registrations. Game statistics arrive with gameplay. | manage users |
| Users | search, filters, profile, ban history; ban/unban (reason, optional expiry), deactivate/activate, roles (super admins only) via `UserModeration` | manage users |
| Games | translated catalog text, images, visibility, order, bot fallback; modes (relation manager). "Playable" unlocks when an engine ships. Games are seeded, never created or deleted here. | manage games |
| Bots | names, avatars, game + difficulty assignments | manage games |
| Settings | `SettingsCatalog` keys (ADR 0013) | manage settings |
| Pages | privacy, terms, others; translations; publish flag | manage settings |
| Audit log | read-only | view audit log |

Super admins hold every permission, but rules that are false for everyone still apply (the audit
log can't be edited, games can't be created or deleted). Every admin action is audited:
explicitly (moderation, settings) or through `AdminChangeObserver` (games, modes, bots, pages).
Maintenance mode is a setting enforced by middleware on the API (config, pages, login and `/me`
stay available); staff bypass it.

## 10. Roadmap

| Phase | Scope | Status |
|---|---|---|
| 1 | Architecture and plan | ✅ |
| 2 | Project setup, tooling, CI, layout, catalog slice | ✅ |
| 3 | Authentication and user management | ✅ |
| 4 | Admin panel (Filament) | ✅ |
| 5 | Gaming infrastructure (built against a test-only TicTacToe engine) | ✅ |
| 6 | Nardi (short nardi first), end to end | ✅ |
| 7 | Checkers (Russian draughts) → Chess → Durak (podkidnoy, 2 players first) → Poker (NL Hold'em, play chips per table session) | ✅ |
| 8 | Hardening, performance, monitoring, deployment | ✅ (see DEPLOYMENT.md) |

## 11. Main risks

| Risk | Mitigation |
|---|---|
| Rules bugs | Pure engines, exhaustive tests, perft for chess, fuzz self-play, written rule specs |
| Concurrent or duplicate actions | Room lock, versions, idempotency keys, DB unique constraints |
| Redis state loss or eviction | Separate noeviction instance with AOF; state can be rebuilt from `game_moves` |
| Hidden-information leaks | Per-seat views; bots get views only; tests assert no leaked cards |
| SMS cost and fraud | Country allowlist, CAPTCHA, limits, provider spending caps |
| PHP bot performance (chess hard level) | Time-boxed search, dedicated queue, Stockfish later |
| Simulated gambling (poker) | Chips have no value and can't be bought or transferred; age gate; legal review of terms |
