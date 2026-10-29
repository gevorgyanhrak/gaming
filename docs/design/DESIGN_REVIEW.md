# Design reviews

One section per reviewed task or batch, newest first (format: `.claude/agents/ui-ux-agent.md`).
Paths are under `apps/web/src`.

## B1 · TASK-001…005 — 2026-10-29

Reviewed by the UI/UX agent against `DESIGN_SPECIFICATIONS.md` (B1) and
`IMPLEMENTATION_REPORT.md#b1`. Implementation: commit `68f0865` on `main`.

| Task | Verdict |
|---|---|
| TASK-001 Game sounds | **APPROVED WITH MINOR ISSUES** |
| TASK-002 Statistics | **APPROVED WITH MINOR ISSUES** |
| TASK-003 History outcome | **NEEDS REVISION** |
| TASK-004 Tab title | **APPROVED** |
| TASK-005 Card tables without player row | **APPROVED WITH MINOR ISSUES** |

### What I checked

- **Code:** the full diff of `68f0865` for `apps/web/src` (sounds, hook, registry traits, RoomPage,
  DashboardGames, RoomSummary, Badge, labels, Durak/Poker boards, PreferencesForm, en/ru/hy).
- **Live app:** local stack (SPA at `localhost:5175`), headless Chrome over CDP, signed in as the
  local test account `player2@example.test`.
- **Routes and viewports:**
  - `/dashboard`: 1440×900, 820×1180 and 390×844 in en; 1440 and 390 in ru and hy.
  - `/history`: 1440 and 390 in en, ru and hy; 820 in hy.
  - `/settings`: 390 and 1440.
  - Durak, 4 players (me + 3 bots): 1440 and 390, my turn, their turn, after "Take", after resigning
    (finished).
  - Poker, 6 players (me + 5 bots): 390 and 1440, my turn, calling, low time, after resigning
    (finished).
  - Chess Blitz (2 players): 1440 and 390, my turn and their turn, hidden tab, reload, finished on
    time; also in hy and ru.
  - Checkers (2 players): 1440, my turn, resigned.
- **States seen:** active / my turn / their turn / finished (lost, timed out) / sound on and off /
  settings preview / hidden tab (`document.hidden` overridden) / focus and visibility refetch with an
  unchanged version.
- **Sounds:** checked by instrumenting `AudioContext`, `OscillatorNode.prototype.start`,
  `AudioBufferSourceNode.prototype.start` and `console.error/warn` before a real tap. Not heard.
- **Contrast:** computed (WCAG 2.x relative luminance) from the `@theme` tokens; see the per-task
  tables below.
- **Screenshots** (scratchpad `review/`): `dash-1440(-full)`, `dash-390-full`, `dash-820-full`,
  `dash-390-ru`, `dash-1440-ru`, `dash-390-hy`, `dash-1440-hy`, `hist-1440-full`, `hist-390-full`,
  `hist-390-en`, `hist-390-ru`, `hist-1440-ru`, `hist-390-hy`, `hist-820-hy`, `durak-1440`,
  `durak-after-take`, `durak-390(-full)`, `durak-left-390`, `poker-390`, `poker-1440`,
  `poker-finished-390`, `chess-1440`, `chess-390`, `chess-finished-390`, `chess-390-hy`,
  `checkers-1440`, `settings-390`, `settings-1440`.

**Not checked:**
- Nardi, live.
- Live capture sounds in Chess, Checkers and Nardi (only Durak "take" was seen live; the detectors
  are covered by unit tests).
- Win and draw sounds live: the account has no wins or draws. The recipes were read in code.
- Low time after a reload below 10 s.
- Durak and Poker at 820.
- Keyboard-only focus on history rows: the global `:focus-visible` rule applies to the `Link`, but I
  did not see it.
- The win-streak line and colour: no streak data on this account.
- A "Won" (`success`) badge live: its contrast was computed instead.
- How the sounds sound to a person.

---

### TASK-001 · Game sounds — APPROVED WITH MINOR ISSUES

**Spec vs implementation.** `sounds.ts` follows the recipe table:
- master 0.25;
- wood tick at 220→120 Hz with a 2 kHz click; card flick at 3 kHz bandpass + 800 Hz highpass;
- capture is a double move at 0.8× pitch (+110 Hz thud for cards);
- chime at 659/880 Hz + octave at 0.15×;
- lowTime is a 523 Hz triangle ×2, 150 ms apart; win triad, draw pair and lowpassed loss pair as
  specified.

The envelope attack is a fixed 4 ms instead of 1/2/5 ms per sound. That is inaudible, so it is
accepted. `soundFor` implements rules 1–6 in order. The AudioContext is created only inside a
gesture and only when sound is on. Everything is wrapped in `try/catch` with no logging.

