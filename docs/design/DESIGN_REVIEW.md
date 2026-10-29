# Design reviews

One section per reviewed task or batch, newest first (format: `.claude/agents/ui-ux-agent.md`).
Paths are under `apps/web/src`.

## B2 · TASK-026, 006, 028, 007, 024, 008 (+ TASK-030) — 2026-10-29

Reviewed by the UI/UX agent against `DESIGN_SPECIFICATIONS.md` (Batch B2) and
`IMPLEMENTATION_REPORT.md#b2`. Implementation: commit `3114df9` on `main`.

| Task | Verdict |
|---|---|
| TASK-026 Names follow the language switch | **APPROVED** |
| TASK-006 Game-over panel with "Play again" | **APPROVED WITH MINOR ISSUES** |
| TASK-028 Turn caption that tells the truth | **APPROVED** |
| TASK-007 Own profile with records and recent games | **APPROVED** |
| TASK-024 Shareable invite link | **NEEDS REVISION** |
| TASK-008 Clear play actions in the lobby | **NEEDS REVISION** |
| TASK-030 Distinct bot names at a table (quick check) | **APPROVED** |

### What I checked

- **Code:** the `apps/web/src` diff of `3114df9`:
  - `useRefreshOnLanguageChange`, `translatedQuery`, `GameOverPanel`, `turnCaption`;
  - the four boards, RoomPage, ProfilePage, `GameRecord`, `records.ts`;
  - JoinPage, `withNext` and the login, register and verify pages;
  - WaitingTable, PlayPanel, Button, Segmented;
  - the new keys in en/ru/hy (all present; ru plural forms present).
- **Live app:** local stack, headless Chrome over CDP, signed in as `player2@example.test`. Clipboard,
  `navigator.share`, `AudioContext` and `fetch` were stubbed or instrumented where noted.
- **Screens, viewports and languages:**

  | Screen | Viewports | Languages |
  |---|---|---|
  | `/games/poker` lobby | 390, 1440 | en, ru, hy (390) |
  | Durak 4 players (me + 3 bots): active, captions, finished panel, Play again | 390, 1440 | en, ru, hy |
  | Chess (me + Hard bot): captions, language switch with a piece selected, finished panel, reload, Play again | 390 | en, ru |
  | Poker 6 players: names, finished panel under a 6-row player list, Play again | 390 | en |
  | Private 4-seat Poker table: Copy code, Copy link, the clipboard-failure fallback, the stubbed Share path, closed-room panel | 390, 1440 | en, ru, hy (390) |
  | `/join/zzz999` and `/join/<closed code>`, signed in | 390, 1440 | en |
  | `/profile` | 390, 820, 1440 | en, ru, hy |
  | `/history` language switch without reload | 390 | en → ru → hy |

- **Screenshots** (scratchpad `review/`):
  - lobby: `b2-lobby-poker-390(-cards|-ru|-hy|-hy-top)`, `b2-lobby-poker-1440`,
    `b2-outline-rest`, `b2-outline-hover`;
  - Durak: `b2-durak-1440`, `b2-durak-390(-ru|-hy)`, `b2-durak-over-390(-top)`,
    `b2-durak-over-1440`, `b2-over-390-ru`, `b2-over-390-hy`;
  - Chess: `b2-chess-390-ru-selected`, `b2-chess-390-ru`, `b2-chess-390-waiting`,
    `b2-chess-over-390-reload`;
  - Poker: `b2-poker6-390`, `b2-poker6-over-390(-top)`;
  - private table: `b2-private-390(-full|-copied|-fallback|-ru|-hy|-share)`, `b2-private-1440`,
    `b2-private-aborted-390`, `b2-private-aborted-1440`;
  - join: `b2-join-bad-390`, `b2-join-bad-1440`;
  - profile and history: `b2-profile-1440`, `b2-profile-390(-ru|-hy)`, `b2-history-390-ru`,
    `b2-history-390-hy`.

**Not checked:**
- The guest flow `/join/CODE` → login/register → verify → table. The browser is signed in and I did
  not sign out. I read the code: `withNext` is carried through Login ↔ Register → Verify, and Verify
  uses `safeRedirect(next)`; B2 tests cover it.
- Join with a 403 (verification required).
- Matchmaking Play again.
- A finished **private** game: it needs a second human. The closed (aborted) private room was
  checked, and the code gives private rooms only "Back to lobby".
