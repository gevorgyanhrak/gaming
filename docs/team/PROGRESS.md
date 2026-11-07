# Work progress — read this first in every session

The single place that says where the work stands, so a new session (after the terminal was closed) can
continue without asking. **Update it after every finished step** (move items between sections, add the
commit). Newest first inside each section. Details live in the linked files.

How to resume (any Claude account — this file and the repo hold everything): read "Now", check `git log --oneline -5` and `git status`, then continue with the first
unchecked step. Owner's rules: commit and push straight to `main`; work autonomously; run lint, types,
tests and build before pushing; keep `.claude/agents/gaming-agent.md` current.

## Now

**TASK-046 · Nardi in two variants: Long and Short** — started 2026-11-07 (request and references below in
"Next"). Correction: today's engine is SHORT nardi (backgammon: bar, hitting); the new variant is LONG nardi.

Long nardi rules (as commonly played in Armenia/Russia): 15 checkers each start on one point (the "head":
seat 0 on point 24, seat 1 on point 12); both move the same way round the board (seat 0 24→1, seat 1 12→1
then 24→13); no hitting — a point with any opponent checker is closed; only one checker may leave the head
per turn, except on a player's first turn with 6-6, 4-4 or 3-3 (two); no block of 6 points in a row unless
an opponent checker is already ahead of it; doubles play four times; as many dice as possible, the higher if
only one; bear off from home (the last quarter) once all 15 are there; mars (opponent bore off none) = 2.

Decisions: the doubling cube is NOT built (it doubles the stake mid-game → Fishka pot rules; owner question);
one game `nardi` with two modes (`classic` = Short, renamed for clarity; new `long`); the engine reads
`variant` from the mode (states without it stay Short); the view adds pip counts for both players.

Steps:
- [x] 1. Engine: LongNardiRules (path model, closed points, head rule + first-turn doubles, 6-block rule,
      bear-off, mars), NardiEngine variant switch, pips in the view, bot evaluation for Long; unit tests
      per rule + whole bot games (tests/Unit/Games/LongNardiTest.php) — 61a2181. (Replay test: step 2.)
- [x] 2. Mode `long` (GameModeSeeder, listed first; deploy runs db:seed) and migration renaming the seeded
      "Classic" to "Short nardi" (only if unchanged); groups/matchmaking are per mode already; a Long game vs
      a bot through the real pipeline replays from the move log (tests/Feature/Games/LongNardiRoomTest.php) — e0aaa53.
- [x] 3. Frontend: NardiBoard for Long (seat 1's view turned by half a board, no bar), pip counts for both
      variants; the Nardi page's "What shall we play?" picker with two board previews (registry
      `modePreviews`); en/ru/hy; NardiBoard test — c7fc6b0. Live: 1440 en, 390 hy.
- [ ] 4. Live check (both variants, bots and two people, 390/1440), QA, UI/UX, PM; push; report.

## Next

**Owner rule (2026-11-02): strict sequential order.** Finish one task completely (implemented, tested,
verified, pushed) before starting the next; never two at once; nothing partial. Order = the order the
owner gave them: TASK-043 (done) → 044 (done) → 045 (done) → 046 (Now) → 046 → 047 → 048. Older items below those come after.

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

- 2026-11-07 · TASK-045 Poker table redesign (own art: stage, rail, felt, framed seats on chairs, dealer tray,
  corner table info) — fb3506a, be23f04, 5c3639b, 219b76d, b5d1031. QA `docs/qa/QA_REPORT_TASK045.md` (35/38 →
  fixed), design `docs/design/REVIEW_TASK045.md` (revision → Re-review APPROVED), PM ACCEPT WITH FOLLOW-UPS
  (`docs/product/VALIDATION_TASK045.md`; TASK-052 controls fit — done here; TASK-053 header deltas per hand;
  owner Q14 joinable seats; Q11 chat position).
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
