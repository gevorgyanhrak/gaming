# Design review — TASK-043 · Emojis, quick messages and the table chat (2026-11-02)

UI/UX agent. Build: `main` at `2967f6d`. Brief: `docs/product/briefs/TASK-043-emoji-quick-messages.md`
(§3, §4, §6). Design: ADR 0017. QA: `docs/qa/QA_REPORT_TASK043.md` (44/46, both bugs fixed).

## Verdict

**NEEDS REVISION.** There are 3 Medium issues:
- message bubbles cover clocks and cards (brief §4/§6: "don't obstruct important game elements");
- the chat panel sits below the fold on desktop;
- the picker and chat controls are smaller than 44 px.

There are also 4 Low issues. The picker, the emoji arc and the club look are good; the fixes are
placement and sizing, not a redesign.

## What I checked

All live checks used two real players (player2 = A, player1 = B) in isolated browser contexts.
Screenshots are in `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/review/`.
- **Driver:** my own `qa2/rv43.mjs`, based on `react2.mjs`.
- **Each run checked:**
  - the chat panel and its buttons (sizes);
  - the picker on both tabs;
  - A → B ❤️ through the UI, captured mid-arc and on landing;
  - B → A, three quick messages 380 ms apart;
  - one emoji under emulated reduced motion.

| Game | Size | Language | Screenshots (`t43r-…`) |
|---|---|---|---|
| Chess | 390 and 1440 | en | `chess-390-en-*`, `chess-1440-en-*` |
| Nardi | 390 | ru | `nardi-390-ru-*` |
| Durak | 390 | hy and en | `durak-390-hy-*`, `durak-390-en-*` |
| Checkers | 1440 | hy | `checkers-1440-hy-*` |
| Poker, 3 seats (`react3.mjs`) | 390 and 1440 | en | `r3-rv390-*`, `r3-rv1440-*` |