- The real Web Share sheet: only stubbed.
- The profile empty state for a new player: the account has games.
- Nardi and Checkers captions live (unit tests only).
- "Several seats acting" in Durak live: never happened in about 3 minutes of play; it is in
  `turnCaption.test.ts`.
- Keyboard-only Tab order through the lobby.

---

### TASK-026 · Names follow the language switch — APPROVED

**Spec vs implementation:**
- The hook is mounted once in `PublicLayout`, not `App.tsx`. That is equivalent: every page that
  shows translated names is inside it.
- It remembers the last language.
- `translatedQuery` matches `['rooms', id]` (length 2 only), `['me', rooms|games|stats]` and
  `['users', …]`; the `/state` key is never touched.
- Recommendation 4 (`keepPreviousData` in `useGames`/`useGame`) was not done; see minor.

**Observed:**

| Check | Result |
|---|---|
| `/history` en → ru → hy → ru → en, at 390 and 820 | names change in place, no skeleton, no navigation, no horizontal scroll ✓ |
| Active Durak room, en → ru → hy | h1 "Durak" → "Дурак" → "Դուրակ"; mode and tab title follow ("● Ваш ход · Дурак") ✓ |
| Requests per switch | `/me/rooms`, `/rooms/{id}`, `/me/settings` (the save); no `/state` ✓ (AC 2) |
| Sound during switches (instrumented, sound on) | 0 sounds ✓ (AC 3) |
| Durak hand during switches | stays 6 cards ✓ |
| Chess: e2 selected, then switch to ru | e2 stays `aria-pressed="true"`; caption "Выберите, куда пойти." ✓ (AC 1) |
| `/profile` 390 en → hy → ru → hy → en | records, recent games and note re-render in place ✓ |

**Minor (no revision required):** the game lobby (`/games/<slug>`) uses locale-keyed catalog
queries. The first switch to a language not yet cached put me back at the top of the page (seen
once in hy). Later switches kept `scrollY 700` with no skeleton. Adding `placeholderData:
keepPreviousData` to `useGame`/`useGames` in `features/catalog/queries.ts` (spec recommendation 4)
would remove that.

---

### TASK-006 · Game-over panel with "Play again" — APPROVED WITH MINOR ISSUES

**Spec vs implementation:**
- `club-frame max-w-xl rounded-3xl` panel with eyebrow, h2 outcome (gold + medal on a win), reason,
  green Play again + secondary Back to lobby, `min-h-11 w-full sm:w-auto`, and the "Same settings"
  line.
- Errors show in an `Alert` inside the panel.
- `MatchmakingStatus` replaces the actions while searching.
- Heading has `tabIndex=-1` and is focused per room id.
- Accepted deviation: the level comes from `participants[].bot_difficulty` and the mode from
  `mode.id` (no localStorage), which resolves spec open question 1 exactly.

**Observed:**

| Check | Result |
|---|---|
| Durak 4p resign, 390 | "GAME OVER / You lost. / A player resigned. / Play again / Back to lobby / Same settings: Podkidnoy · Level: Easy · 4 players"; buttons 314×44 ✓; focus on `#game-over-title` ✓; tab "Lost · Durak" ✓; only the loss sound ✓ |
| 1440 | centred 576 px panel, buttons in one row (103×44, 126×44) ✓ |
| ru, hy at 390 | strings fit, buttons 314×44, no horizontal scroll ✓ (AC 5) |
| Play again, Durak | new room `type bot`, mode 5, 4 seats, all bots `easy` ✓ (AC 1) |
| Play again, Chess | new room mode 2, 2 seats, bot `hard` ✓ |
| Play again, Poker | new room mode 6, 6 seats, all bots `medium` ✓ |
| Reload a finished Chess room | panel present, focus on the heading ✓ (AC 4) |
| Closed private Poker table (aborted) | "This room was closed." + one primary "Back to lobby", no Play again, no POST ✓ (AC 3) |

Not seen: a win (no wins on this account), matchmaking, the left-table variant on a table that
plays on, a finished private game.

**Minor (fix in the next pass, no re-review needed):**
1. `pages/RoomPage.tsx`: the panel renders **after** `PlayerList`; the spec puts it under the room
   header, before the player list. At a finished 6-player table at 390 the panel spans 490–768 px.
   It is only just above the tab bar on an 844 px screen and would start below the first screen on a
   667 px phone. Move the `GameOverPanel` block above the `showPlayers` block.
