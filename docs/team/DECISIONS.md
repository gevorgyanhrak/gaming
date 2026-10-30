# Product and design decisions

Architecture decisions live in `docs/adr/`. This log is for product and design choices.
Newest first. Format: date · decision · why · who.

## Open — needs owner

(see the "Decisions needed" section of `docs/product/PRODUCT_BACKLOG.md`)

## Decided

- 2026-10-30 · TASK-035, TASK-036 and TASK-029 validated and APPROVED (79a053d). Accepted: the Poker
  phone bet placement applies to every table size below 640 px (not only 5–6 seats), on condition that
  the 2/4-seat check in TASK-033 passes · one consistent phone layout · PM.
- 2026-10-30 · Durak table: keep the club room with a thin wood rail around a **rectangular** green
  felt fitted to the screen height (no oval; Poker keeps its oval); one tap plays a card; stickers/emoji
  out of scope · owner chose the recommended options (B3a open questions 1, 2, 4).
- 2026-10-30 · Poker pauses between hands so the result can be seen: 3 s after a fold win, 5 s after
  a showdown, nobody acts and no clock runs (B3a-1, server-side) · owner.
- 2026-10-30 · Owner asked for a Durak redesign (references: popular Durak apps) and a Poker hand
  result "you can feel" (TASK-035, TASK-036, P1) · owner.
- 2026-10-29 · Go-ahead for the backend fixes TASK-030 (distinct bots per table) and TASK-031 (chess
  flag-fall = loss): "do all necessary things" · small, non-breaking fixes found in B1 · owner. PM reads it
  as covering these two only; other B4 items still wait (backlog "Decisions needed" 1).
- 2026-10-29 · Room API addition: `RoomResource` returns `mode.id` and `participants[].bot_difficulty`
  (null for people), additive and non-breaking, types regenerated. "Play again" repeats the exact level
  on any device instead of a device-local guess; accepted deviation from the TASK-006 spec (open question
  1) · bot level is chosen by the player and already shown in the lobby; `GameModeResource` already
  exposes mode ids · team (PM).
- 2026-10-29 · Design system: the gold gradient lettering class is `.text-gold-gradient`, used only on
  large headings; `text-gold` is Tailwind's plain gold colour. Never give the gradient class to an
  element with a background (it clips the background to the text: cause of B1's invisible tiles and
  B2's unreadable hover) · team (UI/UX + developer, PM accepts).
- 2026-10-29 · Per-game records on public profiles are public by design (the API already returns them,
  as on Chess.com/Lichess); TASK-015 becomes frontend only and moves to B3. A "hide my stats" option would
  be a new backend item for the owner · team (PM).
- 2026-10-29 · Batch B2 (TASK-026, 006, 028, 007, 008, 024) and TASK-030/031 validated and APPROVED · PM.
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
