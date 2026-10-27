# 0004. Pure-PHP game engines behind one contract

**Status:** Accepted (2026-10-27)

## Context
Rules correctness is the biggest product risk. Engines must be easy to test exhaustively, and new
games must not require changes to shared infrastructure.

## Decision
Each game implements `GameEngine` (initial state, legal actions, apply, per-seat view, outcome) and
`BotStrategy` in plain PHP with no Laravel, database or Redis dependencies. Randomness is injected
through `RandomSource`, and random outcomes (dice, deals) are recorded as events.

## Consequences
- Engines are deterministic and testable as unit tests (perft, scenario tests, fuzzed self-play).
- Games can be replayed from their move log.
- Shared infrastructure interacts with games only through the contract and the `GameRegistry`.
