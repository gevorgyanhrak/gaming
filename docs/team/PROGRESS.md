# Work progress — read this first in every session

The single place that says where the work stands, so a new session (after the terminal was closed) can
continue without asking. **Update it after every finished step** (move items between sections, add the
commit). Newest first inside each section. Details live in the linked files.

How to resume: read "Now", check `git log --oneline -5` and `git status`, then continue with the first
unchecked step. Owner's rules: commit and push straight to `main`; work autonomously; run lint, types,
tests and build before pushing; keep `.claude/agents/gaming-agent.md` current.

## Now

**TASK-040 · Poker bots, real balance in Poker, game groups** — brief:
`docs/product/briefs/TASK-040-groups-poker-bots.md` (owner, 2026-10-31). Status: in progress (started 2026-11-01).

Steps (tick when done, with the commit):
- [x] 1. "Bots don't act at Poker": bots did act; the page never heard about it. The WebSocket host was fixed
      to the computer's old LAN address (192.168.11.50 → .56 overnight), and polling only kicked in when
      realtime wasn't configured at all. Fixed: socket host defaults to the page's host; polling whenever the
      socket isn't connected (room state 3 s, wallet 5 s); local Sanctum accepts any 192.168.* origin.
      Verified live: a full hand with 3 bots, UI caption in sync at every version — edd04cf.
- [x] 2. Poker uses the real balance: buy-in = whole balance (no 1,000 cap); bots get the people's average
      stack — backend in COMMIT_GROUPS. (Table/header display: step 4b.)
- [x] 3. Central group configuration: `play_groups` table (migration seeds 10 groups), `PlayGroups` service,
      `TableTerms`, admin "Play groups" (edit limits/open, audited) — COMMIT_GROUPS.
- [x] 4. Backend for groups (poker AND other games): entry by minimum balance (not charged), bet range
      validation, poker blinds = group min bet, rooms carry group + stake, matchmaking queues per mode+group,
      stake games: winners share the pot / draw returns bets (ledger `game_stake`, `game_payout`) —
      COMMIT_GROUPS. Tests: tests/Feature/Fishka/GroupsTest.php, PlayGroupAdminTest.
- [ ] 4b. Frontend groups: group picker on every game page (all 5 with min balance / bets, locked ones
      greyed with "need N"), bet slider for bot/private tables, matchmaking per group, group badge + stake on
      the room page and waiting table, Play again keeps group + bet, poker shows whole-balance buy-in.
- [ ] 5. Win/lose animation: chips fly to the header balance on cash-out; exactly once per transaction.
- [ ] 6. Full Poker test pass (brief §5): balances 100 / 1,000 / 5,000 / 50,000 / 500,000; all actions;
      folds; showdowns; reconnects; double-payout checks.
- [ ] 7. (Backend done in step 4 for every game.) Verify Nardi, Checkers, Chess, Durak live per group; pot payouts.
- [ ] 8. QA agent end to end, UI/UX review, PM validation; fix; final report to the owner.

Owner answers (2026-11-01) for step 7: the winner takes the pot (Durak: all but the loser split it; vs
bots the house matches the bet; a draw returns stakes); the bet replaces the 500 entry fee; the table's
creator picks the bet within the group range, matchmaking plays at the group minimum.

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
