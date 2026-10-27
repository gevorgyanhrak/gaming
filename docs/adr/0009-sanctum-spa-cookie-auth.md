# 0009. Sanctum SPA cookie authentication

**Status:** Accepted (2026-10-27)

## Decision
The first-party SPA authenticates with Sanctum's stateful session cookies (HttpOnly, SameSite=Lax,
CSRF via `XSRF-TOKEN`), served from the same site as the API (`app.` and `api.` subdomains in
production; one origin via the Vite proxy in development). Personal access tokens remain available
for a future mobile app. Broadcast channel authorization uses the same session.

## Consequences
- No tokens in browser storage.
- `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, CORS `allowed_origins` and `FRONTEND_URL` must be configured per environment.
