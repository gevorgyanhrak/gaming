# Product backlog

The single task tracker (format and statuses: `docs/team/README.md`). Sources: `UI_UX_AUDIT.md`
(UX-NNN), `PRODUCT_RESEARCH.md` (OPP-NNN), `TECHNICAL_DEBT.md` (TD-NNN).
Last planning: 2026-10-29 (B1 validated; B1 follow-ups added as TASK-026…032).

## Batches

| Batch | Theme | Tasks | Approval |
|---|---|---|---|
| B1 | Honest and clear: fix what misleads or loses games — **done 2026-10-29** (all APPROVED) | TASK-001…005 | none (frontend) |
| B2 | Keep playing: after-game flow, profiles, invites (**next**) | TASK-026, TASK-006, TASK-028, TASK-007, TASK-008, TASK-024 | none (frontend) |
| B3 | Polish and consistency | TASK-009…013, TASK-025, TASK-027, TASK-029, TASK-032 | none (frontend) |
| B4 | New capabilities and backend fixes | TASK-014…023, TASK-030, TASK-031 | **owner** (go-ahead for backend work) |

---

## B1 · Honest and clear

### TASK-001 · Game sounds that respect the setting            P0 · M · APPROVED · B1
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
Validation 2026-10-29: **APPROVED** — AC 1–3 seen live (one sound per new version, none on refetch/reload, off = no AudioContext, no console errors) and AC 4 live in Chess/Checkers/Durak/Poker with Nardi covered by unit tests (`useGameSounds.test.ts`); open: human listen (owner), left-player end sound → TASK-032.

### TASK-002 · Statistics that add up                          P1 · S · APPROVED · B1
Problem: Dashboard shows "Played 2 · W 0 · L 0 · D 0".
Scope: show `abandoned` (already in `GET /me/stats`) in `DashboardGames`; compact on phones; also show
`current_streak` / `best_streak` and win rate, which the API already returns (OPP-008).
Acceptance: Played = W + L + D + Abandoned on every row; no horizontal scroll at 390 px; en/ru/hy.
Tests: component test with a fixture that has abandoned games and a streak. Evidence: UX-002, OPP-008.
Depends on: —   Approval: none
Validation 2026-10-29: **APPROVED** — sums add up on every live row, 390/820 no scroll in en/ru/hy, streaks + win rate asserted in `Records.test.tsx`; minor review items fixed in 2af549b.

### TASK-003 · History shows the outcome                       P1 · S · APPROVED · B1
Problem: history rows say "Timed out" but not who won.
Scope: `RoomSummary`: game icon, outcome badge (Won / Lost / Draw / Abandoned) with text, end reason
under it. Evidence: UX-003.
Acceptance: every finished row shows outcome + reason; AA contrast; colour never the only signal.
Tests: component test per outcome. Depends on: —   Approval: none
Validation 2026-10-29: **APPROVED** — after revision 2af549b every row shows badge + reason with the opponent visible at 390 in en/ru/hy; contrast ≥ 6.5:1 computed; text always present.

### TASK-004 · Your-turn signal in the browser tab             P1 · S · APPROVED · B1
Problem: moves made while you're on another tab go unnoticed; games time out.
Scope: room page title "● Your turn · {game}" while it's your turn; plays the your-turn sound
(TASK-001) when the tab is hidden. Evidence: UX-004.
Acceptance: title flips within one state update and reverts after your move; translated.
Tests: RoomPage test for title on my turn / their turn. Depends on: TASK-001 (sound part only).
Validation 2026-10-29: **APPROVED** — title flips and reverts without refetch (live + test), en/ru/hy strings present (`room.tab.*`), hidden-tab chime seen; stale game name after a language switch is pre-existing → TASK-026.

### TASK-005 · Card tables without the duplicate player row    P1 · S · APPROVED · B1
Problem: Durak/Poker show every player twice; on phones the table starts below the fold.
Scope: RoomPage skips `PlayerList` for boards that render seats (Durak, Poker). Evidence: UX-006.
Acceptance: at 390×844 a 6-player Poker table starts on the first screen; names, bot badges, turn
and timers still visible at the seats. Tests: RoomPage test per game type. Approval: none
Validation 2026-10-29: **APPROVED** — 6-player Poker oval fully on the first screen at 390×844 with names, Bot pills, turn ring and timer; no game-slug checks in `RoomPage`; finished tables keep the list.

## B2 · Keep playing

Order: TASK-026 first (P1, wrong language in the main locales), then TASK-006 + TASK-028 together
(both change the finished-room view), then TASK-007, TASK-024, TASK-008.

