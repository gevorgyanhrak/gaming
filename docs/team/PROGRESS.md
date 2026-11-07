# Work progress — read this first in every session

The single place that says where the work stands, so a new session (after the terminal was closed) can
continue without asking. **Update it after every finished step** (move items between sections, add the
commit). Newest first inside each section. Details live in the linked files.

How to resume (any Claude account — this file and the repo hold everything): read "Now", check `git log --oneline -5` and `git status`, then continue with the first
unchecked step. Owner's rules: commit and push straight to `main`; work autonomously; run lint, types,
tests and build before pushing; keep `.claude/agents/gaming-agent.md` current.

## Now

**TASK-047 · Level & XP progression** — started 2026-11-07. Brief (verbatim):
`docs/product/briefs/TASK-047-levels-xp.md`. Levels and XP live in the DB and are computed only on the backend; a
configurable curve (1–30 easy, harder after); a reward per level (Fishkas via `FishkaWallets`, plus cosmetic
items) granted exactly once with history; pending level-ups shown on return; bots with fixed, distinct high
levels (no XP, no rewards); a level badge next to every avatar; a progress bar; a level-up popup.

Steps:
- [x] 1. Analyse the existing architecture (users, wallets/ledger, game finish pipeline, bots, settings,
      avatars, admin) and write ADR 0018 (XP sources, curve formula, reward config, tables, events, concurrency)
      — `docs/adr/0018-levels-and-xp.md`. No leaderboard exists (N/A until one is built).
- [x] 2. Backend: migrations (user level/xp, level rewards config, level-up/reward history), `Levels` service
      (curve from admin settings, atomic award with row lock, multi-level-ups, rewards once), XP on game finish
      (not for bots), bot levels; API (me/level, pending level-ups + collect; level in user/participant
      resources); admin config; tests (all 13 brief checks that are backend) — tests/Feature/Levels/LevelsTest.php (16),
      tests/Unit/Platform/LevelCurveTest.php; full suite 379 green. Existing players start at level 1 (no back-fill:
      it would pay every past level's Fishkas at once).
- [ ] 3. Frontend: LevelBadge everywhere (profile, rooms, poker seats, player lists, leaderboards, chat),
      progress bar (profile + header/menu), level-up popup with confetti and collect, milestone celebrations;
      en/ru/hy; tests; live check.
- [ ] 4. QA agent → fix; UI/UX review → fix; PM validation → fix; push; report (XP formula, rules, rewards,
      DB changes, files).

## Next

**Owner rule (2026-11-02): strict sequential order.** Finish one task completely (implemented, tested,
verified, pushed) before starting the next; never two at once; nothing partial. Order = the order the
owner gave them: TASK-043 (done) → 044 (done) → 045 (done) → 046 (done) → 047 (Now) → 048. Older items below those come after.

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

- 2026-11-07 · TASK-046 Nardi in two variants, Long (new engine variant: heads, closed points, head rule,
  no 6-wall, mars) and Short (the old backgammon engine), with a picture picker — 61a2181, e0aaa53, c7fc6b0,
  4876787, 6ea1ba0 + this close-out. QA `docs/qa/QA_REPORT_TASK046.md` (38/40 → fixed), design
  `docs/design/REVIEW_TASK046.md` (NEEDS REVISION → all 5 fixed), PM ACCEPT WITH FOLLOW-UPS
  (`docs/product/VALIDATION_TASK046.md`; TASK-055 mars wording — done here; TASK-054 reference-board extras;
  owner Q15 doubling cube, Q16 mars payout, Q17 first-turn head exception).
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
