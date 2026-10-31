# Work progress — read this first in every session

The single place that says where the work stands, so a new session (after the terminal was closed) can
continue without asking. **Update it after every finished step** (move items between sections, add the
commit). Newest first inside each section. Details live in the linked files.

How to resume: read "Now", check `git log --oneline -5` and `git status`, then continue with the first
unchecked step. Owner's rules: commit and push straight to `main`; work autonomously; run lint, types,
tests and build before pushing; keep `.claude/agents/gaming-agent.md` current.

## Now

**TASK-040 · Poker bots, real balance in Poker, game groups** — brief:
`docs/product/briefs/TASK-040-groups-poker-bots.md` (owner, 2026-10-31). Status: not started.

Steps (tick when done, with the commit):
- [ ] 1. Investigate "bots don't act at Poker" live (bot jobs, pause between hands, queue worker) and fix.
- [ ] 2. Poker uses the real balance: buy-in = whole balance (no 1,000 cap); table shows it; wallet synced.
- [ ] 3. Central group configuration (5 Poker groups, 5 groups for other games; admin-editable).
- [ ] 4. Poker groups: entry by minimum balance (not deducted), blinds/minimum bet per group, separate
      tables and matchmaking per group, locked groups shown, backend validation.
- [ ] 5. Win/lose animation: chips fly to the header balance on cash-out; exactly once per transaction.
- [ ] 6. Full Poker test pass (brief §5): balances 100 / 1,000 / 5,000 / 50,000 / 500,000; all actions;
      folds; showdowns; reconnects; double-payout checks.
- [ ] 7. Groups for Nardi, Checkers, Chess, Durak: stakes within the group range (decide pot rules with the
      owner), rooms and matchmaking per group, validation.
- [ ] 8. QA agent end to end, UI/UX review, PM validation; fix; final report to the owner.

Open questions for the owner (ask before step 7): what a non-poker game's bet does (winner takes the
pot? vs bots the house matches?), whether the 500 entry fee stays alongside bets, how a table's bet is
chosen within the group range.

## Next (after TASK-040)

- TASK-039 · Fishka follow-ups from PM: QA re-run of the 5 failed checks, frozen-clock midnight test,
  live multi-seat poker leave, matchmaking bot fallback without Fishkas.
- TASK-037 · Durak bout space at 1280×720, phone role bubbles 0.65rem.
- Owner questions 6–7 in `docs/product/PRODUCT_BACKLOG.md` (chip dumping at private poker; refill tops up
  or adds).
- The rest of B3 (`docs/product/PRODUCT_BACKLOG.md`), then B4 (needs the owner's choice).

## Done (recent)

- 2026-10-31 · Admin: edit a player's details (username, email, phone, language, display name, country,
  bio; re-verification on email/phone change; audited). Fishka design-review fixes (popup focus/inert,
  cooldown state, contrast, 44 px pill). Lock timeout → 422.
- 2026-10-31 · TASK-038 Fishka currency + QA fixes — commit 53018b3 (QA report `docs/qa/QA_REPORT_FISHKA.md`,
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