### TASK-026 · Names follow the language switch                 P1 · S · READY_FOR_REVIEW · B2
Problem: after switching language, game and mode names on History and in the room stay in the old
language until a reload (hy UI showing "Шашки · Русские шашки", title "● Ваш ход · Շախմատ").
User story: As a ru/hy player, I want everything on screen in the language I just picked.
Why: ru/hy are the main audience; mixed languages look broken. Evidence: DESIGN_REVIEW.md B1
"Pre-existing issues" P1 and TASK-004 note. Source: query keys don't include the locale.
Scope: API-backed queries whose payload is translated (games, game, rooms, room, me/games, me/stats)
refetch on language change (locale in the query key or invalidate on `languageChanged`). No API change.
Out of scope: translating user-entered text.
Acceptance criteria:
  1. On `/history`, a room page and the dashboard, switching en → ru → hy updates game and mode names
     and the tab title without a reload.
  2. No duplicate requests on normal navigation without a language change.
  3. Room state (board, version) is not reset by the switch; no sound plays because of the refetch.
Tests: component test: change `i18n.language`, assert the refetched name (MSW returns per-locale names).
Depends on: —   Approval: none

### TASK-028 · Turn caption that tells the truth               P2 · S · READY_FOR_REVIEW · B2
Problem: the board caption says "Waiting for the other player" on finished games (chess on time) and at
3–6 player tables, where it isn't one "other" player. Replaces TD-004 (P3, raised: it misleads on
finished games). Evidence: DESIGN_REVIEW.md B1 P3, TD-004, IMPLEMENTATION_REPORT B1 known issues.
Scope: finished room → no waiting caption (the result/game-over panel from TASK-006 takes the place);
active, not my turn → "Waiting for {name}" when exactly one seat acts, else "Waiting for other players";
en/ru/hy (plural forms in ru).
Acceptance criteria:
  1. Finished games in all five games show no "Waiting…" text.
  2. 2-player games: "Waiting for Alex"; Durak/Poker with several opponents: the acting player's name,
     or "Waiting for other players" when more than one seat acts.
  3. Translated in en/ru/hy; `RoomPage` still has no game-slug checks.
Tests: RoomPage tests: finished, 2-player their turn, 4-player their turn.
Depends on: build with TASK-006 (same view)   Approval: none

### TASK-006 · Game-over panel with "Play again"               P1 · M · READY_FOR_REVIEW · B2
Problem: after a game the only way on is back to the game page. Evidence: UX-007, OPP-001.
Scope: panel over the board with outcome, reason, "Play again" (same mode, table size and bot level
for bot games; new matchmaking ticket for online games) and "Back to lobby". Rematch against the same
human is TASK-016 (backend). Evidence: UX-007.
Acceptance: one tap from a finished bot game starts an equivalent new game; focus moves to the panel;
works for all five games. Tests: RoomPage test for finished state actions.

### TASK-007 · Own profile with records and recent games       P1 · M · READY_FOR_REVIEW · B2
Scope: `ProfileView`: per-game record cards from `/me/stats` (incl. streaks, OPP-008) and the last 5 games (`RoomSummary`);
empty state for new players. Public profile stats are TASK-015 (backend). Evidence: UX-005.
Acceptance: records match the dashboard; empty state shown with a link to the games.

### TASK-008 · Clear play actions in the lobby                 P2 · S · READY_FOR_REVIEW · B2
Scope: `ModeCard` shows a visible text action; bot level pills don't wrap at 390 px.
Evidence: UX-008, UX-014. Acceptance: action label visible without hover; pills on one line at 390 px.

### TASK-024 · Shareable invite link                         P1 · S · READY_FOR_REVIEW · B2
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

### TASK-027 · Armenian dates in Armenian                       P2 · S · PLANNED · B3
Problem: with the hy UI, dates read "Oct 29, 2026" and "October 28, 2026-ից" (English month in an
Armenian sentence). Evidence: DESIGN_REVIEW.md B1 P2. Code: `Intl.DateTimeFormat(i18n.language, …)` in
`RoomSummary.tsx`, `ProfileView.tsx`, `DashboardPage.tsx`, `ContentPage.tsx`; the browser falls back to
English when its ICU data has no `hy` dates.
Scope: one shared date helper; if `Intl` resolves `hy` to another locale, format with translated month
names from the hy locale file. Optional: drop the year for the current year (review suggestion).
Acceptance criteria:
  1. hy UI shows Armenian month names on History, dashboard ("member since"), profile and content pages.
  2. en and ru output unchanged (or only the agreed short form).
  3. The "since" sentence reads grammatically in hy (native check, see Decisions needed).
Tests: unit test of the helper for en/ru/hy, including the fallback path.   Approval: none

### TASK-029 · Poker bet chips clear the nameplates on phones   P3 · S · PLANNED · B3
Problem: at 390 px the "Bet 5" chip overlaps the right-hand nameplate ("David 995").
Evidence: DESIGN_REVIEW.md B1 P4.
Acceptance criteria: at 390×844 and 1440×900 with 6 players, no bet chip overlaps a nameplate, stack
or Bot pill (checked by bounding boxes) on any seat; 2- and 4-player tables unchanged.
Tests: browser check at both sizes (screenshots in the implementation report).   Approval: none

