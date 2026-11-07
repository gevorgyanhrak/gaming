# PM validation — TASK-045 · Poker table redesign

- **Date:** 2026-11-07 · **Build:** `main` at 73245d5 (redesign fb3506a; QA fixes be23f04, 5c3639b; design
  fixes 219b76d)
- **Request (owner, 2026-11-02):** "poker table like it, with chat", with a screenshot of a poker site's
  table. The screenshot showed:
  - a wide oval felt with a thick rail on a dark patterned background;
  - framed square avatars on chairs;
  - empty seats as "+";
  - the dealer at the top with chip stacks;
  - table info (name, blinds) at the bottom left;
  - a chat box below.
- **Inputs:**
  - `docs/team/PROGRESS.md` "Now";
  - QA `docs/qa/QA_REPORT_TASK045.md`: 38 checks, 35 pass, 3 Low, all fixed;
  - design `docs/design/REVIEW_TASK045.md`: NEEDS REVISION (1 Medium, 3 Low) → fixed → re-review
    **APPROVED** 2026-11-07.
- **PM's own checks (read-only):**
  - **Scope:** `git diff fb3506a^ 219b76d` outside docs touches only `games/poker/PokerBoard.tsx`, the
    `poker-*` styles in `styles/index.css` and 5 strings per locale. No API, engine or bot change, so the
    rules are untouched by construction.
  - **Screenshots viewed:** `review/p45-rr1440-botturn.png`, `p45-rr1440-myturn.png` and
    `p45-rr390-myturn.png`. They show the patterned stage, the wooden rail, the oval felt, framed avatars
    on chairs, the info plate bottom-left at both sizes, and the lit acting seat (Michael; "You").
  - **Web vitest:** 145 passed.
  - Backend tests not run (no backend change; shared testing DB).

## Verdict: **ACCEPT WITH FOLLOW-UPS**

- **Matches the reference:** the table matches the screenshot's look in our own art. Two elements were
  changed on purpose, and I agree with both: no "+" seats, and the dealer tray only when the top is free.
- **Nothing broken:** gameplay, the reaction anchors and the phone layout work (QA 2–4).
- **Follow-ups:**
  - the chat placement, which is still the owner's question 11;
  - a check that the action controls fit on short desktop screens (seen near the bottom edge at 1440×900);
  - an older header-delta issue from TASK-040 that the reviewer saw again.

## A. Reference and rules

| Element | Status | Evidence |
|---|---|---|
| Wide oval felt | Met | `poker-felt`: lit green oval with a spade watermark; 2.1:1 on wide screens, a tall oval on phones (design "What works"; PM screenshots) |
| Thick rail | Met | `poker-rail`: warm wood gradient, inner shadow, gold hairline (design review) |
| Dark patterned background | Met | `poker-stage` damask-like pattern, outer ovals raised to 12 % after the nit; "reads as texture, doesn't compete with the cards" (re-review 4) |
| Framed square avatars on chairs | Met | `poker-chair` + square gold-edged frames at 2–6 seats; folded seats dim with "FOLDED" / «Сбросил» / «Պաս», BOT tag kept (QA 1, design review) |
| Acting player stands out | Met | Design issue 1 fixed: the chair glows in the turn colour and the nameplate gets a turn border; the winner's look is different (re-review 1; PM screenshot `p45-rr1440-botturn.png`) |
| Empty seats as "+" | Not met **by decision** | No "+" seats: nobody can sit down at a running table, so a "+" would promise a missing feature (§B) |
| Dealer at the top with chip stacks | Partly **by decision** | A dealer tray with five chip racks and "DEALER" appears at the top at 2–3 seats. At 4–6 seats a player sits at the top, so there is no tray; the D button marks the button seat (QA 1, §B) |
| Table info (name, blinds) bottom left | Met (blinds + hand; no name) | Corner plate "Blinds 1 / 1 · Hand 1" at 1440 and, after QA BUG-2, at 390. No overlap measured with seats, bets or cards; ≥ 7:1 contrast (QA fix status; design review). Our tables have no names; the group badge in the room header carries the group |
| Chat box below | Partly **by decision** | The TASK-043 chat bar sits above the table (visible while playing); owner question 11 is open (§B) |
| Our own art | Met | All CSS/SVG in the repo; nothing copied (design review) |
| Gameplay unchanged | Met | Frontend-only diff (PM check); QA 2: check, call, bet, fold, all-in, several streets, showdown, pause, bust and Play again all pass |
| Phone layout kept | Met | Same seat positions and phone bets beside seats; the table-info plate moved from the felt into the stage corner (QA BUG-2/3 fix), which improves contrast (dark pill, white/90). QA 1 matrix at 390; re-review at 390 |
| en / ru / hy | Met | QA 1: 30 layouts (2–6 seats × 1440/390 × 3 languages) with no clipping and numbers per locale; hy bet overlap fixed by showing the amount only, with "Bet N" kept for screen readers (BUG-1) |
| Accessibility | Met | Seats labelled with name, chips and Bot; `aria-current="step"` on the acting seat; art `aria-hidden` (QA 5); BUG-3 caption contrast fixed |
| Reactions still land on the seats | Met | QA 3: every flight and bubble on the new frames, 3 players, 1440/390 (O-3) |
| No performance cost | Met | QA 4: 6 seats, 0 long tasks, median frame 16.7 ms |

## B. Decisions taken without the owner

| Decision | PM judgement | Owner? |
|---|---|---|
| **No "+" empty seats** | Right. A "+" means "sit here", and the platform has no mid-game joining: tables start full, from matchmaking or a private invite. Showing it would present a feature that only exists in the UI (CLAUDE.md non-negotiable). If the owner wants open tables people can join and leave (ring-game style), that is a backend change to rooms, buy-ins and Fishka settlement | **Question 14** (only if they want joinable tables) |
| **Dealer tray only when no seat is at the top** | Right: at 4–6 seats the top position belongs to a player. The D button still marks the dealer | No |
| **Table info in the corner, on phones too** | Better than the reference on phones: the felt caption collided with ru/hy bets and failed contrast (QA BUG-2/3) | No |
| **Chat bar above the board, not below** | Usable (always visible), but it differs from the reference, which shows the chat under the table. Still owner question 11. The screenshot also shows the controls sitting low at 1440×900 (see C), so moving the chat below would push the controls further down unless the layout changes | Question 11 (open; refined below) |

## C. Follow-ups

| Item | Where |
|---|---|
| Joinable tables with "+" seats | Owner question 14 |
| Chat under the table vs above it | Owner question 11 (updated with the layout trade-off) |
| The action area ends near the bottom edge at 1440×900 (in `p45-rr1440-myturn.png` the ½ pot / Pot / All-in presets are cut at the bottom after a small scroll). Check that cards, the actions and the bet slider fit without scrolling at 1280×720 and 1440×900 | TASK-052 (new, P2) |
| The header Fishka pill still shows small "−1 / +3" deltas at each hand while chips are at the table (re-seen in this review; TASK-040 design issue 2 has no recorded fix) | TASK-053 (new, P2) |
| Small: the top seat's card backs (390) and its "+N" (1440) poke slightly above the stage edge; the reviewer accepts this | — |
