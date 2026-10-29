# Product backlog

The single task tracker (format and statuses: `docs/team/README.md`). Sources: `UI_UX_AUDIT.md`
(UX-NNN), `PRODUCT_RESEARCH.md` (OPP-NNN), `TECHNICAL_DEBT.md` (TD-NNN).
Last planning: 2026-10-29.

## Batches

| Batch | Theme | Tasks | Approval |
|---|---|---|---|
| B1 | Honest and clear: fix what misleads or loses games (in progress) | TASK-001…005 | none (frontend) |
| B2 | Keep playing: after-game flow, profiles, invites | TASK-006…008, TASK-024 | none (frontend) |
| B3 | Polish and consistency | TASK-009…013, TASK-025 | none (frontend) |
| B4 | New capabilities (backend / new rules) | TASK-014…023 | **owner** |

---

## B1 · Honest and clear

### TASK-001 · Game sounds that respect the setting            P0 · M · DESIGN_IN_PROGRESS · B1
Problem: Settings offers "Sound effects", but the game is silent; players miss turns.
User story: As a player, I want a sound when it's my turn and when pieces move, so I notice the game
without staring at it.
Why: removes a UI-only feature (project rule); fewer timeouts. Evidence: UX-001, UX-004, TD-003, OPP-005.
No asset licensing: sounds are synthesized in code, so the asset-source question in PRODUCT_RESEARCH §6.4 doesn't arise.
Scope: Web Audio synthesized sounds (no files, no dependency) for move, capture/take, your turn,
low time (≤ 10 s, once), win, loss; a `useGameSounds` hook fed by room state changes; respects
`preferences.sound`. Out of scope: volume slider, music.
Spec: DESIGN_SPECIFICATIONS.md#task-001
Acceptance criteria:
  1. Sound on: each event plays exactly once per new server state (not on re-render, refetch or
     reconnect with an unchanged version).
  2. Sound off: nothing plays. Changing the setting applies without reload.
  3. No errors when the browser blocks audio before the first gesture.
  4. Works in all five games.
Tests: unit tests for the state→event mapping; manual check in a bot game.
Depends on: —   Approval: none

### TASK-002 · Statistics that add up                          P1 · S · DESIGN_IN_PROGRESS · B1
Problem: Dashboard shows "Played 2 · W 0 · L 0 · D 0".
Scope: show `abandoned` (already in `GET /me/stats`) in `DashboardGames`; compact on phones; also show
`current_streak` / `best_streak` and win rate, which the API already returns (OPP-008).
Acceptance: Played = W + L + D + Abandoned on every row; no horizontal scroll at 390 px; en/ru/hy.
Tests: component test with a fixture that has abandoned games and a streak. Evidence: UX-002, OPP-008.
Depends on: —   Approval: none

### TASK-003 · History shows the outcome                       P1 · S · DESIGN_IN_PROGRESS · B1
Problem: history rows say "Timed out" but not who won.
Scope: `RoomSummary`: game icon, outcome badge (Won / Lost / Draw / Abandoned) with text, end reason
under it. Evidence: UX-003.
Acceptance: every finished row shows outcome + reason; AA contrast; colour never the only signal.
Tests: component test per outcome. Depends on: —   Approval: none

### TASK-004 · Your-turn signal in the browser tab             P1 · S · DESIGN_IN_PROGRESS · B1
Problem: moves made while you're on another tab go unnoticed; games time out.
Scope: room page title "● Your turn · {game}" while it's your turn; plays the your-turn sound
(TASK-001) when the tab is hidden. Evidence: UX-004.
Acceptance: title flips within one state update and reverts after your move; translated.
Tests: RoomPage test for title on my turn / their turn. Depends on: TASK-001 (sound part only).

### TASK-005 · Card tables without the duplicate player row    P1 · S · DESIGN_IN_PROGRESS · B1
Problem: Durak/Poker show every player twice; on phones the table starts below the fold.
Scope: RoomPage skips `PlayerList` for boards that render seats (Durak, Poker). Evidence: UX-006.
Acceptance: at 390×844 a 6-player Poker table starts on the first screen; names, bot badges, turn
and timers still visible at the seats. Tests: RoomPage test per game type. Approval: none

## B2 · Keep playing

### TASK-006 · Game-over panel with "Play again"               P1 · M · PLANNED · B2
Problem: after a game the only way on is back to the game page. Evidence: UX-007, OPP-001.
Scope: panel over the board with outcome, reason, "Play again" (same mode, table size and bot level
for bot games; new matchmaking ticket for online games) and "Back to lobby". Rematch against the same
human is TASK-016 (backend). Evidence: UX-007.
Acceptance: one tap from a finished bot game starts an equivalent new game; focus moves to the panel;
works for all five games. Tests: RoomPage test for finished state actions.