### TASK-032 · No end sound after you left a table              P3 · S · PLANNED · B3
Problem: a player who left a Durak/Poker table that plays on hears the final "loss" much later. Spec
rule 3 says "left the table → nothing". Matchmaking already seats humans at 3–6 player tables, so this
is not only a bot-table case (decided now, not deferred). Evidence: DESIGN_REVIEW.md TASK-001 minor;
IMPLEMENTATION_REPORT B1 revision ("left as is").
Acceptance criteria: after leaving a table that plays on, no sound plays for that room, including its
end; players who did not leave still hear win/loss.
Tests: `useGameSounds`/RoomPage test for the left-table case.   Approval: none

## B4 · New capabilities (need the owner)

Each needs a product brief + spec before development and the owner's go-ahead (reason in brackets).
Ordered by the PM's recommendation.

| Task | Opportunity | Priority | Size | Why approval |
|---|---|---|---|---|
| TASK-030 · Unique bot names at a table (was TD-002) | B1 review | P1 | S | backend work (seeders / `BotSelector`); not an approval item by README, but outside autonomous frontend scope |
| TASK-031 · Chess loss on time counts as a loss | spec B1 open question 1 | P1 | S | backend result rule; same reason as TASK-030 |
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

### TASK-030 · Unique bot names at a table                     P1 · S · READY_FOR_REVIEW · B4
Problem: a 6-player Poker bot table showed "Alex" four times; Durak repeats "David". Players can't tell
seats apart, and it looks fake. Replaces TD-002. Evidence: IMPLEMENTATION_REPORT B1, DESIGN_REVIEW B1 P5.
Scope: `BotSelector::pick` excludes bots already seated in the room; seed enough bot profiles per game
and difficulty for a full 6-seat table (5 bots). No schema change.
Acceptance criteria:
  1. No two bots in one room share a name (6-player Poker and Durak, every difficulty).
  2. If too few profiles exist, the room still starts (fallback documented) and the admin panel shows
     the shortage; feature test covers it.
  3. Existing rooms unaffected.
Tests: feature test filling a 6-seat table with bots.   Depends on: —
Approval: none by the README list (no new domain/table, not breaking); backend → owner go-ahead to run
outside the frontend autonomous mode.

### TASK-031 · Chess loss on time counts as a loss              P1 · S · READY_FOR_REVIEW · B4
Problem: when a chess clock runs out, the player's result is "Timed out" (abandoned) instead of "Lost";
chess players expect a loss on time. `GameActionService::end` passes every `TIMEOUT` seat to
`GameFinisher` as abandoned. Evidence: DESIGN_SPECIFICATIONS.md open question 1; chess convention
(Lichess/Chess.com count flag-fall as a loss — PRODUCT_RESEARCH §3.1, Inf).
Scope: a clock flag-fall in an engine with a game clock (Chess) records `loss` for the flagged seat (or
the engine's `timeoutOutcome`, e.g. draw); turn-timer timeouts in Nardi, Checkers, Durak, Poker and
leaving stay "abandoned". End reason stays "on time". Past games are **not** rewritten.
Acceptance criteria:
  1. Chess flag-fall: flagged player `loss`, opponent `win`; `user_game_stats.losses` +1, `abandoned`
     unchanged; History shows "Lost · On time".
  2. Other games and leaving: unchanged (feature tests per game).
  3. No API shape change (`make types` produces no diff).
Tests: feature tests for chess flag-fall vs turn timeout vs leave.   Depends on: —
Approval: decided by README rules: not an owner-approval item (no new domain/table, no breaking API,
no destructive data change); it is backend work, so it waits for the owner's B4 go-ahead. Rewriting old
results would be destructive → out of scope.

## Decisions needed

1. B4 tasks: which to build and in what order (PM recommends TASK-014 ratings, TASK-016 rematch,
   TASK-018 long nardi). Details per task in PRODUCT_RESEARCH.md §6.
2. Brand: the real product name and logo (UX-009).
3. Go-ahead for the two small backend fixes TASK-030 (bot names) and TASK-031 (chess loss on time) —
   PM recommends running them now, before the larger B4 items.
4. Owner checks for B1 (not blocking the approval): listen to the sounds (Settings → Sound effects
   plays a preview; then a bot game) and say if volume/character should change; have a native speaker
   check the new hy strings (`room.tab.*`, `room.endReasonShort.*`, `dashboard.*`, `settings.soundHint`).
(Decided 2026-10-29: autonomous mode for B1–B3; TD-001 deleted; sound on by default. See DECISIONS.md.)
