# Design specifications

One section per task (format: `.claude/agents/ui-ux-agent.md`). Paths are under `apps/web/src`.
Batch B1 written 2026-10-29 by the UI/UX agent from the code (components, `games/*`, `schema.d.ts`,
backend `GameFinisher` / `GameActionService` for result semantics) and the 2026-10-29 audit. Screens
were **not** re-inspected in a browser for this phase; layout figures for 390 / 820 / 1440 are worked
out from the Tailwind classes and must be confirmed in the design review.

Facts from the code that the specs rely on:
- `abandoned` = the player's timer ran out (turn timer, chess clock or the presence timer after
  leaving the page). Resigning, including "Resign" at a multi-player table, is a `loss`. So the
  existing label "Timed out" (`room.result.abandoned`) is accurate and is kept everywhere.
- `current_streak` / `best_streak` count consecutive **wins**; any draw, loss or timeout resets it.
- A resignation or timeout also advances `version` (the board doesn't change).
- `Badge` has only `neutral` and `warning` tones; Durak/Poker seats show name, turn ring and timer but
  **no bot badge and no result** (`players` prop carries names only).

Shared change used by TASK-001 and TASK-005: game traits in `games/registry.ts` (eager, tiny; boards
stay lazy), so `RoomPage` and the sound hook never test game slugs.

```ts
export interface GameTraits {
  seatsOnBoard: boolean           // board draws every player at a seat → no PlayerList while active
  material: 'wood' | 'cards'      // which "move" sound
  capture?: (prev: Record<string, unknown>, next: Record<string, unknown>) => boolean
}
export const traits: Partial<Record<string, GameTraits>>  // unknown slug → { false, 'wood' }
```

---

## TASK-001 · Game sounds that respect the setting

**Components:** new `features/play/sounds.ts` (AudioContext singleton + synth recipes), new
`features/play/useGameSounds.ts` (hook, pure `soundFor(prev, next, ctx)` for unit tests), `games/registry.ts`
(`traits`), `pages/RoomPage.tsx`, `features/account/components/PreferencesForm.tsx`. No files, no deps.

**Derivation (one call per new state snapshot, at most one sound per snapshot):**
1. No previous snapshot for this room → store it as baseline, no sound (first load, reload).
   Exception: if this page saw the room in `waiting` and the first state arrives, it counts as
   "game start": `yourTurn` if `seats_to_act` includes my seat, else nothing.
2. `next.version <= prev.version` → nothing (re-render, refetch, reconnect, duplicate broadcast).
   A jump of several versions (missed updates) still yields one sound.
3. `seat === null`, or I already left this table (`leftTable` in RoomPage) → nothing.
4. `result` newly non-null → `result.seats[seat]`: `win` → **win**; `draw` → **draw**;
   `loss` / `abandoned` → **loss**. Stop (no move sound for the resign/timeout version bump).
5. `becameMyTurn` = my seat now in `seats_to_act` and was not before. Play **yourTurn** if
   `document.hidden` OR the room has more than 2 seats (Durak/Poker turns arrive irregularly).
   In a visible 2-player game the opponent's move sound already says "your turn".
6. `traits.capture(prev.view, next.view)` true → **capture**. Else → **move** (own and others').
7. **lowTime** is separate, from `useCountdown`: fires when it is my turn and `secondsLeft` crosses
   from > 10 to ≤ 10 for the same `deadline` (so never on reload, never twice per turn; with a chess
   clock already under 10 s it doesn't repeat every move).

**Capture detectors (cheap, pure; others have none):** chess: non-null squares in `view.board`
decreased. Checkers: `view.captured.length` grew, or piece count decreased. Nardi: `bar[0] + bar[1]`
grew (a hit). Durak: `view.taking` went false → true (the defender takes). Poker: none; no chip or
pot sounds (nothing casino-like).

**Sounds** (master `GainNode` 0.25 → destination; exponential envelopes from 0.0001; nodes stopped
and disconnected after the tail; peaks are relative to the master):

| Event | Character | Recipe |
|---|---|---|
| move · wood | low soft tick, ~60 ms | sine 220→120 Hz pitch drop over 40 ms + noise click (bandpass 2 kHz, 8 ms, gain 0.3); attack 2 ms, decay 60 ms; peak 0.8 |
| move · cards | card flick, ~50 ms | white noise → bandpass 3 kHz Q 0.7 → highpass 800 Hz; attack 1 ms, decay 50 ms; peak 0.5 |
| capture | double knock, ~140 ms | two moves of the game's material 70 ms apart, the second at 0.8× pitch (cards: + 110 Hz thud 80 ms) |
| yourTurn | two-note chime, ~450 ms | sine + 0.15× octave; E5 659 Hz at 0, A5 880 Hz at 110 ms; attack 5 ms, decay 300 ms; peak 0.6 |
| lowTime | two soft pulses, ~300 ms | triangle 523 Hz, 2 × 80 ms, 150 ms apart; peak 0.45 |
| win | rising triad, ~700 ms | triangle C5 523 / E5 659 / G5 784 at 0 / 100 / 200 ms, last decays 450 ms; peak 0.55 |
| draw | two level notes, ~400 ms | sine G4 392 Hz twice, 160 ms apart, decay 220 ms; peak 0.5 |
| loss | falling pair, ~550 ms | sine A4 440 → F4 349 Hz, 180 ms apart, lowpass 1.5 kHz, decay 350 ms; peak 0.5 |

**Behaviour:** `preferences.sound` is read at play time (ref), so toggling in Settings applies
without reload; when off, no AudioContext is created. The context is created and `resume()`d inside
the first `pointerdown`/`keydown` (capture listener, once) after the room mounts; if
`AudioContext` is missing or `state !== 'running'`, the sound is dropped, not queued. Everything in
`try/catch`, no console output. iOS silent switch muting Web Audio is accepted. No visual change at
any width; every sound duplicates a visible signal (turn badge `role="status"`, board `aria-live`).

**Settings (toggle stays):** label `settings.sound` unchanged. Switching it **on** plays `yourTurn`
once as a preview (the change event is the gesture that unlocks audio). Hint `settings.soundHint`:
en "Moves, captures, your turn, low time and game end." · ru "Ходы, взятия, начало вашего хода,
мало времени и конец партии." · hy "Քայլեր, վերցումներ, ձեր հերթը, քիչ ժամանակ և խաղի ավարտ։"

**Acceptance criteria**
1. Sound on, bot game in each of the five games: each move (own too) plays one move sound; reload,
   refetch, refocus or reconnect with an unchanged `version` plays nothing.
2. Hidden tab: when my turn starts a chime plays (2-player games included).
3. Capture plays the capture sound in Chess, Checkers, Nardi (hit) and Durak (take); not in Poker.
4. Low time plays once per turn when crossing 10 s; not after a reload below 10 s.
5. Game end plays exactly one of win / draw / loss and no move sound.
6. Sound off: nothing plays, no AudioContext; toggling applies without reload. Before any gesture
   (autoplay blocked): no console errors or warnings.
7. Unit tests for `soundFor` cover rules 1–6 and every capture detector.

---

## TASK-002 · Statistics that add up

**Components:** `features/play/components/DashboardGames.tsx`, locales. Uses `GameIcon`
(`features/catalog/components/GameArt.tsx`).

**Win rate:** `wins / played`, whole percent via `Intl.NumberFormat(locale, { style: 'percent' })`;
`played === 0` → "—". Why not `wins / (played − abandoned)`: `abandoned` are real losses on time
(chess flag-falls included); leaving them out would reward letting a losing game run out and would
not match what players expect from chess sites. Draws stay in the denominator. A note under the table
says so.

**Layout:** the stats card goes full width (the grid becomes one column: "Games in progress" above,
"Your statistics" below), and the card is a container (`@container`) so the switch follows the card's
width, not the viewport.
- **Card ≥ 48rem (`@3xl`) — table:** Game (GameIcon `size-5` + name, `<th scope="row">`) · Played ·
  Wins · Losses · Draws · Timed out · Win rate · Win streak · Best streak. Numbers `text-right
  tabular-nums`; headers `text-xs font-medium text-muted`, may wrap (hy is long). Rows `border-t
  border-border py-2.5`.
- **Card < 48rem — list:** one `<li>` per game, `py-3 border-t border-border`:
  line 1: GameIcon + name `font-medium` … right: win rate `text-lg font-bold tabular-nums` with
  "Win rate" `text-xs text-muted` under it. Line 2: `<dl class="flex flex-wrap gap-x-3 gap-y-1
  text-sm">` with `whitespace-nowrap` pairs "Played **12**", "Wins **7**", "Losses **3**", "Draws **1**",
  "Timed out **1**" (labels `text-muted`, values `font-semibold`). Line 3 (only if `best_streak > 0`):
  `text-sm text-muted` "Win streak 2 · Best 5". Pairs wrap, so no horizontal scroll in any language.
- Current streak ≥ 2 shown in `text-accent-text`; no colour on win rate (no judgement).
- Note `dashboard.statsNote` `text-xs text-muted mt-3`, then the existing "See all games" link.

**Responsive:** 390: list (card content ≈ 318 px). 820: list (≈ 724 px < 768). 1440: table
(≈ 1104 px). Both markups render; CSS hides one (`@3xl:hidden` / `hidden @3xl:table`).

**States:** loading = existing `Skeleton`; no rows = card hidden (unchanged); query error = card
hidden (unchanged, dashboard stays usable).

**Accessibility:** table keeps `<th scope="col">`; list uses `dt`/`dd`; GameIcon `aria-hidden`.

**New keys** (`dashboard.*`):
| key | en | ru | hy |
|---|---|---|---|
| timedOut | Timed out | Время вышло | Ժամանակը սպառվեց |
| winRate | Win rate | Процент побед | Հաղթանակների տոկոս |
| streak | Win streak | Серия побед | Հաղթանակների շարք |
| bestStreak | Best streak | Лучшая серия | Լավագույն շարք |
| statsNote | Win rate counts every game played, timeouts included. | Процент побед считается от всех сыгранных игр, включая игры, где вышло время. | Հաղթանակների տոկոսը հաշվվում է բոլոր խաղացված խաղերից, ներառյալ ժամանակի սպառմամբ ավարտվածները։ |

**Acceptance criteria**
1. Every row: Played = Wins + Losses + Draws + Timed out (fixture: played 6 = 2/1/1/2).
2. Win rate = round(wins / played × 100)% (fixture → 33%); "—" when played is 0.
3. Win streak and best streak shown (fixture current 1, best 3); streak line hidden when best is 0.
4. At 390 px and 820 px in en, ru and hy: no horizontal scroll (`scrollWidth === clientWidth`).
5. At 1440 px the table shows all nine columns.
6. Component test with that fixture asserts the numbers in both the table and the list.

---

## TASK-003 · History shows the outcome

**Components:** `features/play/components/RoomSummary.tsx`, `shared/ui/Badge.tsx` (two new tones),
`features/play/labels.ts` (new `endReasonShort`), locales. Used by `pages/HistoryPage.tsx` and
`DashboardGames` ("Games in progress").

**Layout (one row, all widths):** `Link` `flex items-center gap-3 rounded-xl border border-border
bg-surface px-4 py-3 hover:bg-surface-raised transition-colors`, min height 56 px.
- Left: icon tile `size-10 shrink-0 rounded-xl bg-gold/15 text-gold` with `GameIcon` `size-5`
  (same treatment as the RoomPage header, one size down).
- Middle `min-w-0 flex-1`: line 1 "Chess · Blitz" `font-medium truncate`; line 2 `text-sm
  text-muted flex`: "vs Bot Anna" (`truncate`) + " · Oct 28, 2026" (`shrink-0`) so the date survives.
- Right `shrink-0 flex flex-col items-end gap-1 text-right`: outcome `Badge`; under it the short end
  reason `text-xs text-muted max-w-32` (may wrap to two lines).

**Outcome → badge:** win → `success` "Won"; loss → `danger` "Lost"; draw → `neutral` "Draw";
abandoned → `warning` "Timed out" (existing `room.result.*`). Rooms without my result: `neutral`
with the status label (In progress / Waiting / Closed / Finished), no reason line.

**New Badge tones** (same pattern as `Alert`: tint + `text-fg`, because `text-danger` on a tint over
`surface-raised` is 3.85:1):
`success: 'border-success/50 bg-success/20 text-fg'`, `danger: 'border-danger/50 bg-danger/20 text-fg'`.
Computed: fg on success/20 ≥ 9.1:1, on danger/20 ≥ 9.8:1 (worst case on hover `surface-raised`);
warning text on its tint 6.5:1; neutral muted 6.8:1. Text always present, colour never the only cue.

**End reason (short):** `room.endReasonShort.*` — completed / resign / timeout / abandon:
en "Played to the end" / "By resignation" / "On time" / "Left the game";
ru "Доиграна до конца" / "Сдача" / "По времени" / "Игрок ушёл";
hy "Խաղացվեց մինչև վերջ" / "Հանձնվելով" / "Ժամանակով" / "Խաղացողը լքեց". Unknown reason → no line.

**Responsive:** 390: row inner ≈ 326 px; name truncates first, badge and reason never truncate.
820 / 1440: same row, more room for names. No layout switch needed.

**States:** hover `bg-surface-raised`; focus-visible = global brass outline; loading / empty / error
are HistoryPage's existing `Skeleton` / `EmptyState` / `QueryErrorState`.

**Motion:** colour transition only (fine under reduced motion).

**Accessibility:** the link's name reads "Chess · Blitz, vs Bot Anna · Oct 28, 2026, Won, On time";
icon `aria-hidden` (GameIcon already is).

**Acceptance criteria**
1. Component test per outcome (win, loss, draw, abandoned): badge text and tone class, reason text.
2. Every finished row shows a badge and a reason line; active rows show "In progress", no reason.
3. Badge text contrast ≥ 4.5:1 on `surface` and `surface-raised` (values above).
4. At 390 px a long opponent list truncates; badge, reason and date stay fully visible.

---

## TASK-004 · Your-turn signal in the browser tab

**Components:** `pages/RoomPage.tsx` (the `<title>` element), locales; the sound comes from
TASK-001's rule 5 (chime when the tab is hidden), nothing extra here.

**Title per state** (`{{game}}` = `data.game.name`, `{{result}}` = `room.result.*`):
| State | Title |
|---|---|
| active, my seat in `seats_to_act` | `room.tab.yourTurn` → "● Your turn · Chess" |
| active, not my turn / I left the table / no seat | "Chess" (plain, as now) |
| waiting, aborted | "Chess" |
| finished with my result | `room.tab.finished` → "Won · Chess", "Timed out · Chess" |
| finished, no result for me | "Chess" |

Prefix "●" (U+25CF) only for your turn: it is the one state that needs action and it survives tab
truncation. No blinking or alternating titles (distracting, and reduced-motion users can't stop it).

| key | en | ru | hy |
|---|---|---|---|
| room.tab.yourTurn | ● Your turn · {{game}} | ● Ваш ход · {{game}} | ● Ձեր քայլն է · {{game}} |
| room.tab.finished | {{result}} · {{game}} | {{result}} · {{game}} | {{result}} · {{game}} |

**Behaviour:** derived in render from the same `myTurn` that drives the header badge, so it flips in
the same state update and reverts as soon as my action's state arrives.

**Accessibility:** the in-page `role="status"` turn badge stays the announced signal; the title is
for sighted users in other tabs (some screen readers say "black circle", acceptable).

**Acceptance criteria**
1. RoomPage test: my turn → `document.title` "● Your turn · Chess"; their turn → "Chess".
2. After my action's state (seat no longer to act) the title is "Chess" without a refetch.
3. Finished as winner → "Won · Chess"; ru locale → "● Ваш ход · Шахматы"-style strings.
4. With the tab hidden and sound on, my turn starting plays the chime (TASK-001 AC 2).

---

## TASK-005 · Card tables without the duplicate player row

**Components:** `games/registry.ts` (`traits`, see top), `pages/RoomPage.tsx`,
`games/durak/DurakBoard.tsx`, `games/poker/PokerBoard.tsx` (bot badge), `BoardProps` gains
`bots?: Partial<Record<number, boolean>>` (filled from `data.participants` like `players`).

**Rule in RoomPage (no slug checks):**
`showPlayerList = status !== 'waiting' && !(traits[slug]?.seatsOnBoard && status === 'active')`.
Durak and Poker: `seatsOnBoard: true`. Chess (clocks), Checkers, Nardi: `false`, unchanged.
Finished/aborted card tables **keep** PlayerList: it is the only place that shows every player's
result (who is the durak), and the board no longer needs the space.

**What the seats must show once the row is gone:** names ✓ and turn ring with timer ✓ (already);
**bot badge — missing today, add it:**
- Durak `Seat`: the name line becomes `flex items-center gap-1.5`: name `truncate` + pill
  `shrink-0 rounded-full bg-black/60 px-1.5 py-px text-[0.65rem] font-semibold uppercase
  tracking-wide text-white/85` "Bot" (`room.bot`). Group `aria-label` gets ", Bot".
- Poker nameplate: same pill centred on the plate's bottom edge (`absolute -bottom-2.5 left-1/2
  -translate-x-1/2`), mirroring the status pill on the top edge; `li` `aria-label` gets ", Bot".
- Contrast: white/85 on black/60 over felt ≥ 10:1. My own seat never shows it.

**Layout / responsive:** 390×844: with the chip row gone the Poker oval starts right under the room
header (≈ 216 px from the top) and its bottom (≈ 620 px) sits above the phone tab bar; a 6-player
Durak table likewise starts on the first screen. 820 / 1440: the table moves up by one chip row; no
other change.

**States:** waiting → `WaitingTable` (unchanged); active → no PlayerList; my turn → header badge +
gold seat ring (unchanged); left the table → existing `leftTable` alert, still no PlayerList;
finished/aborted → PlayerList with results above the board, as now.

**Motion / a11y:** none added; seats already carry `aria-current="step"` for the acting player.

**Acceptance criteria**
1. RoomPage tests: active Chess/Checkers/Nardi render `list "Players"`; active Durak/Poker don't;
   finished Poker does.
2. At 390×844, 6-player Poker bot game: the whole oval is visible without scrolling; names, "Bot"
   pills, turn ring and the header timer visible.
3. Durak and Poker board tests: a bot seat shows "Bot"; a human seat and my seat don't.
4. `RoomPage` contains no `'durak'` / `'poker'` string checks.

---

## Open questions

1. **Chess flag-fall counts as "Timed out", not "Lost"** (`GameActionService::end` marks every
   timeout as abandoned). Specs label it honestly, but chess players expect a loss on time to be a
   loss. Backend change → PM/owner.
2. **Sound defaults to on** (`UserResource`: `sound ?? true`), so every existing player starts
   hearing sounds after release. Keep on (recommended: low volume, it's what the toggle promised) or
   ship with default off? PM.
3. The new hy strings (here and in TASK-002/003) should get a native-speaker check before release.

---

# Batch B2 · Keep playing

Written 2026-10-29 by the UI/UX agent from the code (`apps/web/src`, backend `RoomController`,
`RoomResource`, `RoomPresenter`, `Matchmaker`, `SetLocaleFromRequest`, `EnsureContactVerified`) and
the audit. Screens were **not** opened in a browser for this phase; px figures are worked out from the
Tailwind classes and must be confirmed in the design review. Order: TASK-026, TASK-006 + TASK-028
(one view, build together), TASK-007, TASK-024, TASK-008.

---

## TASK-026 · Names follow the language switch

**Components:** new `app/useRefreshOnLanguageChange.ts` (called once in `app/App.tsx` with its
`QueryClient`); `features/play/api.ts` exports a `translatedQuery(key)` predicate next to the keys.
No UI change. `features/catalog/queries.ts` and `features/content/queries.ts` already put the locale
in the key and stay as they are.

**Facts:** `apiRequest` (`shared/api/http.ts`) sends `Accept-Language: i18n.language` on every
request; the API answers in that locale. Translated game/mode names arrive in `GET /rooms/{id}`
(`roomKeys.room`), `/me/rooms` (`myRoomsKey`), `/me/games` (`['me','games',page]`), `/me/stats`
(`['me','stats']`, DashboardGames + GamePage hero) and `/users/{username}` (`['users',username]`,
`stats[].game.name`). None of these keys has the locale. `GET /rooms/{id}/state` has no translated
text (ids, view, result codes). Language changes via `LanguageSwitcher` and `useSetCurrentUser`.

**Decision: invalidate on `languageChanged`, not locale-in-key, for these queries.**
- Locale in `roomKeys.room` makes a new empty query on switch: `room.isPending` → RoomPage returns its
  skeleton, the lazy board unmounts and loses local state (selected piece, raise amount, resign
  confirm), and every `setQueryData`/`invalidateQueries` site (useRoom, realtime handler,
  ActiveGamesBar, PlayPanel) would need the locale threaded through.
- Invalidation keeps cached data on screen (`isFetching`, not `isPending`): only the words change.
- `roomKeys.state` is never touched, so `version` is unchanged and `useGameSounds` (effect on
  `[roomId, version]`, "version ≤ prev → nothing") stays silent.

**Behaviour**
1. `i18n.on('languageChanged', h)` in a `useEffect`, `i18n.off` on cleanup; remember the last
   language and ignore events where it did not change (i18next also emits during init).
2. `invalidateQueries({ predicate: q => translatedQuery(q.queryKey) })`: `['rooms', id]` with length 2
   only (not `state`); `['me', 'rooms' | 'games' | 'stats', …]`; `['users', …]`. Use the exported key
   helpers, not new literals.
3. Default `refetchType: 'active'`: visible queries refetch now, inactive ones refetch on next mount;
   `cancelRefetch` drops an in-flight old-language response.
4. Recommended in the same change: `placeholderData: keepPreviousData` in `useGames`/`useGame`, so the
   locale-keyed catalog keeps the old names during its fetch instead of flashing a skeleton.

**Visible result:** History rows, dashboard cards, ActiveGamesBar, profile records and the room header
update in place; the tab title (`room.tab.*`, re-rendered by `t`) gets the new `{{game}}` when the room
refetch returns. No loading state, layout shift or focus change. Refetch error → old names stay,
no error UI. `<html lang>` already follows (`shared/i18n/index.ts`).

**Acceptance criteria**
1. `/history`, an active bot room and `/dashboard`: en → ru → hy updates game and mode names and the tab
   title without reload; the board and a selected piece stay.
2. Network: no extra requests on navigation without a switch; one request per active translated query
   per switch; no `/state` request caused by the switch.
3. Sound on: switching language mid-game plays nothing.
4. Tests: MSW answers by `Accept-Language`; `await i18n.changeLanguage('ru')` → ru name appears; a
   second test asserts `roomKeys.state` is not invalidated.

---

## TASK-006 · Game-over panel with "Play again"

Built with TASK-028 (same view). **Components:** new `features/play/components/GameOverPanel.tsx` +
`features/play/playAgain.ts` (`usePlayAgain(room)`); `pages/RoomPage.tsx` (drops the finished/aborted/
`leftTable` `Alert`s and the footer "Play again"); `PlayPanel.tsx` (remembers the bot level). Reuses
`MatchmakingStatus`, `useGame`, `Button`/`ButtonLink`, `Alert`, `TrophyIcon`, `labels.ts`.

**"Play again" = existing endpoints only.** Mode id from `useGame(room.game.slug).data.modes` (`RoomResource.mode` has only slug/name; enabled only while the panel shows):
| `room.type` | Action |
|---|---|
| `bot` | `createBotRoom(modeId, level, room.seats)` → `/rooms/{id}` (`room.seats` = you + bots → `opponents`) |
| `matchmaking` | `startMatchmaking(modeId)`; `result.room` → navigate; else `setQueryData(matchmakingKey, result)` and show `<MatchmakingStatus />` inside the panel (search, bot offer, cancel, navigates on match) |
| `private` | `ButtonLink` `/games/{slug}` as today (a new table needs the friend to rejoin; rematch = TASK-016) |

**Bot level — exact match needs backend** (B2-1): `RoomResource` has no difficulty (stored in
`game_participants.bot_difficulty`, not returned). Until then `PlayPanel` saves the level per game when
it creates a bot game (`localStorage play.botLevel.{slug}`, try/catch) and preselects it next time; Play
again uses it, else `medium` (PlayPanel's and accept-bot's default), and always prints the level used.
Accepted bot offers are `type: bot` too. Mode gone or game not playable → Play again = lobby link.

**Layout:** under the room header, before PlayerList and the board — not over the board, so the final
position / last trick stays visible without a dismiss control.
`section.club-frame mx-auto w-full max-w-xl rounded-3xl p-5 sm:p-6 text-center motion-safe:animate-rise`:
- eyebrow `text-xs font-semibold uppercase tracking-wider text-muted` `room.over`;
- `h2 text-2xl sm:text-3xl font-extrabold`: `room.outcome.*` (win: `text-gold`, `TrophyIcon` in a
  `club-medal size-12` above; else `text-fg`). No result `room.finished`; aborted `room.aborted`; left a
  table that plays on `room.leftTable`. Then `mt-1 text-sm text-muted` `room.endReason.*` (finished);
- actions `mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center`: `Button` primary (green) "Play
  again" + `ButtonLink` secondary `room.backToLobby` → `/games/{slug}`, `min-h-11 w-full sm:w-auto`.
  Aborted: only Back to lobby, as primary. Footer keeps only the dashboard link;
- `mt-3 text-xs text-muted`: `room.sameSettings` "Blitz · Level: Hard · 4 players" (mode · `play.botHint`
  · `play.playersCount` if seats > 2) or "Blitz · Find an opponent"; none for private/aborted. While
  searching, `MatchmakingStatus` replaces the actions and this line.

**Responsive:** 390: full width, buttons stacked (Play again first), ≈ 210 px tall; a Chess board starts
below it (fine after the game). 820 / 1440: `max-w-xl` centred, buttons in one row.

**States:** creating → Play again `disabled aria-busy`; `useGame` loading → disabled; error → `Alert
tone="danger"` under the buttons with `message(error.message)` (too many games, no bot, unavailable),
403 adds the `/verify` link as in PlayPanel. Hover/focus-visible: Button defaults.

**Motion / a11y:** `animate-rise` once, none under reduced motion; no confetti (club mood); end sound is
TASK-001. `section aria-labelledby` its heading; heading `tabIndex={-1}`, focused once per room id when
the panel first renders; it replaces the `role="status"` alert, so the outcome is read once. Gold on
frame ≥ 7:1, muted 6.8:1, white on green 4.99:1 (computed).

| key | en | ru | hy |
|---|---|---|---|
| room.over | Game over | Игра окончена | Խաղն ավարտվեց |
| room.backToLobby | Back to lobby | Назад в лобби | Վերադառնալ լոբբի |
| room.sameSettings | Same settings: | Те же настройки: | Նույն կարգավորումները՝ |

**Acceptance criteria**
1. Finished bot game, all five games: one tap opens a new room with the same mode, seats and remembered
   level (fixture Poker, 4 seats, hard → body `opponents: 3`, `bot_difficulty: hard`).
2. Finished matchmaking game: POST `/matchmaking` with the same `game_mode_id`; status shows in the
   panel; a match navigates to the new room.
3. Private and aborted rooms: lobby link, no POST. Left table: panel with `room.leftTable` + actions.
4. Focus on the panel heading at game end and on opening a finished room; errors show in the panel.
5. 390 px en/ru/hy: no horizontal scroll, buttons ≥ 44 px. RoomPage tests per room type.

---

## TASK-028 · Turn caption that tells the truth

**Where the caption lives:** `room.theirTurn` ("Waiting for the other player") in the RoomPage header
badge (active only), and each board's own line: `chess.waiting` (`ChessBoard.tsx`, `aria-live`),
`checkers.waiting` (`CheckersBoard.tsx`), `nardi.waiting` (`NardiBoard.tsx`), `durak.waiting`
(`DurakBoard.tsx`). They show whenever my seat is not in `seats_to_act` — so also on finished games
(`seats_to_act` empty). Poker already says `poker.waitingFor` with the acting name: unchanged.

**Components:** new `features/play/turnCaption.ts`, used by `RoomPage.tsx` and the four boards. Boards
read the existing `players` prop (RoomPage already passes names by seat to every board).

```ts
/** null = no waiting caption (game over, nobody to act, or it is my turn). */
export function waitingCaption(state: RoomState, players: BoardProps['players'], t: TFunction): string | null
// state.result !== null || seats_to_act.length === 0 → null
// my seat in seats_to_act → null (the board shows my own prompt)
// exactly one other seat acts → name ? t('room.waitingFor', { name }) : t('room.theirTurn')
// more than one → t('room.waitingForOthers')
```
- Boards: replace the `*.waiting` branch; when it returns null on a finished game, render the line
  empty (keep the `aria-live` element mounted so nothing jumps). Durak's `durak.youTake` branch stays.
- Header badge: `myTurn ? room.yourTurn : waitingCaption(...) ?? room.theirTurn` (it only renders
  while active). No game-slug checks in RoomPage.
- Finished: no caption anywhere; the TASK-006 panel says how it ended.

**Typography / layout:** unchanged classes per board (`text-sm text-muted` etc.); names come in
`truncate`-safe lines already (header badge right-aligned: add `max-w-48 truncate` to its `p` so a long
display name can't push the badge to a second line at 390).

**Copy:** ru/hy use the colon form already used by Poker, so names need no case endings; no count in
the text, so no plural forms are needed.
| key | en | ru | hy |
|---|---|---|---|
| room.waitingFor | Waiting for {{name}} | Ждём: {{name}} | Սպասում ենք՝ {{name}} |
| room.waitingForOthers | Waiting for the other players | Ждём других игроков | Սպասում ենք մյուս խաղացողներին |
`chess.waiting`, `checkers.waiting`, `nardi.waiting`, `durak.waiting` become unused → delete in all
three locales. `room.theirTurn` stays as the no-name fallback.

**Accessibility:** the board line stays `aria-live="polite"`; the header badge stays `role="status"`.

**Acceptance criteria**
1. Finished games in all five games show no "Waiting…"/"Ход соперника" text.
2. 2-player their turn: "Waiting for Alex" in header and board caption.
3. Durak/Poker, one other seat acting: that name; several acting: "Waiting for the other players".
4. en/ru/hy present; RoomPage has no `'durak'`/`'poker'` checks. Unit tests for `waitingCaption`
   (finished, mine, one, many, missing name) + RoomPage tests finished / 2-player / 4-player.

---

## TASK-007 · Own profile with records and recent games

**Components:** `pages/ProfilePage.tsx` (two sections under `ProfileView`); new
`features/play/components/GameRecords.tsx` = the per-game block extracted from `DashboardGames`' narrow
list (`GameRecordCard`) + shared `winRate`/`counts` helpers; `DashboardGames.tsx` uses them (output
unchanged); `RoomSummary.tsx` as is; `EmptyState`, `Skeleton`, `QueryErrorState`. `ProfileView` is
unchanged (shared with `PublicProfilePage`).

**Data (no new endpoint):** `/me/stats` via key `['me','stats']` — the dashboard's and GamePage hero's
query, so numbers match and come from cache. `/me/games?page=1` via `['me','games',1]` — History's page
1 (20 per page, no `per_page`); show the first 5, newest first.

**Layout** (`Container py-10 space-y-8`):
1. `ProfileView` card as now.
2. Records `section aria-labelledby`: `h2 text-xl font-bold` `profile.records`; grid
   `mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3`; each `article.rounded-2xl border border-border
   bg-surface p-4`: line 1 `GameIcon size-5 text-gold` + `h3 font-medium` name … right: win rate
   `text-2xl font-bold tabular-nums` over "Win rate" `text-xs text-muted`; line 2 the dashboard `dl`
   pairs (Played/Wins/Losses/Draws/Timed out, `whitespace-nowrap`, wrapping); line 3 `streakLine` if
   `best_streak > 0` (`text-accent-text` when current ≥ 2). Under the grid `dashboard.statsNote`.
3. Recent games `section`: `h2` `profile.recent`; `ul space-y-2` of 5 `RoomSummary`; then
   `dashboard.history` link → `/history` when `meta.total > 5`.

**Responsive:** 390: one column (card inner ≈ 326 px). 820: two columns (≈ 382 px). 1440: three
(≈ 357 px). The `dl` wraps; no horizontal scroll in hy.

**States:** each section loads and fails on its own: `Skeleton h-40` / `h-48`; `QueryErrorState` with
retry. No stats rows → both sections replaced by one `EmptyState` `profile.noGames` with a primary
`ButtonLink` `dashboard.play` → `/games`. Hover/focus on RoomSummary rows: existing styles. Records
cards are not interactive.

**Motion:** none. **Accessibility:** sections labelled by their h2; card names are h3; numbers in
`dt`/`dd`; GameIcon `aria-hidden`.

| key | en | ru | hy |
|---|---|---|---|
| profile.records | Records | Результаты по играм | Արդյունքներ ըստ խաղերի |
| profile.recent | Recent games | Последние игры | Վերջին խաղերը |
| profile.noGames | No games yet. Play one and your records will show here. | Пока нет сыгранных игр. Сыграйте, и здесь появятся ваши результаты. | Դեռ խաղեր չկան։ Խաղացեք, և այստեղ կհայտնվեն ձեր արդյունքները։ |

**Acceptance criteria**
1. Same fixture → the same numbers on `/profile` and `/dashboard` (shared component test).
2. Five newest finished games, each with badge + reason; "See all games" only when total > 5.
3. New player: the empty state with the link to `/games`, no empty cards or headings.
4. 390 / 820 en, ru, hy: no horizontal scroll; 1440: three columns.
5. Dashboard → profile within `staleTime`: no second `/me/stats` request.

---

## TASK-024 · Shareable invite link

**Components:** new `pages/JoinPage.tsx`; `app/routes.tsx` (`join/:code` under `AuthRoute`);
`features/play/components/WaitingTable.tsx`; `shared/ui/icons.tsx` (+ `LinkIcon`, `ShareIcon`, 24 px
line style); `pages/auth/LoginPage.tsx`, `RegisterPage.tsx`, `VerifyPage.tsx` (keep `next`). API:
existing `joinRoom` → `POST /rooms/join`; no change.

**Facts:** `RequireAuth` sends guests to `/login?next=<path>`; `RequireGuest` follows `next`
(`safeRedirect`, same-app paths only). But the login page's "Sign up" link drops `next`, register goes
to `/verify?channel=…&sent=1` and Verify then to `/dashboard`, so a **new** player (the usual invitee)
would lose the table. Join is idempotent for someone already seated; a room that has started or is full
returns 422 `invite_invalid` ("This invite code is not valid or the room is full."); an unverified
contact returns 403 `verification_required` when the platform requires it.

**Flow:** link = `${location.origin}/join/${code}`. JoinPage: `code.trim().toUpperCase()`, one
`joinRoom` per mount (ref guard against StrictMode), success → `navigate('/rooms/{id}', { replace })`.
Guest → login (`next=/join/CODE`) → back → joined. Carry `next`: Login "Sign up" → `/register?next=`,
Register "Log in" → `/login?next=`, register redirect `/verify?channel=…&sent=1&next=…`, Verify success
→ `safeRedirect(next)`. Login/Register show `Alert tone="info"` `join.signInFirst` when `next` starts
with `/join/`.

**JoinPage states** (`<title>` `join.title`): joining → `Container py-12`, `Skeleton h-40` + `p
role="status"` `join.joining`; 422 → `EmptyState` titled with the server message + `ButtonLink`
`dashboard.play` → `/games`; 403 → message + link `/verify?next=/join/CODE`; network → `QueryErrorState`.

**WaitingTable** (invite still shown only to seated players of a waiting room):
- `room.waiting` copy mentions the link; code unchanged (`font-mono text-3xl text-gold`); under it a
  link-style `Button` `min-h-11 text-sm font-medium text-accent-text` `room.copyCode`.
- Primary gold `Button w-full`: if `navigator.share` exists → `ShareIcon` `room.share` (`share({ title,
  text: room.shareText, url })`; `AbortError` silent, other errors → copy), else `LinkIcon` `room.copyLink`.
- Below the oval (`mt-16 sm:mt-20 flex flex-wrap justify-center gap-2`, clears the bottom seat):
  secondary `room.copyLink` (only when Share is primary) + secondary `room.cancel` (moved out of the frame).
- Feedback: the pressed button reads `room.linkCopied` / `room.codeCopied` for 2 s (no extra row), an
  `sr-only role="status"` repeats it. Clipboard missing/rejected → read-only `TextField`
  `room.copyFallback` with the link appears under the buttons, focused and selected.

**Responsive / a11y:** 390: oval 286 × 381 px, frame content ≈ 198 px wide; header + text + code + Copy
code + primary ≈ 320 px fits (Close room is no longer inside). 820 / 1440: 2:1 oval, same order. All
buttons have visible text, 44 px targets, global focus outline; JoinPage status is polite.

| key | en | ru | hy |
|---|---|---|---|
| room.waiting | Send your friend the link or this code: | Отправьте другу ссылку или этот код: | Ուղարկեք ընկերոջը հղումը կամ այս կոդը՝ |
| room.share | Share invite | Поделиться приглашением | Կիսվել հրավերով |
| room.copyLink | Copy link | Скопировать ссылку | Պատճենել հղումը |
| room.linkCopied | Link copied | Ссылка скопирована | Հղումը պատճենվեց |
| room.codeCopied | Code copied | Код скопирован | Կոդը պատճենվեց |
| room.copyFallback | Copy this link | Скопируйте ссылку | Պատճենեք այս հղումը |
| room.shareText | Join my {{game}} table. | Садись за мой стол: {{game}}. | Միացիր իմ սեղանին՝ {{game}}։ |
| join.title | Join a table | Присоединиться к столу | Միանալ սեղանին |
| join.joining | Joining the table… | Садимся за стол… | Միանում ենք սեղանին… |
| join.signInFirst | Log in or sign up to join your friend's table. | Войдите или зарегистрируйтесь, чтобы сесть за стол друга. | Մուտք գործեք կամ գրանցվեք՝ ընկերոջ սեղանին միանալու համար։ |

**Acceptance criteria**
1. Signed in, `/join/ABC123` (MSW) → one POST, lands on `/rooms/{id}` (replace: Back doesn't re-join).
2. Signed out → login → back at the table; sign-up + verify path also ends at the table.
3. Invalid/started/full code → the server message and a link to `/games`; 403 → verify link with `next`.
4. Share shown only when `navigator.share` exists; copy shows "Link copied"; clipboard failure shows
   the selectable field. 390 px en/ru/hy: frame content does not overflow the oval.

---

## TASK-008 · Clear play actions in the lobby

**Components:** `features/play/components/PlayPanel.tsx` (`ModeCard`, its use), `shared/ui/Segmented.tsx`
(new `fill` prop), `shared/ui/Button.tsx` (new `outline` variant), locales.

**Today at 390:** card content = 358 − 32 padding − 56 medal − 2×16 gap − 48 chevron ≈ **190 px** for the
title and the level pills; "Easy Medium Hard" needs ≈ 204 px (ru ≈ 258) → wraps (UX-014). The only
action is a 48 px chevron with the label in `aria-label` (UX-008). The players pills (Poker 2–6,
"2 players" × 5 ≈ 420 px) wrap too.

**ModeCard layout (all cards):** `section.club-panel rounded-3xl p-4 sm:p-5`, inner
`grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 sm:gap-x-5`:
- row 1: medal (`size-12`, `sm:size-20 sm:row-span-3`) + `h3` title (`text-xl sm:text-2xl text-gold`),
  vertically centred on phones;
- body (`children`) `col-span-2 sm:col-span-1 sm:col-start-2`: full card width on phones (326 px);
- action `col-span-2 sm:col-start-2 sm:justify-self-end`: `Button variant="outline"`
  `min-h-11 w-full sm:w-auto` = visible text + `ChevronIcon size-5`; `aria-describedby` the title.
- New `outline` variant: `border border-gold/40 bg-surface-raised text-gold hover:border-gold
  hover:bg-gold hover:text-black` (the old chevron's look; gold on surface-raised 7.0:1, black on gold
  9.8:1). The green PLAY stays the one primary action of the page; the Join submit also moves from
  `gold` to `outline` so the three cards match.
- Labels: bot `play.startBot`, friend `play.createTable`, join `play.join` (existing). Titles unchanged.

**Segmented `fill`:** `div` becomes `flex w-full flex-nowrap`, labels `flex-1 text-center`, `min-h-11
sm:min-h-0 py-2.5 sm:py-1.5` (44 px phone targets). Level pills: 326 / 3 ≈ 105 px each, "Сложный" fits.
Players pills (hero) show only the number with `fill`; the legend `play.players` gives the meaning
(screen readers read "Players at the table, 4, radio"). `play.playersCount` stays for the settings
line in TASK-006.

**Responsive:** 390: one column, medal and title on one row, pills and button full width. 820 (`sm`):
medal left spanning three rows, button right-aligned under the pills. 1440: right column of the
lobby grid (≈ 500 px), same as 820.

**States:** hover = border gold/40 → gold (card) and button fill; focus-visible global outline on the
button and pills (`has-focus-visible` as now); disabled `opacity-50` + `cursor-not-allowed` while a
request is pending; errors stay in the existing Alert above.

**Motion:** keep the staggered `animate-rise`; drop the button `hover:translate-x-0.5` (it moved a
44 px target); reduced motion → none.

| key | en | ru | hy |
|---|---|---|---|
| play.startBot | Start game | Начать игру | Սկսել խաղը |
| play.createTable | Create table | Создать стол | Ստեղծել սեղան |

**Acceptance criteria**
1. At 390 and 1440 each ModeCard shows a text action without hover; no icon-only button remains.
2. 390 × 844 in en, ru, hy: the three level pills are on one line (equal tops) and the 2–6 players
   pills on one line; no horizontal scroll.
3. Pills and buttons ≥ 44 px tall at 390; keyboard: Tab reaches each pill group and button with a
   visible focus ring.
4. PlayPanel test: buttons found by visible name "Start game" / "Create table".

---

## Open questions (B2)

1. **Bot level is not in the room payload** (`game_participants.bot_difficulty` exists, `RoomResource`
   doesn't return it). TASK-006 uses the last level saved on this device, else Medium. Adding
   `bot_difficulty` to the bot participants in `RoomResource` is a small non-breaking backend change
   (+ `make types`) that makes "same level" exact on any device → PM / owner (B4 go-ahead).
2. **Public profile stats already come from the API:** `PublicProfileController` loads `gameStats` and
   `PublicUserResource` returns `stats` for any active user. TASK-015 is listed as "new public API field
   (privacy)", but the data is already public. PM: decide whether that is intended; if so, TASK-015 is
   frontend only (reuse `GameRecords` from TASK-007); if not, remove it from the API.
3. Native-speaker check for the new hy strings, especially `room.backToLobby` ("լոբբի" loanword) and
   `room.shareText`.
