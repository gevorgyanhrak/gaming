# PM validation — TASK-056 · Home page redesign and game categories

- **Date:** 2026-11-08
- **Build:** `main` at 45b0ddf
  - categories 3909990
  - redesign bc9daf7
  - night room 82b7612
  - QA and design fixes 45b0ddf
- **Owner's request (2026-11-08):** the verbatim brief and revision in `docs/product/briefs/TASK-056-home-page-redesign.md`.
  - Brief: redesign the home page and the games carousel, set categories because more games are coming, change the
    home page's background colours and images.
  - Revision: "white is more, pls again redesign it, and you can set one big background image about casini, poker".
- **Inputs:**
  - `docs/team/PROGRESS.md` "Now".
  - QA `docs/qa/QA_REPORT_TASK056.md`: 145 checks, 127 pass; 1 High, 4 Medium, 2 Low, all reported fixed in 45b0ddf.
  - Design review, in the same QA report: NEEDS REVISION, 8 items, items 5–8 reported done (1–4 are BUG-1 to BUG-4).
  - `docs/design/HOME_REDESIGN.md`: colours, hero art and ivory and honey bands superseded by the note at its top.
    Posters, carousel, categories, states and strings still apply.
  - `docs/team/DECISIONS.md`, 2026-11-08 entry.
  - Code: `HomeBackdrop.tsx`, `LandingPage.tsx`, `DashboardPage.tsx`, `PublicLayout.tsx`, `GameLobby.tsx`, `index.css`,
    `GameCategoryResource.php`, `CreateGameCategory.php`, `GamesTable.php`, `GameSeeder.php`, migration
    `2026_11_08_210000_translate_game_descriptions.php`, the en/ru/hy `landing`, `lobby`, `carousel`, `dashboard` keys.
- **PM's own checks (read-only):**
  - **Tests:** web vitest **161 passed** (re-run). Backend tests not run. QA's 387 predate 45b0ddf; the reorder and
    "new category goes last" assertions added there were read, not run.
  - **Catalog API:** five games, each with a category (`board` 10: nardi, chess, checkers; `card` 20: durak, poker);
    names and descriptions translated.
  - **Live, guest, at 45b0ddf (headless):**
    - At 360 and 390 the pills scroll (`scrollWidth` 461 against 358 and 388) and the last pill comes fully into view.
    - No page overflow at 360, 390, 820 or 1440.
    - Hero centred at 360, 390 and 820.
    - `/?category=card` shows 2 cards with its pill selected; `/games` is still `club-room`.
    - **New, from the BUG-1 fix:** the first pill sits 1 px from the screen edge on phones (TASK-063).
  - **Screenshots of the current build (`scratchpad/shots56b/`):** guest at 1440, 820, 390 and 360, hy and ru at 390,
    `/dashboard` at 1440 and 390. The signed-in `/`, drawer and `/games` shots are covered by the Daily Bonus popup, so
    they show nothing of those surfaces. QA's pre-fix shots pass the drawer, and 45b0ddf doesn't touch it.
  - **Not re-tested after the fixes:** the active-games bar and verification banner over the lamps, the pill focus ring,
    the one-game shelf, the Filament reorder audit. All read in code only → TASK-065.

## Verdict: **ACCEPT WITH FOLLOW-UPS**

- **Request met end to end.** The home page is new and no longer reads as white. One big poker-room picture sits behind
  the whole page. The carousel is reworked into shelves by category. Categories come from the catalog API and are
  managed in the admin panel, so a new category needs no code. en/ru/hy. No real-money wording, and nothing about
  Fishkas changes.
- **No requirement of the owner's is unmet.** One item of the design's own list is only partly met: acceptance 7 (every
  control 44 px) still fails for the shared header controls and the language select. That is left to TASK-048 and is
  recorded below.
- **Should go in with the "PM validation → fix" step, before TASK-056 is marked done:** TASK-063, a side effect of the
  BUG-1 fix, one class. TASK-064 is small and can go with it.
