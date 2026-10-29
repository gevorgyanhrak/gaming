# Implementation reports

One section per task or batch, newest first. Paths under `apps/web/src`.

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
