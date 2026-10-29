# Implementation reports

One section per task or batch, newest first. Paths under `apps/web/src`.

## B2 revision after design review — 2026-10-29 · READY_FOR_REVIEW

- Root cause of TASK-008's unreadable hover (and B1's invisible icon tiles): the custom `.text-gold`
  component class shared its name with Tailwind's `text-gold` colour utility, so every element with
  gold text also got a gradient background clipped to the text. Renamed it `.text-gold-gradient`
  (`styles/index.css`) and used it only on the seven big gold headings (games, dashboard, game page,
  landing, carousel slide titles, lobby card titles, game-over win). `text-gold` is now plain gold.
  Checked: Start game at rest gold on `#33231a`, forced `:hover` black on gold, `background-clip`
  border-box.
- TASK-008: `Segmented` `fill` sets the fieldset `w-full max-w-xs`; at 390 table-size pills 59×44,
  level pills 101×44.
- TASK-024: `WaitingTable` hides the medal and dots below `sm`, title `min-w-0 break-words text-lg`;
  Share/Copy link, Copy link and Close room `min-h-11`. hy at 390: frame `scrollWidth 234 =
  clientWidth 234`, page 390, all buttons 44 px.
- Minor: `GameOverPanel` renders above `PlayerList`; no "Game over" eyebrow for a closed room;
  catalog queries keep the previous language's data during a switch (the game page only for the same
  game).
Checks: typecheck ✓ lint ✓ format ✓ vitest 99 passed ✓ build ✓.

## B2 (TASK-026, 006, 028, 007, 024, 008) + TASK-030, TASK-031 — 2026-10-29 · reviewed

Owner go-ahead for TASK-030/031 ("do all necessary things", 2026-10-29). Built to the B2 specs; the
spec's open question 1 was resolved in code: the room payload now carries `mode.id` and each bot's
`bot_difficulty` (additive API fields, `make types` committed), so "Play again" repeats the exact
level instead of remembering it on the device.