- **Close-out still owed by the developer:**
  - PROGRESS step 4.
  - An `IMPLEMENTATION_REPORT.md` section (none exists for TASK-056).
  - `.claude/agents/gaming-agent.md` and `ARCHITECTURE.md` don't mention `game_categories`, `GameLobby` or `HomeBackdrop`.
  - One `make test-api` after 45b0ddf if it hasn't been run.
- **No QA or UI/UX re-run after the fixes.** I checked the live items above and the post-fix screenshots; the rest is
  listed under TASK-065.

## A. Brief and revision, item by item

### Owner's words

| Element | Status | Evidence |
|---|---|---|
| "redesig home page" | Met | New `/`: hero, games straight after it, ways to play below (`LandingPage.tsx`) |
| "games calusel" | Met | Native rail per shelf, arrows, "1–3 of 18", swipe, one Tab stop, no autoplay; 18 games tried (QA §2, §5) |
| "set categoryes by games" | Met | `game_categories`; each game has `category` in the catalog; pills and shelves (QA §1, §3, §4) |
| "becouse we need add games" | Met for the layout | An 18-game shelf, a new category with generic cover and crest and no code (QA §1). A new game itself still needs an engine, a seeder row and a poster or monogram; `GameResource` has no create page, by design |
| "change backgound colors, images" | Met | Night scope and the picture replace the browns; posters per game |
| "white is more" | Met | QA: pixels brighter than luma 200 are 1.7–4.9% of the top of `/`, mostly posters and aces (measured before the fixes). Screenshots agree: the ivory and honey bands are gone, the only `home-paper` use left is the "Coming soon" chip |
| "one big background image about casini, poker" | Met | `HomeBackdrop`: one fixed picture of a night poker room, table, lamps, cards, chips, dealer button, behind the whole page and visible at 1440, 820 and 390. It is drawn in code, not a photo → question 22 |

### Implementers' notes in the brief

| Element | Status | Evidence |
|---|---|---|
| Categories from the backend, admin-managed, not hardcoded | Met | API response; Filament Games → Categories, audited; `groupGames` builds shelves from the data |
| UI/UX agent changes colours and images | Met | `HOME_REDESIGN.md`, then the night room in `DESIGN_SYSTEM.md` "Home pages (TASK-056)" |
| Overlap with TASK-048: decide | Decided | Delivered ahead of 048 in the owner's order (PROGRESS). 048 can build on it |

### Standing owner rules

| Rule | Status | Evidence |
|---|---|---|
| Recreational only | Met | Nothing to buy, no shop, no VIP, no prizes on either page |
| Fishkas are free | Met | Only the existing header balance and the level card's reward line; no Fishka rule touched |
| No real-money wording | Met | `landing`, `lobby`, `carousel`, `dashboard` keys read in en/ru/hy, and a grep of the home components. The only mention is the eyebrow "Free to play · no real money", a disclaimer. Poker's "size your bets and take the pot" is reworded, on existing installs too (migration) |
| Chips in the picture | Met with a question | Table pieces only, no amounts or text. The owner's words don't name chips, though DECISIONS credits the owner → question 21 |

### `HOME_REDESIGN.md` acceptance (what still applies)

| Item | Status | Evidence |
|---|---|---|
| 1. Bands and frame | Superseded | Replaced by the night room; other pages keep their look (`/games` is `club-room`) |
| 2. CTAs, menu, drawer, tab bar, language | Met | Code; QA §6; screenshots |
| 3. No page overflow | Met | QA; re-checked live at 360, 390, 820, 1440 |
| 4. Shelves from API; generic cover; empty category hidden | Met | QA §1; a new category goes last (BUG-6 fix) |
| 5. Arrows, status line, one Tab stop, keys, whole focus ring | Met | QA §2 |
| 6. Reduced motion | Met | QA §5 |
| 7. Contrast; 44 px controls | **Partly** | Contrast met (QA; hero scrim raised after). 44 px: dashboard buttons fixed; menu 40, account 40, logo 32 and language select 29 px remain → TASK-048 |
| 8. No chip imagery | Superseded | The revision asked for a casino/poker picture; see question 21 |

### `/dashboard` (deliberate extension)

