# Product backlog

The single task tracker (format and statuses: `docs/team/README.md`). Sources: `UI_UX_AUDIT.md`
(UX-NNN), `PRODUCT_RESEARCH.md` (OPP-NNN), `TECHNICAL_DEBT.md` (TD-NNN).
Last planning: 2026-11-07 (TASK-046 validated: ACCEPT WITH FOLLOW-UPS, `docs/product/VALIDATION_TASK046.md`;
TASK-054, TASK-055 and owner questions 15–17 added).
Before: 2026-11-07 (TASK-045 validated: ACCEPT WITH FOLLOW-UPS, `docs/product/VALIDATION_TASK045.md`;
TASK-052, TASK-053 and owner question 14 added; question 11 refined).
Before: 2026-11-06 (TASK-044 validated: ACCEPT WITH FOLLOW-UPS, `docs/product/VALIDATION_TASK044.md`;
TASK-051 and owner questions 12–13 added).
Before: 2026-11-02 (TASK-043 validated: ACCEPT WITH FOLLOW-UPS, `docs/product/VALIDATION_TASK043.md`;
TASK-049, TASK-050 and owner questions 9–11 added).
Before: 2026-11-01 (TASK-040 validated: ACCEPT WITH FOLLOW-UPS, `docs/product/VALIDATION_TASK040.md`;
TASK-039 (e), TASK-041, TASK-042 added; owner questions 6–8 updated).
Before: 2026-10-31 (TASK-038 Fishkas validated APPROVED (53018b3); follow-up TASK-039 added).
Before: 2026-10-30 (TASK-035, TASK-036, TASK-029 validated APPROVED (79a053d); TASK-037 added; owner
feedback earlier the same day: BUG-001 fixed, TASK-035/036 added at the top of B3). Before: 2026-10-29 B1/B2 validated, TASK-030/031 approved, TASK-033…034 added.

## Batches

| Batch | Theme | Tasks | Approval |
|---|---|---|---|
| B1 | Honest and clear: fix what misleads or loses games — **done 2026-10-29** (all APPROVED) | TASK-001…005 | none (frontend) |
| B2 | Keep playing: after-game flow, profiles, invites — **done 2026-10-29** (all APPROVED) | TASK-026, TASK-006, TASK-028, TASK-007, TASK-008, TASK-024 | none (frontend) |
| B3 | Polish and consistency (in progress; TASK-039 Fishka follow-ups first; TASK-035, TASK-036, TASK-029 **done 2026-10-30**; owner go-ahead so far only for TASK-035/036) | order: TASK-039, TASK-033, ~~TASK-035~~, ~~TASK-036~~, TASK-037, TASK-032, TASK-015, TASK-027, ~~TASK-029~~, TASK-034, TASK-012, TASK-009, TASK-010, TASK-011, TASK-013, TASK-025 | none (frontend) |
| B4 | New capabilities and backend fixes | TASK-014, TASK-016…023, TASK-041, TASK-042 (TASK-030, TASK-031 **done 2026-10-29**) | **owner** (go-ahead for backend work) |

## Fishkas (owner spec 2026-10-30)

### TASK-038 · Fishka currency: wallet, bonuses, entry fees, poker buy-in, admin   P0 · L · APPROVED
Owner's spec, pasted 2026-10-30, with the owner's choices: poker buy-in/cash-out, bots with house chips.
Welcome 5,000 · daily 1,000 · entry fee 500 (poker: buy-in ≥ 1, up to the table stack) · "out of
Fishkas" popup bonus 5,000 at most every 24 h while the balance is below the fee · header balance with
animation · /wallet history · admin add/deduct/set with reason, transaction tab, existing ban/unban.
Acceptance: the owner's 16 test scenarios (QA report `docs/qa/QA_REPORT_FISHKA.md`).
Validation 2026-10-31: **APPROVED** (53018b3, local) — QA ran 44 checks (39 pass); all 10 bugs are fixed
and I read each fix in code: per-player start lock `PlayRules::exclusively` on bot/private create, join,
matchmaking and accept-bot (+ wallet row `FOR UPDATE`, ADR 0015); chip games settle from the
post-departure state; popup portalled to `<body>`; ref guard on the refill; `inactiveMessage()`;
`meta.signsOut`; plurals. Web vitest 122 passed (re-run by PM); API 311 passed (developer's run, not
re-run). Rule check: nothing can be bought, sold or cashed out; amounts are admin settings; admin
changes are audited; ADR 0015 accepted.
Gaps, stated honestly (none blocks; follow-ups in TASK-039 and Decisions needed 6–7):
  - The owner's original spec text with the 16 scenarios and "Final Requirements" is not in the repo;
    I validated against the QA report's mapping, DECISIONS.md and ADR 0015. It should be saved as
    `docs/product/briefs/TASK-038.md` so each scenario can be traced.
  - The 5 failed QA checks (3.3, 6.7, 7.5, 8.4, 9.7) were re-checked by the developer (live + tests),
    not by an independent QA re-run.
  - Scenario 16 (parallel requests): no true parallel-request test; protected by the wallet row lock and
    the per-player start lock, checked live with 3 simultaneous starts (201, 422, 422; one fee).
  - The real midnight rollover (Asia/Yerevan) is untested; "next day" was simulated in tinker.
  - Leaving a multi-seat Poker table mid-hand (`depart()`) not checked live (code read by QA only); no
    full Poker game to a natural bust-out; matchmaking bot fallback with too few Fishkas untested.
  - Refill at 499: by the code +5,000 (→ 5,499), not "up to 5,000"; owner to confirm (Decisions 7).
  - hy dates/numbers on /wallet render in English (QA bug 10, unconfirmed headless ICU) → TASK-027.

