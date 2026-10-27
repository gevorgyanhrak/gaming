---
name: laravel
description: Laravel 13 best practices as applied in the game-platform API (apps/api) — routing, Form Requests, API Resources, policies and gates, Sanctum SPA auth, Eloquent with MySQL 8.4, queues/notifications with Horizon, Reverb, Pest testing, Larastan and Pint. Use when writing, reviewing, or debugging Laravel/PHP code in this repository.
---

# Laravel 13 (game-platform conventions)

Official docs: https://laravel.com/docs — check the version-specific page before using an API you
are not sure exists in Laravel 13. Read the surrounding code first and match it.

## Running things
- PHP is **not installed on the host**. Everything runs in Docker:
  `docker compose exec -T api php artisan …`, `docker compose exec -T api composer …`, or `make` targets
  (`make test-api`, `make lint-api`, `make artisan cmd="route:list"`).
- Never install PHP locally or add Laravel Boost (the upstream skeleton's agent files suggest it; ignore them).

## Structure
- `app/Http/Controllers/Api/V1/{Auth,Me,…}` — thin controllers: authorize, delegate, return a Resource.
- `app/Http/Requests/{Auth,Me,Rooms,…}` — all non-trivial validation in Form Requests; normalize input in
  `prepareForValidation()` (e.g. `AccountRules::normalize()` lower-cases emails, phones → E.164).
  Gotcha: `validated('x')` keeps only the nested keys that have rules — read free-form objects (e.g. a
  game action validated by its engine) with `input()`.
- `app/Http/Resources` — every response goes through an API Resource (`{ data: … }`). Never return models.
  Add `/** @var type */` PHPDoc on array items Scramble can't infer (nullable URLs, lists, unions), then
  regenerate frontend types (`make types`).
- `app/Platform/*` — cross-cutting services: `Auth`, `Sms`, `Settings` (admin-managed settings),
  `Audit` (`AuditLogger`), `Moderation` (`UserModeration`: bans, status, roles).
- `app/Filament` — admin panel (Filament 5): `Resources/<Plural>/{<Model>Resource, Pages, Schemas, Tables,
  RelationManagers}`, `Pages/ManageSettings`, `Widgets`, `Support` (translatable fields helpers).
- `app/Gameplay/*` — shared, rule-agnostic game infrastructure. `app/Games/<Game>/Engine` — **pure PHP**,
  no Laravel/DB/Redis dependencies.
- Enums in `app/Enums` (backed string enums, cast on models).

## Laravel 13 idioms used here
- Model attributes: `#[Fillable([...])]`, `#[Hidden([...])]`; local scopes with `#[Scope]` on a
  `protected function name(Builder $query): void`.
- Console commands: `#[Signature('…')]`, `#[Description('…')]` attributes.
- Casts via `protected function casts(): array`. Model `$attributes` mirrors column defaults when code
  reads them before a reload (see `User::$attributes['status']`).
- `bootstrap/app.php` configures middleware (`statefulApi()`, aliases `active`, `touch-last-active`) and
  exception rendering (JSON for `api/*`, generic 404 body).
- Named rate limiters in `AppServiceProvider` (`auth`, `lookups`, `verification`, `account`, `uploads`).

## Admin-managed settings (ADR 0013)
- Read admin-managed values with `app(Settings::class)->get('group.key')` (or inject `Settings`), never
  `config()`; `config/platform.php` only supplies defaults. Add new keys to `SettingsCatalog`, a field in
  `ManageSettings`, and (if the SPA needs it, `public: true`) a field in `ConfigController`.
- Never read settings at boot (service providers): artisan and image builds run without a database.
  Framework values are applied per request by `ApplyRuntimeSettings`.
- Secrets are never settings; they stay in the environment.

## Admin panel (Filament 5)
- Guard `admin` (ADR 0014). In code running in the panel use `auth('admin')->user()`.
- Authorization through model policies in `app/Policies` (auto-discovered) calling permission checks.
  `Gate::before` grants super admins *permissions only*; policy methods returning a hard `false`
  (e.g. editing audit logs, creating games) still hold for them.
- Business actions (ban, roles, settings) go through services that audit; config models are audited by
  `#[ObservedBy(AdminChangeObserver::class)]` (only when an admin is acting).
- Translatable models: form fields via `Translatable::input()/textarea()`, pages use the
  `HandlesTranslations` trait, relation-manager actions `TranslationData::fill()/clean()`.
- Uploads: raster images only (`png/jpeg/webp`, favicon also `ico`) — never SVG, which would be served
  from the API origin and can carry scripts.
- Filament assets are generated (gitignored); the prod Dockerfile runs `php artisan filament:assets`.
- Custom page with a form: `form()` with `statePath('data')` + `content()` returning
  `Form::make([EmbeddedSchema::make('form')])->livewireSubmitHandler('save')` (see `ManageSettings`).

## Gameplay infrastructure (app/Gameplay)
- Engines implement `Contracts\GameEngine` (initialState, seatsToAct, legalActions, apply, viewFor,
  outcome) on plain array state; randomness only via `RandomSource`. Register a `GameDefinition` in the
  singleton `GameRegistry` (from the game's service provider); `games.is_enabled` can only be switched on
  for registered slugs.
- **All** state changes go through `Actions\GameActionService` (submit / resign / timeOut) — never write
  moves or room status directly. Rooms are created/started by `Rooms\RoomService`; who may start what is
  `Rooms\PlayRules` (playable game, mode flags, concurrent-games limit) plus the `verified-contact`
  middleware. Resigning is accepted at any time; other actions only from `seatsToAct`.
- Matchmaking (`Matchmaking\Matchmaker`): tickets per mode in the game store, every queue change under
  the mode's lock (no double matches); `app:matchmaking-sweep` (every 5 s) drops tickets without a
  heartbeat for 45 s and applies the game's bot fallback (offer / auto / off, game timeout or the platform
  default). Events `matchmaking.matched` / `matchmaking.bot_offered` on `private-user.{id}`.
- HTTP: `/api/v1/matchmaking` (POST join, GET status, POST heartbeat, POST accept-bot, DELETE leave),
  `/api/v1/rooms` (bot | private), `rooms/join`, `rooms/{room}` (+ `/state`, `/actions`, `/leave`),
  `me/rooms`, `me/games` (history, paginated), `me/stats`, `stats/online`. Engine exceptions map to 422 (IllegalAction) and 409 (RoomNotActive, VersionConflict with
  `current_version`) in bootstrap/app.php.
- Live state lives in the `game` cache store (`RoomStateStore`, Redis DB 2, `array` in tests) and is
  rebuilt from `game_moves` by `RoomStates` (recorded RNG draws make replays exact). seq 0 = `start`.
- After commit: `RoomEvents` broadcasts `RoomUpdated` per human player on `private-user.{id}` (own view);
  `GameScheduler` queues `TurnTimeoutJob` / `BotTurnJob` on the `game` queue, each tied to a version.
- Tests: `ticTacToe()` (tests/Pest.php) registers the test-only engine in tests/Fixtures and returns a
  playable mode. `Queue::fake()` and run jobs explicitly (`app()->call([$job, 'handle'])`).

## Auth & authorization
- Sanctum **SPA cookie** auth (stateful). Session-based; `AuthenticateSession` signs out other sessions
  when the password hash changes — don't build a separate mechanism.
- Guard is `web`; routes use `auth:sanctum` + `active` (banned/deactivated → 403 and logout).
- Roles/permissions: spatie/laravel-permission, always on the `web` guard (`User::$guard_name`, needed
  because `actingAs($u, 'admin')` and the panel switch the default guard). Use policies/permission checks
  (`$user->can('manage users')`), never role-name string checks, except super-admin-only actions.
- Never reveal whether an account exists (forgot-password returns the same 202; throttle before lookup).

## Database (MySQL 8.4 — see docs/adr/0008-mysql.md)
- `$table->datetimes()` / `dateTime()` (UTC), not `timestamps()`/`timestamp()` (2038 limit).
- CHECK constraints through `DB::statement('ALTER TABLE … ADD CONSTRAINT … CHECK (…)')`.
- No cascading FK actions on columns used in a CHECK constraint (MySQL forbids it).
- BIGINT keys internally; ULID `public_id` when an id is exposed in URLs/channels.
- JSON columns for translations/config/payloads; translatable fields via spatie/laravel-translatable.
  MySQL's JSON type **does not preserve object key order** — compare decoded JSON with `toEqual`, not `toBe`.
- Critical writes: `DB::transaction(fn () => …, attempts: 3)`; `lockForUpdate()` where races matter.
- Seeders must be idempotent and must not overwrite admin edits (`firstOrCreate`, not `updateOrCreate`).
- Never edit a migration that is already on `main`; add a new migration. (Phase 3 rewrote the initial
  users migration before anything was deployed — that was the one exception.)

## Queues, notifications, real time
- Redis queues processed by Horizon; queue names: `default`, `notifications` (Phase 5 adds game queues —
  update `config/horizon.php` supervisors when adding one).
- Scheduler (`routes/console.php`): `app:lift-expired-bans` every minute, `model:prune` daily
  (`VerificationCode` is `MassPrunable`).
- Notifications that send mail/SMS implement `ShouldQueue` and `onQueue('notifications')`.
- SMS goes through `App\Platform\Sms\SmsGateway` (driver from `services.sms.driver`); the `log` driver
  refuses to run in production.
- Broadcasting: Reverb. Commands come over HTTP; WebSockets only push server events (ADR 0003).

## Translations
- `lang/{en,ru,hy}` — framework strings from laravel-lang (excluded from Pint, refreshed with
  `php artisan lang:update`); our own strings in `lang/*/platform.php`. Add every new key in all three.

## Testing (Pest 5)
- Feature tests in `tests/Feature`, run against the MySQL **`testing`** database. `Tests\TestCase` uses
  `RefreshDatabase` and refuses any other database — never weaken that guard.
- Requests carry the SPA `Origin`/`Referer` headers (set in `TestCase::setUp`) so the stateful pipeline runs.
- Use factories and states (`User::factory()->withPhone()->unverified()->create()`), `Notification::fake()`
  with `codeSentTo()` / `wrongCode()` helpers from `tests/Pest.php`, `Storage::fake('public')`, `travel()`.
- When a second request in one test must resolve the user from the session, call
  `$this->app['auth']->forgetGuards()` first.
- Datasets (`->with([...])`) for validation matrices. Test behaviour through HTTP, not internals.
- Admin panel: `signInToAdmin('moderator')` (tests/Pest.php) seeds roles, signs in on the `admin` guard
  and sets the Filament panel; then `Livewire::test(ViewUser::class, ['record' => $id])->callAction('ban',
  data: [...])`, `->fillForm([...])->call('save')->assertHasNoFormErrors()`, `->assertNotified()`.
  `ADMIN_MFA_REQUIRED=false` in phpunit.xml.
- `JsonResource` answers 201 for a model with `wasRecentlyCreated`; in multi-request tests call
  `$this->app['auth']->forgetGuards()` so the next request reloads the user.

## Quality gates (all must pass)
`make lint-api` (Pint `laravel` preset + Larastan level 6) and `make test-api`.
Code style follows the Laravel skeleton and Pint's `laravel` preset — no `declare(strict_types=1)`,
PHPDoc generics on relations/collections for Larastan.
