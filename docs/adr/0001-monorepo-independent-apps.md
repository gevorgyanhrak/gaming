# 0001. Monorepo with independently deployable apps

**Status:** Accepted (2026-10-27)

## Context
The frontend and backend must be developed, tested, deployed and scaled independently, but they share
one product, one set of docs and one local development environment.

## Decision
One repository with `apps/api` (Laravel) and `apps/web` (React). Each app has its own dependencies,
tests, build artifact and CI jobs, triggered by path-filtered rules. The API contract between them
is the OpenAPI spec generated from the backend; the frontend generates its types from it.

## Consequences
- One clone, one `compose.yaml`, one place for documentation and ADRs.
- No shared code between PHP and TypeScript; the OpenAPI spec is the only coupling.
- Splitting into two repositories later is mechanical if ever needed.
