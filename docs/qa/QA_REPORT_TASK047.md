# QA report — TASK-047 · Level & XP progression

- **Date:** 2026-11-08, about 13:25–13:50 local.
- **Build:** `main` at d0ab709 (backend 7935e4b, frontend d0ab709). The stack was already running; I did not restart it.
- **Machine load:** 4–7.
- **Method:**
  - **Backend:** real games through the API (random legal moves against Easy bots, presence kept alive) for all
    five games; parallel XP awards in separate PHP processes and parallel HTTP claims; the curve computed through
    `LevelCurve`. `make test-api` once: **379 passed**.
  - **Browser:** headless Chrome (CDP :9334, own profile, isolated contexts, tabs in one context) on throwaway
    accounts. A real checkers game played to its end in the browser, the level-up popup after it, en/ru/hy,
    "Later", two tabs, the progress bar at the boundaries and at the top level, badges on every surface,
    exclusive emojis.
  - **Admin:** Filament panel with a throwaway `super_admin` (not player1), level rewards and settings edited in
    the UI, and restored.
- **Evidence:** scripts in
  `/private/tmp/claude-501/-Users-hrak/dcf481df-22d2-4f78-a522-b2a3b7f2218b/scratchpad/tools/` (written below as
  `tools/`), logs and screenshots in
  `/private/tmp/claude-501/-Users-hrak/dcf481df-22d2-4f78-a522-b2a3b7f2218b/scratchpad/qa47/` (written below as
  `qa47/`).
  - Scripts: `tools/qa47lib.mjs`, `qa47play.mjs`, `qa47api.mjs`, `qa47conc.mjs`, `qa47clock.mjs`, `qa47cdp.mjs`,
    `qa47ui.mjs`, `qa47ui2.mjs`, `qa47admin.mjs`, `qa47curve.php`.
  - Logs: `qa47/log-api.txt`, `qa47/log-ui1.txt`, `qa47/log-ui2.txt`, `qa47/log-ui3.txt`, `qa47/log-admin.txt`,
    `qa47/log-clock.txt`, `qa47/play-*.json`, `qa47/concurrency.json`, `qa47/make-test-api.txt`.
  - Screenshots: `qa47/shots/`.

## Summary

| Brief §9 check | Result |
|---|---|
| 1. New user starts at Level 1 | **PASS** (`level 1`, `xp 0`, `next_level_xp 80`) |
| 2. User earns XP after playing | **PASS** — real finishes in all 5 games, win/draw/loss. See **BUG-1** for what should *not* pay |
| 3. Progress bar updates correctly | **PASS** — 0, one below, exact and one past each boundary, live over Reverb |
| 4. User reaches the next level | **PASS**, including from a real game finish |
| 5. Level-up popup appears | **PASS** (offline, live, after a real finish, held back during a game) |
| 6. Reward is granted exactly once | **PASS** — 20 parallel claims paid once; two tabs paid once |
| 7. Level displayed everywhere | **PASS with BUG-2** (phone profile: the badge is detached from the avatar). Leaderboards don't exist yet |
| 8. Multiple level-ups | **PASS** (1→5, 1→6, 1→10, 1→100) |
| 9. Offline level-ups after login | **PASS** |
| 10. Bots display high levels | **PASS** (bot levels 19–56, all distinct, same in every game) |
| 11. Bots get no XP or rewards | **PASS** (bots have no user row; levels unchanged after all games) |
| 12. Levels above 30 cost progressively more | **PASS** (990, 1,089, 1,198, 1,318 … every step larger through level 99) |
| 13. Concurrent XP: no wrong levels, no duplicates | **PASS** (10 processes, 150 awards; same key ×10; awards and claims together) |

Also tested: the Fishka economy is unbroken (stakes, payouts, draw refund, poker buy-in, daily bonus, level-reward
ledger); en/ru/hy strings; "Later" then returning; two tabs; the admin config changes what is paid and is audited.

