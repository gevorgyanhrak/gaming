# Work progress — read this first in every session

The single place that says where the work stands, so a new session (after the terminal was closed) can
continue without asking. **Update it after every finished step** (move items between sections, add the
commit). Newest first inside each section. Details live in the linked files.

How to resume: read "Now", check `git log --oneline -5` and `git status`, then continue with the first
unchecked step. Owner's rules: commit and push straight to `main`; work autonomously; run lint, types,
tests and build before pushing; keep `.claude/agents/gaming-agent.md` current.

## Now

**TASK-043 · Emoji & Quick Messages** — brief: `docs/product/briefs/TASK-043-emoji-quick-messages.md`
(owner, 2026-11-02, verbatim). Status: in progress (started 2026-11-02). Design: ADR 0017.

Decisions taken autonomously (brief allows; no free text = no moderation needed):
- Only catalog emojis and predefined messages (keys; each viewer reads them in their own language).
- Ephemeral: not stored in the DB; a 2-minute buffer per room in the state store lets pages without a
  socket (polling) and reconnecting pages catch up; ids are ULIDs, the client dedupes by id.
- Delivered like room updates: `room.reaction` on each person's private channel `user.{id}`.
- Allowed for the room's people while it waits, plays, and up to 10 minutes after it ends; a reaction is
  to one seat or to everyone; bots never send. Rate limit `reactions` (3/s, 40/min). Admin switch
  `reactions.enabled`. Viewers can mute reactions (local).

Steps (tick when done, with the commit):
- [x] 1. Backend: `Gameplay/Reactions` (ReactionCatalog, RoomReactions), event `RoomReactionSent`,
      `GET /reactions`, `POST/GET /rooms/{room}/reactions`, limiter, setting + admin toggle, tests, types.
- Owner, 2026-11-02 (screenshot of a poker table with a chat box): a **table chat panel** under the board
  (log: time · name · message/emoji; the emoji button and quick messages live in it). Still no free typing
  (would need moderation: filter, reports, mute → owner question). So the room keeps its last 50 reactions
  for 3 hours (chat history after a reload); only fresh ones (≤ 8 s) animate.
- [x] 2. Frontend: table chat panel (log + emoji/quick-message buttons); `features/reactions` (catalog query, useRoomReactions: socket + poll + dedupe),
      ReactionPicker (emoji tabs, search, recents, messages, recipient), ReactionLayer (flight
      animations), seat anchors (`data-seat-anchor`) in PlayerList, WaitingTable, Poker, Durak; RoomPage
      button; en/ru/hy; tests.
- [x] 3. Live check with two real browsers (isolated contexts, real sessions): A→B and B→A in Chess, 3-seat
      Poker and Durak, flights in the right direction, chat on both sides; 6 simultaneous sends from two
      players all delivered once; leaving and coming back shows missed ones without replay; Reverb stopped →
      polling delivered in < 1 s; 1440 and 390 without horizontal scroll. Fixes: picker category row
      collapsed (shrink-0); a send is retried once with the same client id (a 502 under local php-fpm
      overload, 5 workers) — 1d9dcef.
- [ ] 4. QA agent, UI/UX review, PM validation; fix; push; report to the owner.

## Next

**Owner rule (2026-11-02): strict sequential order.** Finish one task completely (implemented, tested,
verified, pushed) before starting the next; never two at once; nothing partial. Order = the order the
owner gave them: TASK-043 (Now) → 044 → 045 → 046 → 047 → 048. Older items below those come after.

- **TASK-044 · Daily bonus popup with a login streak** (owner, 2026-11-02, after TASK-043; reference
  screenshot: a "Daily bonus" dialog with a row of days 1…5, the current day highlighted, growing rewards
  for consecutive days, "one day to go until your double bonus", an open chest with the amount). Build our
  own art in the card-club look (no copied assets); backend owns the streak and amounts (settings), today's
  daily +1,000 becomes day-based; popup once a day after login; en/ru/hy. Plan it like TASK-043.
- **TASK-045 · Poker table redesign like the owner's screenshot** (2026-11-02): wide oval felt with a
  thick rail on a dark patterned background, seats with avatar frames around the rail (empty seats as
  "+"), dealer at the top, table info (name, blinds) at the bottom left, chat panel below (done in
  TASK-043). Our own art; keep the phone layout. After TASK-044.
- **TASK-046 · Nardi in two variants: Long and Short** (owner, 2026-11-02, screenshot of a "what shall we
  play?" picker with two board thumbnails "Длинные" / "Короткие"). Long nardi = today's engine. Short
  nardi (backgammon rules: opposite directions, hitting blots to the bar, entering from the bar, bearing
  off; standard 2-5-3-5 setup) = a new engine variant + a second Nardi game mode; a picker with two board
  previews on the Nardi page; bots, groups, matchmaking per variant; en/ru/hy. After TASK-045 unless the
  owner reorders.
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
