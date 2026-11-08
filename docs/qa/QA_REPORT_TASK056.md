# QA report — TASK-056 · New home page and game categories (night room revision)

- **Date:** 2026-11-08.
- **Build:** `main` at 82b7612 (categories 3909990, redesign bc9daf7, night room 82b7612). Web on :5175, API and admin on :8000.
- **Method:**
  - **Browser:** headless Chrome over CDP on port 9337, own profile, at 360, 390, 820, 1023/1024, 1280, 1440, 1920 and 2560 px, in en, ru and hy.
  - **Contrast:** measured from rendered pixels. Text is hidden, the screen is captured, and the brightest pixel behind each text box is compared with its colour. This is repeated while scrolling, because the picture is fixed and shows through. The hero was swept every 25 px.
  - **18+ games:** `/api/v1/games` intercepted in the browser (18 on the Board shelf), nothing written to the DB.
  - **Admin:** driven through the Filament UI as `player1@example.test`, with the catalog checked from a separate guest browser context.
  - **Backend:** `make test-api` run once.
- **Evidence:** `/private/tmp/claude-501/-Users-hrak/dcf481df-22d2-4f78-a522-b2a3b7f2218b/scratchpad/`, written below as `qa56/`.
  - Screenshots: `qa56/qa56shots/` (prefixes `a-` guest sweep, `b-` signed in, `c-` shelf and keyboard, `d-` admin).
  - Scripts: `qa56/tools/qa56lib.mjs`, `qa56a…qa56i.mjs`. Log: `qa56/qa56.log`.

## Summary

| Area | Result |
|---|---|
| 1. Admin: categories create, rename, reorder, delete; assign a game; catalog follows | PASS, except **BUG-5 (Medium)** reorder not audited and **BUG-6 (Low)** a new category goes first |
| 2. 18+ games on a shelf: arrows, "1–3 of 18", one Tab stop, Left/Right/Home/End, focus ring | **PASS** at 1440, 820 and 390 |
| 3. Category filter pills | **BUG-1 (High)** the row doesn't scroll on phones; **BUG-4 (Medium)** focus ring clipped |
| 4. `?category=`, Back, refresh, unknown slug | **PASS** |
| 5. Reduced motion | **PASS** |
| 6. Drawer, tab bar, language switcher, FishkaBalance, UserMenu over the picture | **PASS** |
| 7. Verification banner and active-games bar on the see-through frame | Banner PASS. **BUG-3 (Medium)** the active-games bar is unreadable over the lamps |
| 8. View transition card → game page; `/games` stays dark | **PASS** |
| 9. Dashboard Level card and in-progress list | **PASS** |
| 10. Layout | **BUG-2 (Medium)** hero text off-centre at 640–1023; **BUG-7 (Low)** controls under 44 px on the dashboard |
| 11. Text and art, no real-money wording | **PASS** |
| 12. Fresh install (migrations, `GameSeeder`, tests) | **PASS** (387 tests) |

**Totals:** 145 checks: 127 PASS, 18 FAIL. The 18 failures come from 7 bugs (1 High, 4 Medium, 2 Low) and one console error (O-3). There are 7 observations in all, O-3 included.

Results where my script was wrong are left out of the count. They were reported in `qa56/qa56.log` and then corrected:
- `sr-only` radio inputs counted as small controls;
- text boxes running under the sticky header;
- a 1 px frame edge at x=389;
- colour transitions counted as motion.

## 1. Admin: categories

| Check | Actual | Result |
|---|---|---|
| Duplicate slug `board` | Refused with a validation message; no row added | PASS |
| Create "QA Dice" / `qa-dice` (en, ru, hy) | Row created with `sort_order` 0; `game_category.created` audited | PASS |
| Category with no games | No pill, no shelf on `/` | PASS |
| Game form select | Options "No category, Board games, Card games, QA Dice": translated names, no raw JSON | PASS |
| Assign Chess to it | Saved; `game.updated` audited | PASS |
| Catalog after assigning | New pill "QA Dice 1"; Board games drops to 2 | PASS |
| Generic cover, crest | 3 gold arches on the slug's ground; crest shows "Q" (`d-home-new-category.png`) | PASS |
| Position of a new category | First on the page, ahead of Board games | **BUG-6** |
| Rename | Follows on next load; ru name returned by the API; `game_category.updated` audited | PASS |
| Reorder | Order changes; API `sort_order` updates; shelves follow | PASS |
| Reorder audited | Audit rows 70 → 70, while `updated_at` moved | **BUG-5** |
| Delete | Modal says "Its games stay in the catalog, without a category."; `game_category.deleted` audited | PASS |
| After delete | Chess `game_category_id` null; last shelf and last pill "More games"; `?category=_other` opens its grid; game form shows "No category" | PASS |
| Restore | Chess back in Board through the form; `sort_order` reset to 10 / 20 | PASS |

