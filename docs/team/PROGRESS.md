# Work progress — read this first in every session

The single place that says where the work stands, so a new session (after the terminal was closed) can
continue without asking. **Update it after every finished step** (move items between sections, add the
commit). Newest first inside each section. Details live in the linked files.

How to resume (any Claude account — this file and the repo hold everything): read "Now", check `git log --oneline -5` and `git status`, then continue with the first
unchecked step. Owner's rules: commit and push straight to `main`; work autonomously; run lint, types,
tests and build before pushing; keep `.claude/agents/gaming-agent.md` current.

## Now

**TASK-048 · Complete platform redesign** — started 2026-11-09 (owner said yes). Brief (verbatim): `docs/product/briefs/TASK-048-platform-redesign.md`. Owner rule applies: no paid VIP, no value prizes.

Steps:
- [x] 1. PM market research → `docs/product/research/TASK-048-market-research.md` ("The Lamplit Room"; owner decisions listed at the end of its report: backend for leaderboards/friends/missions, brand name and logo, age confirmation).
- [x] 2. UI/UX agent: visual direction and design system → `docs/design/REDESIGN_048.md`, preview `docs/design/preview-048-home.html` (build order in its §7; show the owner the relit home at stage 6).
- [ ] 3. Platform redesign, by the steps in `REDESIGN_048.md` §7: [x] tokens and surfaces; [x] fonts; [x] primitives; [x] modal, tooltip (toast waits for a real use); [x] shell (`/ranks` link and TableBar come later); [x] home; [x] lobby and game pages; [x] dashboard; [x] tables (surround only; Nardi's walnut unchanged); [x] remaining pages (wallet, profile, auth, ranks). Not done from the spec: TableBar and floating GameOverPanel, Toast (no real use yet), featured double-width poster.
- [x] 4. Game redesign: poker, nardi, shashki, durak, chess (d5d33d5, 257c92d, f3eb0c5; report in IMPLEMENTATION_REPORT). [x] seat plates facing off for two-player tables (Nardi, Checkers, Chess); [x] game-over panel floating / bottom sheet / folded strip and the table bar; [x] Poker (phone bets clear the Bot pill; tokens); [x] Durak (tokens); [x] Nardi dice strip; Checkers/Chess already match §5. Known: at 390×844 poker's action buttons are below the fold (table is width-limited), as before.
- [x] 5. Polish: spinners on every server-waiting button, hover guards, featured double-width poster, turn pill pop (3f752a7); skeletons for the level and daily-bonus cards, dashboard error state with retry (this commit). Interface transitions already on 150/200/260 ms; XP bar and poker chips keep their longer reward motion. Seen for QA: the dashboard's "with us since" date shows an English month in hy.
- [ ] 6. QA of every page, comparison with the research, fixes, final report.

## Next

**Owner rule (2026-11-02): strict sequential order.** Finish one task completely (implemented, tested,
verified, pushed) before starting the next; never two at once; nothing partial. Order = the order the
owner gave them: TASK-043 (done) → 044 (done) → 045 (done) → 046 (done) → 047 (done) → 056 (done) → 057 (done) → 058 (done) → 048 (Now) (owner set 056, 057 after 047 on 2026-11-08). Older items below those come after.

Later (older backlog):
- From TASK-047 QA (O-2, predates it): the host's waiting table doesn't update when someone joins a table with
  3+ seats (`RoomService::join` sends no event).
- TASK-039 · Fishka follow-ups from PM: QA re-run of the 5 failed checks, frozen-clock midnight test,
  live multi-seat poker leave, matchmaking bot fallback without Fishkas.
- TASK-037 · Durak bout space at 1280×720, phone role bubbles 0.65rem.
- Owner questions 6–7 in `docs/product/PRODUCT_BACKLOG.md` (chip dumping at private poker; refill tops up
  or adds).
- The rest of B3 (`docs/product/PRODUCT_BACKLOG.md`), then B4 (needs the owner's choice).

## Done (recent)

- 2026-11-08 · TASK-058 Poker uses its phone layout at 900 px and below; the table's size follows the window height — this commit. Checked live at 1440, 901, 900, 820, 640, 639, 390, 360.
- 2026-11-08 · TASK-057 New Nardi board (own carved walnut design), with TASK-054 — 181b10b + this commit. QA and design `docs/qa/QA_REPORT_TASK057.md` (1 High, 4 Medium → fixed).
- 2026-11-08 · TASK-056 Home page redesign and game categories — 3909990, bc9daf7, 82b7612, 45b0ddf + this close-out. QA and design `docs/qa/QA_REPORT_TASK056.md`
  (7 bugs → fixed), PM ACCEPT WITH FOLLOW-UPS (`docs/product/VALIDATION_TASK056.md`; TASK-063, 064 done here; TASK-065 and owner Q21–23 open).
- 2026-11-08 · TASK-047 Level & XP progression — 51acc3e, 7935e4b, d0ab709, 19dc650 + this close-out. QA `docs/qa/QA_REPORT_TASK047.md`
  (135/148, 5 bugs → fixed), design `docs/design/REVIEW_TASK047.md` (NEEDS REVISION → all fixed), PM ACCEPT WITH FOLLOW-UPS
  (`docs/product/VALIDATION_TASK047.md`; TASK-059, 060 done here; TASK-061, 062 and owner Q18–20 open). Summary in IMPLEMENTATION_REPORT.
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
