# PM validation — TASK-047 · Level & XP progression

- **Date:** 2026-11-08
- **Build:** `main` at 19dc650
  - ADR 51acc3e
  - backend 7935e4b
  - frontend d0ab709
  - QA and design fixes 19dc650
- **Owner's request (2026-11-02):** the verbatim brief `docs/product/briefs/TASK-047-levels-xp.md`, sections 1–9
  and the final requirements.
- **Inputs:**
  - `docs/team/PROGRESS.md` "Now".
  - QA `docs/qa/QA_REPORT_TASK047.md`: 148 checks, 135 pass; 2 Medium and 3 Low, reported fixed in 19dc650.
  - Design `docs/design/REVIEW_TASK047.md`: NEEDS REVISION, 3 High, 8 Medium, 6 Low items, reported done.
  - `docs/adr/0018-levels-and-xp.md`.
  - Code: `Levels.php`, `LevelCurve.php`, `LevelItems.php`, `GameFinisher.php`, `GameActionService.php`, migration
    `2026_11_08_100000_create_levels_tables.php`, `BotSeeder.php`, `SettingsCatalog.php`, `features/levels/*`,
    `Avatar.tsx`, `LevelBadge.tsx`, the seat and chat components.
- **PM's own checks (read-only):**
  - **Tests:** web vitest **158 passed** (re-run). Backend tests not run (shared testing DB). The 379 in QA's
    report predate the fixes; the two tests added in 19dc650 were read, not run.
  - **Dev database:** 15 bots at levels 19–56, all distinct; 29 reward rows, 86,200 Fishkas, milestones at 5, 10,
    15, 20, 25, 30 with the items the ADR lists; every `levels.*` setting at its default,
    `min_game_moves` = 3. Across all 113 accounts: no duplicate level-up, no duplicate reward key, and no claimed
    reward without a ledger entry.
  - **Every `Avatar` and seat in the web app** read for `level` and `frame`. The only avatar without a level is the
    upload preview in Settings, which is right.
  - **Screenshots from the fix pass (`scratchpad/shots/`, 13:57–13:59, commit 13:59:56):**
    - The popup at 844×390 keeps Collect in view (`02b-popup-landscape.png`).
    - Confetti passes behind the words (`01-popup-desktop.png`).
    - The phone profile has the badge on the avatar's corner (`06-profile-phone.png`).
    - Durak and poker seats have the level top-left, clear of name, pill and cards (`10-durak-desktop.png`,
      `11-durak-phone.png`, `10-poker-desktop.png`).
    - The dashboard bar reads "LEVEL 6 · 20 / 180 XP · 11% · 160 XP until level 7" (`04-dashboard.png`).
  - **Not seen after the fixes:** frames (the opaque tile, and frames at poker and durak seats), the level 30
    "major" popup, and the waiting table at 820 and 390. All are read from code only, and no account at level 20+
    was shown in the post-fix shots → TASK-061.

## Verdict: **ACCEPT WITH FOLLOW-UPS**

- **Request met end to end:** XP from real games in all five games and from the daily bonus, computed only on the
  server; a level on every avatar; a progress bar; a popup with a reward collected exactly once; offline
  level-ups; distinct high bot levels on this database; admin-editable curve and rewards; en/ru/hy. The Fishka
  rules are unchanged: rewards are credits through `FishkaWallets`, nothing is bought, sold or sent.
- **Two gaps, both small backend fixes. They should go in with the "PM validation → fix" step of TASK-047, before
  it is marked done:**
  1. **§6 not met on a new install: every bot gets level 30** (TASK-059). The distinct levels come only from the
     migration's back-fill of bots that already exist. `make setup` and `DEPLOYMENT.md` migrate first and seed after,
     and `BotSeeder` creates bots with the column's default of 30. QA and I saw distinct levels because this database
     had its bots before the migration ran.
  2. **BUG-1 is only partly fixed: sitting idle at poker still earns XP** (TASK-060). See §C.
- **Close-out still owed by the developer:** the summary the brief asks for (XP formula, rules, reward config, DB
  changes, files) and the `IMPLEMENTATION_REPORT.md` section; neither exists yet. This is step 4 in PROGRESS.
- **No UI/UX re-review after the fixes.** The developer states this. I checked what the post-fix screenshots show;
  the rest is listed above.

## A. Brief, section by section

### §1 User level

| Element | Status | Evidence |
|---|---|---|
| Every user has a level; new users start at 1 | Met | `users.level` NOT NULL default 1, CHECK ≥ 1; QA §9.1 |
| XP from playing and activities | Met | Finished games (QA §1, all 5 games) and the daily bonus (QA §5) |
| Advances automatically | Met | `Levels::award` recomputes under a row lock |
| Badge: profile (own and public) | Met | `ProfileView`; phone position fixed (BUG-2, screenshot) |
| Badge: game rooms, waiting table | Met | `WaitingTable`, `PlayerList`; QA §9 |
| Badge: poker tables | Met | `PokerBoard`, top-left of the seat (screenshot) |
| Badge: player lists | Met | `PlayerList`: chess, checkers, nardi |
| Badge: chat | Met | `TableChat`; QA `surf-chat.png` |
| Badge: other areas | Met | Header menu, dashboard; Durak seats; history has no avatars |
| Badge: leaderboards | N/A | None exist. ADR 0018 says one built later shows the badge |
| Attractive, consistent badge | Met | One `LevelBadge`, five tiers; design "What works"; contrast raised (issue 8) |

