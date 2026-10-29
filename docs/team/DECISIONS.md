# Product and design decisions

Architecture decisions live in `docs/adr/`. This log is for product and design choices.
Newest first. Format: date · decision · why · who.

## Open — needs owner

(see the "Decisions needed" section of `docs/product/PRODUCT_BACKLOG.md`)

## Decided

- 2026-10-29 · Sound stays **on by default** (`sound ?? true`) at low volume (master 0.25, synthesized);
  existing players hear sounds after release and can switch them off in Settings · the toggle already
  promised sound, and the your-turn cue is the point of TASK-001/004 (spec B1 open question 2) · team (PM).
- 2026-10-29 · A player who left a table that plays on hears nothing more from it, including the end
  (spec rule 3) — fixed now as TASK-032, not deferred · matchmaking already seats humans at 3–6 player
  tables · team (PM).
- 2026-10-29 · Chess flag-fall should count as a loss (TASK-031); turn-timer timeouts and leaving stay
  "Timed out"; past results are not rewritten. Not an owner-approval item under README rules, but
  backend work waits for the owner's B4 go-ahead · team (PM).
- 2026-10-29 · Batch B1 (TASK-001…005) validated and APPROVED · PM.
- 2026-10-29 · Autonomous mode on: batches B1–B3 are built task by task (spec → build → design review →
  PM validation) and pushed to `main` without asking per task; B4 still needs the owner · owner.
- 2026-10-29 · Delete the committed cookie jars `a.txt`/`b.txt` (TD-001) and ignore cookie files · owner.
- 2026-10-29 · Game sounds are synthesized with Web Audio in code (no sound files, no licensing) ·
  avoids an asset decision; small and themeable · team.

- 2026-10-29 · Chess and Checkers use a classic beige/brown board (`#e3d0ac` / `#8b7355`) in the wood
  frame, with a "Move: White/Black" heading · matches the reference the owner gave · owner.
- 2026-10-28 · "Card club" look: dark wood room, gold emphasis, green play buttons, game carousel with
  view transitions, oval tables for card games · owner asked for a bazarblot-style lobby · owner.
- 2026-10-28 · No coins, VIP, shop, entry fees or prize wheel even where references show them · the
  platform is recreational only (ARCHITECTURE.md risks, CLAUDE.md) · standing rule.
- 2026-10 · Recreational only: no real money, no purchasable or transferable chips, ever · owner.
