# Work progress — read this first in every session

The single place that says where the work stands, so a new session (after the terminal was closed) can
continue without asking. **Update it after every finished step** (move items between sections, add the
commit). Newest first inside each section. Details live in the linked files.

How to resume: read "Now", check `git log --oneline -5` and `git status`, then continue with the first
unchecked step. Owner's rules: commit and push straight to `main`; work autonomously; run lint, types,
tests and build before pushing; keep `.claude/agents/gaming-agent.md` current.

## Now

**TASK-045 · Poker table redesign like the owner's screenshot** — started 2026-11-06. Reference: a wide
oval green felt with a thick rail on a dark patterned background, framed square avatars on chairs around
the rail, empty seats as "+", the dealer at the top, table info (name, blinds) at the bottom left, a chat
box below. Our own art (no copied assets); gameplay, rules and the phone layout stay.

Decisions (autonomous):
- Poker gets its own classes (`poker-stage`, `poker-rail`, `poker-felt`, `poker-chair`): `.table-rim`
  is shared with chess/checkers and stays as it is.
- No "+" empty seats: a running table has no free seats and nobody can sit down mid-game (it would
  promise something that doesn't exist). Players who left keep their dimmed seat.
- The dealer: a dealer tray with chip racks at the top of the felt when no seat sits at the top; the "D"
  button still marks the button seat.
- Table info panel at the bottom left on wide screens (hand number, blinds); on phones it stays on the felt.
- The table chat stays above the board (owner question 11 is open).

Steps:
- [x] 1. Stage (dark patterned backdrop), rail and felt with a watermark, framed square avatars with
      chairs, dealer tray, info panel; phone keeps its layout with the new skin — fb3506a.
- [x] 2. Tests (PokerBoard 9/9, all 145 web), live screenshots: 3 seats 1440 (dealer tray), 4 seats 1440
      (seat at the top → no tray), 3 and 6 seats at 390; no horizontal scroll; seat anchors intact.
- [x] 3a. QA (docs/qa/QA_REPORT_TASK045.md: 35/38, 3 Low → all fixed: be23f04, 5c3639b; the table info
      moved off the felt into the stage's corner on phones too).
- [x] 3b. UI/UX review NEEDS REVISION (acting seat; Lows) → fixed, 219b76d.
- [ ] 3c. Reviewer's check of the fixes, PM validation; push; report.

## Next

**Owner rule (2026-11-02): strict sequential order.** Finish one task completely (implemented, tested,
verified, pushed) before starting the next; never two at once; nothing partial. Order = the order the
owner gave them: TASK-043 (done) → 044 (done) → 045 (Now) → 046 → 047 → 048. Older items below those come after.

- **TASK-045 · Poker table redesign like the owner's screenshot** (2026-11-02): wide oval felt with a
  thick rail on a dark patterned background, seats with avatar frames around the rail (empty seats as
  "+"), dealer at the top, table info (name, blinds) at the bottom left, chat panel below (done in
  TASK-043). Our own art; keep the phone layout. After TASK-044.
- **TASK-046 · Nardi in two variants: Long and Short** (owner, 2026-11-02, screenshot of a "what shall we
  play?" picker with two board thumbnails "Длинные" / "Короткие"). Long nardi = today's engine. Short
  nardi (backgammon rules: opposite directions, hitting blots to the bar, entering from the bar, bearing
  off; standard 2-5-3-5 setup) = a new engine variant + a second Nardi game mode; a picker with two board
  previews on the Nardi page; bots, groups, matchmaking per variant; en/ru/hy. After TASK-045.
  Owner reference (2026-11-02, second screenshot, a short-nardi game board): carved wooden board, points
  numbered 1–24 on both edges, pip count per player beside the board ("167"), round turn timer, red dice,
  bear-off trays at the side, a doubling cube ("64"), right panel with both players' avatars, clocks
  ("1:00") and the stake ("50"). Our own art. Owner question before building: the doubling cube (it
  doubles the stake mid-game → Fishka pot rules).
- **TASK-047 · Level & XP progression** — brief `docs/product/briefs/TASK-047-levels-xp.md` (owner,
  2026-11-02, "implement level logic", verbatim). Levels in the DB, backend-only XP, configurable curve
  (1–30 easy, harder after), per-level rewards granted once with history, pending level-ups shown on
  return, bots with fixed high levels (no XP, no rewards), level badge everywhere, progress bar, popup.
- **TASK-048 · Complete platform redesign** — brief `docs/product/briefs/TASK-048-platform-redesign.md`
  (owner, 2026-11-02, verbatim). Phase 1 (PM market research → `docs/product/research/TASK-048-market-research.md`)
  not started (a parallel start was stopped on the owner's sequencing rule; nothing was written). Then UI/UX agent: design system and
  redesign of every page and game; QA. Owner rule applies: no paid VIP, no value prizes (flagged as question).

Later (older backlog):
- TASK-039 · Fishka follow-ups from PM: QA re-run of the 5 failed checks, frozen-clock midnight test,
  live multi-seat poker leave, matchmaking bot fallback without Fishkas.
- TASK-037 · Durak bout space at 1280×720, phone role bubbles 0.65rem.
- Owner questions 6–7 in `docs/product/PRODUCT_BACKLOG.md` (chip dumping at private poker; refill tops up
  or adds).
- The rest of B3 (`docs/product/PRODUCT_BACKLOG.md`), then B4 (needs the owner's choice).

## Done (recent)

- 2026-11-06 · TASK-044 Daily bonus popup with a login streak — 8c7a0d6, 89ecad5, b599e4e, 6c816e4, d93d67f.
  QA `docs/qa/QA_REPORT_TASK044.md` (38/41 → fixed), design `docs/design/REVIEW_TASK044.md` (NEEDS REVISION →
  fixed, mid-flight screenshot), PM ACCEPT WITH FOLLOW-UPS (`docs/product/VALIDATION_TASK044.md`; owner Q12:
  confirm amounts/repeat/reset — a 30-day streak is 137,000 vs 30,000 before; Q13: collect-in-popup instead of a
  silent credit; TASK-051 flaky guards test).
- 2026-11-02 · TASK-043 Emoji & Quick Messages + table chat (ADR 0017) — 491b624, 1d9dcef, 2967f6d, 54a3b6b,
  a21a698, c0c0bc4. QA `docs/qa/QA_REPORT_TASK043.md` (44/46 → fixed), design review APPROVED after two
  revisions (`docs/design/REVIEW_TASK043.md`), PM ACCEPT WITH FOLLOW-UPS (`docs/product/VALIDATION_TASK043.md`:
  owner questions 9–11, TASK-050 mute one player = owner's call).
- 2026-11-01 · TASK-040 groups, poker real balance, bots — b90cd47, 7ce1f4a, d4ca892, edd04cf; QA fixes
  fd32039; design fixes 288639d. QA `docs/qa/QA_REPORT_TASK040.md`, PM `docs/product/VALIDATION_TASK040.md`,
  design `docs/design/REVIEW_TASK040.md`, report in IMPLEMENTATION_REPORT. QA re-run 2026-11-02: fixes confirmed;
  R-1…R-4 fixed — cc236d2.
- 2026-10-31 · Admin: edit a player's details (username, email, phone, language, display name, country,
  bio; re-verification on email/phone change; audited). Fishka design-review fixes (popup focus/inert,
  cooldown state, contrast, 44 px pill). Lock timeout → 422 — 7032ada.
- 2026-10-31 · TASK-038 Fishka currency + QA fixes — 53018b3 (pushed with 7032ada) (QA report `docs/qa/QA_REPORT_FISHKA.md`,
  ADR 0015).
- 2026-10-30 · Waiting table shows you at the bottom — e59414a.
- 2026-10-30 · TASK-035 Durak redesign, TASK-036 Poker hand result, pause between hands — 79a053d, a3ea54b.
- Earlier batches: B1, B2 (see `docs/development/IMPLEMENTATION_REPORT.md`).

## Local environment notes

- Stack: `make up`, `make web` (Vite on 5175; for phones on the LAN: `VITE_REVERB_HOST=<lan ip> npm run dev -- --host`,
  and `apps/api/.env` has the LAN origin in `SANCTUM_STATEFUL_DOMAINS`). After backend job/class changes:
  `make restart` (queue workers keep old code).
- Headless Chrome for checks: CDP port 9333; driver scripts in the session scratchpad (`tools/cdp4.mjs`) —
  recreate if missing (see the gaming-agent notes).
- Test accounts: player1/player2@example.test (password `password`); QA admin accounts exist in the dev DB.
- Never run two backend test commands at once (shared testing DB).