### TASK-040 · Poker bots, real balance in Poker, game groups   P0 · L · APPROVED
Owner brief, verbatim: `docs/product/briefs/TASK-040-groups-poker-bots.md` (2026-10-31); owner decisions
2026-11-01 (winner takes the pot, bet replaces the fee, creator picks the bet, matchmaking at the group
minimum); ADR 0016. Code b90cd47, 7ce1f4a, d4ca892, edd04cf; QA fixes fd32039.
Validation 2026-11-01: **APPROVED — ACCEPT WITH FOLLOW-UPS** (`docs/product/VALIDATION_TASK040.md`): no brief
requirement unmet. Partly met: group visual identity (colour + name only), real network
disconnect, independent QA re-run of the 6 failed rows, a Chess/Checkers win vs a bot and a Durak draw
not seen live, final report to the owner. BUG-3 (one ~20 s bot stall per ~70 moves under host overload)
is not blocking → TASK-041. Conditions: the UI/UX design review (PROGRESS 8b) is still pending; a
required revision there reopens this task as NEEDS_REVISION. Fishka rule risk: owner question 6.

## Owner requests 2026-11-02 (strict order: one task at a time)

Order set by the owner: TASK-043 → 044 → 045 → 046 → 047 → 048. Details and status in
`docs/team/PROGRESS.md`; briefs in `docs/product/briefs/`.

