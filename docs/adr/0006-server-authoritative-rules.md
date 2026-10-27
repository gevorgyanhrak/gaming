# 0006. The server is the only rules authority

**Status:** Accepted (2026-10-27)

## Context
Clients cannot be trusted to decide legal moves, winners, cards or balances.

## Decision
The backend validates every action and computes every outcome. Each state response includes the
player's `legal_actions`; the frontend highlights and submits only what the server offers. No game
rules are implemented in TypeScript.

## Consequences
- No duplicated rule logic to keep in sync.
- Games with multi-step turns (e.g. Nardi) return their legal actions as a tree the UI walks.
