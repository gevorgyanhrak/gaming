# QA report — TASK-046 · Nardi in two variants: Long and Short

- **Date:** 2026-11-07, about 16:30–21:15 local (with a break while the usage limit reset).
- **Build:** `main` at fc718e5 (engine 61a2181, mode e0aaa53, board and picker c7fc6b0). `make restart` was
  run at the start; the stack stayed up through the break and I checked it was healthy before resuming.
- **Machine load:** 5–7.
- **Method:**
  - **Rules:** checked twice.
    - On crafted positions run straight through `NardiEngine` / `LongNardiRules` in the api container
      (plain PHP, no DB).
    - In four full games through the real API against bots. The driver plays random legal moves and checks
      every state the server returns: checkers per seat = 15, no bar in Long, pip counts against the
      formula, no six-wall, direction and distance of every step, no step onto an opponent point, and head
      moves per turn.
  - **Boards:** both seats of a two-person private Long table, in isolated headless-Chrome contexts (CDP
    :9333), at 1440 and 390 in en, ru and hy.
  - **Flows:** the picker, Play again and private tables through the UI as player2; matchmaking through the
    API with two accounts.
- **Evidence:** screenshots and scripts are in
  `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/qa2/`, written below
  as `qa2/`.
  - Screenshots: `n46-*`, `t46-*`.
  - Logs: `t46-long1.log`, `t46-long2.log`, `t46-long3.log`, `t46-short1.log`.
  - Scripts: `t46drive.mjs`, `t46board.mjs`, `t46picker.mjs`, `t46mm.mjs`, `t46emo.mjs`, `t46mars.mjs`. The
    crafted-position checks are `qa2_long2.php`, `qa2_long3.php` and `qa2_long4.php` one level up.
  - Note: some older helper scripts in `qa2/` were deleted by someone else during the session; I recreated
    what I needed.

## Summary

| Area | Result |
|---|---|
| 1. Long rules (start, direction, no hitting, head rule, no wall, dice, bearing off, end and mars) | **PASS** — every rule holds in crafted positions and in 3 full Long games (0 violations in 391 states) |
| 2. Boards for both seats, 1440 and 390, en/ru/hy | PASS at 1440. **BUG-1 (Medium):** at 390 the board needs sideways scrolling, so your head, your home and the off tray start out of sight |
| 3. Short nardi unchanged (hitting, bar) | PASS (a full game: 29 hits seen, 23 entries from the bar) |
| 4. Picker, bot / private / matchmaking per mode, Play again | PASS |
| 5. Groups, bets, payouts per mode | PASS |
| 6. Reload, move-log replay, emoji flights | PASS |

Also **BUG-2 (Low):** a mars is recorded (score 2) but the game-over panel doesn't say so.

**Totals:** 40 checks — 38 PASS, 1 Medium, 1 Low, 3 observations.

## 1. Long nardi rules

### Crafted positions (engine)

| Rule | Position and roll | Result | Verdict |
|---|---|---|---|
| Start | `initialState(variant long)` | Seat 0: 15 on point 24; seat 1: 15 on point 12; nothing else. The opening roll is never a double; pips 360 / 360 | PASS |
| Direction | Seat 1 opening 2-3 | Offered 12→10, 12→9 (12 → 1, then 24 → 13); seat 0 moves 24 → 1 | PASS |
| No hitting | Seat 0 on 18, a single seat-1 checker on 15, roll 3-5 | 18→15 is **not** offered; 18→13 is | PASS |
| Head rule | Seat 1's **first** turn with 6-6 / 4-4 / 3-3 | 2 checkers off the head each time (6-6: 12→6 ×2, then blocked by seat 0's head; 4-4: 12→8 ×2, 8→4 ×2; 3-3: 12→9 ×2, 9→6, 6→3) | PASS |
| Head rule | First turn 5-5; a later turn 6-6 | 1 checker off the head | PASS |
| No wall | Seat 0 could fill a seventh point in a row with all of seat 1 behind | 24→18 refused | PASS |
| No wall | The same with a seat-1 checker already ahead of the wall (on 16) | 24→18 allowed | PASS |
| No wall | Seat 1 could close 7…2 against seat 0 (all of seat 0 behind, on 13) | 8→7 refused; the other steps offered | PASS |
| Higher die | Each of 4 and 5 playable, but not both | Only 10→5 (the 5) offered | PASS |
| Doubles | 2-2 mid-board, head already used this turn | 4 steps played | PASS |
| Pass | Seat 1's head blocked on 11, 10 and 9, roll 1-2 | Events `rolled[1,2]@1 → no_moves@1 → rolled[5,6]@0` | PASS |
| Bearing off | One checker still on 7 (not home), 6-6 | Only 7→1; no bearing off | PASS |
| Bearing off | All home, roll 6-1, farthest checker on 5 | 5→off with the 6 (higher die from the farthest); 1→off with the 1 | PASS |
| Bearing off | All home, roll 4-2, nothing on 4 and a checker farther (5) | No bearing off with the 4 (5→1 instead) | PASS |
| End | The last checker off, opponent bore off none | winner 0, reason `mars`, score 2 | PASS |
| End | The same, opponent bore off 1 | reason `bear_off`, score 1 | PASS |

