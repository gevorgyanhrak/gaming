# QA report — TASK-045 · Poker table redesign

- **Date:** 2026-11-06, about 11:24–11:50 local.
- **Build:** `main` at b2ae856 (redesign fb3506a). `make restart` was run first.
- **Machine load:** average 14–26, heavy.
- **Scope:** the visual redesign of the poker table, `apps/web/src/games/poker/PokerBoard.tsx` and the
  `poker-stage`, `poker-rail`, `poker-felt` and `poker-chair` styles. There are no rule or API changes, so
  this run checks that nothing broke.
- **Method:**
  - Real sessions in isolated headless-Chrome contexts (CDP :9333).
  - Layout was measured, not just viewed. A script reads every seat's avatar, plate and box, the bet
    chips, the dealer tray, the info panel, the board cards, the pot and the felt caption, then reports
    any overlaps, off-screen elements, truncated names and horizontal scroll.
  - Contrast was read with DevTools' `CSS.getBackgroundColors`, and on the lit felt by sampling screen
    pixels.
  - Scripts were run one at a time, with `cleanup.mjs` / `leaveall.mjs` between runs.
- **Evidence:** screenshots and scripts are in
  `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/qa2/`, written below
  as `qa2/`.
  - Screenshots: `t45-s{2..6}-{1440,390}-{en,ru,hy}.png`, `t45-play1440-*`, `t45-play390-*`,
    `t45-again1440-*`, `t45-perf6-*`, `t45-bigbet-*`, `t43-t45emo-*`.
  - Scripts: `t45layout.mjs` with `t45m.js`, `t45play.mjs`, `t45big.mjs`, `t45a11y.mjs`, `t45px.mjs`,
    `t43game.mjs`.
  - Log: `qa2/t45-layout.log`.

## Summary

| Area | Result |
|---|---|
| 1. 2–6 seats × 1440/390 × en/ru/hy (30 layouts) | PASS for seats, names, stacks, Bot labels, states, the D button, pot, board, dealer tray, info panel and no horizontal scroll. **2 Low overlap bugs:** hy bet chips on the board at 1440 (BUG-1), and ru/hy bet chips on the felt caption at 390 with 5 seats (BUG-2) |
| 2. A full hand (check, call, bet, fold, all-in), showdown, pause, bust, Play again | PASS |
| 3. Emoji flights and bubbles at a 3-person table, two contexts, 1440 and 390 | PASS (all flights land on the seats). One observation (O-1) |
| 4. Performance at 6 seats, console | PASS — no long tasks, frames median 16.7 ms, no errors |
| 5. Accessibility: seat labels, turn status, contrast | PASS for labels and the info panel and seat plates. **BUG-3 (Low):** the phone caption on the lit felt is about 3.4:1 |

**Totals:** 38 checks — 35 PASS, 3 Low bugs, 3 observations. No Critical, High or Medium bugs.

## 1. Layout matrix

Bot tables at the Beginner group, Easy bots, player q44a. Each cell is one page load in that language and
width. The measurements were taken after the blinds, often with some bots already folded.

| Seats | 1440 en / ru / hy | 390 en / ru / hy |
|---|---|---|
| 2 | Tray: no (the opponent sits at the top). Info panel "Blinds 1 / 1 · Hand 1" (ru "Блайнды … Раздача", hy "Բլայնդներ … Բաժանում"). Bets "Bet 1" ×2. **No issues** | No info panel (as designed); felt caption "Hand 1 · blinds 1/1"; bets show just the amount beside the seat. **No issues** |
| 3 | Dealer tray at the top. Info panel. **hy: the bet chips touch or overlap the first and last empty card slots (BUG-1)** | Tray; no issues in any language |
| 4 | No tray (seat at the top). No issues | No issues |
| 5 | Tray. A folded seat dims, shows "FOLDED" / "Сбросил" / "Պաս" and keeps its BOT tag. No issues | **ru / hy: the bet chips of the two lower side seats overlap the felt caption (BUG-2)**. en: no issues |
| 6 | No tray (seat at the top); two folded seats. **hy: one bet chip overlaps a card slot (BUG-1)** | No issues |

In every layout:
- `scrollWidth` equals the viewport (no horizontal scroll).
- No seat overlaps another seat, the tray, the info panel, the cards or the pot.
- No name is truncated and every seat is inside the screen.
- Each seat shows its name, stack, a "BOT" tag on bots, the D button, and "Folded", "All-in" or "Out"
  as it happens.
- Numbers follow each language's format (ru "24 997").

Evidence: `qa2/t45-s{2..6}-{1440,390}-{en,ru,hy}.png`, for example
`qa2/t45-s3-1440-hy.png`, `qa2/t45-s6-1440-hy.png`, `qa2/t45-s5-390-ru.png` and `qa2/t45-s6-390-ru.png`.

