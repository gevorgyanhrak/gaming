# 0007. Bots use the same write path as humans

**Status:** Accepted (2026-10-27)

## Context
Bots must follow the rules exactly and must not see information a human in their seat could not.

## Decision
A bot turn is a queued `BotTurnJob(room, version)` that receives the bot seat's redacted view and
legal actions, chooses one through its `BotStrategy`, and submits it through `GameActionService`,
exactly like a human move. Bots are shown with a visible "Bot" badge.

## Consequences
- An illegal bot move is rejected by the same validator as a human one.
- Bots cannot cheat in hidden-information games.
- Stale bot jobs (version changed) do nothing.
