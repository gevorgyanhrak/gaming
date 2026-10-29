# Technical debt

Found while working; each item says where, why it matters and the proposed fix. Move an item into the
backlog when it is scheduled.

| ID | Where | Problem | Fix | Priority |
|---|---|---|---|---|
| TD-001 | `a.txt`, `b.txt` (repo root, committed in a4caa2c) | curl cookie jars with local session and XSRF cookies for `.localhost`; local-only, but cookie files don't belong in git and the pattern could leak a real session later | delete both, add `*.cookies` / `cookies*.txt` to `.gitignore`, write future jars to a temp dir | P1 (needs owner OK: deletes tracked files) |
| TD-002 | bot profiles (seeders) | Durak has two easy bot profiles, so a 3+ player bot table repeats a name ("David" twice) | seed more profiles per game and difficulty, or let `BotSelector::pick` exclude used names | P2 |
| TD-003 | `apps/web/src/features/account/components/PreferencesForm.tsx` | "sound" preference saved but unused (see UX-001) | implement sounds or hide | P0 (tracked as UX-001) |
