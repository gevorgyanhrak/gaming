# QA report — TASK-057 · New look for the Nardi board (includes TASK-054)

- **Date:** 2026-11-08, evening local (after commit 181b10b at 18:09).
- **Build:** `main` at 181b10b (board step 1 of TASK-057). Web http://localhost:5175, API http://localhost:8000.
- **Method:**
  - Two throwaway verified accounts (ids 128, 129) in a two-person private room: seat 0 = room creator, seat 1 =
    joiner. Headless Chrome on CDP :9338, two isolated browser contexts, 1440×900 unless stated.
  - **Crafted positions** (doubles, bar, bearing off, tall stacks, full trays, no deadline) were written straight
    into the live room state in the game state store, at the room's current `state_version`. The server still
    decided every legal move and applied every step through the normal endpoints, so nothing about legality was
    faked; only the starting position was. The turn deadline was extended so `TurnTimeoutJob` left the rooms alone
    during testing.
  - The first board views in both variants and both seats, and the Long reload check, were on natural positions.
  - Requests were paced under the 60/minute limit; no 429s were seen.
- **Evidence:** screenshots and scripts are in
  `/private/tmp/claude-501/-Users-hrak/dcf481df-22d2-4f78-a522-b2a3b7f2218b/scratchpad/qa57/shots/` and
  `/private/tmp/claude-501/-Users-hrak/dcf481df-22d2-4f78-a522-b2a3b7f2218b/scratchpad/tools/qa57{lib,a,b,c,d,e}.mjs`,
  written below as `shots/` and `tools/`.

## Summary

| Area | Result |
|---|---|
| 1. Fit: whole board without scrolling | 1440×900 **PASS** (2–4 px to spare). 1366×768 **BUG-1 (High):** the board ends 128–130 px below the fold; your home and Off tray are cut off |
| 2. Numbering and orientation, both seats, Long and Short | **PASS** |
| 3. Turn flow: select, targets, cancel, doubles, spent dice | Selection, targets, cancel and four dice **PASS**. **BUG-2 (Medium):** the wrong die dims when you play the second die first |
| 4. Short bar with hit checkers | Works. **BUG-3 (Medium):** the bar is the only legal move and nothing marks it |
| 5. Bearing off, trays to 15, Off button | **PASS** on both variants at ≥640. **BUG-7 (Low):** no checker stack at phone width |
| 6. +N on tall stacks at 390 / 360 | Readable, off the numbers. **BUG-8 (Low):** the `+10` pill is 1–3 px wider than a point |
| 7. Timer ring vs banner, no deadline | Hidden with no deadline **PASS**. **BUG-4 (Medium):** ring shows full after a reload mid-turn. **BUG-6 (Low):** ring and banner differ by 1 s about half the time |
| 8. Contrast | Numbers, trays' text and target/selection frames **PASS**. **BUG-5 (Medium):** the ready ring on a maple point is nearly invisible (1.2–1.3:1) and it wipes the stone's own rim and shadow |
| 9. Keyboard, reduced motion | Tab/Enter works, outline visible, no animations **PASS**. **BUG-9 (Low):** focus falls to the page after a keyboard move |
| 10. en/ru/hy at 390 and 1440, trays | Trays **PASS**. **BUG-10 (Low):** the Russian bar label sticks out of the spine at ≥640 |
| 11. Touch targets on phones | 27 px (Long) / 25 px (Short) at 390; 24 / 22 at 360. Long meets the 24 px WCAG 2.5.8 minimum; Short at 360 is 2 px under it (known narrow, per the task) |
| 12. Rules unchanged | No rule code touched in 181b10b; every move went through the server |

**Totals:** 67 scripted checks — 56 PASS, 11 with findings. Those 11 are BUG-1 (4 checks), BUG-2, BUG-3, BUG-4, BUG-6, BUG-8 (2) and BUG-9. BUG-5, BUG-7 and BUG-10 came from the contrast, layout and language review. In all: 1 High, 4 Medium, 5 Low. Design review verdict: **NEEDS REVISION** (see the last section).

