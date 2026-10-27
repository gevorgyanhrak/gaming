# 0005. Hot state in Redis, durable move log in MySQL

**Status:** Accepted (2026-10-27)

## Context
Real-time state should not be written to the database on every small event, but games must survive
crashes and provide history.

## Decision
The live state of each room lives in Redis (with a per-room lock). Each accepted move is one
append-only row in `game_moves`, written in the same transaction that validates it. Presence,
clocks, heartbeats and matchmaking queues live only in Redis. If Redis state is missing or behind,
it is rebuilt by replaying `game_moves`.

## Consequences
- The move log is the game history, the replay source and the recovery mechanism.
- Write volume is small (about 100 inserts/s at 1,000 concurrent rooms).
- Game-state Redis must use `noeviction` with AOF and be separate from cache Redis in production.
