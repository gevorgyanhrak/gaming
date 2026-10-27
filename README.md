# Game Platform

An online platform for classic board and card games (Nardi, Chess, Checkers, Durak, Poker),
played in real time against other people or against bots. Recreational play only: no real money.

- **Backend:** Laravel 13 API (`apps/api`): authoritative game state, matchmaking, bots, admin panel
- **Frontend:** React 19 SPA (`apps/web`): Vite, TypeScript, TanStack Query, Tailwind CSS
- **Infrastructure:** MySQL 8.4, Redis, Laravel Reverb (WebSockets), Horizon (queues)

Architecture and decisions: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) and [`docs/adr/`](docs/adr/README.md). Running it in production: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

## Status

| Area | State |
|---|---|
| Project setup, Docker, CI, linting, tests | ✅ Done (Phase 2) |
| Public layout, game catalog, game info pages (en/ru/hy) | ✅ Done; reads real data from the API |
| Health checks, public config endpoint, OpenAPI docs | ✅ Done |
| Registration and login by email or phone, verification codes, password reset, profiles, avatars, settings, roles | ✅ Done (Phase 3). The SMS provider is not chosen yet (see below). |
| Admin panel: users and moderation, games and modes, bots, settings, content pages, audit log, dashboard | ✅ Done (Phase 4). Game statistics join the dashboard with gameplay. |
| Gameplay infrastructure: rooms, matchmaking with bot fallback, bots, turn timers, real-time updates, history and statistics | ✅ Done (Phase 5), tested with a test-only engine. Real games plug in from Phase 6. |
| Playable games | ✅ All five, with bots easy/medium/hard: Nardi (short nardi), Checkers (Russian draughts), Chess (with chess clocks), Durak (podkidnoy, 2–6 players) and Poker (no-limit hold'em sit-and-go, 2–6 seats, play chips only). Matchmaking fills poker and durak tables of up to six (a full table starts at once, otherwise everyone waiting sits down after 20 seconds). |

## Prerequisites

- Docker Desktop (Compose v2). PHP does **not** need to be installed locally; it runs in containers.
- Node.js 22 or newer (the frontend dev server runs natively for fast hot reload)
- `make`, `openssl`, `perl` (preinstalled on macOS and most Linux distributions)

## Quick start

```sh
make setup   # env files, images, composer/npm install, keys, migrations + seed data
make web     # frontend dev server
```

Open http://localhost:5175.

`make setup` is safe to re-run: it never overwrites existing `.env` files or keys, and the
seeders never overwrite data that already exists.

## Services and ports

| Service | URL / port | Notes |
|---|---|---|
| Web (Vite) | http://localhost:5175 | Proxies `/api`, `/sanctum` and `/broadcasting` to the API |
| API (nginx + php-fpm) | http://localhost:8000 | `/up`, `/api/health/ready`, `/api/v1/...` |
| Admin panel | http://localhost:8000/admin | See "Admin panel" below |
| API docs | http://localhost:8000/docs/api | OpenAPI UI (local environment only) |
| Reverb (WebSocket) | ws://localhost:8080 | |
| Mailpit | http://localhost:8025 | Catches all outgoing email locally |
| MySQL | localhost:3307 | User `game` / password from `.env`; databases `game_platform` and `testing` |
| Redis | localhost:6380 | |

Host ports are set in the root `.env`. If one is taken on your machine, change it there and run `make up`.

## Everyday commands

```sh
make help            # list all commands
make up / make down  # start / stop backend services (data is kept)
make logs            # follow backend logs
make sh              # shell inside the API container
make artisan cmd="route:list"
make migrate         # run new migrations
make seed            # (re)seed reference data, non-destructive
make fresh           # DESTRUCTIVE: rebuild the dev database from scratch
make test            # backend + frontend tests
make lint            # Pint, Larastan, tsc, ESLint, Prettier
make types           # regenerate frontend API types from the running API
```

## Project structure

```
apps/api/       Laravel API
apps/web/       React SPA
docker/         PHP image (dev and prod targets), nginx and MySQL configuration
docs/           architecture and ADRs
compose.yaml    local development stack
Makefile        developer commands
.github/      CI (GitHub Actions): lint, test and build each app independently
```

## Configuration

| File | Purpose |
|---|---|
| `.env` (from `.env.example`) | Docker Compose: host ports, local database credentials |
| `apps/api/.env` (from `apps/api/.env.example`) | Laravel settings. Under Compose, `DB_*`, `REDIS_*`, `MAIL_*` and `REVERB_HOST/PORT` are injected by `compose.yaml` and take precedence. |
| `apps/web/.env` (from `apps/web/.env.example`) | `VITE_API_BASE_URL` (empty locally) and the dev proxy target |

`.env` files are git-ignored. Never commit real credentials. The example values are for local
development only.

## Admin panel

http://localhost:8000/admin, a separate login from the player app (same accounts).

1. Register a normal account in the app (with an email address: the admin login uses email).
2. Grant it a role: `make artisan cmd="app:role you@example.com super_admin"` (or `admin`, `moderator`).
3. Sign in at `/admin`.

- **Two-factor authentication** (authenticator app) is required for admins by default (`ADMIN_MFA_REQUIRED=true`). The local `.env` turns it off for convenience; keep it on everywhere else.
- **What each role can do:** moderators manage users and read the audit log; admins also manage games, bots, settings and pages; super admins can do everything, including changing roles and moderating staff.
- **Platform settings** (name, logo, languages, maintenance mode, registration, verification, matchmaking defaults, session lifetime) live in the database and override the environment defaults. Secrets never go there: they stay in environment variables.
- **Maintenance mode** shows players a maintenance screen; staff can still sign in and use the app.
- **Privacy policy and terms of service** are created as unpublished drafts. Write and review them in Content → Pages before publishing. Once the terms are published, new players must accept them to register.
- To serve the panel on its own host, set `ADMIN_DOMAIN` (e.g. `admin.example.com`).

## Accounts and roles (local development)

- **Verification and reset codes by email** arrive in Mailpit: http://localhost:8025.
- **Players for trying things out:** `docker compose exec api php artisan db:seed --class=LocalPlayersSeeder` creates `player1@example.test` and `player2@example.test` (password `password`), verified and ready to play. It refuses to run outside the local environment.
- **After changing PHP code**, restart the long-running processes so they load it: `docker compose restart horizon reverb scheduler` (bots and timers run in Horizon).
- **Codes by SMS** use the development `log` driver, which writes them to `apps/api/storage/logs/laravel.log`. It refuses to run in production: a real SMS provider still has to be chosen and added (`App\Platform\Sms\SmsGateway`) before phone sign-up can be offered publicly.
- **Roles** are granted with `make artisan cmd="app:role you@example.com super_admin"` (add `--revoke` to remove), or by a super admin in the admin panel.

The "verification required" settings are enforced on gameplay routes: an unverified account can browse but not play.

## Testing

- **Backend:** Pest feature tests run against the MySQL `testing` database inside the API container (`make test-api`). The test base class refuses to run against any other database, so the development database can't be wiped by accident.
- **Frontend:** Vitest + Testing Library, with the API mocked by MSW at the network level (`make test-web`).

## API types

The backend's OpenAPI spec (generated by Scramble from controllers and resources) is the contract
with the frontend. After changing an API response, run `make types` and commit the updated
`apps/web/src/shared/api/schema.d.ts`.

## Production builds

See [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) for the full setup (processes, configuration, release steps, security, monitoring and backups).

- **API:** `docker build --target prod -f docker/php/Dockerfile .` produces an image with code and an optimized autoloader. It runs as `www-data` and contains no `.env` or tests. The same image runs php-fpm, `php artisan reverb:start`, `php artisan horizon` and `php artisan schedule:work`, with all configuration supplied through environment variables.
- **Web:** `cd apps/web && VITE_API_BASE_URL=https://api.example.com npm run build` produces static files in `apps/web/dist`, to be served by any static host or CDN with an SPA fallback to `index.html`.

Production requirements, in short: separate Redis instances for game state
(`noeviction`) and cache, `APP_DEBUG=false`, HTTPS with `SESSION_SECURE_COOKIE=true`, and
`SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` and `FRONTEND_URL` set for the real domains.

## Troubleshooting

- **Port already in use:** change the port in the root `.env`, then `make up`.
- **`api` requests fail after editing `apps/api/.env`:** run `make restart`.
- **Readiness reports `degraded`:** Horizon or Reverb isn't running. Check `make ps` and `make logs`.