**Totals:** 148 checks — 135 PASS, 11 FAIL across 5 defects (0 Critical, 2 Medium, 3 Low), and 2 that are not
counted as defects because the behaviour predates this task (O-2).

## 1. XP from real games

Six games played to the end through the API by a driver (random legal moves, presence reported), one more in the
browser. All against Easy bots, Beginner group.

| Game | Result | Moves / seconds | XP | Fishka ledger | Verdict |
|---|---|---|---|---|---|
| Checkers | Loss | 24 / 34 s | **+30** | `game_stake −500` | PASS |
| Chess blitz | **Draw** | 52 / 67 s | **+40** (30 + 10) | `game_stake −500`, `game_payout +500` | PASS |
| Durak | Win | 37 / 50 s | **+50** (30 + 20) | `game_stake −500`, `game_payout +1000` | PASS |
| Short nardi | Loss | 97 / 135 s | **+30** | `game_stake −500` | PASS |
| Long nardi | Win | 114 / 157 s | **+50** | `game_stake −500`, `game_payout +1000` | PASS |
| Poker holdem, played > 30 s, all-in vs bot | Win | 24 / 69 s | **+50** | `poker_bet −50,000`, `poker_winning +100,000` | PASS |
| Checkers in the browser, from 70 XP | Loss | 21 / 33 s | **+30**, level 1→2 (`level_before 1`, `level_after 2`) | — | PASS; popup "You reached level 2!" +350 over the game-over panel, dashboard bar "20 / 100 XP" |

Each room wrote exactly one `xp_transactions` row (`game:{room}:{user}`). The server rejected none of the moves.

### Games that should pay nothing

| Case | Actual | Verdict |
|---|---|---|
| Six bot games abandoned at 17 s (no presence reported) | Result `abandoned`, end `abandon`, **0 XP** | PASS |
| Under 30 s: resigned at 2 s, chess | 0 XP | PASS |
| Under 30 s: poker bust in 2 s | 0 XP | PASS |
| Presence lost after 35 s (checkers) | Result `abandoned`, end `abandon`, **0 XP**, no payout | PASS |
| Leave (resign) a checkers game after 35 s | Result `loss`, end `resign`, **+30 XP** | **FAIL (BUG-1)** |
| Stop moving, chess blitz clock runs out (presence kept) | Result `loss`, end `timeout`, **+30 XP** | **FAIL (BUG-1)** |
| Poker: sit 31 s, leave, no real play | **+30 XP**, balance 20,000 → 19,999 | **FAIL (BUG-1)** |
| Person vs person (private chess), other player resigns after 35 s | Winner +50, resigner +30 (loss) | Winner PASS, resigner BUG-1 |
| A repeated leave | XP unchanged | PASS |

### Draw and manipulation

| Check | Actual | Verdict |
|---|---|---|
| Draw through the finisher on a room started 5 minutes before | +40 XP | PASS |
| `PATCH /me/profile`, `PATCH /me/settings` with `level`, `xp` | 200 but no change (level 1, XP 0) | PASS |
| `POST /me/level`, `PUT /me/level` | 405 | PASS |
| `POST /me/level-ups/claim` with crafted `levels`, `fishkas` | 200, `collected` 0, balance unchanged | PASS |

## 2. The curve, levels above 30

Computed through `LevelCurve` with the default settings (`qa47curve.php`).

| Figure | Value | Matches ADR 0018 |
|---|---|---|
| Level 1→2, 2→3 | 80, 100 | yes |
| 29→30 | 640 | yes |
| 30→31, 31→32, 32→33, 33→34 | 990, 1,089, 1,198, 1,318 | yes |
| Total XP for 30, 31, 32 | 10,440; 11,430; 12,519 | yes |
| Every step above 30 larger than the last, to level 99 | 0 violations | PASS |
| `levelFor` at 79, 80, 10,439, 10,440 | 1, 2, 29, 30 | PASS |
| Top: total for level 100 | 7,819,067; one XP below is level 99; `PHP_INT_MAX` XP is level 100 | PASS |
| Rewards seeded, levels 2–30 | 29 rows, 86,200 Fishkas in all | PASS |