## 1. Fit without scrolling

| Check | Long seat 0 / 1 | Short seat 0 / 1 | Result |
|---|---|---|---|
| 1440×900: board top and bottom inside the viewport | top 438 / 440, bottom **896 / 898** | top 438 / 440, bottom 896 / 898 | PASS, 2–4 px spare |
| 1366×768: same | bottom **896 / 898** (viewport 768) | 896 / 898 | **BUG-1** |
| Horizontal scroll | none | none | PASS |

Evidence: `shots/Long-seat{0,1}-{1440,1366}.png`, `shots/Short-seat{0,1}-{1440,1366}.png`. The page also scrolls vertically at 1440 (document height 1097, below the fold); only the board was required.

## 2. Numbering and orientation

| Check | Seat 0 | Seat 1 | Result |
|---|---|---|---|
| Printed numbers (top row by halves, bottom row) | 13..18, 19..24 / 12..7, 6..1 | Same arrangement from their side | PASS, Long and Short |
| Printed number = `aria-label` point | Yes | Yes | PASS |
| Long: own head at top right, labelled Point 24: 15 checkers, yours | Yes | Yes (absolute point 12) | PASS |
| Short: own start (2 on 24, 5 on 13 and 6) | Yes | Yes (absolute 1, 12, 19) | PASS |
| Seat 1 selection → printed targets match the server's absolute steps | n/a | Long head with 3 and 5 → targets 21, 19 (server 12→9, 12→7); Short 13 with 6-1 → 7 (server 12→18; the 1-step to 13 is blocked) | PASS |
| Moved checker appears at the same point for both players | Seat 0 sees it at 7 (Long) | Seat 1 sees it at 19 | PASS |

Evidence: `shots/seat1-long-1440.png`, `shots/seat1-short-1440.png`, `shots/flow-long-seat1-selected.png`, `shots/flow-short-seat1-selected.png`.

## 3. Turn flow

| Check | Actual | Result |
|---|---|---|
| Only the server's sources are enabled | 6, 8, 13, 24 with 3-3 | PASS |
| Select: targets, caption, pressed state | 13 → target 10 lights; "Pick where it goes."; `aria-pressed` true; frame around 13 | PASS |
| Click the selected point again | Selection cleared, targets gone | PASS |
| Click another source | Selection moves to it, targets follow | PASS |
| Doubles: four dice, one dims after each step | 4 dice; after one step the first dims and 3 remain (server `dice` 3,3,3); selection cleared | PASS |
| Non-double: the die actually played dims | Rolled 3-5, played 13→8 with the 5: server dice `[3]`, but the **3** dims and the 5 stays lit | **BUG-2** |

Evidence: `shots/flow-doubles-start.png`, `shots/flow-selected-13.png`, `shots/flow-doubles-after-1.png`, `shots/flow-dice-wrong-dim.png`.

## 4. Short bar (crafted: my bar 2, their bar 1, roll 3-5)

| Check | Actual | Result |
|---|---|---|
| Only the bar can be played | All 24 points disabled, bar button enabled | PASS |
| Stones and count | Their bar 1 stone + "1", your bar 2 stones + "2"; 390: spine 32 px, button 28×48, count only, no overflow | PASS |
| Select the bar → targets, cancel, enter | Targets 22, 20 light; click again cancels; entering on 20 leaves bar 1 | PASS |
| The bar is marked as the move to make | No ring or tint before it is selected; caption still says "Pick a checker to move." | **BUG-3** |

Evidence: `shots/bar-seat0-1440.png`, `shots/bar-seat0-selected-1440.png`, `shots/bar-seat1-1440.png`, `shots/bar-390-seat0.png`.

## 5. Bearing off

