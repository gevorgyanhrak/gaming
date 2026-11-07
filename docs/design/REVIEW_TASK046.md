# Design review — TASK-046 · Nardi in two variants, Long and Short (2026-11-07)

UI/UX agent. Build: `main` at `88239bd`. Decisions: `docs/team/PROGRESS.md` "Now" (own art, no doubling
cube, existing board look kept with pip counts added). QA: `docs/qa/QA_REPORT_TASK046.md` (38/40, fixed).

## Verdict

**NEEDS REVISION.** Two small required fixes:
- the Long preview shows the wrong start position;
- in Russian at 1440 the off-tray labels are cut and the board overflows.

The rest is Low or a follow-up.

The picker answers reference 1 well: "What shall we play?" with two board thumbnails, the selected
one framed in gold, in our own art. Both variants play from the right orientation for both seats, the
pip counts are in place, and the phone board fits (QA's Medium fix holds).

## What I checked

Live, as player2 (`qa2/nardishot.mjs`, plus my `qa2/rv46.mjs`, where player2 joins player1's private
table as seat 1). Screenshots are `scratchpad/review/n46-*`.

| Screen | Where |
|---|---|
| Picker | 1440 en, 390 en, 390 hy, 1440 ru |
| Long, seat 0 vs a bot | 1440 en, 390 en, 390 hy, 1440 ru |
| Long, seat 1 | 390, 1440 |
| Short, seat 1 | 390 |

The mars line ("Mars! A double win." / «Марс! Двойная победа.» / «Մարս։ Կրկնակի հաղթանակ։») was
checked in code and strings only; reaching a mars live needs a full game.

## What works

- **Picker:**
  - Two equal tiles: 283 px wide at 1440, 172 px at 390.
  - The selected tile has a gold border and glow; the names fit in ru and hy.
  - Native radios are visually hidden, and the tile shows the global focus outline (`has-focus-visible`).
  - The legend "What shall we play?" names the group.
- **Orientation:** Long seat 0 sees its 15 on its head at the bottom left and the opponent's head at
  the top right; seat 1 sees the same layout from its own side ("Point 24: 15 checkers, yours"). Short
  seat 1 shows the 2-5-3-5 setup correctly.
- **Pips:** "Pips 360 · 360" (Long) and "167 · 167" (Short), the same figure as the reference. The
  screen-reader text is "Pips: you 360, opponent 360".
- **Phone fit:** the board box is 356 = 356 at 390 in en and hy, and the off trays sit under the
  points. No page-level horizontal scroll anywhere.

## Issues

### 1 · Medium — Long preview puts both heads on the same side

| | |
|---|---|
| Seen | In the Long tile, your 15 (white) and the opponent's 15 (dark) stack at the top-left and bottom-left points of the same column (`n46-r1440-picker.png`, `n46-r390-picker.png`). In the game, and in long nardi, the heads are diagonally opposite: yours bottom left, theirs top right (`n46-r390-room.png`). The picture is the main way the picker explains the difference, so it should be true |
| File | `games/nardi/NardiModePreview.tsx` (`stacks` for `long`) |
| Fix | Match the board as the player sees it: `[0, 'bottom', 15, true]` and `[11, 'top', 15, false]`. Check that Short's preview also matches seat 0's board (mine at the bottom). **Check:** the Long tile and a fresh Long game's board show the heads in the same corners |

### 2 · Medium — Russian at 1440: off-tray labels cut, board overflows 5 px

| | |
|---|---|
| Seen | The side trays read «у соперника» (wrapped onto two lines) and «Выброшен…» (cut off) (`n46-r1440ru-room.png`). The board box scrolls sideways: `scrollWidth 1109 > clientWidth 1104` |
| File | `games/nardi/NardiBoard.tsx`, the wide-screen off trays |
| Fix | Give the tray column a minimum width that fits the longest label (`min-w-20`), or use shorter labels for the trays (ru «Сброшено» / «У соперника», hy likewise), with `text-center` and wrapping allowed. **Check:** at 1440 in en, ru and hy, the board box has `scrollWidth == clientWidth` and no tray label is cut |

### 3 · Low — Long shows an empty bar

Long nardi has no bar (and Short shows "0 / 0" in it), but the Long board keeps the same wide dark
middle column. It reads as a bar with nothing in it (`n46-r1440ru-room.png`, `n46-long390-seat1.png`).
In Long, narrow it to a thin wooden divider (`w-2`), and give the points the space.

### 4 · Low — opponent checkers invisible in the previews

In the previews the dark checkers (`#3a2a22`) sit on dark green felt at about 1.1:1 and on the red
points at 1.7:1. In the Short tile at 390 the opponent's stacks almost vanish (`n46-r390-picker.png`).
They're decoration, but they carry the meaning. Give the dark checkers a light rim
(`stroke="#c9b48a"`, `strokeWidth 1.2`), as the real board does.

### 5 · Low — narrow point targets on phones

At 390 each point is a 26 × 144 px button (24 points; measured). That passes WCAG 2.5.8 (24 px), but
it is under our 44 px phone target, and neighbouring points are easy to mis-tap. Lowering the target
count isn't possible. Mitigate it:
- extend each point's hit area to the full column, including the gap: no gaps between buttons;
- make the selected checker's legal targets more prominent (a bigger glow), so a mis-tap is obvious;
- consider a 0.5 s "tap again to confirm" only for moves that bear off.

## Follow-ups (not blockers; reference 2 items this task didn't promise)

1. Point numbers 1–24 along both edges, in your own numbering. This helps with learning, and with
   reading moves aloud with friends.
2. A round turn timer on the board (today it lives in the room header badge).
3. Bear-off trays at the side on desktop styled as wooden trays, showing stacked checkers, not only a
   count.
4. A side panel with both players, their clocks and the stake (today: the player chips above the
   board and the group badge).
5. The doubling cube: an owner question (it changes the Fishka pot rules), as recorded.

## Clean-up

0 open rooms (checked in the database). The main browser tab is on `/dashboard` as player2, in
English. No code was changed.

## Fix status (2026-11-07, developer)

1. **Fixed.** Long preview: each player's 15 sit on their own head, diagonally opposite (mine top-right,
   theirs bottom-left), as on the live board. The Short preview's 2-stacks were corrected too.
2. **Fixed.** Russian at 1440: the off column is `sm:w-20` with `leading-tight sm:text-[0.7rem]`, and the
   labels fit. Measured: board scroll 1104 = client 1104.
3. **Fixed.** In Long the middle column is a thin divider (`w-1.5 sm:w-2`), and the points take the space.
4. **Fixed.** Preview checkers use the board's colours with rims (mine `#2f9a5a` with `#d8f0e0`, theirs
   `#efe6cf` with `#5a4630`).
5. **Mitigated.**
   - Points are already gapless `flex-1` columns, so each one's whole column is its hit area.
   - Legal targets now get a 2 px inset gold outline and a glow, not just a tint
     (`n46-sel390-select.png`).
   - The "tap again to bear off" confirm is not built. Bearing off has its own large tray button, which
     lights up only when it is legal.
