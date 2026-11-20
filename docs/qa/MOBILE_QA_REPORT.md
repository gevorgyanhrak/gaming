# Mobile QA report — whole platform, 320–1440 px (2026-11-18 … 2026-11-20)

Lead: gaming-agent. Agents: Mobile QA Tester (ui-ux-agent), UI/UX Developer, Game UI Developer, Code Quality Reviewer
(gaming-agent subagents). Every bug below was seen in a real browser against the real local stack (API + MySQL +
Redis + Reverb), signed in as the local test account; none is hypothetical.

**How it was tested**
- Playwright suite `apps/web/e2e` (how to run: `apps/web/e2e/README.md`):
  - `routes.spec.ts` — 9 signed-out routes in en, ru and hy, 23 signed-in routes; each at 320×667, 344×740,
    360×740, 375×812, 390×844, 414×932, 430×932, 768×1024, 1024×768, 1280×800, 1440×900.
  - `games.spec.ts` — all 11 games against the AI, the table at all 11 sizes, then real moves from the server's
    `legal_actions` through the real action endpoint.
  - `casino.spec.ts` — the casino shelf at 11 sizes; each of the 13 house slots at 320, 375, 812×375 (phone
    sideways), 768 and 1440, one real server-dealt spin at 375, and the way back to the casino.
  - `admin.spec.ts` — 14 Filament admin pages at 320, 375 and 1440 (signed in as the dev QA admin).
  - Each check: page wider than the screen; elements past the screen edge (outside scroll containers); tap targets
    under 24 px (WCAG 2.5.8; inline text links exempt); console errors. Screenshots per iteration / area / route /
    viewport in `apps/web/e2e/screenshots/iteration-N/` (git-ignored, local).
- A visual review of every screenshot at 320/360/375 (+768/1440) and live checks of menus, dialogs, search, filters
  and popups by the Mobile QA Tester; the developers re-checked each fix live at 320, 375 and 1440.

Severity: P0 unusable · P1 major broken · P2 significant · P3 minor.

## Bugs