| Check | Actual | Result |
|---|---|---|
| Short seat 0 (all home, 6-5): select 6 | Off tray enabled with a gold frame; click bears off; tray and count go to 1 | PASS |
| Long seat 1 (home, 6-5): select printed 6 | Off lights; click bears off; server `off` 0,2 | PASS (`shots/bearoff-long-seat1.png`, `shots/bearoff-long-seat1-after.png`) |
| Trays at 14 and 15 (crafted) at 1440, 1024, 820, 640 | 14 and 15 flat chips, nothing overflows (tray 163–180 px tall) | PASS (`shots/tray-15-1440.png`) |
| Same at 390 | Label and count only, no chips (`display:none`); nothing overflows | **BUG-7** |
| Opponent's chips against the teal tray | Visible only as faint bars, 1.1:1 | Part of design review, D-6 |

## 6. +N on tall stacks

| Check | Actual | Result |
|---|---|---|
| 390: 15-checker stacks, Long and Short | `+10`, `+2`, `+7` readable, inside the column height, never over the number | PASS (`shots/stacks-390-seat0.png`, `shots/stacks-390-seat1.png`) |
| Width at 390 / 360 | `+10` pill 27.7 px wide vs point 25 (Short 390), 24 (Long 360), 22 (Short 360) | **BUG-8** (`shots/stacks-360-seat0.png`) |

## 7. Timer

| Check | Actual | Result |
|---|---|---|
| Ring number vs banner number | 30 samples over ~10 s: never more than 1 s apart, but 16/30 differ by 1 | **BUG-6** |
| Ring share after a reload mid-turn | Real room, reload at 23 s of 60: shows 37 s, ring **full** (1.00, should be 0.62) | **BUG-4** (`shots/ring-after-reload.png`) |
| No deadline (crafted) | Ring and timer text gone; banner shows no seconds; no broken layout | PASS (`shots/no-deadline-1440.png`) |

## 8. Contrast (WCAG 2.2 ratios from the CSS, sRGB)

| Pair | Ratio | Note |
|---|---|---|
| Point number on the walnut field | 8.0–9.3:1 | PASS |
| `+N` pill text | 7.5:1 | PASS |
| Tray label on teal | 5.7:1 | PASS |
| Light stone on maple | 1.05–1.7:1 | Separated only by the rim and drop shadow; reads fine in the screenshots |
| Dark stone on garnet | 1.2–1.8:1 (rim 2.9:1) | Same; reads fine in the screenshots |
| Ready ring on maple / on ivory stone | 1.2–1.3:1 / 1.4:1 | **BUG-5** |
| Selection/target frame on walnut / garnet | 9.9 / 6.8:1 | PASS; on maple 1.35:1 but only at the frame's corners |
| Opponent's chips in the tray | 1.1:1 | D-6 |

## 9. Keyboard and reduced motion

| Check | Actual | Result |
|---|---|---|
| Tab reaches the legal points only (others are `disabled`) | First point reached is 13 | PASS |
| Focus outline | 2 px solid gold, offset 2, `:focus-visible`, clear on walnut and over both target and selected frames | PASS (`shots/focus-1440.png`, `shots/focus-target-1440.png`) |
| Enter selects, Tab to a target, Enter moves | Selected 13, tabbed to target 10 (the 3), Enter played it; server dice `[5]` | PASS |
| Focus after a keyboard move | Falls to `<body>` | **BUG-9** |
| `prefers-reduced-motion: reduce` | No transitions or animations run on the board at all | PASS |

## 10. en / ru / hy

Trays: every label fits at 390 and 1440 (`shots/lang-{en,ru,hy}-{390,1440}.png`): en "Their off" / "Off", ru "У соперника" / "Выброшено", hy "Մրցակցինը" / "Դուրս"; no horizontal scroll. Bar label overflow is BUG-10.

## 11. Touch targets

