# Product backlog

The single task tracker (format and statuses: `docs/team/README.md`). Sources: `UI_UX_AUDIT.md`
(UX-NNN), `PRODUCT_RESEARCH.md` (OPP-NNN), `TECHNICAL_DEBT.md` (TD-NNN).
Last planning: 2026-10-30 (TASK-035, TASK-036, TASK-029 validated APPROVED (79a053d); TASK-037 added; owner
feedback earlier the same day: BUG-001 fixed, TASK-035/036 added at the top of B3). Before: 2026-10-29 B1/B2 validated, TASK-030/031 approved, TASK-033…034 added.

## Batches

| Batch | Theme | Tasks | Approval |
|---|---|---|---|
| B1 | Honest and clear: fix what misleads or loses games — **done 2026-10-29** (all APPROVED) | TASK-001…005 | none (frontend) |
| B2 | Keep playing: after-game flow, profiles, invites — **done 2026-10-29** (all APPROVED) | TASK-026, TASK-006, TASK-028, TASK-007, TASK-008, TASK-024 | none (frontend) |
| B3 | Polish and consistency (in progress; TASK-035, TASK-036, TASK-029 **done 2026-10-30**; owner go-ahead so far only for TASK-035/036) | order: TASK-033, ~~TASK-035~~, ~~TASK-036~~, TASK-037, TASK-032, TASK-015, TASK-027, ~~TASK-029~~, TASK-034, TASK-012, TASK-009, TASK-010, TASK-011, TASK-013, TASK-025 | none (frontend) |
| B4 | New capabilities and backend fixes | TASK-014, TASK-016…023 (TASK-030, TASK-031 **done 2026-10-29**) | **owner** (go-ahead for backend work) |

## Bugs

### BUG-001 · Moves fail over plain HTTP ("crypto.randomUUID is not a function")   P0 · S · APPROVED
Reported by the owner 2026-10-30: playing Durak from another device on the LAN (app opened by its
network address over HTTP), every move failed. Cause: `crypto.randomUUID` exists only on secure origins
(HTTPS/localhost). Fix f40b59d (pushed): `randomId()` in `apps/web/src/shared/lib/uuid.ts` falls back to a
v4 UUID from `crypto.getRandomValues`; `features/play/api.ts` uses it; `uuid.test.ts` covers both paths.
Validation 2026-10-30: **APPROVED** — fix and test read; no other `randomUUID` call left in `apps/web/src`
(grep). Production is documented as HTTPS (`docs/DEPLOYMENT.md`), so this hit LAN/dev use, but it
blocked the owner's testing on phones.

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

### TASK-026 · Names follow the language switch                 P1 · S · APPROVED · B2
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
Validation 2026-10-29: **APPROVED** — names and tab title switch live on history, room and dashboard without reload; no `/state` refetch and no sound on switch; `B2.test.tsx` covers the refetch.

### TASK-028 · Turn caption that tells the truth               P2 · S · APPROVED · B2
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
Validation 2026-10-29: **APPROVED** — no caption on finished games, "Waiting for {name}" live in 2-player and Durak; the several-seats case and Nardi/Checkers are unit-tested only (`turnCaption.test.ts`); TD-004 closed by this task.

### TASK-006 · Game-over panel with "Play again"               P1 · M · APPROVED · B2
Problem: after a game the only way on is back to the game page. Evidence: UX-007, OPP-001.
Scope: panel over the board with outcome, reason, "Play again" (same mode, table size and bot level
for bot games; new matchmaking ticket for online games) and "Back to lobby". Rematch against the same
human is TASK-016 (backend). Evidence: UX-007.
Acceptance: one tap from a finished bot game starts an equivalent new game; focus moves to the panel;
works for all five games. Tests: RoomPage test for finished state actions.
Validation 2026-10-29: **APPROVED** — one tap repeats mode, seats and bot level (seen live in Durak, Chess, Poker, Checkers; Nardi shares the code path), focus on the heading, private/closed rooms lobby-only; matchmaking Play again has code but no test or live check → TASK-033. Spec deviation accepted: level from the new `participants[].bot_difficulty` + `mode.id` instead of device storage (DECISIONS.md).

