# 0003. Commands over HTTP, events over WebSocket

**Status:** Accepted (2026-10-27)

## Context
Players need real-time updates, but every move must be authenticated, validated, rate limited,
authorized and idempotent. All five games are turn-based.

## Decision
Clients submit actions with `POST /api/v1/rooms/{id}/actions` (including `client_action_id` and
`expected_version`). The server broadcasts the resulting state through Laravel Reverb. Clients do
not send game commands over the socket.

## Consequences
- Laravel's Form Requests, policies, rate limiting and middleware apply to every move.
- Round-trip latency of an HTTP request is acceptable for turn-based play.
- Reverb carries only server-originated events; it needs no command-validation logic.