### Full games through the API

| Game | Result | Invariant violations |
|---|---|---|
| Long, q44a vs **Easy** bot, Casual, bet 3,000 | **Won**; off 15:7. 48 of my turns, 104 steps, 4 doubles (4 steps each) | **0** after fixing my own head-check (the first run's 7 hits were a bug in my script) |
| Long, q44a vs Easy, Casual, bet 2,000 | **Won**; off 15:13. 51 turns, 115 steps, 7 doubles | **0** |
| Long, q44d vs **Medium** bot, Beginner, bet 500 | **Lost with none borne off (mars)**; off 0:15 | **0** |
| Short, q44e vs Easy, Beginner, bet 500 | Lost. The bar was used: 29 hits seen, 23 of my entries from the bar | **0** |

What "invariant violations" covers, on every state: 15 checkers each, bar 0/0 in Long, server pips equal
the formula, no six-wall for either side, every step goes forward by exactly its die, no step onto an
opponent point, and no head step offered after the turn's limit. In the second and third Long games
neither side ever built a wall and no head limit was broken.

## 2. Boards (two-person private Long table: player2 = seat 0, player1 = seat 1)

| Check | 1440 | 390 | Result |
|---|---|---|---|
| Your head on your screen | Both seats see "Point 24: 15 checkers, yours" at the **top right** (x 1113); the opponent's head ("Point 12 …, opponent's") at the bottom left | Same positions, but the board is 576 px wide in a 356 px viewport, so your head (x 478) is **off-screen** | 1440 PASS, 390 BUG-1 |
| Your way ends bottom right; point numbers from your side | Seat 1's head is labelled Point 24 and its home Points 6…1 at the bottom right, like seat 0 | same | PASS |
| No bar in Long | The middle column is empty (no bar controls) | same | PASS |
| Pips | "Pips: you 360, opponent 360" / "Пипсы: у вас 360…" / "Միավորներ՝ դուք 360…"; updates after moves (e.g. 360 · 352) | same | PASS |
| Off tray, dice, highlights | "Their off 0 / Off 0" (ru "У соперника / Выброшено", hy "Մրցակցինը / Դուրս"). Doubles show 4 dice and used ones dim. Only legal sources are clickable: the head for an opening; clicking it highlights exactly the server's targets (24→21, 24→22) | the off tray is off-screen too | PASS (1440) |
| Horizontal page scroll | none (scrollWidth 1440) | none (390); the board scrolls inside its box | — |

Evidence: `qa2/t46-board-{1440,390}-{en,ru,hy}-seat{0,1}.png`, `qa2/t46-highlight-seat0.png`,
`qa2/n46-w-en-room.png`, `qa2/n46-m-hy-room.png`.

## 3. Short nardi

A full Short game against an Easy bot went to the end. 29 hits were seen, I entered from the bar 23
times, and there were no rule violations. The pip counts also appear for Short (final 90 · 0). The
mode is listed as "Short nardi" / "Короткие нарды" / "Կարճ նարդի" — **PASS**.

## 4. Picker and starting games

| Check | Actual | Result |
|---|---|---|
| Picker | "What shall we play?" ("Во что будем играть?"). Two board pictures, **Long nardi first and selected**, then Short nardi; 283 px each at 1440, 172 at 390; no horizontal scroll | PASS (qa2/n46-*-picker.png) |
| Start a bot game: Short | Choose Short → Start game → "Short nardi (mode 1), bot, Beginner, bet 500" | PASS |
| Play again after Short | New room: Short nardi (mode 1), same group and bet | PASS |
| Back on the page | The picker shows Long selected again (the default) | PASS |
| Start a bot game: Long | Long nardi (mode 7) | PASS |
| Play again after Long | Long nardi (mode 7), same group and bet | PASS |
| Private table: Long | "Create table" → Long nardi, private, Beginner, bet 500 | PASS (qa2/t46-ui-private-long.png) |
| Matchmaking per mode | player1 queues Long Casual and q40mm2 Short Casual: no match after 9 s | PASS |
| Matchmaking per group | Long Casual vs Long Beginner: no match after 9 s | PASS |
| Matchmaking, same mode and group | Long Casual for both: matched → "Long nardi, matchmaking, Casual, stake 2,000"; each charged 2,000 | PASS |

## 5. Groups, bets and payouts per mode

| Game | Ledger | Result |
|---|---|---|
| Long win, Casual 3,000 | `game_stake −3,000`, `game_payout +6,000` (wallet 149,966 → 152,966) | PASS |
| Long win, Casual 2,000 | `−2,000`, `+4,000` | PASS |
| Long loss (mars), Beginner 500 | Only `game_stake −500`. A mars doesn't change the Fishka payout (O-1) | PASS |
| Long matchmaking, the opponent leaves | Winner `game_payout +4,000` on a 2,000 stake | PASS |
| Short loss, 500 | `game_stake −500` | PASS |

## 6. Reload, replay and emojis

| Check | Actual | Result |
|---|---|---|
| Reload mid-game | Long table after moves: every point's label and count identical before and after `Page.reload` (state rebuilt from the move log) | PASS |
| Emoji flights on the Nardi player chips (two contexts), 1440 | A→B ❤️: start and landing OK on both screens. B→A 😂: OK on both. Quick "Good luck!" bubble: it travels from A's chip to B's chip on both. Chat logs match | PASS |
| Same at 390 | All OK; no horizontal scroll | PASS |

## Bugs

### BUG-1 — Medium — On phones the board doesn't fit: your head, your home and the off tray are off-screen

- **Steps:** at 390×844, open any Nardi game. It is most visible in Long, where your 15 checkers start on
  the top-right point.
- **Expected:** the whole board is visible. At least your head, your home (bottom right) and your off tray
  should be visible without hunting for them.
- **Actual:** the board has `min-w-[36rem]` (576 px) inside an `overflow-x-auto` box of 356 px. On load
  you see only the left part: the opponent's head and empty points. Your head (x 478), your home and the
  "Off" tray are off-screen, and there is no visible cue that the board scrolls sideways.
- **Evidence:** qa2/t46-board-390-en-seat1.png, qa2/n46-m-hy-room.png; measured board 356 / 576 px.
- **Suspected:** `apps/web/src/games/nardi/NardiBoard.tsx` (`<div className="flex min-w-[36rem] …">`
  inside `overflow-x-auto`). This dates from Phase 6 (2d58be4), so Short has it too; Long makes it worse
  because both your head and your home are on the right. Two possible fixes:
  - scale the board to the width on phones (smaller points and checkers, or two stacked halves);
  - at least scroll the box to the right edge on load and show a scroll hint.

### BUG-2 — Low — A mars isn't shown to the players

- **Steps:** lose a Long game without bearing off a checker, or win one that way.
- **Expected:** the result says it was a mars (the engine scores it 2, and the brief lists mars).
- **Actual:** the server records it (`game_participants.score` = 2 for the winner, reason `mars`), but the
  panel says "You lost. The game was played to the end." with nothing about a mars.
- **Evidence:** room 01M6VFGK65YWS20FGDK3CRE54V; qa2/t46-mars-gameover-390.png; `t46mars.mjs` output.
- **Suspected:** `apps/web/src/features/play/components/GameOverPanel.tsx` (reason text) and the room or
  result payload: the outcome's `reason` (`mars`) doesn't reach the client (`end_reason` is `completed`).

## Observations

- **O-1:** a mars counts 2 in the engine but the Fishka payout is the same (winner takes bet × 2). If the
  owner expects a mars to pay double, that is a rules or Fishka decision (like the doubling cube).
- **O-2:** two random-move games won against the **Easy** Long bot. Easy is very weak at Long; worth a
  look in the bot difficulty review. The Medium bot beat random play with a mars.
- **O-3:** the Nardi page text above the picker is still the game's English description in ru/hy ("…bear
  them off before your opponent.") — content, not new.

## Data and cleanup

- **Accounts used:** player2 (picker and bot rooms at Beginner 500, left at once), player1 and q40mm2
  (matchmaking, emojis, the two-person table), q44a, q44d and q44e (bot games).
- **Final balances:** q44a 154,966 (two wins), q44d 4,499, q44e 5,500.
- **Rooms:** none waiting or active (DB checked).
- **Browser:** the main tab is signed in as player2@example.test, in English, on /dashboard.

## Fix status (developer, 2026-11-07)

| Bug | Status | Fix | Verified |
|---|---|---|---|
| 1 Medium: the board is 576 px wide in a 356 px box at 390 | Fixed (Short too) | Below `sm` the board fits the width: narrower points and checkers, a thin bar (labels for screen readers), the off trays in a row under the board | Live 390: board scroll width = client width (356), off tray on screen; your head top right, the opponent's bottom left |
| 2 Low: a mars is not shown | Fixed | Rooms send each participant's `score`; the game-over panel says "Mars! A double win." (en/ru/hy) for a win worth 2 | RoomPage.test ("calls a double win…") |
| Obs. a mars doesn't change the Fishka payout | Owner question | — | — |
| Obs. the Easy Long bot is weak | Not changed | Easy plays random legal moves by design (all games) | — |
| Obs. the Nardi page description is English in ru/hy | Admin content | The game's description is edited in Admin → Games | — |