Points measure 27 px wide in Long at 390 (24 at 360) and 25 px in Short at 390 (22 at 360); 160 px tall. Short at 360 is 2 px under WCAG 2.5.8's 24 px minimum; the task notes point width was already about this narrow, and I did not re-measure the old build. The Off tray (135×59) and bar (28×48) are large enough. Real finger input was not tested (emulated viewport and synthetic clicks only).

## Bugs

### BUG-1 — High — At 1366×768 your home and Off tray are below the fold

- **Steps:** any Nardi room at 1366×768.
- **Expected:** the whole board is visible without scrolling.
- **Actual:** the board runs from y 438 to 896–898 (458 px tall) in a 768 px window, so the bottom row is cut off: your home points 6–1 and your Off tray. Everything above the board (header, title and banner, chat strip, player chips, dice) takes 438 px. At 1440×900 the board fits with 2–4 px to spare, so any extra line pushes it out.
- **Evidence:** `shots/Long-seat0-1366.png`, `shots/Short-seat1-1366.png`.
- **Suspected:** `apps/web/src/games/nardi/NardiBoard.tsx:106` (fixed `lg:h-52` points) and the stack in `apps/web/src/pages/RoomPage.tsx`. Fix in Design review D-1.

### BUG-2 — Medium — The wrong die dims when you play the second die first

- **Steps:** Short or Long, roll 3-5, play the 5 first.
- **Expected:** the 5 dims and the 3 stays lit.
- **Actual:** the server's `view.dice` is `[3]` but the 3 dims and the 5 stays lit (`shots/flow-dice-wrong-dim.png`); Long seat 1 the same. Position, not value, is compared.
- **Suspected:** `NardiBoard.tsx:158-163`, `spent={i < all.length - view.dice.length}`. It predates this task (same condition in HEAD~1), but the new pip dice make it obvious. Fix: consume `view.dice` by value:

  ```ts
  const left = [...view.dice]
  // Reverse so a double spends its first die first.
  const spent = [...shown].reverse().map((d) => {
    const i = left.indexOf(d)
    if (i < 0) return true
    left.splice(i, 1)
    return false
  }).reverse()
  ```

### BUG-3 — Medium — With hit checkers, nothing marks the bar as the move

- **Steps:** Short, your bar > 0, your turn.
- **Expected:** the bar shows the "ready" cue the stones get.
- **Actual:** all points are disabled; the bar has no ring until clicked (`classes: 'rounded-lg p-1 '`), and the caption says "Pick a checker to move." A new player sees a dead board.
- **Suspected:** `NardiBoard.tsx:199-206`. Fix: when `sources.has('bar')` add a ring to the button or a ready ring on `BarStack`'s top stone.

### BUG-4 — Medium — The ring starts full after a reload or late join

- **Steps:** a room with a 60 s turn; wait 20 s; reload.
- **Expected:** the ring shows about 40/60.
- **Actual:** it shows full (dashoffset 0) with 37 s left, because the "total" is whatever was left when the component mounted (`NardiBoard.tsx:40-43`, used at `143-144`).
- **Fix:** take the turn's length from the server (the mode config has `turn_seconds`, not in the state today) and compute `share = secondsLeft / turnSeconds`; until then, don't shrink the ring from a client-side guess. TASK-054 acceptance said "the timer matches the server deadline".

### BUG-5 — Medium — The ready ring vanishes on maple and strips the stone's rim

