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