### TASK-007 · Own profile with records and recent games       P1 · M · APPROVED · B2
Scope: `ProfileView`: per-game record cards from `/me/stats` (incl. streaks, OPP-008) and the last 5 games (`RoomSummary`);
empty state for new players. Public profile stats are TASK-015 (backend). Evidence: UX-005.
Acceptance: records match the dashboard; empty state shown with a link to the games.
Validation 2026-10-29: **APPROVED** — shared `GameRecord` keeps profile and dashboard numbers identical, 5 recent games + "See all games", empty state tested in `B2.test.tsx` (not seen live → TASK-033).

### TASK-008 · Clear play actions in the lobby                 P2 · S · APPROVED · B2
Scope: `ModeCard` shows a visible text action; bot level pills don't wrap at 390 px.
Evidence: UX-008, UX-014. Acceptance: action label visible without hover; pills on one line at 390 px.
Validation 2026-10-29: **APPROVED** — after 3306db4 "Start game"/"Create table" readable at rest and hover (7.0:1 / 9.8:1), pills one line and 44 px at 390 in en/ru/hy; width nit → TASK-034.

### TASK-024 · Shareable invite link                         P1 · S · APPROVED · B2
Problem: a friend has to type the invite code. Evidence: OPP-007.
Scope: `/join/:code` route that joins via the existing `POST /rooms/join` (guests go through login
and come back); "Copy link" + Web Share on phones in `WaitingTable`. No API change.
Acceptance: opening the link signed in seats you at the table; signed out, after login you land at
the table; an invalid/expired code shows the existing error. Tests: route test with MSW.
Validation 2026-10-29: **APPROVED** — signed-in join, guest → login with `next`, invalid code and copy link covered in `B2.test.tsx`; waiting table fits at 390 in en/ru/hy with 44 px buttons; sign-up → verify → table and the real Share sheet not seen live → TASK-033; fallback-field nit → TASK-034.

## B3 · Polish and consistency

Order (PM, updated 2026-10-30): TASK-033 first (verify what B1/B2 shipped before polishing), then the
owner-requested TASK-035 (Durak table) and TASK-036 (Poker hand result), then the honesty/locale fixes TASK-032, TASK-015, TASK-027, then layout TASK-029, TASK-034, TASK-012, then the
larger visual TASK-009, then TASK-010, TASK-011, TASK-013, and TASK-025 last (hypothesis; only once
real traffic exists).

### TASK-035 · Durak table redesign                            P1 · M · APPROVED · B3
Problem: on desktop the Durak table is taller than the screen, so the player's own hand is cut off,
while the felt is mostly empty; it doesn't look or feel like the Durak apps players know.
User story: As a Durak player, I want the whole table, my hand and the one action I need on one screen,
so I can play without scrolling or hunting for buttons.
Why: owner request 2026-10-30 ("i think we need change durak ui") with two reference screenshots of
popular Durak apps; Durak is a core game for the ru/hy audience (PRODUCT_RESEARCH §3.4).
Reference pattern (owner screenshots): full-bleed felt, opponents around the top, a large fanned hand at
the bottom, one big primary action button, a status bubble per player (attacking / defending / took /
passed).
Scope: Durak board layout and its seat/hand/action components; frontend only. Absorbs TASK-034 (d)
(no role pills on a finished table). No coins, shop or VIP elements even if the references show them
(DECISIONS.md standing rule). Out of scope: rule changes, perevodnoy (TASK-020).
Spec: DESIGN_SPECIFICATIONS.md#task-035 (UI/UX agent writing it)
Acceptance criteria (PM draft; the spec may refine):
  1. At 1440×900, 1280×720, 820×1180 and 390×844 the whole table, all opponents, my full hand and the
     action button are visible without page scroll, for 2–6 players.
  2. Each player shows name, card count, Bot pill and a status bubble; whose turn it is and the turn
     timer are visible at the seat.
  3. One primary action button shows the action available now (e.g. Take / Done / Pass); it is ≥ 44 px
     and keyboard reachable.
  4. Hands up to 12+ cards stay selectable on a phone (fan overlaps, tap targets still usable).
  5. Existing behaviour kept: sounds, tab title, turn caption, Game-over panel, en/ru/hy, no
     `'durak'` checks in `RoomPage`.