**Not re-checked live** (QA's `t43kb.mjs`/`t43i18n` covered them):
- keyboard-only use of the picker;
- a screen-reader pass beyond reading the markup.

## What works

- **Picker:**
  - A bottom sheet on phones (full width, 658 px tall, safe-area padding) and a 400 px panel
    bottom-right on desktop.
  - The page behind is `inert`, Escape closes it, and focus returns to the opener.
  - It has recipient chips (Everyone or a player, preselected with one opponent), Emojis / Quick
    messages tabs, search (ru/hy words too), a 12-category jump row with smooth scroll, and recently
    used emojis.
  - The grid is 7 columns on phones and 8 on desktop, with **44×44** emoji targets (200 emojis).
  - Quick messages are grouped under headings and fit in ru and hy.
  - It feels premium and in the card-club style (club-frame, gold active tab).
- **Emoji flights:**
  - A clear arc from sender to receiver (scale 0.4 → 1.6 at the apex), landing on the avatar with a
    small bounce, then rising away. The direction reads clearly in both directions.
  - For "everyone" the emoji pops over the sender.
  - The layer is `pointer-events-none` and `aria-hidden`; only transforms and opacity are animated.
- **Reduced motion:** the emoji appears still at the destination and fades (1 position, no
  translation). Bubbles also stay still.
- **Message bubbles:**
  - Black/85 with a gold hairline, the sender in gold-light over white text: ≥ 13:1, readable over
    felt, wood and the board.
  - They stack without overlapping each other (QA BUG-1 fixed).
- **Table chat:**
  - Clear hierarchy (gold "TABLE CHAT" label, a log with time · name → recipient: message).
  - "You" in accent, others in gold-light; the newest entry auto-scrolls into view.
  - `role="log"` with polite announcements; the 😊 button (44×44) carries the label "Send an emoji".
  - Long ru/hy labels ("Հաղորդագրություններ", "Без анимаций") fit; no horizontal page scroll at any
    size.

## Issues

### 1 · Medium — bubbles cover clocks, cards and the trump

| | |
|---|---|
| Seen | Chess 390: three messages to me stack over the game title. The third goes below my seat chip and covers **player1's chip and clock** ("5:00") (`t43r-chess-390-en-bubbles.png`). Chess 1440: the stack covers the title, the group badge and **my clock** (`t43r-chess-1440-en-bubbles.png`). Durak 390: the stack climbs the left edge over the **deck and trump card** (`t43r-durak-390-en-bubbles.png`). In a blitz game the clock is hidden for up to 3.4 s per bubble |
| Cause | `features/reactions/components/ReactionLayer.tsx` puts message bubbles centred on the seat anchor, stacking up then down. The anchors (`PlayerList` chips, Durak avatar) sit right next to clocks and piles |
| Fix | Place message bubbles **beside** the anchor, on the side away from the board centre (Chess/Checkers/Nardi list rows: to the right of the chip; Durak/Poker seats: towards the screen edge). Show at most **2** at once per seat: a third replaces the oldest (fade it at once) instead of stacking below. Also exclude rects marked `data-reaction-avoid` (clock badges, the Durak deck, bets/board) when choosing above or below. **Check:** at 390 and 1440 with 3 rapid messages, no bubble rect intersects a clock, the deck/trump, a bet chip or the board |

### 2 · Medium — the chat is below the fold on desktop and far from the board on phones

| | |
|---|---|
| Seen | Chess 1440×900: the chat starts at y = 956, under a viewport-capped board, so it is never visible while playing (`t43r-chess-1440-en-chat.png`). Checkers 1440: y = 908. Poker 1440/390: under the action panel. Phones: under the board and the Moves panel (Chess 390: y = 934). The owner's reference has the chat box visible under the table |
| Fix | Desktop (≥ 1024 px): render `TableChat` in the right column beside the board, under "Moves" (Chess, Checkers, Nardi), where the column is empty below 440 px. For Durak and Poker, put a compact 😊 button inside the stage or table corner (bottom-right of the felt, outside card and bet areas) that opens the picker, and keep the log below. Phones: add the same floating 😊 button (44 px, bottom-right of the stage) so sending doesn't need scrolling away from the cards. Keep the full panel where it is now. **Check:** at 1440×900 and 1280×720, the 😊 button is visible without scrolling in all five games |

### 3 · Medium — targets under 44 px in the picker and the chat

| | |
|---|---|
| Measured | Chat **Mute** 52×26 (hy 152×26). Picker: **Close** 36×36, **recipient chips** 88×34, **tabs** 174×36, **category row** 36×36, **quick-message chips** 49–131×40 |
| Files | `TableChat.tsx` (Mute: `px-2.5 py-1`), `ReactionPicker.tsx` (close `size-9`, chips `py-1.5`, tabs `py-2`, categories `size-9`, message chips `min-h-10`) |
| Fix | `min-h-11` on Mute, recipient chips, tabs and message chips. `size-11` on Close and the category buttons: 12 × 44 = 528 px scrolls in the row, which is fine, or use `size-10` with a 44 px hit area via padding. **Check:** every button in the dialog and the chat is ≥ 44 px tall at 390 |

### 4 · Low — the send error text fails contrast

`TableChat.tsx`: the error is `text-xs text-take` (#d9483b) on the club-frame, about 4.1:1 for
12 px text. Use the lighter red already used for losses (`text-[#f87171]`, ≥ 5.4:1) or
`text-sm`.

### 5 · Low — one-tap chips are cut at the panel edge with no hint

| | |
|---|---|
| Seen | 390: "Nice…" is cut and "GG!" hidden; the row scrolls sideways with no affordance (`t43r-chess-390-en-chat.png`, hy `t43r-durak-390-hy-chat.png`) |
| Fix | Let the row wrap on phones (`flex-wrap` with `overflow-x-auto` removed), or show only the 😊 and Messages buttons on the first line and the chips on a second wrapping line |

### 6 · Low — bubbles stick to the screen when you scroll

| | |
|---|---|
| Seen | Bubbles and flights are `position: fixed` at coordinates taken when they start. A player scrolled down to the chat (where the 😊 button is) gets bubbles clamped to the top edge, over the app header (`t43r-durak-390-hy-bubbles.png`: "Լավ քայլ էր!" over the logo). Scrolling during the 3.4 s also detaches them from the seat |
| Fix | In `ReactionLayer.tsx`, when the destination anchor is off-screen, show the bubble docked just above the chat panel (or the floating 😊 button from fix 2) instead of clamping it to the top. Fixes 1 and 2 reduce this a lot |

### 7 · Low — screen-reader details

All in `ReactionPicker.tsx` / `TableChat.tsx`:
- Emoji buttons are labelled with the bare character (`aria-label={emoji}`). Use the catalog's
  first keyword, e.g. "red heart", so every reader names it.
- The tabs have `role="tab"` without arrow-key handling or `aria-controls`. Either add them or
  render two `aria-pressed` buttons.
- **Mute** changes both its label ("Mute"/"Unmute") and `aria-pressed`, so it is announced as
  "Unmute, pressed". Keep one fixed label ("Mute animations") with `aria-pressed`.

## Observations for QA (not design)

`react3.mjs` printed `logs equal false` after the 6-reaction burst at the 3-seat Poker table: A's and
B's chat logs differed (8 entries). This may be ordering only (arrival vs sequence). Please confirm the
log is sorted by the server sequence for everyone.

## Clean-up

- Every room I created was left by all its players (each leave returned 200; Chess, Nardi, Durak,
  Checkers and two 3-seat Poker rooms). Play groups and balances were not touched beyond the
  stakes/buy-ins of those test tables.
- **At the end the local Docker stack had stopped** ("Cannot connect to the Docker daemon"). The
  final checks (0 open rooms, re-login as player2) couldn't run, and the login returned 502. The main
  headless tab is on `/dashboard` in English. The session should still be player2's, because the
  logout request failed as well. Please re-check once the stack is up.

## Fix status (developer, 2026-11-02)

| # | Finding | Status | Fix | Verified |
|---|---|---|---|---|
| 1 | Bubbles cover clocks, deck, cards | Fixed | `ReactionLayer.bubbleSpot`: a bubble goes beside the seat on the side away from the middle, else the other side, below, above — the first spot on screen that touches no other seat; at most 2 per seat (a third retires the oldest) | Live: Chess 1440 (left of your chip, clocks clear), Chess 390, Durak 390 (deck and trump clear), 3-seat Poker 1440 (beside your seat on the felt); r6/r7/r8 screenshots |
| 2 | Chat never visible while playing | Fixed | The table chat is now a bar between the room header and the board (😊, 💬, the latest line, a toggle); opened, the full log, the one-tap messages and Mute | Live 1440 and 390 in Chess, Durak, Poker |
| 3 | Targets below 44 px | Fixed | Close, recipient chips, tabs, category row, quick-message chips, Mute, chat toggle: all ≥ 44 px | Code review of classes (`size-11`, `min-h-11`) |
| 4 | Error text contrast | Fixed | `#f87171` | — |
| 5 | One-tap chips cut at 390 | Fixed | They wrap, in the opened chat | Live 390 |
| 6 | Bubbles over the header when scrolled | Fixed | A seat out of view gets no bubble or flight (the chat lists it) | Reactions.test ("draws nothing for a seat scrolled out of view") |
| 7 | Screen readers | Fixed | Emoji buttons read "❤️ heart"; tabs have ids, `aria-controls`, roving tabindex and arrow keys; Mute keeps one label ("Mute animations") with `aria-pressed` | Reactions.test |
| — | Chat logs differ after a burst | Not a bug | Both logs are sorted by the server's sequence; they differ only in "You" vs the name | Live: identical entries in sequence order |


---

## Re-review — 2026-11-02 (main at `683d062`, fixes in `54a3b6b`)

UI/UX agent. Every claim below was measured live, with two real players (player2 = A, player1 = B;
q40mm2 as the third seat at Poker) in isolated browser sessions. Driver:
`qa2/rv43b.mjs`. Screenshots: `scratchpad/review/t43rr-<game>-<width>-<lang>-*.png`.

**Coverage:**
- **All five games** at 390×844 and 1440×900 in en, plus Durak 390 in hy and Chess 1440 in ru.
- **In each run:**
  - layout at the top of the page;
  - the bar closed and opened;
  - every target measured with `getBoundingClientRect`;
  - the picker, including keyboard tabs;
  - ❤️ A→B and 😂 B→A through the UI, sampled every 230 ms;
  - a one-tap message;
  - both players' chat logs;
  - three messages at once B→A, with the bubble rectangles tested against clocks, cards, the deck
    and discard, bets, other seats, the board, the app header and the turn badge.
- **Chess 390 only:** a 429 from the UI and a seat scrolled out of view.

### Final verdict: **NEEDS REVISION**

The chat bar above the board pushes the Chess and Checkers boards past the bottom of the screen. That
is a gameplay regression at the two most common sizes. Everything else in the original review is fixed
except bubbles on Durak.

### Original findings, verified

| # | Finding | Result |
|---|---|---|
| 1 | Bubbles cover clocks, deck, cards | **Partly fixed.** At most 2 bubbles per seat in every run ✓. They sit beside the seat and touch no clock, board, bet, header or other seat in Chess, Checkers, Nardi and Poker at 390 and 1440 ✓. **Durak still overlaps** (new issue 2) |
| 2 | Chat out of view while playing | **Fixed:** the bar sits between the header and the board (Chess 390: 206–268; 1440: 216–278). It never overlaps the header. It opens and closes, and the open chips wrap (2 rows at 390, no sideways scroll: 354/354). But see new issue 1 |
| 3 | Targets below 44 px | **Fixed.** 😊 44×44, 💬 47×44 (label only from `sm`, `aria-label` kept), toggle 44×44, one-tap chips 56–131×44, Mute 129–204×44. Picker: Close 44×44, recipients 44 tall (78 wide), tabs 174×44, category row 12 × 44×44, emoji buttons 200 × 44×44, message chips 21 × 44 tall |
| 4 | Error contrast | **Fixed:** a real 429 from the UI shows "Couldn't send. Try again in a moment." in `rgb(248 113 113)` at 12 px, ≥ 5.4:1 |
| 5 | Chips cut at 390 | **Fixed:** they wrap in the opened chat |
| 6 | Bubbles over the header when scrolled | **Fixed:** with my seat scrolled out of view (anchor at −137…−95), a message drew no bubble; it was only listed in the chat |
| 7 | Screen readers | **Fixed.** Emoji buttons read e.g. "😀 grin". ArrowRight moves selection and focus to "Quick messages" (`aria-selected`, roving `tabIndex`, `aria-controls="reaction-panel-message"`). Mute has one label ("Mute animations" / «Без анимаций» / «Առանց անիմացիաների») with `aria-pressed` |

**Smoke test, all five games, 390 and 1440 (en; Durak hy, Chess ru):**
- Every emoji flight went the right way on both screens: it started on the sender's seat anchor and
  landed on the receiver's. For example, Chess 390 on B: ❤️ from A's chip (107,313) to B's (125,363).
  Poker 390 on A: 😂 from player1's seat (65,435) to mine (195,735).
- Both players' logs held the same entries in the same order, with "You"/«Вы»/«Դուք» on each side.
- No horizontal scroll anywhere.

### Issues

#### 1 · High — the board no longer fits the screen in Chess and Checkers (regression)

| | |
|---|---|
| Seen | The bar adds ~86 px above the board. Chess 1440×900: board 448–964, so the bottom rank (white's home row, where the player moves from) is 64 px below the fold. Before `54a3b6b` it ended at 878. Checkers 1440: 454–970. Chess 390: 484–840 and Checkers 390: 442–798, both under the tab bar (786); rank 1 is half hidden (`t43rr-chess-390-en-A-top.png`, `t43rr-chess-1440-en-A-top.png`). With the chat opened, a further 114–164 px. Nardi fits closed (squares 429–729 at 390, 435–799 at 1440) but not opened. Durak fits closed through `useFitHeight` |
| Cause | `games/chess/ChessBoard.tsx` (lines 98 and 244) and `games/checkers/CheckersBoard.tsx` (line 74) cap the board at `calc(100dvh-24rem)`, a budget set before the bar existed. On phones the board is width-limited, so the extra stack height pushes it down |
| Fix | Make the board budget include the bar. Simplest: `calc(100dvh-30rem)` for the cap in both files. Better: size the board from its measured top like Durak (`features/play/useFitHeight`), so an opened chat or a wrapped header can't push it out. On phones also tighten the stack, e.g. put the PlayerList chips and the closed chat bar on one row, or show only 😊/💬 in the bar's row and the latest line on open. **Check:** at 390×844 and 1440×900, scrolled to the top, the board's bottom ≤ the viewport bottom minus the tab bar, in Chess, Checkers and Nardi, with the chat closed |

#### 2 · Medium — Durak bubbles still cover the trump card and your role and status

| | |
|---|---|
| Seen | Three messages to me. 390 (en and hy): the first bubble overlaps the deck/trump group and a card. The second covers my role pill ("attacking"/"defending") and the start of the status hint (`t43rr-durak-390-en-A-bubbles.png`). 1440: "Well played!" overlaps the bottom of the trump card; "Good luck!" hides my "defending" pill (`t43rr-durak-1440-en-A-bubbles.png`) |
| Cause | `features/reactions/components/ReactionLayer.tsx` `bubbleSpot` checks only the screen edges and other seat anchors. Game elements aren't in its avoid list |
| Fix | Add the elements to avoid: mark them `data-reaction-avoid` (Durak deck and discard groups, my role bubble and status hint, the hand `ul`; Poker bets and board; PlayerList clocks) and include those rects in `others`. If no side spot is free, prefer above the seat. **Check:** the same three-message test at Durak 390 and 1440 reports no hits on card, pile or status |

#### 3 · Low — opening the chat pushes the table down

Opening the bar adds 114–164 px above the board (Durak 390: stage bottom 774 → 884, past the tab bar;
Poker 390: 752 → 916). The chat stays open while you play. Either cap the open log so the bar grows
only to a fixed small height, or render the opened log as an overlay panel (`absolute`, over the area
under the bar, `pointer-events` on it only), so the table doesn't move.

### Clean-up

- 0 waiting/active rooms (checked in the database); every test room was left by all its players.
- The main browser tab is on `/dashboard` as player2 (37,495 Fishkas) in English.
- Balances moved only by the test tables' stakes and buy-ins.

## Re-review fix status (developer, 2026-11-02)

| # | Finding | Status | Fix | Verified (live, measured) |
|---|---|---|---|---|
| 1 | High: Chess/Checkers board below the fold after the chat bar | Fixed | `useBoardFit` (from `useFitHeight`): the board's width = the measured space from its top to the bottom of the screen (above the tab bar), 280–608 px; phone heading `text-xl` | Chess 1440: board 448–888 of 900; Chess 390: 480–774, tab bar at 786; Checkers 1440: 454–888; Checkers 390: 438–774 |
| 2 | Medium: Durak bubbles over the trump, my role and status | Fixed | Durak marks the deck, discard, my role, my status and my hand `data-reaction-avoid`; bubbles avoid those and other bubbles' landing spots; when nothing is clear (a crowded phone) one bubble at a time on the spot covering least | Durak 1440: two bubbles left of my seat, all clear; Durak 390: one bubble above my seat, trump and count visible, role/status/hand clear; Chess and Poker: two bubbles, no overlap |
| 3 | Low: opening the chat pushes the table down | Fixed | The opened chat lies over the table (the bar keeps its place); Escape closes it | Board moves ≤ 2 px on open, in Chess and Checkers at 1440 and 390 |