2. `features/play/components/GameOverPanel.tsx`: the eyebrow "Game over" also shows for a room
   that never started ("GAME OVER · This room was closed."). Hide the eyebrow when `aborted`.

---

### TASK-028 · Turn caption that tells the truth — APPROVED

**Spec vs implementation:**
- `waitingCaption` follows the rules (result or nobody acting → null; mine → null; several → others;
  one → name or fallback).
- The four boards use it with `?? ''`, so the `aria-live` line stays mounted.
- The header badge falls back to `room.theirTurn` and has `max-w-48 truncate`.
- The `*.waiting` keys are removed in all three locales.
- Poker is unchanged.
- RoomPage has no slug checks.

**Observed:**

| Check | Result |
|---|---|
| Durak 4p, during play | header and board read "Waiting for Anna", "Waiting for David", "Waiting for Mariam" as the turn moves; hy "Սպասում ենք՝ David" ✓ (AC 3, one actor) |
| Chess vs Robert | "Waiting for Robert" in header and board in en; "Ждём: Robert" in ru ✓ (AC 2) |
| Chess at 390 | header badge fits on one line next to the title ✓ |
| Finished Durak, Chess and Poker | board `aria-live` line empty, header badge gone; nothing says "Waiting…" ✓ (AC 1) |

The "several acting" caption was not seen live (unit-tested).

---

### TASK-007 · Own profile with records and recent games — APPROVED

**Spec vs implementation:**
- Records `section aria-labelledby` with h2, a grid `sm:grid-cols-2 lg:grid-cols-3` of cards
  (`GameRecord size="lg"`: h3 name, `text-2xl` win rate, `dt`/`dd` pairs, streak line).
- Note under the grid.
- Recent games: 5 `RoomSummary` rows and "See all games" when total > 5.
- Each section has its own skeleton and error state; the empty state replaces both.
- The dashboard list now uses the same `GameRecord`.

**Observed:**

| Check | Result |
|---|---|
| Columns | 1440: three (w 360); 820: two (w 363); 390: one (w 356) ✓ (AC 4) |
| Horizontal scroll at 390 and 820 in en, ru, hy | none ✓ (AC 4) |
| Chess record | Played 6 = 0+3+0+3 ✓ |
| Checkers record | 4 = 0+3+0+1 ✓ |
| Recent games | 5 newest rows, each with badge + reason; "See all games" present (total > 5) ✓ (AC 2) |
| Dashboard → profile (SPA) | no `/me/stats` request ✓ (AC 5); `/me/games?page=1` was fetched twice in dev (StrictMode re-mount aborts the first), not a production issue |

The empty state was not seen (account has games); I read the code.

---

### TASK-024 · Shareable invite link — NEEDS REVISION

**Spec vs implementation:**
- `/join/:code` under the signed-in routes.
- JoinPage joins once (ref guard) and navigates with `replace`.
- Errors: 422 → EmptyState with the server message + "Choose a game"; 403 → verify link with
  `next`; anything else → `QueryErrorState`.
- WaitingTable:
  - new copy, the code, a link-style "Copy code";
  - gold primary Share (when `navigator.share` exists) or Copy link;
  - secondary Copy link (only with Share) and Close room under the oval;
  - 2 s "Link copied" / "Code copied" labels plus an `sr-only role="status"`;
  - a read-only, focused and selected fallback field when the clipboard fails.

**Observed:**

| Check | Result |
|---|---|
| Copy link (stubbed clipboard) | writes `http://localhost:5175/join/UF5988`; label "Link copied", status "Link copied", resets after 2 s ✓ |
| Copy code | writes `UF5988`, label "Code copied" ✓ |
| Clipboard rejects | "Copy this link" field appears, focused, whole link selected ✓ (AC 4) |
| `navigator.share` stubbed | primary "Share invite" with secondary "Copy link" + "Close room" under the oval ✓ |
| Share payload | `{ title: "Join a table", text: "Join my Poker table.", url: …/join/UF5988 }` ✓ |
| Share `AbortError` | silent, nothing copied ✓ |
| Share other error | falls back to copying ✓ |
| `/join/zzz999` and `/join/<closed code>`, signed in | "This invite code is not valid or the room is full." + "Choose a game"; no horizontal scroll ✓ (AC 3) |
| 1440 | 2:1 oval, frame centred; Share invite and the secondary buttons below the bottom seat ✓ |
| 390 en / ru | frame content inside the frame (`scrollWidth 234 = clientWidth 234`) ✓ |
| 390 hy | **overflows**: frame `scrollWidth 293 > clientWidth 234`; the title "Սպասում ենք խաղացողներին" pushes the three gold dots past the frame's right edge (x ≈ 372 vs frame edge 313) ✗ (AC 4) |
| Button heights at 390 | primary Copy link / Share invite **40 px**, Close room and secondary Copy link **42 px**; the spec says 44 px targets ✗. "Copy code" is 44 ✓ |

