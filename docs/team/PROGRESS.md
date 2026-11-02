# Work progress — read this first in every session

The single place that says where the work stands, so a new session (after the terminal was closed) can
continue without asking. **Update it after every finished step** (move items between sections, add the
commit). Newest first inside each section. Details live in the linked files.

How to resume: read "Now", check `git log --oneline -5` and `git status`, then continue with the first
unchecked step. Owner's rules: commit and push straight to `main`; work autonomously; run lint, types,
tests and build before pushing; keep `.claude/agents/gaming-agent.md` current.

## Now

Nothing in progress. TASK-040 is done and reported to the owner (2026-11-01). Waiting for the owner's
answers to questions 6–8 in `docs/product/PRODUCT_BACKLOG.md` (Fishka transfers through pots / poker chip
dumping, refill top-up, beginner blinds 1/1) and approval of TASK-041 (staging host for bot timing).
Next without the owner: the first item of "Next".

## Next

- TASK-039 · Fishka follow-ups from PM: QA re-run of the 5 failed checks, frozen-clock midnight test,
  live multi-seat poker leave, matchmaking bot fallback without Fishkas.
- TASK-037 · Durak bout space at 1280×720, phone role bubbles 0.65rem.
- Owner questions 6–7 in `docs/product/PRODUCT_BACKLOG.md` (chip dumping at private poker; refill tops up
  or adds).
- The rest of B3 (`docs/product/PRODUCT_BACKLOG.md`), then B4 (needs the owner's choice).

## Done (recent)

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