- **Steps:** a legal source on a maple point (bottom-row 8 or 6, top-row 24 in the Short start).
- **Expected:** the stone to lift is unmistakable.
- **Actual:** `ring-2 ring-warning` (#f0b429) is 1.2–1.3:1 on maple and 1.4:1 on the ivory stone. The ring also replaces the stone's `box-shadow` from `.nardi-stone-*` (computed ready stone has no inset rim and no drop shadow), so the only stone that matters is the flattest on the point. On garnet (6.1:1) it is fine.
- **Suspected:** `NardiBoard.tsx:242-244`, `index.css:201-215`. Fix in D-2.

### BUG-6 — Low — Ring and banner are out by 1 s about half the time

- 16 of 30 samples differ by 1 (`shots/tray-15-1440.png`: banner 597, ring 598). Each has its own `useCountdown` with its own 500 ms tick (`NardiBoard.tsx:39`, `RoomPage.tsx:35`). Fix: one countdown, passed down to the board (or via a shared per-deadline tick).

### BUG-7 — Low — No checker stack in the trays at phone width

- At 390 the trays show label and count only (`TrayStack` is `hidden sm:flex`, `NardiBoard.tsx:268`). TASK-054's acceptance named stacked checkers "at 1440 and 390". Fix: a short row of 15 thin chips between the label and the count, or close the acceptance item with the PM.

### BUG-8 — Low — `+10` pill is wider than a point on small phones

- 27.7 px vs 25 (Short 390), 24 (Long 360), 22 (Short 360); it pokes 1–3 px into the next point (`NardiBoard.tsx:130`). `+10` occurs at 15 checkers, the Long start head. Fix: drop the side padding below `sm` or use `tracking-tight`.

### BUG-9 — Low — Focus falls to the page after a keyboard move

- After Enter on a target the focused button becomes disabled; `document.activeElement` is `<body>`, so the next Tab starts at the top. `NardiBoard.tsx:99` (`disabled`). Long-standing pattern. Fix: move focus to the die row or the first legal point after the state updates.

### BUG-10 — Low — "Бар соперника" sticks out of the spine at ≥640

- 63 px wide in a 56 px spine, about 3.5 px over each side (`shots/lang-ru-1440.png`). Armenian exactly fills it. `NardiBoard.tsx:194` (`sm:w-14`), `252`. Fix: `sm:w-16`, or a two-line label at `text-[0.6rem]`, or a shorter string.

## Observations

- **O-1:** the ready ring on bottom-row stacks sits on the stone by the rail, not the top of the stack (`i === shown - 1` at `NardiBoard.tsx:127`); in the top row it is on the correct stone. Low. Fix: `row === 'top' ? i === shown - 1 : i === 0`.
- **O-2:** at 360×740 the dice row wraps and the board needs a small scroll; not in this task's list.
- **O-3:** point numbers are 9.6 px on phones and `+N` 10.4 px; contrast is high but very small. Not new.
- **O-4:** the ring has no change near the end of the turn; the banner has none either, so nothing here is out of line with the page.

## Not tested

- The reference board itself: the site opens on a language screen with no accessible board, and I did not go further. The copy check below is against the owner's written description and the brief.
- Real-device finger input; only emulated phone viewports and synthetic clicks.
- A real end of game on the new board (ring hidden at game over): the no-deadline state was crafted; a 15-off tray was crafted without finishing the game.
- A real screen reader (only the accessible names were read).
- Browsers other than Chrome; chess-clock modes (Nardi has none).
- Widths beyond 1440, 1366, 1024, 820, 640, 390, 360.

## Data and cleanup

- **Accounts:** qa57a-7119957@example.test (id 128), qa57b-7123274@example.test (id 129), wallets set to 500,000 at creation; balances changed by the stakes of the rooms used. Owner's accounts were not touched.
- **Rooms:** none waiting or active for those accounts (DB checked).
- **Browser:** Chrome on :9338 stopped.

## Design review

**Verdict: NEEDS REVISION.** The look is strong and clearly ours: dark walnut case, maple and garnet points, the six-armed rosette, teal trays and the ring between them read as a single, deliberate object, and Armenian-derived ornament keeps it distinct. The revision is about fit on a common laptop and two turn cues, not the art.

### Reads as a copy?

Against the owner's description of the reference (light carved oak, arabesque point carvings, medallions, brass hinges, burgundy trays, grey and white stones, timer on the left edge) and the brief:

- **Clearly different:** case and point colours, no carvings on the points, no medallions or hinges, teal trays not burgundy, the timer in the tray column on the right.
- **Genre, fine:** numbered points, stone-like checkers, a round timer, pip counts, bearing-off trays. The brief lists these as common.
- **Watch item:** a pale and a dark stone is the same pairing the owner describes on the reference (white and grey). It is the genre default, so this is not a copy finding, but warming the dark stone toward bronze or black-cherry would make the set unmistakably ours and also lift it off garnet (D-5).
- **Not checked directly:** the reference board itself could not be opened (language screen only), so this is a comparison with the written description.

### Fixes, in priority order

- **D-1 (High, BUG-1) — Fit at 1366×768.** Below ~800 px of height, bring the board up: put player chips and dice on one row at `lg` (saves ~66 px), and cap the point height, e.g. `lg:h-[min(13rem,calc((100svh-24rem)/2))]` with `size-7` stones on short viewports. Check the 5-stone + `+N` stack still fits the new height, and keep 1440×900 fitting by more than the current 2–4 px.
- **D-2 (Medium, BUG-5) — Ready ring that works on maple.** Replace the Tailwind ring on the stone with a combined shadow that keeps the stone's rim and shadow and adds a dark halo, so the ring is seen on any point, e.g. in `.nardi-stone-ready`: `box-shadow: <stone's own shadows>, 0 0 0 2px #1a0f08, 0 0 0 4px var(--color-warning)`. Do the same on bottom-row and top-row stacks (O-1).
- **D-3 (Medium, BUG-2) — Dim the die actually spent.** See BUG-2.
- **D-4 (Medium, BUG-3) — Mark the bar.** Same ready cue on the bar when it is a source, and let the caption say so ("Enter from the bar").
- **D-5 (Low) — Colour of the dark stone.** Slightly warmer, or a stronger copper rim, so it lifts off garnet (1.2–1.8:1 today) and reads as our own set rather than the genre's black-and-white (see above).
- **D-6 (Low) — Opponent's chips in the tray.** `#2a2d31` on teal is 1.1:1 (`NardiBoard.tsx:272`); use a lighter slate or a thin gold edge so the count is visible at a glance.
- **D-7 (Low) — Trays at 390 and the ru bar label.** BUG-7 and BUG-10.
- **D-8 (Low) — Timer ring.** BUG-4 and BUG-6 together: one countdown and a server-known turn length.

## Fix status (developer)

| Bug | Status | Fix | Verified |
|---|---|---|---|
| 1 High: board cut off at 1366×768 | Open | | |
| 2 Medium: wrong die dims | Open | | |
| 3 Medium: bar not marked | Open | | |
| 4 Medium: ring full after reload | Open | | |
| 5 Medium: ready ring on maple | Open | | |
| 6 Low: ring and banner out by 1 s | Open | | |
| 7 Low: no stack in trays at 390 | Open | | |
| 8 Low: `+10` wider than a point | Open | | |
| 9 Low: focus lost after a keyboard move | Open | | |
| 10 Low: Russian bar label overflows | Open | | |

## Fix status (2026-11-08)

1. Fit: point height and stone size follow the window height; the whole board shows at 1440×900 and 1366×768 (checked live). At 1280×720 it runs 24 px under the fold.
2. The die actually played dims (test). 3. Bar: gold ring on the bar and "Enter your checker from the bar." (test). 4. The state now sends `turn_seconds`; the ring (Nardi and poker) is a share of the whole turn after a reload (test).
5. Ready stones get a dark and a gold ring, and keep their rim. 7. Tray stacks show at phone width. 8. Smaller +N pill, laid on the stone. 9. Focus stays on the board after a keyboard move. 10. Long bar labels wrap.
Notes: warmer dark stones, lighter opponent chips in the tray, ready ring on the outermost stone of bottom stacks.
Left: 6 (ring and banner can differ by 1 s; two separate ticks).

