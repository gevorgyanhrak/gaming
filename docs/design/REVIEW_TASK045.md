# Design review — TASK-045 · Poker table redesign (2026-11-06)

UI/UX agent. Build: `main` at `d540587`. Code: `games/poker/PokerBoard.tsx`, `poker-*` classes in
`styles/index.css`. Decisions: `docs/team/PROGRESS.md` "Now". QA: `docs/qa/QA_REPORT_TASK045.md`
(35/38, all fixed).

## Verdict

**NEEDS REVISION**, with one required fix (issue 1: the acting player doesn't stand out). The rest is
Low.

The table now has the reference's look and atmosphere in our own art:
- a wide oval of lit green felt with a spade watermark;
- a thick, warm wooden rail with a gold hairline;
- a dark stage with a faint gold damask-like pattern;
- framed square avatars on leather chairs;
- a dealer tray with chip racks at the top when no seat is there;
- a "Blinds / Hand" plate in the bottom-left corner.

The two decisions differ from the reference, and I agree with both:
- no "+" seats, because nobody can join a running table;
- the tray only when the top seat is free.

## What I checked

Live on the main tab as player2 at bot tables (`qa2/pokershot.mjs`, plus my `qa2/rv45res.mjs` for a
bot's turn, the hand result and reduced motion). Screenshots: `scratchpad/review/p45-*.png`.

| Size | Seats | Language | Screenshot |
|---|---|---|---|
| 1440×900 | 2, 3, 4, 6 | en | `p45-r1440-2/3/4/6.png` |
| 1440×900 | 6 | ru | `p45-r1440-6-ru.png` |
| 1440×900 | 3 | hy | `p45-r1440-3-hy.png` |
| 390×844 | 3, 6 | en | `p45-r390-3/6.png` |
| 390×844 | 6 | hy | `p45-r390-6-hy.png` |
| 1440×900 | 3, a bot's turn and the hand result | en | `p45-res1440-botturn.png`, `p45-res1440-result.png` |
| 390×844 | 3, result under emulated reduced motion | en | `p45-res390r-result.png` |

## What works

- **Hierarchy:** the board cards, the pot pill and, in the result, the plate "Daniel won 3 chips ·
  with two pair" sit dead centre on the brightest felt. Shown hands turn face up at their seats; the
  winner gets a gold plate and "+N".
- **Stage pattern:** faint and calm (gold at 8–10 %). It reads as texture, not as a busy wallpaper.
  It never competes with the cards. If anything it could be a touch stronger at 1440 (see nit).
- **Rail and felt:** a convincing wood gradient with an inner shadow; the felt light falls off
  towards the rail. Good at both sizes.
- **Chairs and frames:** the leather chair backs and gold-edged square frames are consistent at 2–6
  seats.
  - Folded seats dim with "FOLDED" / «СБРОСИЛ» / «Պաս» and keep the BOT tag.
  - An out player shows "OUT".
  - The D button sits on the frame's corner.
- **Dealer tray:** shown only at 2–3 seats when the top is free (1440 and 390), with five coloured
  chip racks and a "DEALER" label. Tasteful.
- **Info panel:** "Blinds 1 / 1 · Hand 1" in the stage's bottom-left corner at both sizes, with no
  overlap with any seat, bet or board (measured). ru/hy labels fit. Text white/60–70 on the stage is
  ≥ 7:1 and the values are gold-light (14:1).
- **Contrast:** stacks gold-light on dark plates ≥ 12:1, names white, the pot pill white on black/55
  ≥ 15:1.
- **No horizontal scroll** in any layout.
- **Reduced motion:** the result shows its plate and "+30,959" with no transform animations
  (`anims: []`).

## Issues

### 1 · Medium — the acting player barely stands out

| | |
|---|---|
| Seen | When a bot is to act, the only signal at its seat is the 3 px timer ring round the square frame (`p45-res1440-botturn.png`: Michael). The same gold is used for every frame's edge, so at a glance all seats look alike. At 390 it's a 2–3 px line on a 48 px frame. The old round avatar had the same ring, but the new frames, with their gold hairline, make it read as decoration. The brief asked that the acting player stand out |
| File | `games/poker/PokerBoard.tsx`, the seat (chair + `Avatar` + nameplate) |
| Fix | Mark the acting seat at three levels: (a) the chair gets a soft gold glow (`shadow-[0_0_0_2px_var(--color-turn),0_0_22px_rgb(242_193_78/0.45)]`); (b) the nameplate border turns `border-turn`; (c) the ring stays as the timer. Keep it off under reduced motion apart from the static glow (no pulse). Make sure it differs from the hand-winner glow (gold-light "+N" plus a gold plate): use the `turn` colour for acting, `gold` for winning. **Check:** in a bot's-turn screenshot at 390 and 1440, the acting seat is identifiable without zooming |

### 2 · Low — the top seat breaks out of the stage

At 1440 with 4–6 seats, the top seat's chair pokes above the stage's rounded top edge, about 20 px,
and almost touches the chat bar (`p45-r1440-6.png`, `p45-r1440-6-ru.png`: chair top ≈ 140, stage top
158, chat bar bottom 133). It looks like a layout slip rather than a design choice.
- **Fix (`PokerBoard.tsx`, stage padding):** add `pt-8` to the stage on wide screens, or pull the top
  seat's `spot()` radius in a little (`radiusY` 46 instead of 50).

### 3 · Low — showdown cards hide who is who

In the result, each shown hand covers the whole avatar frame, initial included
(`p45-res1440-result.png`). Only the nameplate below says whose cards they are. That's fine at 1440,
but at 390 the "+N" chip covers part of the cards as well (`p45-res390r-result.png`). Offset the shown
cards upward by about 25 % of the frame, so the initial and the top of the frame stay visible, and
place "+N" above the cards.

### 4 · Nit — the pattern at 1440

On large screens the damask ovals are so faint (≈ 8 %) that the stage reads as plain dark brown at
normal viewing distance. The reference's patterned backdrop is part of its atmosphere. Try 12 % for
the four outer ovals, keeping the centre medallion at 10 %, and re-check that the info panel stays at
≥ 7:1.

## Observation (not TASK-045)

The header Fishka pill still shows small "−1" / "+3" deltas at every poker hand while chips are at
the table (`p45-res1440-result.png`, top right). That is TASK-040 review issue 2 (header delta while
`at_table > 0`), still open; it's for the backlog, not this task.

## Clean-up

0 open rooms (checked in the database). The main browser tab is on `/dashboard` as player2, in
English. No code was changed.

## Fix status (developer, 2026-11-06)

| Finding | Status | Fix | Verified |
|---|---|---|---|
| Medium: the acting player barely stands out | Fixed | The acting seat's chair glows in the turn colour and its name plate takes a turn-coloured border; the ring stays the timer; a winner keeps the gold plate | Live 1440 and 390, 6 seats (`qa2/p45-g6.png`, `p45-g6p.png`): "You" lit; measured `acting` true |
| Low: the top seat breaks out of the stage | Fixed | More top room (`pt-14`, `sm:pt-20`) | Measured: top chair 12 px inside the stage at 1440, 2 px at 390 (6 seats) |
| Low: shown cards hide the initial; "+N" covers them | Fixed | Shown cards sit above the frame (`-top-8`, `sm:-top-11`); "+N" above the cards | Code |
| Nit: the stage pattern reads as plain brown | Fixed | Outer ovals at 12 % | Screenshot |