Native drag does not fire in headless Chrome. I called `reorderTable` on the Livewire component, which is the call the drag handle makes (`vendor/filament/tables/resources/js/components/table.js:138`). The reorder toggle and the handles were on screen.

## 2. Shelf with 18 games

| Check | 1440 | 820 | 390 |
|---|---|---|---|
| Status line at start | "1–3 of 18" (cover tile) | "1–2 of 18" | "1 of 18" |
| After one Next | "4–7 of 18" | "3–4 of 18" | "2 of 18" |
| Prev dimmed at start, Next dimmed at end | PASS | PASS | PASS |
| Reaches the end | 4 clicks, "15–18 of 18" | 8 clicks, "17–18 of 18" | 17 clicks, "18 of 18" |
| Tab stops in the rail (`tabindex=0`) | 1 | 1 | 1 |
| Home, Right, Left, End | PASS; last card fully in view | PASS | PASS |
| Focus ring whole on all four sides (first and last card) | PASS | PASS | PASS |

Tab order from the pills: Previous, Next, the card, See all, then the next shelf, so one card per shelf.
All controls in the shelves are at least 44 px tall at 1440.

## 3. Filter pills

| Check | Actual | Result |
|---|---|---|
| Last pill reachable at 360 and 390, en/ru/hy | Row can't scroll: `scrollLeft` stays 0 | **BUG-1** (6 checks) |
| Last pill at 820 and 1024 | On screen | PASS (6 checks) |
| Focus ring on a pill | Cut off at top and bottom at all widths, and at the left at 1024 and up | **BUG-4** (3 checks) |

## 4. `?category=`

| Check | Actual | Result |
|---|---|---|
| `/?category=card` | Grid of 2 games; "Card games" pill selected | PASS |
| Refresh | Kept | PASS |
| Card → game page → Back | Back on `?category=card` with the grid | PASS |
| `/?category=doesnotexist` | 5 games; "All games" selected | PASS |
| Back after clicking a pill | Leaves `/`: the pill uses `replace: true`, as designed | Note |

## 5. Reduced motion

| Check | Actual | Result |
|---|---|---|
| Normal motion, hover | `translate: 0 -4px`, art `scale: 1.05` | PASS |
| Reduced motion, hover | `translate: none`, `scale: none` | PASS |
| Next arrow | Moves 1124 px within one frame | PASS |
| Idle page | No running animations | PASS |
| Page `scroll-behavior` | `auto` | PASS |

## 6–9. Over the picture, and other pages

| Check | Actual | Result |
|---|---|---|
| Header, FishkaBalance, UserMenu | Header text passes in every sweep on the 85% header; the menu is solid (`b-usermenu-1440.png`) | PASS |
| Drawer | Solid `#0a1a15` at 1440 and 390 (`b-drawer-390.png`) | PASS |
| Tab bar | Solid enough at 95%; doesn't cover the footer at page end | PASS |
| Language switcher | Readable on the 75% footer | PASS |
| Verification banner | Measured at 1440: text 8.59, "Verify now" 5.13; also passes at 1920 and 390 | PASS |
| Active-games bar | See BUG-3 | **BUG-3** |
| Level card | Passes at 1440, 1920, 820, 390 (`b-dash-*`) | PASS |
| Dashboard H1 (gold gradient), worst real row | 3.02 at 1920 (large text needs 3) | PASS, thin |
| In-progress list | "Games in progress" card, solid surface (`b-dash-1440-inprogress.png`) | PASS |
| Card → game page view transition | `startViewTransition` ran and finished; no console warnings; arrives on `/games/chess` with the old dark look | PASS |
| `/games` | `club-room`, no picture, new carousel; worst contrast 5.32 (`c-games-1440.png`) | PASS |

## 10–12. Layout, text, fresh install

| Check | Actual | Result |
|---|---|---|
| Hero centred at 390 | Centre 195 of 195 | PASS |
| Hero centred at 640, 820, 1023 | Centre 272 in all three (page centre 320, 410, 512) | **BUG-2** |
| Controls at 44 px on `/dashboard` at 390 | 7 under | **BUG-7** |
| Real-money wording in `landing`, `lobby`, `carousel`, `dashboard`, `catalog`, `footer` keys (en, ru, hy) and API descriptions | Only the eyebrow "Free to play · no real money", which is a disclaimer. Poker's description says "play chips" | PASS |
| Art | Cards, chips and dealer button as table pieces, as the owner asked; no money, cash or wager text | PASS |
| Fresh install | `2026_11_08_200000` creates both categories before seeding; `DatabaseSeeder` runs `GameSeeder`, which assigns each game by slug; on an existing install the migration assigns them | PASS |
| `make test-api` | 387 passed, including "seeds the launch games into their categories" and the delete and translation cases | PASS |