### TASK-043 · Emojis, quick messages and the table chat         P1 · M · APPROVED
Owner brief, verbatim: `docs/product/briefs/TASK-043-emoji-quick-messages.md`; ADR 0017. Code 491b624,
1d9dcef, QA fixes 2967f6d, design fixes 54a3b6b. QA `docs/qa/QA_REPORT_TASK043.md` (44/46; 2 Low, fixed);
design `docs/design/REVIEW_TASK043.md` (7 items fixed).
Validation 2026-11-02: **APPROVED — ACCEPT WITH FOLLOW-UPS** (`docs/product/VALIDATION_TASK043.md`). Every
brief requirement is met, except "relevant social areas" (partly met: tables only, since there are no
other social areas yet). Real-time delivery is proven end to end with real sessions. Follow-up: TASK-049
should close it before TASK-044 starts (owner's "nothing partial" rule). Owner questions 9–11.

### TASK-049 · TASK-043 re-review and smoke test of the fixes    P2 · S · DONE
Done 2026-11-02: the UI/UX re-review and the five-game smoke ran (REVIEW_TASK043.md "Re-review" and "Re-review 2": APPROVED); the regression it found was fixed in a21a698 and the phone follow-up in the TASK-043 closing commit.
Problem: the 7 design-review fixes and the new chat bar above the board (54a3b6b) were verified only by
the developer (the 44 px targets only by reading the classes). Evidence: VALIDATION_TASK043 §A/§C.
Scope: the UI/UX agent re-reviews REVIEW_TASK043 items 1–7; QA runs a two-player smoke test in all five
games at 390 and 1440: A→B, B→A, 3 quick messages to one seat, chat bar open/closed, Mute.
Acceptance criteria:
  1. Every button in the picker and the chat bar measures ≥ 44 px at 390.
  2. No bubble rect intersects a clock, the deck/trump, a bet chip, the board or the action buttons, in any
     game at 390 and 1440.
  3. The 😊 button is visible without scrolling at 1440×900, 1280×720 and 390×844 in all five games.
  4. REVIEW_TASK043 gets a re-review section with the verdict; QA_REPORT_TASK043 a smoke-test section.
Depends on: TASK-043   Approval: none

### TASK-050 · Mute one player at the table                      P2 · S · PLANNED
Problem: Mute hides every player's animations; there is no way to silence one opponent who keeps sending
taunts (🤡, 😈, "Haha!", "Better luck next time!"), and no per-player control at all.
Evidence: VALIDATION_TASK043 §B.
Scope: frontend only. Tapping a seat or a chat name offers "Mute this player" for this room: their flights
and bubbles stop and their chat lines collapse into "N hidden". Stored locally per room. No server
change; the other players are not told.
Acceptance criteria: a muted player's reactions don't animate and are collapsed in the log; others still
work; unmute restores; works at 390/1440; en/ru/hy; Reactions.test case.
Depends on: TASK-043   Approval: none (after the owner's queue unless the owner moves it up; see
question 10)

### TASK-044 · Daily bonus popup with a login streak             P1 · M · APPROVED
Owner request 2026-11-02: "daily bonus will be like it", with a reference screenshot (days 1–5, today lit,
growing rewards, "One day to go until your double bonus!", an open chest with the amount). Code 8c7a0d6
(API), 89ecad5 (popup), b599e4e, QA fixes 6c816e4, design fixes d93d67f. QA `docs/qa/QA_REPORT_TASK044.md`
(38/41; 3 Low, fixed); design `docs/design/REVIEW_TASK044.md` (1 Medium and 5 Low, fixed).
Validation 2026-11-06: **APPROVED — ACCEPT WITH FOLLOW-UPS** (`docs/product/VALIDATION_TASK044.md`). Every
element of the reference and every rule is met: free Fishkas, the server decides, once a day in Yerevan
days, en/ru/hy. The mid-flight screenshot confirms the chips land in a visible header. Follow-ups: owner
questions 12–13 (amounts and their economy effect; collect instead of a silent credit) and TASK-051.

### TASK-051 · Stabilise the flaky route-guard test              P3 · S · PLANNED
Problem: `apps/web/src/features/auth/guards.test.tsx` › "sends guests to the login page and remembers where
they were going" failed in 1 of 3 full `vitest run`s on 2026-11-06 (load average ~40). It passes alone and
in the re-runs. A flaky test hides real failures and blocks pushes at random.
Acceptance criteria: the cause is found (timing/`findBy` timeout, shared state between files) and fixed;
10 consecutive full runs pass under load; no test is skipped or given a longer global timeout to hide it.
Depends on: —   Approval: none (test-only; after the owner's queue unless it blocks a push)

### TASK-045 · Poker table redesign like the owner's screenshot  P1 · M · APPROVED
Owner request 2026-11-02: "poker table like it, with chat" (reference: a wide oval felt, a thick rail on a
dark patterned background, framed avatars on chairs, "+" empty seats, the dealer at the top, table info
bottom-left, chat below). Code fb3506a, QA fixes be23f04 and 5c3639b, design fixes 219b76d; frontend only.
QA `docs/qa/QA_REPORT_TASK045.md` (35/38; 3 Low, fixed); design `docs/design/REVIEW_TASK045.md` (re-review
APPROVED 2026-11-07).
Validation 2026-11-07: **APPROVED — ACCEPT WITH FOLLOW-UPS** (`docs/product/VALIDATION_TASK045.md`). The
look of the reference is met in our own art; gameplay, the phone layout, en/ru/hy and the reaction anchors
are all intact. Changed by decision, and the PM agrees: no "+" seats (no mid-game joining exists), and
the dealer tray only when the top seat is free. Chat placement is still owner question 11. Follow-ups:
TASK-052, TASK-053, owner question 14.

### TASK-052 · Poker controls fit on short desktop screens        P2 · S · DONE
Done 2026-11-07 within TASK-045: the desktop table's width follows the screen height (`sm:max-w-[min(64rem,calc((100dvh-25.5rem)*2.45))]`, aspect 2.45:1, less padding). Measured from the stage's top to the bottom of the action panel, plus the 80 px header: 795 of 900 (1440×900), 750 of 768 (1366×768), 702 of 720 (1280×720); the top seat stays inside the stage.
Problem: with the taller stage (`sm:pt-20`) and the chat bar above it, the action area ends near the bottom
edge at 1440×900. In `review/p45-rr1440-myturn.png` the ½ pot / Pot / All-in presets are cut off after a
small scroll. At 1280×720 the actions may start below the fold, which costs time on a 30 s turn.
Evidence: VALIDATION_TASK045 §C.
Acceptance criteria: at 1280×720, 1366×768 and 1440×900 with 6 seats, my cards, Fold/Call/Raise, the slider
and the presets are fully visible without scrolling on my turn (bounding boxes); 390×844 unchanged; no
horizontal scroll. Fit the stage to the viewport height the way Durak does (`useFitHeight`) instead of
shrinking controls.   Depends on: TASK-045   Approval: none (frontend)

### TASK-053 · Header balance steady while chips are at a table    P2 · S · PLANNED
Problem: during poker the header Fishka pill shows small "−1 / +3" deltas at every hand, and on a big win
it moved several times (`52,000 → 0 → 52,000 → 52,020 → 104,000`; REVIEW_TASK040 issue 2). No fix for it
is recorded, and the TASK-045 reviewer saw the hand deltas again.
Acceptance criteria: with chips at a table, the pill shows no per-hand delta or bump; at game end it moves
once, by the room's net (e.g. a win `52,000 → 104,000 [+52,000]`), checked with a 50 ms recorder at 390 and
1440; wallet history unchanged. Backend if needed: report `at_table` until the cash-out entry exists, or
release and credit in one transaction before broadcasting.
Depends on: —   Approval: none (no Fishka rule change)

### TASK-046 · Nardi in two variants: Long and Short             P1 · L · APPROVED
Owner request 2026-11-02: a "what shall we play?" picker with two board thumbnails (Long / Short), plus a
reference short-nardi board. Today's engine was Short (backgammon rules); Long nardi is the new variant.
Code 61a2181 (engine), e0aaa53 (mode `long`, "Classic" renamed "Short nardi"), c7fc6b0 (board, picker),
4876787 (QA fixes), 6ea1ba0 (design fixes). QA `docs/qa/QA_REPORT_TASK046.md` (38/40; 1 Medium + 1 Low,
fixed); design `docs/design/REVIEW_TASK046.md` (2 Medium + 3 Low, addressed; no UI/UX re-review, and the PM
checked the post-fix screenshots).
Validation 2026-11-07: **APPROVED — ACCEPT WITH FOLLOW-UPS** (`docs/product/VALIDATION_TASK046.md`). Both
variants are real and rule-checked (0 violations in 391 states); bots, groups, bets and matchmaking work per
variant; en/ru/hy; no UI-only claims; Fishka rules unchanged. Not built by decision: the doubling cube and a
double payout for a mars (owner questions 15–16). Follow-ups: TASK-054, TASK-055, question 17.

### TASK-054 · Nardi board extras from the owner's reference      P2 · M · PLANNED
Problem: the owner's second reference board has elements this task didn't promise: points numbered 1–24
along both edges (in your own numbering), a round turn timer on the board, wooden bear-off trays at the
side showing stacked checkers, and a side panel with both players' avatars, clocks and the stake.
Evidence: REVIEW_TASK046 follow-ups 1–4; VALIDATION_TASK046 §A.
Acceptance criteria: each element present for Long and Short, both seats, at 1440 and 390 (the side panel
may fold under the board on phones); point numbers follow the viewer's numbering; the timer matches the
server deadline; no horizontal scroll; en/ru/hy; no rule change.
Depends on: TASK-046   Approval: none (frontend). Can fold into TASK-048 (redesign) if the owner prefers.

### TASK-055 · Mars wording matches the Fishka payout             P2 · S · DONE
Problem: the game-over panel says "Mars! A double win." when a mars scores 2, but the Fishka payout is the
normal pot (bet × 2), so players may expect double Fishkas. Evidence: QA_REPORT_TASK046 O-1, VALIDATION_TASK046 §B.
Acceptance criteria: if the owner keeps payouts unchanged (question 16), the line reads "Mars! Your opponent
bore off none." (en/ru/hy) and no text implies double Fishkas. If the owner chooses a double payout, this task
is replaced by the backend payout change (a stake rule → owner approval) and the line stays.
Depends on: owner question 16   Approval: none for the wording
Done 2026-11-07 (with TASK-046): the line now reads "Mars! The opponent bore off no checkers." (en/ru/hy), which is
true whatever question 16 decides; if the owner picks a double payout, the line can say so once the backend pays it.

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
Added 2026-10-31 (QA_REPORT_FISHKA bug 10): /wallet history dates and numbers in hy (first confirm in a
desktop Chrome that it's not only the headless build's ICU data).
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

### TASK-039 · Fishka verification follow-ups                  P1 · S · PLANNED · B3
Problem: TASK-038 was approved with gaps that touch balances; they should be closed before real
players rely on Fishkas. Evidence: TASK-038 validation, QA_REPORT_FISHKA.md "Not tested, or limits".
Scope (QA + developer; backend tests are fine, no rule or amount changes):
  (a) independent QA re-run of checks 3.3, 6.7, 7.5, 8.4, 9.7 at 390 and 1440;
  (b) feature test for the day boundary with a frozen clock (`Carbon::setTestNow`) at 23:59:59 and
      00:00:00 Asia/Yerevan: one daily bonus per local day, none twice across the boundary;
  (c) a contention test for starts: with the `play:start:{user}` lock held, a second start fails with a
      422 (not a 500 from `LockTimeoutException`), and no fee is charged;
  (d) live: 4-seat Poker, leave mid-hand → own stack paid back, chips in the pot stay with the others,
      table total conserved; one full Poker game to a natural bust-out; matchmaking bot fallback with a
      balance below the fee.
  (e) added 2026-11-01 from TASK-040 (VALIDATION_TASK040.md): independent QA re-run of the six rows that
      failed before fd32039 (QA_REPORT_TASK040 1.10, 2.1, 2.6, 5.5, 7.4, 7.6), plus the cases never tested:
      a Chess and a Checkers win against a bot (pot = bet × 2 from the house), a Durak draw (bets
      returned), and a real phone on the LAN: socket host, polling fallback when the socket drops, and
      reconnect mid-hand.
Acceptance criteria:
  1. QA_REPORT_FISHKA.md gets a re-test section: (a) all PASS with screenshot names; QA_REPORT_TASK040.md
     gets one for (e), with ledger amounts per room.
  2. (b) and (c) are automated tests that pass in `make test-api`.
  3. (d) recorded with ledger before/after amounts; any failure becomes a BUG entry.
Depends on: TASK-038   Approval: none (no Fishka amount or rule change)

### TASK-041 · Bot speed on a production-like host              P2 · S · PLANNED · B4
Problem: after fd32039 bots act in a median of ~1.15 s, but on the local Mac (load average 12–15, Docker
under QEMU) about one bot action in 70 stalls for ~20 s. Unknown whether this happens on real hardware.
Evidence: QA_REPORT_TASK040 BUG-3 and "Fix status"; VALIDATION_TASK040 §B.
Scope: a bot-action latency log/metric (delay from state change to the bot's move, per game); measure
500 consecutive bot moves on staging (or any host without emulation) at 2–6 seat tables.
Acceptance criteria:
  1. p95 ≤ 2 s and no bot action over 5 s in 500 bot moves; results recorded in IMPLEMENTATION_REPORT.
  2. If missed, a P1 BUG entry with the slow job/lock found.
Depends on: a staging host   Approval: owner (production-like infrastructure)

### TASK-042 · Play-group audit keeps the old values            P3 · S · PLANNED · B4
Problem: an admin edit of a play group writes an audit row with only the new values, so it can't show
what changed (QA_REPORT_TASK040 observation O-3). Other admin changes record before/after.
Acceptance criteria: editing a group records `before` and `after` for each changed field; the audit
log detail shows both; feature test in PlayGroupAdminTest.   Depends on: —
Approval: none (backend fix, audit only) → owner go-ahead for backend work as usual.

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
5. Content (2026-11-07: the Nardi description above the new picker is English in ru/hy too): the Poker game description is still English in hy — admin catalog text (Admin → Games),
   needs an Armenian text from the owner or a translator. Since Fishkas, the Poker description and rules
   still say "play chips with no monetary value"; update them to describe Fishkas (same admin screen).
6. Fishkas move between players through games: at a private Poker table a player can lose chips on
   purpose to a friend (chip dumping), and since TASK-040 (winner takes the pot) a private stake game
   moves up to 500,000 Fishkas per game when one friend resigns. With bonuses farmable on extra
   accounts (5,000 welcome, 1,000 daily, 5,000 refill per 24 h), this works as the transfer the Fishka
   rule forbids. Accept as part of play, or restrict? Options: private tables pay back only the bets,
   with no pot; a daily cap on net winnings between the same two players; no pot when a game ends by
   resignation within the first N moves. Needs the owner (Fishka rules). (Updated 2026-11-01.)
7. "Out of Fishkas" bonus: (a) at a balance of 1–499 it adds 5,000 (499 → 5,499) — confirm, or top up to
   5,000? (b) since TASK-040 the bet replaced the 500 fee, but the bonus threshold is still the admin
   setting "Game entry fee" (`fishka.entry_fee`, `FishkaWallets::refillStatusOf`). So a player with 100–499
   can play Beginner Poker and still gets the bonus. Should the threshold be "can't enter the lowest group
   of any game" (100, from Poker)? Also: please paste the original Fishka spec into
   `docs/product/briefs/TASK-038.md`.
8. Beginner Poker blinds are 1/1 (small blind = max(1, ⌊1/2⌋)), so the small blind equals the big blind
   (QA observation O-1). Keep, make Beginner 1/2 (big blind 2), or allow 0-size small blinds? A table/Fishka
   rule → owner.
9. Free-text table chat (TASK-043 shipped emojis and predefined messages only, ADR 0017). Typing would
   need moderation first: word filters in en/ru/hy, reports, per-player mute and block, admin review and
   a retention rule (today nothing is stored beyond 50 lines for 3 hours). Do you want free text? If yes,
   it is a new backend domain (approval list) and should be planned as its own task.
10. Taunting content: the catalog includes 🤡, 😈 and messages like "Haha!" and "Better luck next time!".
    Keep them? Also, should staff get a per-player "no reactions" switch (today only the global
    `reactions.enabled` or a ban)? PM recommends keeping the content and adding TASK-050 (players mute one
    player locally); staff tools only if abuse shows up.
11. Table chat placement: your 2026-11-02 screenshot showed the chat under the table. We put a compact chat
    bar between the room header and the board, because under the board it was never visible while
    playing (design review issue 2). Keep this, or move it under the table in TASK-045 (poker) and the
    other game redesigns? (2026-11-07, after TASK-045: under the table would push the poker action buttons
    further down on desktop, so it would need TASK-052's fit-to-screen layout first.)
12. Daily bonus amounts (TASK-044): 1,000 / 1,500 / 2,000 / 2,500 / 5,000; day 5 repeats while the streak
    holds; a missed day resets to day 1. All are admin settings. Effect: a 30-day perfect streak gives
    137,000 free Fishkas (was 30,000 at a flat 1,000), about 4.6×. Bonuses alone take a player into the
    Professional groups (50,000 poker / 100,000 other games) within a month. Keep this? Alternatives: cycle
    back to day 1 after day 5 (about 75,000 in 30 days), or a lower day-5 amount. Should a single missed day
    be forgiven (one grace day)?
13. Collecting the daily bonus (TASK-044): it is now collected with "Collect" in the popup or the wallet
    card. Before, it was added silently at sign-in. Players who never open either no longer get it.
    Confirm this change. It matches your reference and makes the reward visible.
14. Empty seats with "+" (your TASK-045 reference): today a table starts full and nobody can sit down while
    it runs, so we don't show "+" seats; that would promise something that doesn't work. Do you want open
    tables that people can join and leave mid-game, ring-game style? That is backend work: seating during a
    game, buy-in and cash-out at any time, and how it fits the Fishka and stake rules. It would be its own
    task, needing your approval.
15. Doubling cube in nardi (your TASK-046 reference showed one). It raises the stake during a game, so
    Fishkas could move beyond the agreed bet. Build it? If yes: is it in both variants or Short only; is it
    capped at the group's maximum bet and the players' balances; and is it used in matchmaking or only at
    private tables? Not built until you decide.
16. Mars in nardi (the loser bore off no checkers) scores 2 in the game today, but the Fishka payout is the
    normal pot (the winner takes bet × 2). Should a mars pay double (the loser loses a second bet)? That
    changes the stake rules and needs a balance check. Until you decide, the PM recommends the wording in
    TASK-055.
17. Long nardi rule check (TASK-046): on a player's first turn, 6-6, 4-4 and 3-3 let two checkers leave the
    head (otherwise one per turn). Some players allow only 6-6 and 4-4. Is 6-6/4-4/3-3 the rule you play?
(Decided 2026-10-29: autonomous mode for B1–B3; TD-001 deleted; sound on by default; TASK-030/031
go-ahead; room API fields for Play again; `.text-gold-gradient`; public profile stats are public. See
DECISIONS.md.)