**Corrections (required):**
1. `features/play/components/WaitingTable.tsx`, the frame header row (medal · h2 · dots):
   - below `sm`, hide the medal and the dots (`hidden sm:flex` on both spans);
   - give the h2 `min-w-0 break-words text-lg sm:text-xl`.

   The title then has the full ~196 px and the hy title wraps inside the frame. The long hy title was
   there before B2, but AC 4 requires the frame not to overflow at 390 in hy.
2. Same file: add `min-h-11` to the primary Share/Copy link `Button` and to both secondary buttons
   under the oval (Copy link, Close room).
3. Re-check at 390×844 in en, ru and hy:
   - frame `scrollWidth === clientWidth`;
   - all four buttons at least 44 px tall;
   - no horizontal scroll;
   - the fallback field state still fits.

Optional: in the fallback state the frame grows to 401 px and covers the top of my own seat's
avatar at 390. Acceptable for an error path, but hiding the "Copy code" button while the field is
shown would give the space back.

---

### TASK-008 · Clear play actions in the lobby — NEEDS REVISION

**Spec vs implementation:**
- ModeCard is a grid: medal + h3 on one row on phones, the medal spanning three rows from `sm`.
- The body is full width on phones.
- A text `Button variant="outline"` "Start game" / "Create table" with a chevron, `min-h-11 w-full
  sm:w-auto`, `aria-describedby` the title; the hover translate was dropped.
- The Join submit moved to `outline`.
- `Segmented fill`: level pills share the width at 44 px; table-size pills show numbers only.

**Observed:**

| Check | Result |
|---|---|
| 390 en/ru/hy | Start game and Create table 322×44; Join 62–94×44; level pills on one line (equal tops, 101×44 each, "Сложный" fits); table-size pills 2–6 on one line, 44 px tall; no horizontal scroll ✓ (AC 1–3 except below) |
| 1440 | medal left, button right-aligned under the pills (137×44, 145×44) ✓ |
| Outline button at rest | gold label on the card, gold/40 border ✓ — but computed `background-clip: text`, so its `bg-surface-raised` fill is not painted |
| Outline button **on hover** | the label turns **black on the dark card** (computed `color: rgb(0 0 0)`, `background-clip: text`; screenshot `b2-outline-hover`). About 1.3:1, unreadable on the main action of every card ✗ |
| Table-size pills at 390 | only **32–33 px wide**: the hero's fieldset is shrink-wrapped (190 px) inside a centred flex row, so `fill` has nothing to fill. 44 px tall, but the touch area is 32×44 against the 44 px rule ✗ |

**Cause of the hover bug:** the `outline` variant uses `text-gold`. In `styles/index.css`,
`.text-gold` is also a component class: a gold gradient with `background-clip: text`. Its
`background-clip` applies to the button, so the theme `bg-*` utilities only paint inside the glyphs.
On hover, `hover:bg-gold` shows only through the glyphs while `hover:text-black` paints the text
black over them, so the label goes black. This is the third time this name clash has bitten (B1 icon
tiles, here, and the old chevron button had the same classes).

**Corrections (required):**
1. `shared/ui/Button.tsx`, `outline` variant: replace `text-gold` with the plain colour utility
   `text-(--color-gold)`, which does not pull in the gradient component class:
   `border border-gold/40 bg-surface-raised text-(--color-gold) hover:border-gold hover:bg-gold
   hover:text-black`.
   - Expected computed values: `background-clip: border-box`; rest = gold on `surface-raised`
     (7.0:1); hover = black on gold (9.8:1).
   - This covers Start game, Create table and Join.
