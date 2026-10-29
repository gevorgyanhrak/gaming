# Product and design decisions

Architecture decisions live in `docs/adr/`. This log is for product and design choices.
Newest first. Format: date · decision · why · who.

## Open — needs owner

(see the "Decisions needed" section of `docs/product/PRODUCT_BACKLOG.md`)

## Decided

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
