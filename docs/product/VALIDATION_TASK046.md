# PM validation — TASK-046 · Nardi in two variants: Long and Short

- **Date:** 2026-11-07
- **Build:** `main` at 6ea1ba0
  - engine 61a2181
  - mode and migration e0aaa53
  - board and picker c7fc6b0
  - QA fixes 4876787
  - design fixes 6ea1ba0
- **Owner's request (2026-11-02):**
  - **Reference 1:** a "what shall we play?" picker with two board thumbnails, «Длинные» / «Короткие».
  - **Reference 2:** a short-nardi board (point numbers, pip counts, a round timer, side trays, a doubling
    cube, a side panel).
  - **Plan:** bots, groups and matchmaking per variant; en/ru/hy.
- **Inputs:**
  - `docs/team/PROGRESS.md` "Now" (the rules as implemented, decisions, steps).
  - QA `docs/qa/QA_REPORT_TASK046.md`: 40 checks, 38 pass; 1 Medium and 1 Low, fixed in 4876787.
  - Design `docs/design/REVIEW_TASK046.md`: NEEDS REVISION, 2 Medium and 3 Low, all addressed per "Fix
    status".
  - Code: `LongNardiRules.php`, `NardiEngine.php`, `NardiBot.php`, `GameModeSeeder.php`, migration
    `2026_11_07_100000_name_nardi_modes.php`, `NardiBoard.tsx`, `NardiModePreview.tsx`, the PlayPanel picker.
- **PM's own checks (read-only):**
  - **Tests:** `tests/Unit/Games/LongNardiTest.php` has 11 cases covering every rule in the "Now" list
    plus whole bot games; `tests/Feature/Games/LongNardiRoomTest.php` has 2 cases (a real pipeline game
    and a move-log replay). Web vitest **147 passed** (re-run). Backend tests not run (shared testing DB).
  - **Bot:** `NardiBot` has a Long evaluation (`LongNardiRules` branch).
  - **Migration:** renames the seeded "Classic" only while it still has the seeded name, and has a working
    `down()`. Not destructive.
  - **Screenshots taken after the fixes (`qa2/n46-sel390-picker.png`, `n46-sel390-select.png`, 21:21, the
    6ea1ba0 commit time):**
    - The picker shows two thumbnails, Long first and selected.
    - The Long preview has the heads diagonally opposite, as on the live board.
    - Opponent checkers in the previews now have a visible rim.
    - The 390 board fits with a thin divider in Long; legal targets have a gold outline and glow;
      "Pips 360 · 360" shows.
  - **Correction recorded earlier:** today's engine was Short (backgammon rules), so Long is the new
    variant. PROGRESS states this correctly.

## Verdict: **ACCEPT WITH FOLLOW-UPS**

- **Nothing blocks acceptance.**
- **Request met:** two real, rule-checked variants with a picture picker. Bots, groups, bets and
  matchmaking work per variant. en/ru/hy throughout. No UI-only claims. The Fishka rules are unchanged.
- **Deliberately out:**
  - The doubling cube, and whether a mars pays double. Both change the Fishka pot, so they are owner
    questions 15–16.
  - The decorative items of reference 2 (point numbers, the timer on the board, wooden trays, the side
    panel). The design review listed them as follow-ups, not promises → TASK-054.
- **No UI/UX re-review after the fixes:** the design fixes weren't re-reviewed by the UI/UX agent. I
  checked fixes 1, 3, 4 and 5 on the post-fix screenshots. Fix 2 (ru at 1440) rests on the developer's
  measurement (1104 = 1104).

## A. Request and rules