**Big bets (3-person private table).** I made player1 and q40mm2 raise to 46,911 and 23,456 so the bet
chips would be at their widest:

| Width | Language | Bet chips vs the board slots |
|---|---|---|
| 1440 | en | Clear (chip right edge 533 vs slot left edge 544) |
| 1440 | hy | **Overlap by about 20 px on each side** (395–564 and 876–1045 vs slots 544–896): BUG-1 |
| 390 | en / hy | Amount-only chips beside the seats; clear |

Evidence: `qa2/t45-bigbet-1440-hy.png`, `qa2/t45-bigbet-1440-en.png`.

## 2. Gameplay through the UI

Clicks on the real action buttons:
- q44b, 4 seats, at 1440.
- q44c, 6 seats, at 390.
- q44d, heads-up, at 1440.
- q44a, 6 seats, at 1440 (the performance run).

| Check | Actual | Result | Evidence |
|---|---|---|---|
| Actions | Call 1 / Call 2, Check, Bet 1 (raise), Fold, All-in 17,007 / 5,006 / 7 / 20 / 42, all accepted. The ½ pot, Pot and All-in presets are offered | PASS | |
| Several betting rounds | Preflop → flop → turn → river in hand 1 | PASS | |
| Showdown | Hand 1 at 1440: "You win 11 chips · with a full house"; the board and every shown hand are face up, the winner's cards have a gold ring, "+11" above the seat. At 390: "You win 11 chips · with two pair". Heads-up: "Alex won 6 chips with a pair" | PASS | qa2/t45-play1440-showdown.png, qa2/t45-play390-showdown.png |
| Pause between hands | The status reads "Next hand · 5 seconds left" | PASS | |
| Bust and game over | After all-ins: "GAME OVER · You lost. · −17,000 Fishkas · Play again · Back to lobby" (4 seats) and "−5,000" (6 seats, 390). Win: "You won! +124,975 Fishkas" (6 seats) | PASS | qa2/t45-play390-gameover.png, qa2/t45-play1440-gameover.png |
| Play again | With 0 Fishkas, the out-of-Fishkas popup comes first ("Get 5,000 Free Fishkas"), as expected. After the bonus, Play again opens a new room (Beginner, same seats). From a win: a new 6-seat room at once | PASS | qa2/t45-play1440-again.png |

## 3. Emoji flights and bubbles on the new seats

Poker private table with player2, player1 and q40mm2, each in their own context. I recorded each
animation's keyframes and compared them with the seat anchors.

| Width | A→B ❤️ | B→A 😂 | C→A 🔥 | B→everyone 🎉 | Quick "Good luck!" to everyone | Chat on 3 screens |
|---|---|---|---|---|---|---|
| 1440 | start and landing OK ×3 | OK ×3 | OK ×3 | over B ×3 | bubble beside A's seat on every screen | identical |
| 390 | OK ×3 | OK ×3 | OK ×3 | over B ×3 | bubble beside A's seat | identical |

On the other players' screens, the bubble for an everyone-message now sits beside the sender's seat,
120–140 px from it; this is the TASK-043 "bubbles beside seats" change. **PASS**, with observation O-1.

Evidence: `qa2/t43-t45emo-{1440,390}-*-{player1,player2,q40mm2}.png`.

## 4. Performance and console

| Check | Actual | Result |
|---|---|---|
| 6-seat game at 1440, start to game over (≈ 3 min, bots acting, chip flights, a showdown) | 5,196 frames: median 16.7 ms, p95 33.4 ms, max 117 ms; **0 long tasks**. The machine load was 14–21 | PASS |
| 4 seats at 1440, 6 seats at 390 | 0 long tasks | PASS |
| Console | No exceptions or errors. Only React Router's dev warning "No `HydrateFallback` element…" (not new) | PASS |

## 5. Accessibility

| Check | Actual | Result |
|---|---|---|
| Seats | The list is labelled "Players". Each seat is labelled "Mariam, 149,967 chips, Bot" or "You, … chips", and the seat to act has `aria-current="step"`. The rail and felt art are `aria-hidden`; the board section is labelled "Board" | PASS |
| Turn status | Header "Your turn · 25 seconds left" | PASS |
| Info panel (1440) | "Blinds" / "Hand" labels 12 px at 7.3:1; values 12 px bold at 19.4:1 | PASS |
| Seat plates | Names 18.0:1, stacks 7.7–8.5:1, "Bot" 14.9:1, "Folded" 21:1 | PASS |
| Felt caption on phones ("Hand 1 · blinds 1/1", 12 px, `text-white/65` on the lit felt) | Brightest text pixel against the felt is about **3.4:1**; the real figure is lower because of anti-aliasing. It is below 4.5:1, and it is the only place phones show the blinds and hand | **BUG-3** |
| "DEALER" label on the felt | About 4.8:1 at the brightest pixel; borderline, but decorative | note |

## Bugs

### BUG-1 — Low — Wide screens, Armenian: side seats' bet chips cover the board's first and last card

