# Technical debt

Found while working; each item says where, why it matters and the proposed fix. Move an item into the
backlog when it is scheduled.

| ID | Where | Problem | Fix | Priority |
|---|---|---|---|---|
| TD-001 | `a.txt`, `b.txt` (repo root, committed in a4caa2c) | curl cookie jars with local session and XSRF cookies for `.localhost`; local-only, but cookie files don't belong in git and the pattern could leak a real session later | delete both, add `*.cookies` / `cookies*.txt` to `.gitignore`, write future jars to a temp dir | done 2026-10-29 (owner approved) |
| TD-002 | bot profiles (seeders) | too few bot profiles per game/difficulty: a 6-player Poker bot table showed "Alex" four times (2026-10-29), Durak repeats "David" | seed more profiles per game and difficulty, or let `BotSelector::pick` exclude used names | P1 |
| TD-003 | `apps/web/src/features/account/components/PreferencesForm.tsx` | "sound" preference saved but unused (see UX-001) | implement sounds or hide | done in TASK-001 (2026-10-29) |
| TD-004 | `pages/RoomPage.tsx` header | "Waiting for the other player" at 3–6 player tables | name the acting player or say "Waiting for other players" | P3 |