Tests: DurakBoard component tests (seats, statuses, action button per phase); browser check at the four
sizes with 2, 4 and 6 players.   Depends on: —   Approval: owner-requested (go-ahead 2026-10-30)
Validation 2026-10-30: **APPROVED** (79a053d, design re-review APPROVED) — AC 1 seen with 3 players at all four sizes and 6 players at 390 (hy, 70 samples, stage ≤ 774 < tab bar 786) and 1280×720; AC 2–4 seen live (compact/panel seats with bubble, timer ring, Bot pill, count; one 48/56 px action button; 11- and 14-card hands, every strip hits its card); AC 5: tab titles, caption, Game-over panel, Bot pills kept, no slug checks in `RoomPage` (grep); owner answers met (rectangular felt in a wood rail, one tap plays). Not seen live: 2 and 4 players, ru strings, keyboard focus → TASK-033; nits → TASK-037; hy role words → owner native check.

### TASK-036 · Poker hand result you can feel                   P1 · M · APPROVED · B3
Problem: after winning a hand the player didn't notice: the result is only in a box below the table.
User story: As a poker player, I want to see clearly that I won a hand and how many chips I got, so a
win feels like a win.
Why: owner request 2026-10-30 ("after won i did not know, i want user feeling win, see winners chips
count"). The data already exists in the view (`last_hand`: pots, winners, hand name, shown cards).
Scope: an on-table result moment after each hand: winner seat highlighted, chips move from the pot to the
winner, "+N" at the winner's stack, hand name (e.g. "Two pair"), shown cards at showdown; split pots
name every winner. Tone: warm and clear, not casino-flashy (no confetti/coin showers; recreational rule).
Plays the win sound for my own win only. Check TASK-029 (bet chip overlap) in the same spec.
Backend: if the next hand starts too quickly to see the result, the spec flags a pause between hands;
that is poker engine/timing work (not an approval-list item) and ships with this owner-requested task.
Spec: DESIGN_SPECIFICATIONS.md#task-036 (UI/UX agent writing it)
Acceptance criteria (PM draft; the spec may refine):
  1. After every hand the winning seat(s) are highlighted on the table with "+N" and the hand name
     (or "Others folded"); visible for at least the spec's minimum time before the next deal.
  2. Stacks update to the new amounts; split pots show each winner's share.
  3. Readable at 390×844 and 1440×900 with 6 players; respects `prefers-reduced-motion` (no movement,
     result still shown).
  4. Screen readers get one polite announcement per hand result.
  5. en/ru/hy strings; no chip value language (chips are per-table play chips).
Tests: PokerBoard tests for single winner, split pot and fold-win; feature test for the pause if added.
Depends on: —   Approval: owner-requested (go-ahead 2026-10-30)
Validation 2026-10-30: **APPROVED** (79a053d, design re-review APPROVED) — AC 1: winner glow, "+N" and hand name on the table, held for the server pause (3 s fold / 5 s showdown, owner decision; feature test incl. stale resume job, API 295 passed per developer); AC 2: stacks update, split plate uses exact `payouts` (component tests only); AC 3: 6 seats at 390 (0 overlaps in 200 samples + result) and 1440, reduced motion shows plate and "+N" without animation; AC 4: one announcement per hand seen; AC 5: en/ru/hy keys, "chips" wording only. Web vitest 117 passed (re-run by PM). Not seen live: split pot, ru, Poker at 820 → TASK-033; `handWin` by ear → owner.

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

### TASK-027 · Armenian dates and labels                        P2 · S · PLANNED · B3
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
Added 2026-10-29 (B2 review, pre-existing): in hy the lobby hero stat labels run together
("ՀԱՂԹԱՆԱԿՆԵՐՊԱՐՏՈՒԹՅՈՒՆՆԵՐ") → 4. hy stat labels on the game page are separated and readable at
390 and 1440 (wrap or abbreviate, no overlap).
Tests: unit test of the helper for en/ru/hy, including the fallback path.   Approval: none

### TASK-029 · Poker bet chips clear the nameplates on phones   P3 · S · APPROVED · B3
Problem: at 390 px the "Bet 5" chip overlaps the right-hand nameplate ("David 995").
Evidence: DESIGN_REVIEW.md B1 P4.
Acceptance criteria: at 390×844 and 1440×900 with 6 players, no bet chip overlaps a nameplate, stack
or Bot pill (checked by bounding boxes) on any seat; 2- and 4-player tables unchanged.
Tests: browser check at both sizes (screenshots in the implementation report).   Approval: none
Validation 2026-10-30: **APPROVED** (done inside TASK-036) — 6 seats: 0 bet ∩ nameplate/stack/Bot pill at 390 and 1440 (re-review, settled layouts). Note: the phone bet placement (`betBeside`, `PokerBoard.tsx`) applies at every seat count below 640 px, so 2- and 4-seat phone tables did change, against this task's "unchanged" clause and the B3a spec; accepted as a consistent phone layout on condition that the 2/4-seat overlap check in TASK-033 passes (else a new task).

### TASK-037 · Durak table follow-ups                           P3 · S · PLANNED · B3
Problem: two small issues left after TASK-035's review (optional nits). (a) At 1280×720 with 6 players
the bout area is only 93 px tall and a defended pair spills about 15 px past it (no collision seen yet).
(b) Phone role bubbles are 9.6 px text (`text-[0.6rem]` in `games/durak/DurakSeat.tsx`), small even on
high-contrast pills. Evidence: DESIGN_REVIEW.md B3a nit and re-review nit; IMPLEMENTATION_REPORT B3a
revision ("Not done: … 93 px").
Scope: (a) give the bout area more height on short desktops with 6 players (e.g. smaller top panels or
a two-row card size that fits); (b) raise compact bubbles to `0.65rem` (reviewer suggestion) while the
hy role words stay short.
Acceptance criteria:
  1. 1280×720 and 1366×768, 6 players, 2–6 bout pairs: every bout card lies inside the bout area and
     nothing overlaps seats, piles or the hand; whole table still fits the screen.
  2. 390×844, 6 players, en/ru/hy: bubble text ≥ 0.65rem, one line where it fits, every bubble inside
     the felt (same checks as the B3a re-review).
  3. No change at 1440×900 and with 2–4 players beyond the bubble size.
Tests: `fitHeight`/layout unit tests where testable; browser check at the listed sizes.
Depends on: TASK-035   Approval: none

### TASK-032 · No end sound after you left a table              P3 · S · PLANNED · B3
Problem: a player who left a Durak/Poker table that plays on hears the final "loss" much later. Spec
rule 3 says "left the table → nothing". Matchmaking already seats humans at 3–6 player tables, so this
is not only a bot-table case (decided now, not deferred). Evidence: DESIGN_REVIEW.md TASK-001 minor;
IMPLEMENTATION_REPORT B1 revision ("left as is").
Acceptance criteria: after leaving a table that plays on, no sound plays for that room, including its
end; players who did not leave still hear win/loss.
Tests: `useGameSounds`/RoomPage test for the left-table case.   Approval: none

### TASK-033 · Live check of paths not yet seen                 P2 · S · PLANNED · B3
Problem: several B1/B2 paths pass only in unit/feature tests; nobody has seen them in the running app.
Evidence: DESIGN_REVIEW.md B1/B2 "Not checked", IMPLEMENTATION_REPORT B2 "Not checked live".
Scope (UI/UX agent with the developer's two-browser local setup; fix only what fails, as new tasks):
guest `/join/<code>` → register → verify → seated; join with 403 (unverified) → verify → back;
matchmaking Play again (two accounts); a finished private game's panel; profile empty state with a new
account; admin Bots list shortage notice (temporarily deactivate bots locally); a win ("You won!" heading,
win sound); chess flag-fall live → "Lost · On time"; Nardi sounds and caption; real Web Share on a phone
(owner's device if headless has none). Added 2026-10-30 from B3a: Durak with 2 and 4 players at
390/820/1280×720/1440 (fit, hand, action button) and with a second human; ru strings on the new Durak and
Poker screens; Poker 2- and 4-seat tables at 390 (bet ∩ board/plate/nameplate = 0) and Poker at 820; a
live split pot; keyboard focus after playing a Durak card.
Acceptance criteria:
  1. Each path above is recorded in DESIGN_REVIEW.md as pass/fail with a screenshot name.
  2. A RoomPage test is added for matchmaking Play again (POST `/matchmaking` with the room's
     `game_mode_id`, status shown in the panel).
  3. Every failure becomes a backlog task before B3 is closed.
Depends on: —   Approval: none (local data only; restore deactivated bots afterwards)

### TASK-034 · Table and lobby nits                             P3 · S · PLANNED · B3
Problem: small layout issues left by B2 review (optional nits) and seen as pre-existing.
Evidence: DESIGN_REVIEW.md B2 re-review nits and "Pre-existing issues seen".
Scope: (a) bot level pill group full card width from `sm` (`sm:max-w-none`; the 320 px cap stays on
phones); (b) waiting table at 390: the clipboard-fallback field must not cover my own seat's avatar
(e.g. hide "Copy code" while the field shows); (c) waiting-table frame no longer overlaps the empty
side seats at 390; (d) **done** in TASK-035 (APPROVED 2026-10-30): a finished Durak table shows no role
pills, only the loser's "durak" bubble.
Acceptance criteria:
  1. ≥ 640 px: level pills end where the Start game button ends; 390 px unchanged (one line, 44 px).
  2. 390 en/ru/hy, clipboard rejected: own avatar fully visible, field selected, no horizontal scroll.
  3. 390: frame and empty seats don't overlap (bounding boxes).
Tests: Segmented/WaitingTable/Durak component tests where testable; browser check otherwise.
Depends on: —   Approval: none

### TASK-015 · Public profile statistics                        P2 · S · PLANNED · B3
Problem: `/u/:username` shows no records, but the API already returns them (`PublicUserResource`
`stats`, loaded by `PublicProfileController`). Decision 2026-10-29 (PM, DECISIONS.md): per-game records
are public by design, as on Chess.com/Lichess; this task is now frontend only (was B4 "new public API
field"). A "hide my stats" privacy option would be a separate backend item for the owner.
Scope: render the shared `GameRecord` grid (TASK-007) on the public profile; no recent-games list (no
public endpoint, out of scope).
Acceptance criteria:
  1. Same fixture → same numbers as on the owner's own profile.
  2. A player with no games shows a short "No games yet" line, no empty cards.
  3. 390/1440 en/ru/hy: no horizontal scroll.
Tests: PublicProfilePage component test with MSW.   Depends on: —   Approval: none

## B4 · New capabilities (need the owner)

Each needs a product brief + spec before development and the owner's go-ahead (reason in brackets).
Ordered by the PM's recommendation.

| Task | Opportunity | Priority | Size | Why approval |
|---|---|---|---|---|
| TASK-030 · Unique bot names at a table (was TD-002) | B1 review | P1 | S | go-ahead given 2026-10-29 · **APPROVED** |
| TASK-031 · Chess loss on time counts as a loss | spec B1 open question 1 | P1 | S | go-ahead given 2026-10-29 · **APPROVED** |
| TASK-014 · Ratings per game/mode + leaderboards | OPP-003 | P1 | L | new backend domain; rating system choice |
| TASK-016 · Rematch against the same opponent | OPP-001 | P1 | M | room flow change |
| TASK-018 · Long nardi as a second Nardi mode | OPP-002 | P1 | L | new engine + bot; rule set to confirm |
| TASK-020 · Durak perevodnoy + table options | OPP-006 | P1 | M–L | rule variants in the engine |
| TASK-021 · Move list + step-through replay (and the missing `GET /rooms/{id}/moves`) | OPP-004 | P1 | M | new public endpoint |
| TASK-017 · Guest play against bots | OPP-017 | P2 | M | auth model change |
| TASK-019 · Nardi match play to N points | OPP-009 | P2 | M | rule change (`points_to_win` unused) |
| TASK-022 · Poker sit-out and fresh table after busting | OPP-016 | P2 | M | play-chip rules (recreational rule) |
| TASK-023 · Fair-play: completion rate, cooldown for serial leavers | OPP-011 | P2 | M | moderation behaviour |
| Later | friends (OPP-012), preset chat (OPP-013), spectating (OPP-014), tutorials (OPP-010), puzzles/analysis (OPP-015), achievements (OPP-020), PWA (OPP-018), more chess time controls (OPP-021), durak 2×2 (OPP-022) | P2–P3 | — | see PRODUCT_RESEARCH §4 |

### TASK-030 · Unique bot names at a table                     P1 · S · APPROVED · B4
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
outside the frontend autonomous mode. **Go-ahead given 2026-10-29.**
Validation 2026-10-29: **APPROVED** — distinct bots at every table seen live (Durak 4p, Poker 6p, and after Play again); 5 bots per level seeded; `BotSelector::shortages()` + admin subheading covered by feature tests (291 passed, developer run), not seen live → TASK-033.

### TASK-031 · Chess loss on time counts as a loss              P1 · S · APPROVED · B4
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
results would be destructive → out of scope. **Go-ahead given 2026-10-29.**
Validation 2026-10-29: **APPROVED** — `GameActionService::end` marks abandoned only for leaving or a timeout without a game clock, so a chess flag-fall is a loss; feature test (`TimeControlTest`) checks result and stats; TASK-031 itself adds no API field. Not seen in a live chess game → TASK-033.

## Decisions needed

1. B4 tasks: which to build and in what order (PM recommends TASK-014 ratings, TASK-016 rematch,
   TASK-018 long nardi). Details per task in PRODUCT_RESEARCH.md §6.
2. Brand: the real product name and logo (UX-009).
3. ~~Go-ahead for TASK-030/031~~ — given 2026-10-29 ("do all necessary things"); both APPROVED. PM reads
   that go-ahead as covering these two fixes only; please confirm whether it also covers any other B4
   item (item 1).
4. Owner checks for B1/B2 (not blocking the approvals): listen to the sounds (Settings → Sound effects
   plays a preview; then a bot game) and say if volume/character should change; have a native speaker
   check the new hy strings (`room.tab.*`, `room.endReasonShort.*`, `dashboard.*`, `settings.soundHint`,
   B2: `room.backToLobby` — "լոբբի" loanword — and `room.shareText`; B3a: the new `durak.*` and
   `poker.result.*` / `poker.nextHand*` / `room.nextRound` strings, especially the shortened Durak role
   words գրոհող (attacker), պաշտպան (defender), վերցնում (taking), shortened to fit 62 px seats); try a
   Web Share invite on a phone; listen to the new Poker `handWin` sound.
5. Content: the Poker game description is still English in hy — admin catalog text (Admin → Games),
   needs an Armenian text from the owner or a translator.
(Decided 2026-10-29: autonomous mode for B1–B3; TD-001 deleted; sound on by default; TASK-030/031
go-ahead; room API fields for Play again; `.text-gold-gradient`; public profile stats are public. See
DECISIONS.md.)