## Contrast over the picture

Worst value across all scroll positions, from rendered pixels. "Needs" is 4.5, or 3 for large text.

| Page and size | Worst text | Ratio | Result |
|---|---|---|---|
| `/` 1440×900, 1280×720 | Hero subtitle at the lamp cone | 4.63 | PASS, thin |
| `/` 1024×768 | Hero subtitle | 4.85 | PASS |
| `/` 2560×1440 | Hero subtitle | 5.09 | PASS |
| `/` 820×1180 (hy) | Hero subtitle | 4.94 | PASS |
| `/` 390 and 360, en/ru/hy | PLAY label (solid button) | 5.25+ | PASS |
| Header, footer, games band, ways band | Every text on the 75–85% bands, all sizes above | above 4.5 | PASS |
| `/dashboard` 1440 and 820 with a waiting bar; 1440, 1920 and 390 with the verification banner | Picture-related text | above 4.5 | PASS |
| `/dashboard` 1920 with a waiting game | "Return to the game" on the lamp outline | **1.61** | **BUG-3** |
| `/dashboard` 390 with a waiting game | Close button "×" on the lamp rim | **1.30** | **BUG-3** |
| `/dashboard` 1440 with a running game | "Come back within 15 seconds…" on lamp glow | **4.21** | **BUG-3** |

## Bugs

### BUG-1 — High — On phones the category pills can't be scrolled; the last ones are cut off or out of reach

- **Steps:** open `/` at 390×844 (any language). Try to swipe the pills row sideways.
- **Expected:** the row scrolls (design section 5: "pills scroll sideways").
- **Actual:** it doesn't scroll. The `<fieldset>` around it is `min-width: min-content`, so it grows to the width of all the pills (429 px en, 499 px ru, 511 px hy, in a 390 px screen) and the row never overflows. The frame's `overflow-clip` hides the rest, which is why "no page overflow" still passed.
  - The third pill shows only "Card gam" in en and a sliver "Кар" in ru (`g-ru-390-y100.png`).
  - Anything past it is unreachable, so a fourth category (the point of this task) would be completely off-screen and untappable.
