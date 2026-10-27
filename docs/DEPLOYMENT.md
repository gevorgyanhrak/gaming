# Deployment

How to run the platform in production. Local development is in the README; the architecture and its
decisions are in `ARCHITECTURE.md` and `adr/`.

## 1. What runs

| Process | Image / command | Scale |
|---|---|---|
| Web (SPA) | `apps/web` build output (`dist/`), static files behind a CDN or nginx | any |
| API | `docker/php/Dockerfile` target `prod` (php-fpm) behind nginx (`docker/nginx/default.conf`) | stateless, any number |
| Queue workers | same image, `php artisan horizon` | one Horizon per host; processes auto-balance (`config/horizon.php`) |
| WebSockets | same image, `php artisan reverb:start --host=0.0.0.0 --port=8080` | one, or several with `REVERB_SCALING_ENABLED=true` |
| Scheduler | same image, `php artisan schedule:work` | **exactly one** |
| MySQL 8.4 | managed service or own instance, InnoDB, utf8mb4 | primary (+ replica for backups) |
| Redis A | `maxmemory-policy noeviction`, AOF on: queues, Horizon, locks, matchmaking, live game state | one |
| Redis B | `maxmemory-policy allkeys-lru`: cache, sessions, rate limits | one |

The scheduler sweeps matchmaking every 5 seconds, lifts expired bans every minute and prunes old
verification codes daily (`routes/console.php`). Running two schedulers double-matches nothing (every
queue change is locked) but wastes work; running none stops matchmaking.

Bots and turn timers run as queued jobs on the `game` queue. Bot moves cost CPU (a hard chess move takes
about a second); give workers real cores, not burstable ones.

## 2. Configuration

Start from `apps/api/.env.example` and set at least:

| Variable | Production value |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_KEY` | `php artisan key:generate --show`, kept in your secret store |
| `APP_URL` | public API URL, e.g. `https://api.example.com` |
| `FRONTEND_URL` | public SPA URL, e.g. `https://play.example.com` |
| `SANCTUM_STATEFUL_DOMAINS` | the SPA host, e.g. `play.example.com` |
| `SESSION_DOMAIN` | parent domain shared by SPA and API, e.g. `.example.com` |
| `SESSION_SECURE_COOKIE` | `true` |
| `TRUSTED_PROXIES` | IPs/CIDRs of your load balancer or TLS terminator |
| `DB_*` | MySQL connection (a user without `SUPER`; migrations need `ALTER`, `INDEX`, `REFERENCES`) |
| `REDIS_HOST` (+ `REDIS_GAME_HOST`) | Redis A |
| `REDIS_CACHE_HOST` | Redis B |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | random values; the key is public, the secret is not |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | how the **API** reaches Reverb (internal) |
| `MAIL_*` | your mail provider |
| `ADMIN_DOMAIN` | optional own host for `/admin`, e.g. `admin.example.com` |
| `ADMIN_MFA_REQUIRED` | `true` |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stderr` / `info` (collected by your container platform) |

Everything an administrator can change (platform name, registration, verification rules, bot
fallback, maintenance mode, limits) lives in the admin panel, not in the environment (ADR 0013).

**SMS:** only the development `log` driver exists, and it refuses to run in production. Before phone
sign-up is offered, implement `App\Platform\Sms\SmsGateway` for a provider and keep phone registration
switched off in the admin panel until then.

The SPA is configured at build time:

```sh
cd apps/web
VITE_API_BASE_URL=https://api.example.com \
VITE_REVERB_APP_KEY=<REVERB_APP_KEY> VITE_REVERB_HOST=ws.example.com \
VITE_REVERB_PORT=443 VITE_REVERB_SCHEME=https \
npm ci && npm run build      # → dist/
```

Serve `dist/` with a fallback to `index.html` for client-side routes, long cache headers for
`dist/assets/*` (file names are content-hashed) and no cache for `index.html`.

## 3. Release

```sh
docker build -f docker/php/Dockerfile --target prod -t game-platform-api:<version> .

# With the new image, once per release:
php artisan migrate --force
php artisan db:seed --force          # reference data only; idempotent, never overwrites admin edits
php artisan optimize                 # config, routes, events, views cached

# Then roll the API containers, and make long-running processes load the new code:
php artisan horizon:terminate        # Horizon restarts under its supervisor with the new code
php artisan reverb:restart
# restart the scheduler container
```

If matchmaking stalls right after a restart, a killed run may still hold its overlap lock (they expire
after a minute); `php artisan schedule:clear-cache` releases them at once.

Workers that keep running old code are the most common deployment bug: a game added in a release is
unknown to an old worker, and its bots never move. Always terminate Horizon and restart Reverb after a
release.

Migrations are written to be safe on a live database (additive first, destructive changes in a later
release). Game state is rebuilt from `game_moves` whenever the state store is behind, so a deploy in the
middle of games is fine.

## 4. Network and security

- Terminate TLS in front of nginx and Reverb. Send `Strict-Transport-Security: max-age=31536000;
  includeSubDomains` from the terminator. nginx already sends `nosniff`, `DENY` framing and a strict
  referrer policy.
- For the SPA host, a Content Security Policy along the lines of `default-src 'self'; connect-src 'self'
  https://api.example.com wss://ws.example.com; img-src 'self' data: https:; style-src 'self'
  'unsafe-inline'; frame-ancestors 'none'`.
- Expose only nginx (HTTPS) and Reverb (WSS). MySQL, both Redis instances and php-fpm stay on the
  private network; set Redis passwords.
- Rate limits (login, codes, lookups, gameplay) are per IP or user; with a proxy in front they only work
  when `TRUSTED_PROXIES` is right.
- Horizon's dashboard (`/horizon`) and the admin panel are for staff only (`viewHorizon` gate, admin
  guard with MFA). Consider `ADMIN_DOMAIN` plus an IP allow-list at the proxy.
- Poker uses play chips that exist only inside one table; nothing can be bought, sold, transferred or
  withdrawn. Keep it that way: real-money or prize play would need licensing.

## 5. Monitoring

| Signal | Where |
|---|---|
| Liveness | `GET /up` (process is up) |
| Readiness | `GET /api/health/ready`: database and Redis are critical (503 when down); queue workers and Reverb are reported as `degraded` |
| Queues | Horizon dashboard: wait times per queue (alert if `game` waits more than a few seconds), failed jobs |
| Logs | `Game finished.` (info, with room, game, reason and duration); warnings for failed health checks; errors are real errors (expected gameplay rejections are not reported) |

Alert on: readiness failing, `game` queue wait > 5 s, failed jobs > 0, Reverb unreachable, MySQL
replication lag, Redis A memory > 70 % (it doesn't evict: full means errors).

## 6. Backups and recovery

- **MySQL** holds everything durable: accounts, settings, games and every move. Take daily full dumps
  (or snapshots) plus binary logs for point-in-time recovery; test a restore regularly.
- **Redis A** with AOF survives restarts. If it is lost anyway, live games rebuild from `game_moves` on
  their next request (per-turn timers restart with a full turn; chess clocks are stored on the room);
  matchmaking queues and presence refill within seconds.
- **Redis B** can be lost at any time: users sign in again, caches warm up.
- Uploaded avatars and branding live on the configured filesystem disk; back it up or use object
  storage with versioning.