See O-1 for what the extreme admin values do to the curve.

## 3. Progress bar at the boundaries (live on the dashboard, XP awarded while it is on screen)

| XP | Level | Shows | Bar | Verdict |
|---|---|---|---|---|
| 0 | 1 | "0 / 80 XP", 0%, "80 XP until level 2" | 0% | PASS |
| 79 | 1 | "79 / 80 XP", 98%, "1 XP until level 2" | 98% | PASS |
| 80 | 2 | "0 / 100 XP", 0%, "100 XP until level 3" | 0% | PASS |
| 81 | 2 | "1 / 100 XP", 1% | 1% | PASS |
| 10,439 | 29 | "639 / 640 XP", 99%, "1 XP until level 30" | 99% | PASS |
| 10,440 | 30 | "0 / 990 XP", 0%, "990 XP until level 31" | 0% | PASS |
| 11,930 | 31 | "500 / 1,089 XP", 45% | 45% | PASS |
| 7,819,066 | 99 | "710,772 / 710,773 XP", 99% | 99% | PASS |
| 7,819,067 | 100 | "Top level", no "until" text | 100% | PASS; the screen-reader label is wrong (**BUG-5**) |
| 7,919,067 | 100 | Level stays 100, no new level-up | 100% | PASS |
| API at the top | `next_level_xp` and `next_reward` are null | — | — | PASS |

Evidence: `qa47/shots/bar-*.png`, `bar-profile-top-390.png`.

## 4. Popup, rewards, "Later", two tabs

| Check | Actual | Verdict |
|---|---|---|
| Popup after login, 1→5, milestone | "From level 1 to 5!", +3,700 Fishkas, Rising star badge | PASS |
| Later | Closes, wallet unchanged, 4 rewards stay pending | PASS |
| Daily bonus after Later | Shows after the level popup is put off | PASS |
| Same visit, navigating | Doesn't reopen (also after a further 4 s) | PASS |
| Reload | Popup returns | PASS |
| Escape | Closes, nothing paid | PASS |
| A new level while put off | Reopens with all pending levels (1→6) | PASS |
| Collect | Paid 4,250 once, ledger 5 rows (`level:{user}:{level}`) | PASS |
| Reload after collecting | No popup | PASS |
| Two tabs, both open a popup | Both show it | PASS |
| Collect in tab A; tab B | Paid once (5,000 + 750), ledger once per level | PASS |
| Tab A collects; tab B's popup | **Stays open** while the tab is on screen | **FAIL (BUG-3)** |
| The same in reverse | Same | **FAIL (BUG-3)** |
| Tab B, popup still open: Collect | `collected` 0, popup closes, nothing extra paid | PASS |
| Tab B, focus regained after the 30 s cache is stale | Popup closes | PASS |
| Tab B, focus within 30 s | Stays open | **FAIL (BUG-3)** |
| Live level-up, two tabs | Popup in both | PASS |
| Tab B shows the new balance | Yes, over Reverb | PASS |

## 5. Concurrency and exact-once payouts

| Scenario | Result | Verdict |
|---|---|---|
| 10 PHP processes, 15 distinct keys each, 200 XP a go (30,000 XP) | XP 30,000 = sum of 150 rows; level 41 = `levelFor(30000)`; `level_before`/`level_after` chain unbroken; level-ups 2…41 contiguous, one each; 0 errors | PASS |
| The same key from 10 processes | 1 row, XP +500, all 10 returned the first entry's id | PASS |
| 20 HTTP claims at once (14 levels pending) | One 201, nineteen 200; paid 22,200 once; 14 ledger rows | PASS |
| Awards (150 in 6 processes) and claims running together | XP 22,500; level 38; 37 level-ups; 110,200 paid, unique keys; 0 errors, no deadlocks logged | PASS |
| Daily bonus ×10 at once | One 201, nine 200; +1,000 once; XP +20 once | PASS |

