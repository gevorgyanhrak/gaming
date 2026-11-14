# ADR 0022 — AI players are marked

- Status: accepted (2026-11-14, owner)
- Supersedes: the "Never marked" part of ADR 0021 (everything else in ADR 0021 stands)

## Context

ADR 0021 kept AI unmarked at tables. With the casino platform (Big Wins, leaderboards, achievements) the owner wants
players to always know who is a person: "Clearly distinguish AI opponents from real players where player identity is
shown. Never present bot activity as real-player activity."

## Decision

- Wherever a player's identity is shown, an AI player is marked: an "AI" tag (`shared/ui/AiTag`) in player lists and
  the matchmaking table, and "Name · AI" on every board (RoomPage's `players` map), from the API's `is_bot`.
- AI activity never counts as players' activity: AI is not in "N playing" counts, Big Wins, leaderboards or
  achievements, and earns no XP (as before).
- A person's seat held by AI while they're away (`ai_controlled`) stays theirs and is not marked as AI.
- Unchanged from ADR 0021: AI plays at the highest level, fills seats after people, holds seats at tables of 3+.

## Consequences

Players can tell people from AI at a glance; statistics stay about people only.
