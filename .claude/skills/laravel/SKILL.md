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
- `app/Http/Requests/{Auth,Me,…}` — all non-trivial validation in Form Requests; normalize input in
  `prepareForValidation()` (e.g. `AccountRules::normalize()` lower-cases emails, phones → E.164).
- `app/Http/Resources` — every response goes through an API Resource (`{ data: … }`). Never return models.
  Add `/** @var type */` PHPDoc on array items Scramble can't infer (nullable URLs, lists, unions), then
  regenerate frontend types (`make types`).
- `app/Platform/*` — cross-cutting services (Auth, Sms, Settings, Audit, Presence).
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

## Auth & authorization
- Sanctum **SPA cookie** auth (stateful). Session-based; `AuthenticateSession` signs out other sessions
  when the password hash changes — don't build a separate mechanism.
- Guard is `web`; routes use `auth:sanctum` + `active` (banned/deactivated → 403 and logout).
- Roles/permissions: spatie/laravel-permission. `super_admin` passes all gates (`Gate::before`).
  Use policies/gates (`$user->can('manage users')`), never role-name string checks in controllers.
- Never reveal whether an account exists (forgot-password returns the same 202; throttle before lookup).

## Database (MySQL 8.4 — see docs/adr/0008-mysql.md)
- `$table->datetimes()` / `dateTime()` (UTC), not `timestamps()`/`timestamp()` (2038 limit).
- CHECK constraints through `DB::statement('ALTER TABLE … ADD CONSTRAINT … CHECK (…)')`.
- No cascading FK actions on columns used in a CHECK constraint (MySQL forbids it).
- BIGINT keys internally; ULID `public_id` when an id is exposed in URLs/channels.
- JSON columns for translations/config/payloads; translatable fields via spatie/laravel-translatable.
- Critical writes: `DB::transaction(fn () => …, attempts: 3)`; `lockForUpdate()` where races matter.
- Seeders must be idempotent and must not overwrite admin edits (`firstOrCreate`, not `updateOrCreate`).
- Never edit a migration that is already on `main`; add a new migration. (Phase 3 rewrote the initial
  users migration before anything was deployed — that was the one exception.)

## Queues, notifications, real time
- Redis queues processed by Horizon; queue names: `default`, `notifications` (Phase 5 adds game queues —
  update `config/horizon.php` supervisors when adding one).
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

## Quality gates (all must pass)
`make lint-api` (Pint `laravel` preset + Larastan level 6) and `make test-api`.
Code style follows the Laravel skeleton and Pint's `laravel` preset — no `declare(strict_types=1)`,
PHPDoc generics on relations/collections for Larastan.