- **Steps:** at 1440×900 in hy, open a 3- or 6-seat poker table. Watch a side seat's bet (blinds are
  enough), or let a side player raise to a 5-digit amount.
- **Expected:** the chip sits between the seat and the middle without touching the board.
- **Actual:** "Խաղադրույք 46,911" spans x 395–564 and the first card slot starts at 544 (20 px overlap);
  the same happens on the right side. With small bets the chips touch the slot edge. In en the chips are
  shorter and clear the cards.
- **Evidence:** qa2/t45-bigbet-1440-hy.png; qa2/t45-s3-1440-hy.png; qa2/t45-s6-1440-hy.png.
- **Suspected:** `apps/web/src/games/poker/PokerBoard.tsx`, the wide-screen bet chip at `spot(seat, 28)`
  with the full `t('poker.bet')` label. Two possible fixes: place side bets closer to the seat or limit
  their width, or show only the amount (as on phones) when the label is long.

### BUG-2 — Low — Phones, 5 seats, ru/hy: lower side seats' bet chips cover the hand caption

- **Steps:** at 390×844 in ru or hy, open a 5-seat poker table. The lower-left and lower-right seats post
  blinds or bets.
- **Expected:** the chip beside the seat doesn't cover "Раздача 1 · блайнды 1/1".
- **Actual:** both chips overlap the caption and hide its first and last letters. In en the caption is
  short enough to fit.
- **Evidence:** qa2/t45-s5-390-ru.png (the "2" chip over "Раздача"); measured as "bet0 × caption, bet3 ×
  caption" in ru and hy.
- **Suspected:** `apps/web/src/games/poker/PokerBoard.tsx`, either `betBeside(seat)` for the lower side
  seats of a 5-seat table, or the caption's position (`p.text-xs` under the pot). Wrap or narrow the
  caption, or move those chips up beside the avatar.

### BUG-3 — Low (accessibility) — The phone caption on the lit felt is below AA contrast

- **Steps:** at 390, open any poker table and look at "Hand N · blinds X/Y" under the pot.
- **Expected:** at least 4.5:1 for 12 px text. It is the only place phones show the blinds and hand,
  because the info panel is wide-screen only.
- **Actual:** about 3.4:1 (pixel-sampled: text rgb(177,209,190) on felt rgb(30,119,67)).
- **Evidence:** `t45px.mjs` output; qa2/t45-s3-390-en.png.
- **Suspected:** `apps/web/src/games/poker/PokerBoard.tsx` (~line 173,
  `className="text-xs text-white/65 sm:hidden"`). Use `text-white/90`, or put it on a dark pill like the
  pot.

## Observations

- **O-1:** at 390, a quick-message bubble beside a right-hand seat covers the dealer tray and that seat's
  bet chip for its 3.4 s. It doesn't block clicks (pointer-events none). See
  qa2/t43-t45emo-390-A-quick-everyone-player1.png.
- **O-2:** at 1440 with 6 seats, the top seat's chair rises a little above the stage's top edge. It
  overlaps nothing, but it breaks the frame; worth a look in the UI review. See qa2/t45-s6-1440-hy.png.
- **O-3:** the redesign didn't change the reaction anchors. Every emoji start and landing matched the seat
  rectangles, so `data-seat-anchor` on the new framed avatars works.

## Data and cleanup

- **Accounts:** q44a–q44e, player1, player2 and q40mm2 at bot tables and one private 3-person table.
- **player2:** lost 15,487 Fishkas in the big-bet test (an all-in call, then cleanup). I set it back to
  15,487 with `FishkaWallets::adjust` (reason "QA TASK-045: restore the 15,487 lost in a QA big-bet test").
  q44a won 124,975 against bots and q44b ended at 0; these are test accounts.
- **Helper files:** some of my earlier scratchpad helpers in `qa2/` had been removed during the session
  (`leaveall.mjs`, `blogin.sh`, `cdpt.mjs`, `setbal.sh`, `w.mjs`). I recreated `leaveall.mjs`; nothing in
  the repository was touched.
- **Rooms:** none waiting or active (DB checked).
- **Browser:** the main tab is signed in as player2@example.test, in English, on /dashboard.

## Fix status (developer, 2026-11-06)

| Bug | Status | Fix |
|---|---|---|
| 1 hy bet chips reach the board cards at 1440 | Fixed | Wide-screen bet chips show the amount only (screen readers hear "Bet N"), like phones |
| 2 ru/hy 5-seat phone: bets cover the felt caption | Fixed | The caption left the felt: the table-info panel (blinds, hand) now shows on phones too, in the stage's bottom-left corner, where no bet or seat goes (measured: no overlap at 2, 5 and 6 seats, 390 ru/hy, and 6 seats at 1440 hy) |
| 3 phone felt caption 3.4:1 | Fixed | Caption on a dark pill, white/90 |