## 6. Admin: level rewards and settings

| Check | Actual | Verdict |
|---|---|---|
| Edit the level 7 reward in Filament (600 → 777) | Saved, audit row `level_reward.updated` with old/new and the admin | PASS |
| A player reaching level 7 afterwards | `level_ups.fishkas` 777; collected amount matches | PASS |
| A player who reached 7 before | Keeps 600 (the reward as configured at that moment) | PASS |
| A level with no row | Pays `reward_default` (3,000) | PASS |
| Settings: `xp_game` 30 → 33 in the panel | Saved, audit row `settings.updated` with old/new | PASS |
| A finished game after that | +43 XP (33 + 10 draw) | PASS |
| Restored | Level 7 = 600, items null; `xp_game` = 30 | PASS |

## 7. Exclusive emojis

| Check | Actual | Verdict |
|---|---|---|
| 🔱 at level 1, level 9 (one XP short) | 422 "Reach level 10 to send this emoji." | PASS |
| 🔱 at level 10 | 201 | PASS |
| 🐉 at level 10 | 422 "Reach level 25…" | PASS |
| 🐉 at level 25 | 201 | PASS |
| An unknown emoji; a free catalog emoji | 422; 201 | PASS |
| Picker at level 9 | All 8 exclusive emojis shown greyed with the unlock level, none clickable | PASS (`qa47/shots/picker-locked-level9.png`) |
| Picker at level 10 on page load | Royal open, Legend locked; a click sends it and it shows in the chat | PASS |
| Level reached during a game, picker reopened | Still all 8 locked (the server would accept it) | **FAIL (BUG-4)** |

## 8. Languages (en, ru, hy)

Key parity across the three locale files: none missing. Popup, progress and wallet strings checked on screen:

| Check | Actual | Verdict |
|---|---|---|
| Popup strings, milestone, items, Later/Collect | Translated; no raw keys. Armenian checked by eye (the automatic check flagged "ՁԵՐ ՊԱՐԳԵՒՆԵՐԸ": "և" uppercases to "ԵՒ", so that is my test, not a defect) | PASS |
| Progress bar text | "До уровня 11: 260 XP", "Մինչև 11-րդ մակարդակ՝ 260 XP" | PASS |
| Badge title | "Уровень 10", "Մակարդակ 10" | PASS |
| Wallet history | "Награда за уровень", "Մակարդակի պարգև" | PASS |
| Server message for a locked emoji | Translated in `lang/ru` and `lang/hy` (read, not triggered in those languages) | PASS |
| Popup at 390 | Fits, both buttons on screen | PASS (`qa47/shots/locale-*-popup-390.png`) |

## 9. Level badges by surface

| Surface | Result | Verdict |
|---|---|---|
| Header menu, dashboard | Badge; compact bar in the menu | PASS |
| Profile and public profile, 1440 | Badge on the avatar, gold frame at 20+, profile badge chip | PASS |
| Profile at 390 and 639 (the public profile uses the same component; measured only at 1440) | **Badge 186–435 px away from the avatar** | **FAIL (BUG-2)** |
| Profile at 700 | Badge on the avatar | PASS |
| Waiting table, host's own seat | Badge | PASS |
| Waiting table, joining player | Sees both badges | PASS |
| Waiting table, host after someone joins | Not updated: see O-2 (not caused by this task) | Observation |
| Durak table, 3 people | All three levels for both seats | PASS |
| Chess, checkers, nardi, poker | Covered by the 30/30 check earlier (`live47.mjs`) | PASS |
| Table chat | Sender's level badge on the line | PASS (`qa47/shots/surf-chat.png`) |
| Level-21 player | Gold frame at the table | PASS |
| Leaderboards | None exist in the product yet (ADR 0018) | N/A |

### Normal play and the 60-a-minute account limit