**Observed (instrumented):**

| Check | Result |
|---|---|
| Durak 4p, tap on blank area with sound on | 1 AudioContext created, no sound ✓ |
| Durak: I press "Take" | 2 noise + 1 oscillator = card **capture** ✓ |
| Durak/Poker: each bot action | one card **move** (noise) per state ✓ |
| Durak/Poker: my turn arrives while the tab is visible (>2 seats) | 4 oscillators = **yourTurn** chime ✓ |
| Poker: my timer crosses 10 s | 2 triangle oscillators = **lowTime**, once ✓ |
| Poker: I call | one card move; title back to "Poker" ✓ |
| Chess: my move / bot reply, tab visible | wood move each, no chime ✓ (rule 5) |
| Chess: bot reply with `document.hidden = true` | **yourTurn** chime ✓ (AC 2) |
| Chess: reload, then focus / visibilitychange / online events | nothing played ✓ (AC 1) |
| Checkers, Durak, Poker: resign | **loss** only (2 oscillators), no move sound ✓ (AC 5) |
| Settings: switch sound off | no AudioContext after a tap, nothing plays ✓ (AC 6) |
| Settings: switch sound on | preview chime plays ✓ |
| Console errors/warnings during all of the above | none ✓ |

AC 1–3, 5 and 6: pass as far as observed (capture for Chess, Checkers and Nardi is in unit tests
only). AC 4 (low time) passes live; the "not after a reload below 10 s" case was not checked. AC 7:
`useGameSounds.test.ts` exists (89 tests pass per the report; I did not re-run them).

**Accessibility:** no visual change. Every sound duplicates a visible signal (turn badge, board
text). ✓

**Minor (no revision required):**
- `RoomPage` enables sounds for a player who left a table that plays on once the game has a result
  (`stillPlaying || Boolean(state.data?.result)`). That player hears the final "loss" much later,
  which goes beyond spec rule 3 ("left the table → nothing"). Harmless for bot tables (resigning
  ended them at once in my tests). Decide when human multi-player tables exist.
- Sound character and volume still need a human listen (also in the report).

---

### TASK-002 · Statistics that add up — APPROVED WITH MINOR ISSUES

**Spec vs implementation.**
- The grid is one column and the stats card is a `@container`.
- From `@3xl` up there is a 9-column table with a GameIcon + name `<th scope="row">`, right-aligned
  `tabular-nums` and `text-xs` muted headers.
- Below `@3xl` it is a list: name with icon, and a large win rate with "Win rate" under it.
- The `dl` of `whitespace-nowrap` pairs wraps.
- The streak line shows only when `best > 0`.
- The note sits above "See all games".
- The win rate uses `Intl.NumberFormat(locale, percent)` and shows "—" at 0 played.

**Observed:**

| Check | Result |
|---|---|
| 1440 en | table, all nine columns ✓ (AC 5) |
| Chess 3 = 0+0+0+3, Checkers 2 = 0+1+0+1, Poker 2 = 0+1+0+1, Durak 1 = 0+1+0+0 | sums add up ✓ (AC 1) |
| Win rate 0 % with 0 wins; ru formats "0 %" | ✓ (AC 2) |
| 390 en/ru/hy and 820 en: list layout | `scrollWidth === clientWidth` (390/390, 820/820); each `dl` 314/314 ✓ (AC 4) |
| 1440 ru/hy table | fits (hy table 1054 = container 1054) ✓ |

820 was checked in en only (list, no overflow). AC 3 and AC 6: streak values come from the API
fixture test (`Records.test.tsx`). Not seen live (no wins on this account).

**Contrast:** muted on surface 7.9:1, accent-text on surface 10.3:1. ✓
**A11y:** `th scope="col"`/`scope="row"`; list uses `dt`/`dd`; GameIcon `aria-hidden`. ✓

**Minor (fix in the next pass, no re-review needed):**
1. `features/play/components/DashboardGames.tsx`, list streak line: the class is
   `mt-1 text-sm text-muted ${streakTone(row)}`. In the compiled CSS `.text-muted` comes after
   `.text-accent-text` (lines 1973 vs 1922 of the served `index.css`), so the gold never shows on
   phones. Use `${row.current_streak >= 2 ? 'text-accent-text' : 'text-muted'}` instead of adding
   both. The table cell is fine.
2. Same file, table: the cells have no horizontal padding. In hy at 1440 the headers
   "Պարտություններ" and "Ոչ-ոքի" are about 7 px apart and read as one label. Add `pl-3` to the
   numeric `th`/`td`, or `px-2` to all cells.

---

### TASK-003 · History shows the outcome — NEEDS REVISION