| Task | Changes |
|---|---|
| TASK-030 bot names | `apps/api/app/Gameplay/Bots/BotSelector.php`: `pickMany` seats distinct bots, borrowing bots of other levels for the game (they play at the table's level, set per seat) before repeating one; `shortages()` lists game/level pairs with too few bots → subheading on the admin Bots list (`ListBotProfiles`). `BotSeeder`: 5 bots per level (15). Ran the seeder locally |
| TASK-031 chess on time | `GameActionService::end`: a timeout with a game clock records `loss` (counts in losses); per-turn timer timeouts and leaving stay `abandoned` |
| API | `RoomResource`: `mode.id`, `participants[].bot_difficulty`; schema.d.ts regenerated |
| TASK-026 | `app/useRefreshOnLanguageChange.ts` (mounted in `PublicLayout`) invalidates `translatedQuery` keys (`features/play/api.ts`: rooms not state, me/rooms|games|stats, users) on `languageChanged`; shared `myStatsKey` / `myGamesKey` |
| TASK-028 | `features/play/turnCaption.ts` `waitingCaption` used by the RoomPage header badge and the Chess, Checkers, Nardi and Durak boards (names via the `players` prop); nothing once the game is over; `*.waiting` strings removed |
| TASK-006 | `features/play/components/GameOverPanel.tsx` replaces the finished/aborted/left alerts and the footer link: outcome, reason, Play again (bot: same mode/seats/level; matchmaking: new search with status in the panel; private/aborted: lobby only), Back to lobby, "Same settings" line; heading focused |
| TASK-007 | `pages/ProfilePage.tsx`: Records grid (`GameRecord`, shared with the dashboard via `features/play/records.ts`) and 5 recent games; empty state for new players |
| TASK-024 | `pages/JoinPage.tsx` + route `join/:code`; `withNext` (`features/auth/forms.ts`) keeps the destination through login ↔ register → verify; join notice on login/register; `WaitingTable`: Copy code, Share (Web Share) or Copy link, secondary Copy link, Close room below the table, selectable fallback field; `LinkIcon`, `ShareIcon` |
| TASK-008 | `PlayPanel` `ModeCard`: medal + title row, full-width body and visible text button (`Button` variant `outline`), labels "Start game" / "Create table"; `Segmented` `fill` (one line, 44 px on phones); table size pills show numbers |

**Tests run:** web `typecheck` ✓ `lint` ✓ `format:check` ✓ `vitest` 21 files **99 passed** (new:
`turnCaption.test.ts`, `pages/B2.test.tsx` language refetch, profile, join flows, copy link; updated
RoomPage/Play/Nardi tests) · `build` ✓. API `make lint-api` ✓ (pint, PHPStan) · `make test-api`
**291 passed** (new: distinct bots with borrowing, repeat-only-when-short + shortages, chess flag-fall
= loss with stats; updated: admin seeder list, room API fields).

**Browser check (390×844 / 1440×900, local stack):** lobby cards show text buttons and one-line size
pills; resigned a Hard checkers game → panel "You lost. · A player resigned · Same settings: Russian
draughts · Level: Hard", focus on the heading, tab "Lost · Checkers"; Play again → new room, "● Your
turn · Checkers", opponent a Hard bot (Viktor); profile records + 5 recent games + See all games;
private 4-seat Poker table: code, Copy code, Copy link, Close room, no horizontal scroll.

**Not checked live:** Web Share sheet (headless has none), matchmaking Play again, sign-up → verify →
join with a real new account, ru/hy on the new screens, TASK-031 in a live chess game (feature tests).

## B1 revision after design review — 2026-10-29 · READY_FOR_REVIEW

Corrections from `DESIGN_REVIEW.md` (B1):
1. `RoomSummary.tsx`: below `sm` the outcome badge and reason move to a third line under the names
   (`sm:hidden`); the right column shows from `sm` (`hidden sm:flex`).
2. Game icon tiles: `text-gold` moved from the tile onto `GameIcon` (`RoomSummary.tsx`, and the room
   header in `RoomPage.tsx`), so the `bg-gold/15` tile is no longer clipped to the text.
3. `Records.test.tsx`: asserts both layouts render the badge with the right tone.
4. Re-checked `/history` at 390×844 in en, ru and hy: opponents and dates fully visible,
   `scrollWidth 390 = clientWidth 390`, icon tiles visible.
Minor items also fixed: streak line uses one colour class; stats table cells `pl-3`; Poker "Bot" pill
`py-0.5 leading-none`; `PlayerList` result badges use `resultTone` (Lost is red everywhere).
Left as is: the end sound for a player who left a table that plays on (harmless at bot tables; to
decide with human multi-player tables).
Checks: typecheck ✓ lint ✓ format ✓ vitest 89 passed ✓ build ✓.

## B1 · TASK-001…005 — 2026-10-29 · reviewed

Built to `docs/design/DESIGN_SPECIFICATIONS.md` (B1). Frontend only; no API or backend change.

| Task | Changes |
|---|---|
| shared | `games/registry.ts`: `GameTraits` (`seatsOnBoard`, `material`, `capture`) + `traitsOf(slug)`; `BoardProps.bots` |
| TASK-001 sounds | new `features/play/sounds.ts` (Web Audio synth, master 0.25, unlocked on first gesture, silent failure) and `features/play/useGameSounds.ts` (pure `soundFor(prev, next, ctx)` + hook incl. low-time at 10 s); `pages/RoomPage.tsx` calls the hook (off for players who left a table that plays on); `PreferencesForm.tsx` plays a preview when sound is switched on; new hint text |
| TASK-002 stats | `features/play/components/DashboardGames.tsx`: full-width card, container query: 9-column table ≥ 48rem, wrapping list below; timed out, win rate (wins / played), streaks, note |
| TASK-003 history | `features/play/components/RoomSummary.tsx`: game icon tile, outcome `Badge`, short end reason, date on line 2; `shared/ui/Badge.tsx` tones `success`, `danger`; `features/play/labels.ts` `endReasonShort`, `resultTone` |
| TASK-004 tab title | `RoomPage`: "● Your turn · {game}" on my turn, "{result} · {game}" when finished, else the game name |
| TASK-005 player row | `RoomPage` hides `PlayerList` while a `seatsOnBoard` game is active (Durak, Poker; finished tables keep it); "Bot" pill on Durak seats and Poker nameplates (+ ", Bot" in their labels) |
| i18n | `room.tab.*`, `room.endReasonShort.*`, `dashboard.{timedOut,winRate,streak,bestStreak,statsNote,streakLine}`, `settings.soundHint` in en/ru/hy |

**Tests run (real results):** `npm run typecheck` ✓ · `npm run lint` ✓ · `npm run format:check` ✓ ·
`npx vitest run` → 19 files, **89 passed** (15 new: `useGameSounds.test.ts` rules 1–6 and every
capture check; `Records.test.tsx` stats numbers + history badges/reasons; `RoomPage.test.tsx` title
and card-table player row; Durak/Poker bot pills) · `npm run build` ✓. Backend untouched.

**Browser check (headless Chrome, local stack):**
- 6-player Poker bot table at 390×844: no player row, whole oval on the first screen, "BOT" pills on
  the five bots. After a tap, card sounds played as bots acted (instrumented `AudioBufferSourceNode`);
  on my turn the title became "● Your turn · Poker" and the chime played (oscillators); after calling,
  the title returned to "Poker" and my move made a card sound.
- Dashboard 1440: nine-column table, Chess 2 = 0+0+0+2, win rate 0%. 390: list layout,
  `scrollWidth 390 = clientWidth 390`. History 390: badges "Timed out" + "On time"/"Left the game".

**Routes to review:** `/dashboard`, `/history`, `/settings` (sound toggle), `/rooms/:id` for Poker and
Durak (players row, Bot pills), Chess (title, sounds, PlayerList kept).

**Known issues / not done:**
- Sounds checked by instrumentation, not by ear; volume/character want a human listen.
- Bot names repeat badly at big tables (five "Alex"-type duplicates at 6-player Poker): TD-002 raised
  to P1.
- Header status says "Waiting for the other player" at multi-player tables (pre-existing copy).
- hy strings need a native-speaker check (spec open question 3).