### §2 Progression and XP

| Element | Status | Evidence |
|---|---|---|
| 30 levels initially | Met with a decision | Levels 2–30 have seeded rewards and the gentle curve; the cap is `max_level` = 100, because the brief also asks for levels above 30. See §B, question 19 |
| 1–30 easy and fast | Met; pace is a judgement | 80 XP to level 2, 640 for 29→30; level 5 in about 11 games, level 30 in about 260. Question 19 |
| After 30 noticeably harder | Met | 640 → 990 (+55%), then +10% a level (QA §2) |
| Each later level costs more | Met | 0 violations to level 99 (QA §2) |
| Balanced curve, centralised, configurable | Met | `LevelCurve` built from `levels.*` settings, edited in Filament and audited (QA §6) |
| Stored in the DB, computed on the backend | Met | `users.xp`, `users.level`; only `Levels` changes them |
| Never trust the frontend; no manipulation | Met | No endpoint accepts XP; crafted `PATCH` and `POST` changed nothing (QA §1) |

### §3 Progress bar

| Element | Status | Evidence |
|---|---|---|
| Level, XP, XP needed, bar, remaining | Met | `LevelProgress`; boundaries 0, one below, exact, one past (QA §3); matches the brief's example layout |
| Visible on the profile and elsewhere | Met | Profile, dashboard, header menu (compact) |
| Top level | Met | "Top level", no "until" text; screen-reader label fixed (BUG-5) |

### §4 Popup and rewards

| Element | Status | Evidence |
|---|---|---|
| Animated, previous → new, confetti | Met | `LevelUpDialog`; a reduced-motion version exists; confetti now behind the words (issue 4, screenshot) |
| Reward information, Collect button | Met | Fishka total and item chips; Collect and Later (QA §4) |
| Popup reachable on small screens | Met | Scrolls, Collect sticky (issue 3, screenshot at 844×390) |
| Every level gives a reward | Met | A `level_rewards` row or `reward_default` (3,000) |
| Reward kinds | Met | Fishkas, avatar frames, profile badges, exclusive emojis. All earned by level, none random, none for sale |
| Configurable per level | Met | Filament "Level rewards" (QA §6) |
| Exactly once, no duplicates | Met | Unique `(user_id, level)`, locked claim, ledger key `level:{user}:{level}`; 20 parallel claims paid once (QA §5); my DB check found none doubled |
| History in the DB | Met | `level_ups` plus the wallet ledger; shown as "Level reward" in wallet history |
| Offline level-ups shown on return | Met | Unclaimed rows; QA §4. After "Later" the level card now says a reward is waiting (issue 10) |
| Other tabs | Met | A claim broadcasts `pending: 0` (BUG-3, new test read) |

### §5 Milestones

| Element | Status | Evidence |
|---|---|---|
| 5, 10, 15, 20, 25, 30 | Met | Seeded: Rising badge, Royal emoji pack, Premium badge, Gold frame, Legend emoji pack, Legend frame and Champion badge (DB). "Premium" is the owner's word and is earned, never sold |
| Configurable | Met | `is_milestone` and items on each row |
| Level 30 a major celebration | Met in code | Own header, gold pill, second burst (issue 5). No post-fix screenshot → TASK-061 |

### §6 Bots

| Element | Status | Evidence |
|---|---|---|
| High levels, not level 1 | Met | Admin range 10–100; the DB has 19–56 |
| Different levels for different bots | **Not met on a new install** | Distinct on this DB only through the migration's back-fill. `BotSeeder` passes only the name; the column default is 30 (`SHOW COLUMNS`). New bots made in the admin get a random 18–57 → TASK-059 |
| Visible like real users; same badge | Met | Same `LevelBadge`; no frame by design |
| Consistent across games and rooms | Met | One `bot_profiles.level` per bot (QA §9) |
| Separate from XP; no rewards | Met | XP only where `user_id` is set; bots have no user row (QA §9.11) |

### §7 Backend architecture

| Element | Status | Evidence |
|---|---|---|
| Level, total XP, XP for next level | Met | Columns; `next_level_xp` in `GET /me/level` |
| Reward config and reward history | Met | `level_rewards`; `level_ups` linked to `fishka_transactions` |
| Level-up events | Met | `level.updated` after commit |
| Atomic updates; no duplicates | Met | Row locks and idempotency keys; 10 processes and 150 awards with no error (QA §5) |
| Scalable, configurable | Met | Settings and reward rows; `Levels` is `scoped`, so workers don't keep a stale reward table. Extreme admin values overflow (QA O-1) → TASK-062 |

### §8 UI/UX