2. `features/play/components/PlayPanel.tsx`, the hero's table-size `Segmented`: give its wrapper
   `fieldset` (or the `Segmented` root when `fill`) `w-full max-w-xs`. The five pills are then about
   60 px wide at 390 (≥ 44 px target), still on one line; 820/1440 unchanged.
3. Re-check at 390 and 1440:
   - hover (mouse) and keyboard focus on Start game, Create table and Join (with a code typed);
   - table-size pill widths ≥ 44 px.

Follow-up for the tech-debt list: rename the component class `.text-gold` (e.g. to
`.text-gold-gradient`), so `text-gold` is only the colour utility and this can't recur.

---

### TASK-030 · Distinct bot names at a table (quick check) — APPROVED

| Table | Bots |
|---|---|
| Durak 4p | David, Mariam, Anna |
| Durak 4p, Play again | Igor, Mariam, Anna |
| Poker 6p (medium) | Sergey, Aram, Nina, Michael, Daniel |
| Poker 6p, Play again | same five, all distinct |

No repeats at any table; the levels are as requested. The admin shortage subheading was not checked
(admin panel not opened).

### Regressions

None found in the B2-touched screens:
- dashboard records (shared `GameRecord`);
- history rows;
- the B1 tab titles ("Lost · Durak", "● Ваш ход · Дурак");
- sounds (loss only on resign, nothing on language switch);
- the B1 icon tiles;
- Bot pills;
- player-list result tones.

### Pre-existing issues seen (not B2; for PM / TECHNICAL_DEBT)

- In hy, the lobby hero stat labels run together ("ՀԱՂԹԱՆԱԿՆԵՐՊԱՐՏՈՒԹՅՈՒՆՆԵՐ" over Wins/Losses).
- The Poker game description stays English in hy (content).
- A finished Durak table still shows seat role pills ("defending"); in hy, "պաշտպանվում է" wraps
  to two lines over the seat.
- At the waiting table at 390 the frame overlaps the left and right empty seats by a few px.
- hy dates still use English formatting.

---

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

### Re-review — 2026-10-29 (commit `2af549b`)

Scope: TASK-003, plus a quick check that the minor fixes caused no regressions. Same setup (local
stack, headless Chrome, `player2`). Each language was set in the footer select and the page
reloaded, then set back to English.

**TASK-003: checked**

| Check | Result |
|---|---|
| `/history` 390×844 en, ru, hy | rows 96 px: name; opponents · date; then badge + reason on their own line ✓ |
| One-opponent rows in every language | name, opponent and full date ("против Alex · 29 окт. 2026 г.", "ընդդեմ Alex · Oct 29, 2026") visible ✓ |
| Only the 5-opponent Poker lists | truncate; date, badge and reason stay whole ✓ |
| Horizontal scroll at 390 | none (390 = 390) ✓ |
| `/history` 1440×900 en | unchanged 70 px rows, outcome in the right column ✓ |
| Icon tiles (`/history`) | visible gold/15 tiles; computed `background-clip: border-box` ✓ |
| Icon tile (room header, finished Poker) | visible gold/15 tile; computed `background-clip: border-box` ✓ |
| Hidden copy of the outcome | `display:none` (`hidden sm:flex` / `sm:hidden`), so it is read once ✓ |

TASK-003 is now **APPROVED**.

**Minor fixes: checked**
- **Stats table:** cells have `pl-3` (computed 12 px); the 1440 table still fits (1054 = 1054).
- **Poker Bot pill:** 14.4 px tall; its top (874.9) clears the stack digits; it overlaps the line
  box by 0.4 px, which is not visible.
- **PlayerList:** "Lost" now uses the `danger` tone and "Won" the `success` tone on the finished
  Poker table, matching History. This is the first live sighting of the `success` badge; it reads
  clearly.
- **Streak line:** checked in code only (one colour class); still no streak data to see it live.
- **Regressions:** none seen.

Screenshots: `rr-hist-390-en`, `rr-hist-390-ru`, `rr-hist-390-hy`, `rr-hist-1440-en`,
`rr-poker-390`, `rr-poker-1440`.

**Final B1 verdicts:**

| Task | Verdict |
|---|---|
| TASK-001 | APPROVED WITH MINOR ISSUES (needs a human listen; left-player end sound left as is by decision) |
| TASK-002 | APPROVED (minor items fixed) |
| TASK-003 | APPROVED |
| TASK-004 | APPROVED |
| TASK-005 | APPROVED (minor items fixed) |