- **Measured:** `rowClient = rowScroll` (461 / 531 / 543), `canScrollBy = 0` at 360 and 390 in all three languages.
- **Where:** `apps/web/src/features/catalog/components/GameLobby.tsx:129`.
- **Fix:** `<fieldset className="min-w-0">`. Reference: [HTML rendering rules for fieldset (`min-inline-size: min-content`)](https://html.spec.whatwg.org/multipage/rendering.html#the-fieldset-and-legend-elements), [MDN `<fieldset>`](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/fieldset).

### BUG-2 — Medium — The hero text sits left of centre between 640 and 1023 px

- **Steps:** open `/` at 820×1180.
- **Expected:** one centred column (design section 5).
- **Actual:** the text is centred inside a 463 px block pinned to the left. The block's centre is at x=272 at every width from 640 to 1023, against page centres of 320, 410 and 512 (`a-guest-820-top.png`). The Container is `flex`, so the `max-w-2xl` block shrinks to its content and sits at the start.
- **Where:** `apps/web/src/pages/LandingPage.tsx:25-26`.
- **Fix:** `mx-auto lg:mx-0` on the block at line 26. Reference: [MDN, aligning items in a flex container: auto margins](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_flexible_box_layout/Aligning_items_in_a_flex_container#using_auto_margins_for_main_axis_alignment).

### BUG-3 — Medium — The active-games bar is unreadable where it crosses a lamp

- **Steps:** have a waiting or running game, then open `/dashboard` at 1920×1080 or 390×844.
- **Expected:** at least 4.5:1, as the rest of the page.
- **Actual:** the bar is only `bg-accent/10` on the see-through frame, and it lands exactly on the lamp shades. The text is not in the night scope, so it uses the older muted colour.
  - 1920×1080: "Return to the game" is 1.61:1 on the brass outline (`b-dash-1920-active.png`).
  - 390×844: the close "×" is 1.30:1 on the lamp rim (`b-dash-390-active.png`).
  - 1440×900 with a running game: "Come back within 15 seconds…" is 4.21:1.
- **Where:** `apps/web/src/features/play/components/ActiveGamesBar.tsx:38`, rendered from `apps/web/src/app/layouts/PublicLayout.tsx:155-157`.
- **Fix:** on home pages give the two status strips a backing like the header's. Wrap lines 155–157 in `<div className={isHome ? 'home-scope-night bg-black/70' : ''}>`, which also applies the night tokens. The verification banner passes today but has the same exposure. Reference: [WCAG 2.2, 1.4.3 Contrast (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).

### BUG-4 — Medium — The focus ring on the filter pills is clipped

- **Steps:** Tab to the pills at any width.
- **Expected:** a whole ring, as on the cards (the rail's ring is kept whole in design section 7).
- **Actual:** the row is `overflow-x-auto`, which clips vertically too, and it has no vertical padding. The top and bottom of the ring are cut, and the left too at 1024 and up. Only the side arcs show (`c-1440-focus-pill.png`). The rail below handles this correctly with `py-2`.
- **Where:** `GameLobby.tsx:132`.
- **Fix:** `py-1 -my-1` on the row, plus `lg:px-1 lg:-mx-1` for the left edge at wide widths. Reference: [WCAG 2.2, 2.4.7 Focus Visible](https://www.w3.org/WAI/WCAG22/Understanding/focus-visible.html).

### BUG-5 — Medium — Reordering categories isn't audited

- **Steps:** Admin → Games → Categories → Reorder → move a row. Look at the Audit log.
- **Expected:** an audit entry. "Admin actions are audited" is a project non-negotiable (`CLAUDE.md`), and the resource's own docblock says "Changes are audited".
- **Actual:** no entry. Audit rows stayed at 70 while `sort_order` and `updated_at` changed, and shelves on the public page moved. Filament's reorder is a bulk `UPDATE … CASE`, so `AdminChangeObserver` never runs (`vendor/filament/tables/src/Concerns/CanReorderRecords.php`, `reorderTable`).
- **Where:** `apps/api/app/Filament/Resources/GameCategories/GameCategoryResource.php:50`. `apps/api/app/Filament/Resources/Games/Tables/GamesTable.php:17` has the same gap and predates this task.
- **Fix:** add `->afterReordering(fn (array $order) => …)` on the table and record one `game_category.reordered` entry through `AuditLogger`, with a test. Reference: `afterReordering` in `vendor/filament/tables/src/Table/Concerns/CanReorderRecords.php:57`; the [Filament tables docs](https://filamentphp.com/docs/tables) cover reordering.

### BUG-6 — Low — A new category appears first on the home page

- **Steps:** create a category and give it a game.
- **Expected:** it joins at the end (or the admin picks a place).
- **Actual:** the form has no `sort_order`, the column defaults to 0, and Board and Card are 10 and 20. The new shelf and pill go ahead of everything until someone reorders.
- **Where:** `GameCategoryResource.php:38-44`; `database/migrations/2026_11_08_200000_create_game_categories_table.php` (default 0).
- **Fix:** on `creating`, set `sort_order` to the current maximum plus 10. Reference: [Eloquent model events](https://laravel.com/docs/eloquent#events).

### BUG-7 — Low — Several controls are under 44 px on `/dashboard` at 390

- **Measured:** Choose a game, Profile and Settings 42 px; Menu 40; account menu 40; logo link 32; language select 29.
- **Expected:** acceptance item 7.
- **Where:** `apps/web/src/pages/DashboardPage.tsx:49-57` (the three buttons are on the redesigned page). The header controls and the select are older shared parts; `PublicLayout.tsx:56` for the icon button, `shared/i18n/LanguageSwitcher.tsx:35` for the select.
- **Fix:** `min-h-11` on the three dashboard buttons. Leave the header and select for TASK-048 if the owner prefers. Reference: [WCAG 2.2, 2.5.8 Target Size (Minimum)](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html) is 24 px; 44 px is this project's own acceptance rule.

## Observations

- **O-1:** `_other` is allowed as a category slug (`alphaDash`), and it is the internal key for "no category" (`GameLobby.tsx:20`). A category with that slug would merge with the uncategorised games. Code reading only.
- **O-2:** A shelf with one game gives the cover tile 823 px at 1440, next to one normal card with empty space under Play (`d-home-new-category.png`). It follows the spec, but it looks oversized. See the design list.
- **O-3:** Two uncaught `InvalidStateError: Transition was aborted… Document hidden` appeared in one signed-in run, and in no other run. They come from a view transition while the document is hidden. It looks like a headless artifact, with nothing visible to a player.
- **O-4:** The avatar's level badge is 2.59–3.80:1 (white "1" on gold). It comes from TASK-047, not this change.
- **O-5:** `PROGRESS.md` says 389 tests; this run has 387 passing and no failures.
- **O-6:** Console warning `No HydrateFallback element…` on load; not new.
- **O-7:** With a long username, the dashboard's three buttons wrap, leaving Settings alone on a second row at 1440 and 1920. Cosmetic.

## Design review

**Verdict: NEEDS REVISION**

The night room does what the owner asked. The page no longer reads as white: pixels brighter than luma 200 are 1.7–4.9% of the screen at the top of `/`, and those are mostly the game posters, aces and lamp rims. The picture is rich and holds together at 1440, 1920, 2560, 820 and 390, the table stays in view at every crop, and the header and all body text are readable. The revision is held back by three things: the filter row on phones, the off-centre hero on tablets, and the bar over the lamps.

Fixes, in priority order:

1. **Phones, filter row (BUG-1):** `min-w-0` on the fieldset. Without it the category feature doesn't work on phones as soon as a shop has more categories.
2. **Tablets, hero (BUG-2):** centre the block. At 820 it looks like a layout mistake.
3. **Status strips (BUG-3):** add a dark backing on home pages, as the header has. Lamps exactly behind the bar are the worst case for readability.
4. **Pill focus (BUG-4):** add the vertical room, same as the rail.
5. **Hero subtitle margin:** 4.63 at 1440 and 1280 is thin for fixed art. Raise the hero's middle stop from `0.58` to about `0.66` (`apps/web/src/styles/index.css:173`), so lamp-glow changes can't take it below 4.5.
6. **Visible seam at 1920 and wider:** the near aces and the red chip stack are cut by the frame edge, bright outside and dim inside (`a-guest-2560-top.png`, `a-guest-1920-top.png`). A stronger vignette at the bottom corners (`#hb-vignette`, `HomeBackdrop.tsx:132`) makes it read as one room. These are the brightest things left on the page.
7. **Cover tile on small shelves (O-2):** cap it at about half the row, so a one-game shelf doesn't turn into a billboard.
8. **Dashboard buttons (BUG-7, O-7):** `min-h-11`, and keep the group on one row at 1024 and up by widening `max-w-3xl`.

**Scroll smoothness on phones (risk, not measured on a device):**
- The picture is one fixed SVG of 274 nodes with 8 gradients and patterns, repainted only on resize, so it should scroll on the compositor.
- A 6× throttled headless probe saw no difference with or without it (16 ms average, 18–19 ms worst). Headless uses software raster and doesn't exercise a phone GPU, so this says little.
- The realistic risks are the `backdrop-blur` on the header and tab bar (`PublicLayout.tsx:99, 222`), which re-sample the picture every frame, and a rescale of the sliced picture when the address bar collapses, because the picture is `fixed inset-0 size-full`.
- Please try it on a mid-range Android and an iPhone. If it stutters, drop the blur on home pages (the bars are 85–95% opaque anyway) and size the SVG to the large viewport (`h-lvh`). Reference: [MDN `backdrop-filter`](https://developer.mozilla.org/en-US/docs/Web/CSS/backdrop-filter), [MDN viewport units](https://developer.mozilla.org/en-US/docs/Web/CSS/length#relative_length_units_based_on_viewport).

## Not tested

- Real phones and tablets (smoothness, address-bar behaviour, iOS Safari).
- Screen readers (roles and live regions read from code only).
- Native drag in the admin (the same Livewire call was used instead).
- Maintenance mode on `/`: `MaintenanceScreen` has no backing and would sit directly on the picture. Code reading only.
- Armenian wording by a native speaker.

## Data and cleanup

- **Catalog restored:** categories `board` 10 and `card` 20; all five games in their original categories; `qa-dice` deleted. Git is clean apart from this report.
- **Audit log:** 8 new rows (62 → 70) from my admin runs, under player1 (`game_category.*`, `game.updated`). They were left in place as a true record.
- **Throwaway accounts:** ids 121–125 (`qa56*@example.test`) remain. Their test rooms are finished or aborted; none is open.
- **Backend tests:** `make test-api` ran once.

## Fix status (2026-11-08)

All seven bugs and design items 5–8 are fixed: BUG-1 `min-w-0`; BUG-2 centred block; BUG-3 dark night backing behind the active-games bar on home pages;
BUG-4 room for the pill focus ring; BUG-5 reorder audited for categories and for games (the older gap); BUG-6 a new category goes last; BUG-7 dashboard buttons
44 px (the shared header and language select stay as they were). Also: subtitle scrim 0.66, stronger corner vignette, cover tile capped at half the row, no
`backdrop-blur` on home bars and `h-lvh` for the picture, and maintenance mode keeps the old look instead of sitting on the picture.