Measured in the browser during the 33 s checkers game: **7 requests** to `/api/v1/me*` of about 95 in all, and no
429s. Normal play comes nowhere near the limit.

## Bugs

### BUG-1 — Medium — Leaving a game or running out of a chess clock still earns XP

- **Steps:**
  1. Start a bot checkers game. Wait 35 s and leave (or resign): `POST /rooms/{id}/leave`.
  2. Or start a chess blitz game, make one move and stop moving, keeping the page open.
  3. Or sit at a poker table against a bot for 31 s and leave.
- **Expected:** nothing. ADR 0018: "an abandoned game (timed out, left) gives nothing"; the admin setting says
  "Not for a game the player abandoned (timed out, left)."
- **Actual:** +30 XP each time (`result: loss`, end `resign` / `timeout`). A poker sitting cost 1 Fishka here (the
  blind) and the buy-in comes back, so it's nearly free XP: 15 sittings (about 8 minutes, 440 XP) reach level 5 and
  3,700 Fishkas; 348 sittings (about 3 hours at a table, 10,440 XP) reach level 30 and 86,200 Fishkas in all.
- **Why:** only `ParticipantResult::Abandoned` is refused, and only `GameMove::ABANDON` (presence lost) or a
  per-turn timeout produce it:
  - `apps/api/app/Http/Controllers/Api/V1/Rooms/RoomController.php:160` — leave submits `resign`;
  - `apps/api/app/Gameplay/Actions/GameActionService.php:229` — a poker leave is `Loss`, unless it's an abandon;
  - `apps/api/app/Gameplay/Actions/GameActionService.php:334` — a chess clock timeout is deliberately not abandoned;
  - `apps/api/app/Gameplay/Lifecycle/GameFinisher.php:93-99` — `awardXp` pays any non-abandoned result older than
    `min_game_seconds`, measured from the room's start.
- **Evidence:** `qa47/log-api.txt`, `qa47/log-clock.txt`.
- **A decision is needed** on what counts as playing, since ADR 0018 also says poker counts one game per sitting.
  Options: leaving a two-player game pays nothing; for poker, require at least one played hand (or that the
  player acted). If the owner wants a loss by resignation or by chess clock to pay XP, the ADR and the admin help
  text must say so, but then the free poker XP still needs fixing.

### BUG-2 — Medium — On phones the level badge is not on the avatar on the profile

- **Steps:** open `/profile` or a public profile `/u/{name}` at a width under 640 px.
- **Expected:** the badge on the avatar's corner, like everywhere else (brief §1, §8).
- **Actual:** the badge is at the far right of the row. Measured gap between the avatar's right edge and the badge:
  **186 px at 390, 435 px at 639**; at 700 it sits on the avatar (−24 px).
- **Evidence:** `qa47/shots/bar-profile-top-390.png`, `qa47/shots/profile-badge-390.png`,
  `qa47/shots/profile-badge-639.png`.
- **Suspected:** `apps/web/src/features/account/components/ProfileView.tsx:18` — `flex flex-col` stretches the
  `relative inline-flex` wrapper from `Avatar.tsx` to full width, and the badge is anchored to its right edge.
  `self-start` on the wrapper (or `items-start` on the row) would keep it round the avatar.

### BUG-3 — Low — A popup stays open in the other tab after the rewards were collected

- **Steps:** have level-ups waiting, open two tabs, both show the popup, Collect in one.
- **Expected:** the other tab's popup closes (the code comment in `LevelUpDialog.tsx` says "collected here or in
  another tab").
- **Actual:** it stays open until the tab is focused with the level query stale (30 s staleTime). Its Collect is
  harmless: `collected` 0, and it closes.
- **Why:** `Levels::claim` (`apps/api/app/Platform/Levels/Levels.php:111-134`) sends no `level.updated`, so the
  other tab doesn't know.

### BUG-4 — Low — Exclusive emojis stay locked after levelling up in a game until the room reloads

- **Steps:** at level 9 in a game, reach level 10 (for example the daily bonus collected in another tab), reopen
  the picker.