### TASK-007 · Own profile with records and recent games       P1 · M · PLANNED · B2
Scope: `ProfileView`: per-game record cards from `/me/stats` (incl. streaks, OPP-008) and the last 5 games (`RoomSummary`);
empty state for new players. Public profile stats are TASK-015 (backend). Evidence: UX-005.
Acceptance: records match the dashboard; empty state shown with a link to the games.

### TASK-008 · Clear play actions in the lobby                 P2 · S · PLANNED · B2
Scope: `ModeCard` shows a visible text action; bot level pills don't wrap at 390 px.
Evidence: UX-008, UX-014. Acceptance: action label visible without hover; pills on one line at 390 px.

### TASK-024 · Shareable invite link                         P1 · S · PLANNED · B2
Problem: a friend has to type the invite code. Evidence: OPP-007.
Scope: `/join/:code` route that joins via the existing `POST /rooms/join` (guests go through login
and come back); "Copy link" + Web Share on phones in `WaitingTable`. No API change.
Acceptance: opening the link signed in seats you at the table; signed out, after login you land at
the table; an invalid/expired code shows the existing error. Tests: route test with MSW.

## B3 · Polish and consistency

### TASK-009 · Nardi board in the club style                   P2 · M · PLANNED · B3
Scope: wood frame, matching checkers/points, "Move" heading, fit-to-screen sizing like Chess/Checkers;
no rule changes. Evidence: UX-013.

### TASK-010 · Game page hero and modes tidy-up                P2 · S · PLANNED · B3
Scope: record strip under the art (UX-010); modes box aligned to start and no duplicate player count
(UX-016).

### TASK-011 · Header and brand on phones                      P2 · S · PLANNED · B3
Scope: logo-only header below 400 px for guests (UX-011); gold default `favicon.svg` (UX-009, brand
name is an owner decision).

### TASK-012 · Chess draw controls beside the board            P2 · S · PLANNED · B3
Scope: on desktop the draw offer/accept controls live in the side panel with the move list (UX-015).

### TASK-013 · Sign-up and dashboard copy                      P3 · S · PLANNED · B3
Scope: show/hide password toggle (UX-012); dashboard banner shows "Continue" or most-played game
instead of duplicate links (UX-017); landing copy says what's true now (UX-018).

### TASK-025 · "Players online" in the lobby (hypothesis)     P2 · S · PLANNED · B3
Scope: show the count from the existing `GET /stats/online` on the landing and games pages; hide it
when the count is low (threshold in admin settings) so an empty site doesn't look empty.
Evidence: OPP-019 (hypothesis). Success metric: matchmaking starts per lobby visit.

## B4 · New capabilities (need the owner)

Each needs a product brief + spec before development and the owner's go-ahead (reason in brackets).
Ordered by the PM's recommendation.

| Task | Opportunity | Priority | Size | Why approval |
|---|---|---|---|---|
| TASK-014 · Ratings per game/mode + leaderboards | OPP-003 | P1 | L | new backend domain; rating system choice |
| TASK-016 · Rematch against the same opponent | OPP-001 | P1 | M | room flow change |
| TASK-018 · Long nardi as a second Nardi mode | OPP-002 | P1 | L | new engine + bot; rule set to confirm |
| TASK-020 · Durak perevodnoy + table options | OPP-006 | P1 | M–L | rule variants in the engine |
| TASK-021 · Move list + step-through replay (and the missing `GET /rooms/{id}/moves`) | OPP-004 | P1 | M | new public endpoint |
| TASK-015 · Public profile statistics | UX-005 | P2 | S | new public API field (privacy) |
| TASK-017 · Guest play against bots | OPP-017 | P2 | M | auth model change |
| TASK-019 · Nardi match play to N points | OPP-009 | P2 | M | rule change (`points_to_win` unused) |
| TASK-022 · Poker sit-out and fresh table after busting | OPP-016 | P2 | M | play-chip rules (recreational rule) |
| TASK-023 · Fair-play: completion rate, cooldown for serial leavers | OPP-011 | P2 | M | moderation behaviour |
| Later | friends (OPP-012), preset chat (OPP-013), spectating (OPP-014), tutorials (OPP-010), puzzles/analysis (OPP-015), achievements (OPP-020), PWA (OPP-018), more chess time controls (OPP-021), durak 2×2 (OPP-022) | P2–P3 | — | see PRODUCT_RESEARCH §4 |

## Decisions needed

1. B4 tasks: which to build and in what order (PM recommends TASK-014 ratings, TASK-016 rematch,
   TASK-018 long nardi). Details per task in PRODUCT_RESEARCH.md §6.
2. Brand: the real product name and logo (UX-009).
(Decided 2026-10-29: autonomous mode for B1–B3; TD-001 deleted. See DECISIONS.md.)