| Element | Status | Evidence |
|---|---|---|
| Same night room as `/` | Accepted | For a signed-in player the logo and the "Home" tab lead to `/dashboard`, so leaving it on the old look would mean the owner's complaint is fixed only for guests. `isHome` covers both; DECISIONS records it |
| Nothing lost | Met | Level card, three buttons, catalog, in-progress list and statistics are all still there, in the same order (old and new `DashboardPage` compared) |
| Readable | Met | QA §6–9; H1 is 3.02 at 1920 against 3 needed, thin. Active-games bar got a backing (not re-measured) |

### QA and design fixes, checked in code

| Item | Status | Evidence |
|---|---|---|
| BUG-1 pills don't scroll on phones | Fixed; live | `min-w-0`; scrolls at 360 and 390, last pill reachable. Side effect: TASK-063 |
| BUG-2 hero off-centre | Fixed; live | `mx-auto lg:mx-0`; centre 410 of 410 at 820 |
| BUG-3 active-games bar over the lamps | Fixed in code | Night scope and `bg-black/90` on the wrapper. Verification banner not wrapped; it passed QA → TASK-065 |
| BUG-4 pill focus ring | Fixed in code | `-my-1.5 py-1.5`, `lg:-mx-2 lg:px-2` |
| BUG-5 reorder not audited | Fixed in code | `afterReordering` on both tables, `game_category.reordered` covered by a test (read); the `admin` guard and `record()` signature match |
| BUG-6 new category first | Fixed in code | `max(sort_order) + 10` on create; test asserts 30 (read) |
| BUG-7 dashboard buttons | Fixed in code | `min-h-11` on the three; the shared header stays → TASK-048 |
| Design 5 hero scrim | Fixed in code | 0.58 → 0.66 |
| Design 6 corner vignette | Fixed; screenshots | Stops 0.45 / 0.9. Near aces and stack are dimmer at 1440; 1920 and wider not seen after the fix |
| Design 7 cover tile cap | Fixed in code | `lg:max-w-[50%]`; a one-game shelf not seen after the fix |
| Design 8 dashboard buttons on one row | Fixed; screenshot | `max-w-4xl`, one row at 1440 with a long name |
| Phone scrolling risk | Done in code | No `backdrop-blur` on home bars, `h-lvh` picture. Real phones not tried → TASK-065 |
| Maintenance mode | Fixed in code | `isHome` is false during maintenance, so the old look stays |

## B. Decisions taken without the owner

| Decision | PM judgement | Owner? |
|---|---|---|
| **`/dashboard` gets the night room too** | Right: it is the signed-in home. `/games` keeps the dark brown look until TASK-048 | Question 23 |
| **Chip stacks in the picture** | Keep: they are table pieces, as at the poker table, with no amounts. But the 2026-10-28 rule and the design's item 8 excluded them, and the owner never said chips | **Question 21** |
| **The picture is drawn in code** | Fine for now: no files, no licence, sharp at every size. The owner said "image", which may mean a photo | **Question 22** |
| **New `game_categories` table** | Inside the brief, which requires backend categories, and the owner's "do everything necessary". No Fishka or rules effect | No |
| **Autoplay and dots removed from the carousel** | Right: 30 dots don't work, and auto-rotation would need a pause control. It is a rework the owner asked for, not a lost feature | No |
| **Poker description reworded in English** | Right: "size your bets and take the pot" is too close to wagering. Admin text is only changed if it still has those words | No |

## C. Follow-ups

| Item | Where |
|---|---|
| First pill loses its gutter on phones (the rail has `scroll-px-4`, the pills row doesn't); text scrolling under the home header shows through since the blur went | TASK-063 (new, P3). Fix at TASK-056 close-out |
| A category with slug `_other` would merge with uncategorised games (QA O-1) | TASK-064 (new, P3) |
| Look again at what wasn't re-tested, plus real phones | TASK-065 (new, P3) |
| Header controls and language select under 44 px (BUG-7 remainder) | TASK-048 |
| Armenian wording by a native speaker | Decisions needed 4, extended |
| "Play chips" in Poker's description and rules, against Fishkas | Decisions needed 5, updated |
| Chips in the picture; drawn or photo; the dashboard and `/games` | Owner questions 21–23 |
| Screen readers (roles and live regions read from code only) | TASK-065 |
