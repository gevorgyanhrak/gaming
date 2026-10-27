# Project guide for AI coding assistants

Sessions in this project run as **gaming-agent** (`.claude/agents/gaming-agent.md`, set in `.claude/settings.json`).
Project knowledge is kept in the repo and updated with every change (see the agent's "Keeping your knowledge current";
a Stop hook in `.claude/hooks/knowledge-check.sh` reminds when code changed but no knowledge file did).

Read `docs/ARCHITECTURE.md` and `docs/adr/` before making structural changes. Development happens in
approved phases (see the roadmap there); stay within the current phase's scope.

## Environment
- PHP is not installed on the host. Run backend commands in Docker: `docker compose exec api <cmd>` (or `make` targets). Do not install PHP locally or add Laravel Boost.
- The frontend runs natively: `cd apps/web && npm ...` (Node 22+).
- Backend tests: `make test-api`. They use the MySQL `testing` database; never point tests at `game_platform`.

## Non-negotiables
- The backend is the only rules authority. Never implement game rules, legal-move logic or outcome calculation in the frontend.
- Game engines in `app/Games/*/Engine` stay free of Laravel, DB and Redis dependencies.
- Shared infrastructure (`app/Gameplay`, `app/Platform`) must not depend on a specific game.
- Never expose settings that aren't explicitly marked public; never return raw models from controllers (use API Resources).
- No mock or fixture data in production code; test fixtures live under `tests/` and `apps/web/src/test/`.
- Don't present a feature as implemented when only its UI exists.

## Conventions
- MySQL conventions in ADR 0008 (DATETIME in UTC, CHECK constraints via `DB::statement`, no cascading FKs on CHECK columns).
- After changing an API response shape, run `make types` and commit `apps/web/src/shared/api/schema.d.ts`.
- Before finishing: `make lint` and `make test` must pass.
