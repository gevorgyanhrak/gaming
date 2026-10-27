# 0011. Toolchain versions and compatibility pins

**Status:** Accepted (2026-10-27)

## Decision
Use the current stable releases at project setup: PHP 8.5, Laravel 13, React 19, Vite 8,
React Router 8, Tailwind 4, MySQL 8.4 LTS, Redis 7.4. Two deliberate pins:

- **TypeScript 6.0**, not 7: typescript-eslint supports TypeScript below 6.1 only.
- **openapi-typescript** requires TypeScript 5, so it is not a project dependency; `npm run api:types`
  runs pinned versions (`typescript@5.9.3`, `openapi-typescript@7.13.0`) in an isolated `npx` environment.

## Consequences
Revisit both pins when typescript-eslint supports TypeScript 7 and openapi-typescript supports TypeScript 6+.