| id | route | component | viewport | actual (before) | expected | sev | fix | status |
|---|---|---|---|---|---|---|---|---|
| H-1 | every signed-in page | `app/layouts/PublicLayout.tsx` header nav | 320 (+ ru 375–399) | Header 327 px wide in 320: the account button ran past the right edge; whole page scrolled sideways | Header fits | P1 | Sound toggle moves into the ☰ menu under 360 px (always there too); logo hides under 400 px when signed in | Fixed (9908aad) |
| V-1 | any page (level-up popup) | `features/levels/components/LevelUpDialog.tsx` | all | "Later" invisible: light text on the ivory footer, contrast ≈ 1.05:1 | Readable secondary action | P1 | Dark footer (`bg-night-800`) | Fixed (9908aad) |
| V-2 | every signed-in page | tab bar (`tabLink`) | 320 hy | Tab labels touching, first at the screen edge | Separated labels | P2 | `min-w-0`, truncating `TabLabel`, 11 px labels under 375 | Fixed (9908aad) |
| V-3 | home, dashboard, every game page | `features/rankings/RankingList.tsx` | 320–375 | Player names shrank to 0–2 letters | Readable names | P2 | Container query: score under the name in narrow lists | Fixed (9908aad) |
| V-4 | `/ranks`, home | `features/levels/components/RankLadder.tsx` | 320–375 hy/ru | Tier names ran together; captions broke mid-word | Whole words | P2 | 3 + 2 grid, level ranges + one legend line, 12 px captions | Fixed (9908aad) |
| V-5 | `/leaderboards` | `Leaderboard.tsx` period `Segmented` | 320 | "This month" wrapped inside the pill | One row | P2 | `fill`, short labels (Week / Month) | Fixed (9908aad) |
| V-6 | home, dashboard | `features/catalog/components/CategoryBanners.tsx` | 320–414 | Art covered two-line titles; one title clipped | Whole titles | P2 | Title below the art, wraps | Fixed (9908aad) |
| V-7 | any signed-in page | current-user query, guards | all | `/me` 429 → skeletons forever, no message | An error with retry | P2 | Retry-After honoured; `AccountUnavailable` alert with Try again | Fixed (9908aad) |
| V-8 | home, dashboard | `features/wins/LiveBigWins.tsx` | 320–375 | Game name "P…", time on 3 lines | Readable rows | P2 | Game · time on its own line | Fixed (9908aad) |
| V-9 | daily bonus popup | `DailyBonusDialog.tsx` | 320 | Day amounts "1,0…" | Readable amounts | P2 | Compact amounts on phones | Fixed (9908aad) |
| V-10 | `/games/blot` (multi-mode games) | `features/play/components/PlayPanel.tsx` | 320–430 | Mode names cut ("Ստանդ…") | Whole names | P2 | Columns as wide as their longest word; names wrap | Fixed (3a2f51a) |
| V-11 | every signed-in page | `features/search/SiteSearch.tsx` | 768–820 | Search field 87 px ("Որ") | Usable field or icon | P2 | Field from 1024 px, icon below | Fixed (9908aad) |
| V-12 | `/casino/slots/jungle-slot` | `play/jungle-slot/*`, `pages/SlotPage.tsx` | 320–430 upright | Game 190 px tall in a 668 px frame, 15–35 px buttons, English-only hint under the tab bar | Usable upright | P2 | Portrait layout of the same art (37–49 px buttons), translated hint, frame ends above the tab bar | Fixed (3a2f51a) |
| V-13 | signed-out pages, ru | header guest branch | 320 ru | Page 323 px wide ("Регистрация") | Fits | P2 | Same as H-1 | Fixed (9908aad) |
| V-14 | `/games` | `GameLobby.tsx` selects | 320 | Sort value clipped | Whole value | P3 | Selects stack when narrow | Fixed (9908aad) |
| V-15 | `/games/:slug` | `features/play/components/GroupPicker.tsx` | phones; 1440 | Third chip cut with no scroll hint; names truncated on desktop | Scroll affordance; whole names | P3 | Edge fade on phones; 3 + 2 grid from sm | Fixed (3a2f51a) |
| V-16 | dashboard, profile, history, wallet | dates | all, hy | English dates in the Armenian UI (Chrome has no hy date data) | Armenian dates | P3 | `shared/lib/dates.ts` (CLDR patterns when the browser lacks hy) | Fixed (9908aad, 3a2f51a) |
| V-17 | footer, ☰, settings | `languageName` | all | "English / русский / Armenian" | Endonyms | P3 | Fixed list: English / Русский / Հայերեն | Fixed (9908aad) |
| V-18 | `/wallet` | `pages/WalletPage.tsx` | 320 | Tall balance card with an empty middle | Compact card | P3 | Icon + number row, number scales | Fixed (9908aad) |
| V-19 | ☰ menu | `PublicLayout.tsx` | phones | No Casino link (desktop header has one) | Same sections | P3 | Shared `useSections` | Fixed (9908aad) |
| C-1 | `/casino/slots/:slug` | `pages/SlotPage.tsx` | all | "Back to the casino" went to the home page | The casino shelf | P3 | Links to `/games?category=casino` | Fixed (9908aad) |
| G-1 | `/rooms/:id` | `pages/RoomPage.tsx` footer | all | "Dashboard" link 20 px tall | ≥ 24 px target | P3 | `min-h-11` | Fixed (3a2f51a) |
| G-2 | Ludo, Rummy rooms | `games/{ludo,rummy}/*Board.tsx` SeatTap | 320+ | Seat tap targets 16–20 px tall | ≥ 24 px | P2 | SeatTap fills the plate (44 px) | Fixed (3a2f51a) |
| G-3 | Nardi room | `games/nardi/NardiBoard.tsx` | 320 only | Points 21 px wide (24 from 344) | ≥ 24 px | P3 | — | Won't fix: 12 points × 24 px need the whole 288 px board width including frame and bar; points are 160 px tall, so easy to hit |
| G-4 | Poker room | `games/poker/Controls.tsx` | 320–344 | MORE chip ran outside the bet row | Inside the row | P2 | 44 px chips under 360 | Fixed (3a2f51a) |
| G-5 | Poker room | `games/poker/PokerBoard.tsx` | 320–344 | Table-info card under your seat plate | No overlap | P3 | Values under labels under 360 | Fixed (3a2f51a) |
| G-6 | every room | `features/play/components/TableBar.tsx` | 768+ | Game name cut ("Շախմ…") by the group badge | Whole name | P3 | Name keeps half the row | Fixed (3a2f51a) |
| L-1 | `/leaderboards` | leaderboard row name link | 320–430 | Player link 20 px tall (`a.min-w-0.flex-1.truncate`) | ≥ 24 px target | P3 | — | Open (found in iteration 2, QA stopped) |
| A-1 | `/admin/*` | Filament tables (vendor) | 320 | Table row links (Edit / View / Delete) and sort headers are 20 px tall | ≥ 24 px | P3 | — | Not fixed: Filament's own components, staff-only screens; no page overflows |

## Iterations

| iteration | what ran | result |
|---|---|---|
| 1 | routes ×11 sizes, games ×11, slots ×13, admin; visual review | 1 measured bug (H-1) + 19 visual (V-1…V-19) + 7 game/casino (C-1, G-1…G-6) + A-1 |
| 1 → fixes | UI/UX Developer (H-1, V-1…V-9, V-11, V-13, V-14, V-16…V-19), Game UI Developer (V-10, V-12, V-15, V-16, G-1…G-6), Code Quality Reviewer on both | 2 rounds approved with small changes; 9908aad, 3a2f51a |
| 2 (partial) | admin, casino shelf, 13 slots, 11 games, signed-out routes in en/ru/hy at all 11 sizes, part of the signed-in routes; stopped by the owner (2026-11-20) before the rest | **No page wider than the screen and nothing past the edges anywhere it ran.** Remaining flags: the leaderboard's player link is 20 px tall (P3, L-1 below); Filament admin links (A-1). 7 game tests (checkers, durak, blot, domino, ludo, pool, rummy) failed within 2 s when the run was stopped, before Playwright printed why — cause not investigated; the same tests passed in iteration 1's rerun (8f6f9b6) |

## Status when QA was stopped (2026-11-20, owner: "lets stop qa testing")

- Fixed and verified live by the developers, reviewed: 27 of 30 (H-1, V-1…V-19, C-1, G-1, G-2, G-4…G-6).
- Won't fix: G-3 (Nardi points at 320 px), A-1 (Filament vendor tables).
- Open: L-1 (leaderboard name link height, P3).
- Not done: the full iteration-2 retest of the signed-in routes, the visual review of iteration-2 screenshots, and the
  cause of the 7 game-test failures in the stopped run.