| Element | Status | Evidence |
|---|---|---|
| Attractive badges | Met | Design "What works"; issue 8 fixed in code |
| Smooth progress bar | Met in code | 700 ms transition, off under reduced motion. Not seen animating by design or me |
| Beautiful popup | Met | Screenshots; issue 6 (the new level is now the largest thing in it) |
| Distinct milestone celebrations | Met | Normal, milestone and major differ |
| Consistent across games | Met in code | Frames now reach poker and durak seats (issue 9). No post-fix screenshot → TASK-061 |
| Not overloaded; compact badge | Met | Screenshots; seat badge placement (issue 7) |

### §9 Testing and the final requirements

| Check | Status | Evidence |
|---|---|---|
| 1–6, 8–9, 11–13 | Met | QA summary, all PASS |
| 7. Displayed correctly everywhere | Met | QA BUG-2 fixed (screenshot); leaderboards N/A |
| 10. Bots display high levels | Met | QA PASS on this DB; see §6 for a new install |
| Analyse first, reuse the architecture | Met | ADR 0018; `FishkaWallets`, `Settings`, `GameFinisher` reused |
| Fishka economy and game logic intact | Met | QA §1, stakes, payouts, draw refund, buy-in, daily bonus |
| No unnecessary dependencies | Met | None added |
| End to end, not only visual | Met | QA played real games through the API and in the browser |
| All affected games | Met | QA §1; real multi-person poker and Durak leaves not played (QA "could not test") |
| Fix issues found | Partly | All QA and design items addressed; BUG-1 has a remainder (TASK-060) |
| Summary of formula, rules, config, DB, files | **Not yet** | Owed at close-out (PROGRESS step 4); no `IMPLEMENTATION_REPORT.md` section |

### QA and design fixes, checked in code

| Item | Status | Evidence |
|---|---|---|
| BUG-1 XP without playing | **Partly fixed** | Resigning at once, a chess clock and a short sitting now earn nothing. Auto-played turns still count as moves (§C) |
| BUG-2 phone profile badge | Fixed | `w-fit`; screenshot |
| BUG-3 popup in the other tab | Fixed | `Levels::claim` broadcasts; `useLevelSync` refetches |
| BUG-4 stale emoji lock | Fixed | The picker takes `user.level` first |
| BUG-5 "level 101" label | Fixed | `aria-label` uses "Top level" |
| Design 1, 3, 4, 7 | Fixed | Code and screenshots |
| Design 2, 5, 9, 11 | Fixed in code | Not in the post-fix shots → TASK-061 |
| Design 6, 8, 10, 12 (six items) | Fixed in code | Read in `LevelBadge`, `LevelUpDialog`, `LevelCard`, `LevelProgress`, `ReactionPicker`, `Avatar`; strings in en/ru/hy |

## B. Decisions taken without the owner

| Decision | PM judgement | Owner? |
|---|---|---|
| **Reward amounts:** 250 + 50 × level, milestones 2,500 to 25,000; 86,200 Fishkas to level 30; 3,000 a level above (296,200 to level 100) | The brief asks for Fishka bonuses but sets no amounts, and Fishka amounts are on the approval list. All editable in the admin panel | **Question 18** |
| **Existing players start at level 1, nothing paid for past games** | Right: a back-fill would pay every past level's Fishkas at once. A long-time player does start at 1 | Inside question 18 |
| **Cap of 100, not 30** | The brief says "maximum of 30 levels initially" and also asks for harder levels after 30. A cap of 100 serves both, and `max_level` can be set to 30 | **Question 19** |
| **Level 30 takes about 260 games** | Reasonable, but "relatively easy and fast" is the owner's call | Inside question 19 |
| **What counts as a game played** (QA asked for a decision): at least 3 of your own moves and 30 seconds; a loss by resigning after that still pays 30 XP | Sensible once idle turns stop counting (TASK-060). A short real poker win (an all-in in one or two actions) earns nothing, which is acceptable | **Question 20** |
| **Poker: one game per sitting** | Right for now (ADR 0018); per-hand XP can come later | No |
| **Items are unlocked by level, not granted one by one** | Right: nothing to duplicate or claim twice | No |

## C. Follow-ups

| Item | Where |
|---|---|
| Bots all level 30 on a new install (§6) | TASK-059 (new, P1). Fix at TASK-047 close-out |
| Idle poker still earns XP: an auto-played check or fold is saved as the player's own move (`GameActionService::play`, type `check`/`fold`), so `GameFinisher`'s count includes it. The dev DB holds 211 such poker rows and 7 Durak ones (`client_action_id` null). About three 30-second turns, then leave: +30 XP. Read from code and data, not played. It also contradicts ADR 0018 and the admin help text ("sitting at a table") | TASK-060 (new, P2). Fix at TASK-047 close-out |
| Visual pass over what wasn't seen after the fixes; five tiers and the seat rule in `DESIGN_SYSTEM.md` | TASK-061 (new, P3) |
| Sanity limits on the curve settings (QA O-1) | TASK-062 (new, P3) |
| Reward amounts and back-fill; cap and pace; what counts as a game | Owner questions 18–20 |
| Host's waiting table doesn't update when someone joins (QA O-2, predates this) | Already in PROGRESS "Later" |
| Gaming-agent notes still list only `min_game_seconds` for XP | Developer, at close-out |