**Spec vs implementation.** The row markup follows the spec:
- 40 px icon tile;
- line 1 truncates; line 2 is "vs …" (truncate) + a `shrink-0` date;
- the right column holds the Badge and a `max-w-32` reason;
- the Badge has new `success`/`danger` tones;
- `resultTone` map, `endReasonShort` keys, `min-h-14`, colour transition.

On the page, the outcome is clear in en at every width, and in ru/hy at 820 and 1440.

**Observed:**

| Check | Result |
|---|---|
| Finished rows | badge + reason on every row ("Lost / By resignation", "Timed out / On time", "Timed out / Left the game") ✓ (AC 2) |
| Badge computed classes | Lost → `border-danger/50 bg-danger/20 text-fg`, text `rgb(244 235 221)` ✓ (AC 1, in tests too) |
| 390 en: long Poker opponent list | truncates; badge, reason, date visible ✓; rows 70 px |
| 390 ru | **every** row's line 2 shrinks to "прот…" / "проти…" + "· 29 окт. 2026 г.", so the opponent is gone even for one opponent ("против Alex") ✗ |
| 390 hy | the badge "Ժամանակը սպառվեց" is ~155 px wide; the game name truncates to one word ("Շախմատ · …") and line 2 to one glyph + date ("ը · Oct 29, 2026") ✗ |
| Icon tile | **not visible**: computed `background-clip: text` on the tile ✗ (see below) |
| No horizontal scroll at 390 en/ru/hy, 820 hy | ✓ |

AC 4 passes literally (badge, reason and date stay visible), but the result fails the purpose of
the row on phones in ru and hy, the platform's main languages: the opponent disappears from every
row. The cause is my spec. A fixed right column plus a `shrink-0` date leaves about 100–130 px for
the opponents when the badge text is long. The layout must change below `sm`.

The icon tile has the classes `bg-gold/15 text-gold`. `.text-gold` is also a component class in
`styles/index.css` (gold gradient with `background-clip: text`). Its `background-clip` applies to
the tile, so the `bg-gold/15` fill is clipped to (non-existent) text and the tile is invisible; only
the icon shows. The same pre-existing bug affects the RoomPage header tile, which the spec told this
row to copy.

**Contrast (computed):**

| Badge | On `surface` | On `surface-raised` (hover) |
|---|---|---|
| Won: fg on success/20 | 10.6:1 | 9.1:1 |
| Lost: fg on danger/20 | 11.3:1 | 9.8:1 |
| Timed out: warning on warning/10 | 7.7:1 | 6.5:1 |
| Neutral muted | — | 6.8:1 |

All are ≥ 4.5:1 ✓ (AC 3). Text is always present, so colour is never the only cue. ✓

**A11y:** the link name reads "Chess · Blitz 5+3 vs Alex · Oct 29, 2026 Lost By resignation" ✓;
icon `aria-hidden` ✓.

**Corrections (required):**
1. `features/play/components/RoomSummary.tsx`: below `sm`, move the outcome into the middle column.
   - Give the right column `hidden sm:flex` (keep its current classes for `sm` and up).
   - Add a third line inside the middle `min-w-0 flex-1` span:
     `<span className="mt-1 flex items-center gap-2 sm:hidden">` containing the same `Badge` and,
     when present, the reason as `<span className="text-xs text-muted">`.
   - Line 2 (opponents · date) then gets the full middle width, about 274 px at 390. The
     `display:none` copy is not in the accessibility tree, so there is no duplicate reading.
   - Expected at 390: about 92 px rows; in ru "против Alex · 29 окт. 2026 г." fully visible; in hy
     the game name and "ընդդեմ Alex" fully visible.
2. `features/play/components/RoomSummary.tsx`, icon tile: move `text-gold` from the tile `span` to
   the icon, i.e. tile `… rounded-xl bg-gold/15` and
   `<GameIcon … className="size-5 text-gold" />` (as `DashboardGames` already does). The tile must
   then compute `background-clip: border-box` with a visible gold/15 fill. Apply the same one-line
   fix to the header tile in `pages/RoomPage.tsx` (pre-existing, same cause), which the
   spec references as the model.
3. `pages/Records.test.tsx`: add one assertion that, for a finished room, the badge renders once in
   the phone block and once in the `sm` block (or query by role within each). This guards the
   duplicated markup from drifting apart.
4. Re-check at 390×844 in en, ru and hy:
   - the first opponent's name is fully visible for a one-opponent room;
   - badge, reason and date are fully visible;
   - `scrollWidth === clientWidth`;
   - 820 and 1440 look unchanged apart from the visible tile.

Optional (nice to have, not required): `Intl.DateTimeFormat(locale, { day: 'numeric', month:
'short' })` when the year is the current year, falling back to `dateStyle: 'medium'` otherwise. This
saves about 9 characters per row in ru ("29 окт." instead of "29 окт. 2026 г.").