| Element | Status | Evidence |
|---|---|---|
| Two variants, Long and Short | Met | One game, two modes: `long` (new, listed first, default) and `classic` renamed "Short nardi" / «Короткие нарды» / «Կարճ նարդի» (migration, QA 3–4) |
| Long rules correct | Met | QA 1 crafted positions: start, direction, no hitting, the head rule (incl. first-turn 6-6/4-4/3-3), the six-wall rule both ways, higher die, doubles, pass, bearing off, mars. 4 full API games: 0 violations in 391 states. Unit: LongNardiTest (11 cases) |
| Short unchanged | Met | QA 3: a full Short game, 29 hits, 23 bar entries, 0 violations; LongNardiTest › "keeps old states Short…" |
| Picker with two board previews | Met | "What shall we play?" with two picture tiles (`NardiModePreview`), gold frame on the selected one (QA 4; design "What works"). Long preview corrected to match the live board (design fix 1; PM screenshot) |
| Bots per variant | Met | Long bot evaluation in `NardiBot`; QA: Easy and Medium Long games to the end; Medium won with a mars. Note: Easy plays random legal moves in every game by design |
| Groups and bets per variant | Met | QA 5: Long Casual 3,000 / 2,000 wins paid bet × 2; Beginner losses; Short loss; stake rules unchanged |
| Matchmaking per variant (and group) | Met | QA 4: Long vs Short → no match; Long Casual vs Long Beginner → no match; Long Casual × 2 → matched, 2,000 each |
| Private tables, Play again per variant | Met | QA 4: Play again keeps the mode, group and bet; "Create table" for Long |
| Board for both seats, desktop and phone | Met | QA 2: each seat sees its own head and home from its side. QA BUG-1 (phone board 576 px in 356 px) fixed: fits at 390, Short too (QA fix status; PM screenshot). ru at 1440 tray labels fixed (design fix 2) |
| Pip counts (reference 2) | Met | "Pips 360 · 360" Long, "167 · 167" Short; screen-reader text (QA 2, design) |
| Mars shown | Met | QA BUG-2 fixed: rooms send each participant's `score`; "Mars! A double win." (en/ru/hy); RoomPage.test › "calls a double win…". Wording caveat in §B |
| Reload and replay | Met | QA 6: identical board after reload (rebuilt from the move log); LongNardiRoomTest replay |
| Reactions on the Nardi page | Met | QA 6: flights and bubbles between the player chips at 1440 and 390 |
| en / ru / hy | Met | QA 2, 4; design checked hy 390, ru 1440. The game description above the picker is still English in ru/hy (admin content, pre-existing → question 5) |
| No UI-only claims | Met | Every visible option (both modes, groups, bets, matchmaking, mars) is backed by the server and tested; the doubling cube is not shown anywhere |
| Recreational Fishka rules | Met | No new Fishka paths: the stake rules from TASK-040 apply unchanged; a mars does not change the payout (QA O-1) |
| Reference 2 extras (point numbers 1–24, round timer on the board, wooden side trays, side panel with clocks and stake) | Not built (not promised) | Design review follow-ups 1–4 → TASK-054 |
| Doubling cube (reference 2) | Not built **by decision** | It doubles the stake mid-game, which is a Fishka pot rule → owner question 15 |

## B. Decisions taken without the owner

| Decision | PM judgement | Owner? |
|---|---|---|
| **No doubling cube** | Right: a cube raises the stake mid-game, so Fishkas could move beyond the agreed bet, and the "Fishka amounts or rules" item needs the owner. If approved, it also needs limits (the cap vs the group's max bet and the player's balance) | **Question 15** |
| **A mars scores 2 in the engine; the Fishka payout is unchanged (bet × 2 pot)** | Consistent with TASK-040 (winner takes the pot). **Caveat:** the panel says "Mars! A double win." while the Fishkas shown are a normal win. A player may expect double Fishkas. If the owner keeps payouts unchanged, the copy should say "Mars! The opponent bore off none." without "double win" | **Question 16**, with copy follow-up in TASK-055 |
| **One game, two modes; Long first and default** | Right for the ru/hy audience (long nardi is the common game there, PRODUCT_RESEARCH §3.3). Groups, matchmaking and history already work per mode | No |
| **Short's seeded "Classic" renamed "Short nardi"** | Right; only the unchanged seed name is renamed, and it is reversible | No |
| **Long rules as listed in PROGRESS "Now"** | They match the common Armenian/Russian rules: one checker per turn from the head, the first-turn doubles exception, no six-wall unless an opponent is ahead, mars = 2. Variants of the head exception exist (some allow only 6-6 and 4-4, some add 3-3). The team chose 6-6/4-4/3-3 | **Question 17** (confirm the head exception) |

## C. Follow-ups

| Item | Where |
|---|---|
| Reference 2 extras: point numbers, round turn timer on the board, wooden side trays with stacked checkers, side panel with players, clocks and stake | TASK-054 (new, P2) |
| "Mars! A double win." vs an unchanged payout | TASK-055 (new, P2), after question 16 |
| Doubling cube; mars payout; head exception | Owner questions 15–17 |
| The Nardi game description above the picker is English in ru/hy | Owner question 5 (content), extended |
| Easy Long bot is weak (random legal moves, as in every game) | Noted; bot difficulty is a general topic, no task now |