- **Expected:** the Royal pack opens.
- **Actual:** all 8 still locked. The server accepts them; the picker reads a stale level.
- **Suspected:** `apps/web/src/features/reactions/components/RoomReactions.tsx:81` takes `myLevel` from the room's
  participants, which aren't refetched on `level.updated`. At the end of a game the room is refetched and it's
  right, so this needs a level reached mid-game.

### BUG-5 — Low — At the top level the screen-reader label names a level past the maximum

- **Steps:** a player at the top level (100); the progress bar on the dashboard or profile.
- **Expected:** a label for the top level.
- **Actual:** `aria-label="Progress to level 101"`.
- **Suspected:** `apps/web/src/features/levels/components/LevelProgress.tsx:46` always passes `level + 1`.

## Observations

- **O-1:** extreme admin curve values overflow. With values the form allows (`xp_base` / `xp_step` 1,000,000,
  `after_30_percent` 1000, `growth_percent` 100), `toNext` goes negative around level 70 and `totalFor` throws a
  `TypeError` (`LevelCurve.php:37`, `(int)` of a huge float). Needs ~10^17 XP to reach, so it can't happen in
  practice; a sanity limit in the settings would close it.
- **O-2 (not TASK-047):** on a private table with more than two seats the host's waiting table doesn't show a
  player who joins until the game starts or the page reloads: `RoomService::join` (`RoomService.php:77-93`)
  sends no event, and `useRoom` only polls without a live connection. So the joined player's badge doesn't appear
  for the host during that wait. This predates this task.
- **O-3 (read from code, not run live):** the reaction catalog is public-cached for an hour
  (`ReactionCatalogController.php:32`) and the popup's copy has `staleTime: Infinity`, so moving an emoji pack to
  another level in the admin takes a while to reach clients. The server still enforces the real level.

## What I could not test

- Leaderboards: none exist. Level display there is N/A.
- Levels above 30 reached only through `Levels::award` (XP injected), not by playing (level 30 needs ~260 games).
- ADR 0018's lock-order exception (a poker player leaving and a claim at the same instant) wasn't reproduced; the
  concurrency runs used `Levels::award` and HTTP claims.
- Real multi-person poker and Durak leaves at a full table were not played out (three-person Durak was started, then
  everyone left at once).
- Behaviour with Reverb down (polling fallback for the popup).
- Real phones and Safari: headless Chrome only. Confetti and animations were judged from screenshots.
- `make lint` / `make test-web` were not run (not requested).

## Data and cleanup

- **Accounts:** 66 throwaway accounts `qa47_*@example.test` (password `password`). None have open rooms. The
  throwaway admin `qa47_adm_121027621` had its `super_admin` role removed after the run.
- **player1 / player2:** not used. player1 is still level 1, 0 XP.
- **Reference data:** `level_rewards` back to 29 rows, 86,200 Fishkas, level 7 = 600 (items null). `levels.xp_game`
  back to 30. Bot levels unchanged: [55, 52, 49, 46, 43, 40, 37, 34, 31, 28, 25, 22, 19, 56, 53]. Audit rows from the
  admin test remain (`level_reward.updated`, `settings.updated`).
- **Browser:** the headless Chrome on :9334 was closed.
- Application code was not changed.

## Fix status (2026-11-08)

- **BUG-1** fixed: new setting `levels.min_game_moves` (default 3, admin-editable). A player with fewer moves of their
  own earns no XP; resigning, timing out and leaving don't count as moves (ADR 0018 updated, 1 new test). A poker sitting
  counts its own actions.
- **BUG-2** fixed by `w-fit` on the avatar wrapper (measured 96 px wide at 390).
- **BUG-3** fixed: a claim broadcasts `level.updated` with `pending: 0`, so other tabs close the popup (1 new test).
- **BUG-4** fixed: the emoji picker uses the signed-in player's current level.
- **BUG-5** fixed: the top level's bar is labelled "Top level".