---

### TASK-004 · Your-turn signal in the browser tab — APPROVED

**Spec vs implementation.** The title is derived in render from the same `myTurn` as the header
badge, excluding `leftTable`. Finished with my result gives "{result} · {game}"; otherwise the game
name. There is no blinking.

**Observed:**

| Check | Result |
|---|---|
| Durak my turn | "● Your turn · Durak" ✓ |
| After "Take" (no longer my turn) | "Durak" with no refetch ✓ (AC 2) |
| Poker my turn → call | "● Your turn · Poker" → "Poker" ✓ |
| Chess, Checkers my turn | "● Your turn · Chess" / "● Your turn · Checkers" ✓ (AC 1) |
| Finished | "Lost · Durak", "Lost · Poker", "Lost · Checkers", "Timed out · Chess" ✓ (AC 3) |
| hy | "● Ձեր քայլն է · Շախմատ" ✓ |
| ru | "Поражение · Шахматы" ✓ |
| Hidden tab + my turn | chime (see TASK-001) ✓ (AC 4) |

Not seen: the `leftTable` case on a table that plays on. All bot tables ended when I resigned; the
code path was read and is correct.

Note (pre-existing, not B1): after switching language inside a room the game name stays in the old
language until a refetch ("● Ваш ход · Շախմատ"). See P1 below.

---

### TASK-005 · Card tables without the duplicate player row — APPROVED WITH MINOR ISSUES

**Spec vs implementation.** `showPlayers` uses `traitsOf(slug).seatsOnBoard`; `RoomPage` has no
`'durak'`/`'poker'` string (grep) ✓ (AC 4). `bots` is passed from `participants`. The Durak seat name
line is `flex` with a `truncate` name + "Bot" pill and ", Bot" in the group label. The Poker pill is
at the bottom edge of the nameplate, with ", Bot" in the `li` label, and is never shown on my own
seat.

**Observed:**

| Check | Result |
|---|---|
| Durak 4p active, 1440 and 390 | no PlayerList; "BOT" pill on all three bots; labels "Alex has 6 cards, Bot" ✓ |
| Durak at 390 | opponents start at about 196 px, the felt is on the first screen ✓ |
| Poker 6p active, 390×844 | no PlayerList; oval from 222 to 622 px (viewport 844), fully visible without scrolling ✓; names, five BOT pills, gold turn ring on my seat and the header timer visible ✓ (AC 2) |
| Poker `li` labels | "David, 1000 chips, Bot" …; mine "You, 1000 chips" ✓ (AC 3) |
| Finished Poker, Durak, Chess and Checkers | PlayerList shown with results ✓ (AC 1) |
| Active Chess, Checkers | PlayerList (with clocks) shown ✓ |

**Contrast:** white/85 on black/60 over felt 12.7:1 ✓.

**Minor (fix in the next pass, no re-review needed):**
1. `games/poker/PokerBoard.tsx`, bot pill: it is 17.6 px tall (`text-[0.65rem]` inherits the parent
   line height), so with `-bottom-2.5` its top edge (671.7) overlaps the stack line box
   (659.3–675.3). The stack digits ("1,000") touch the pill at 390 and 1440. Add `leading-none
   py-0.5` (pill ≈ 12 px, top ≈ 678) so it sits centred on the plate edge as specified.
2. `features/play/components/PlayerList.tsx:58`: results still use
   `tone={result === 'win' ? 'neutral' : 'warning'}`. Since TASK-003, "Lost" is red in History but
   gold in the room's player list (seen on finished Durak and Poker). Use `resultTone[result]` from
   `features/play/labels.ts` so one result looks the same everywhere.

---

### Regressions

None found on the screens I saw. These all render as before:
- the dashboard hero and carousel;
- the history list (the empty, loading and error states were not seen);
- the settings form;
- the Chess and Checkers PlayerList and clocks;
- the Durak and Poker boards and action bars.

### Pre-existing issues seen (not B1; for PM / TECHNICAL_DEBT)

- **P1** Query keys don't include the locale. After a language switch, History and the room keep
  the previous language's game and mode names until reload (hy UI showing "Шашки · Русские
  шашки").
- **P2** hy dates fall back to English formatting ("Oct 29, 2026", "October 28, 2026-ից").
- **P3** The board caption says "Waiting for the other player." on finished games (chess timed
  out) and at Durak when it isn't my turn with several opponents (also in the report).
- **P4** Poker at 390: the "Bet 5" chip overlaps the right-hand nameplate (David 995).
- **P5** Duplicate bot names at big tables (three "David", two "Alex"; TD-002, already P1).
