# UI/UX audit — 2026-10-29

Auditor: UI/UX agent (run by the main session). Method: the running app (`make up` + `make web`),
headless Chrome at 1440×900 and 390×844, signed in as a local test player and as a guest, plus reading
the components named below. Rooms were checked in real bot games earlier on 2026-10-28/29 (Durak,
Poker, Chess, Checkers).

Not inspected this round: tablet (820 px), ru/hy string lengths, keyboard-only pass, screen reader,
the admin panel, the password-reset and phone-verification flows, Nardi in play.

Competitor patterns referenced here are labelled **Recommendation** unless `PRODUCT_RESEARCH.md`
records them as observed; the PM agent's research holds the sources.

## Summary
The new club look is consistent across the lobby, card tables and boards. The biggest problems are
not visual: a setting that does nothing (sound), numbers that don't add up (stats), weak signals
when it's your turn in a background tab (players time out), and dead ends after a game (no quick
"again"). Profiles are almost empty, which removes a reason to come back.

| ID | Issue | Priority | Size |
|---|---|---|---|
| UX-001 | "Sound effects" setting does nothing | P0 | M |
| UX-002 | Statistics hide abandoned/timed-out games, totals don't add up | P1 | S |
| UX-003 | History rows say "Timed out" but not whether you won or lost | P1 | S |
| UX-004 | No turn signal when the tab is in the background | P1 | S |
| UX-005 | Profile pages show no games or statistics | P1 | M |
| UX-006 | Card tables repeat every player in a chip row above the table | P1 | S |
| UX-007 | Game over is a dead end: no "play again with the same settings" | P1 | M |
| UX-008 | Bot / friend actions are only a chevron icon | P2 | S |
| UX-009 | Default purple logo and "Game Platform" name clash with the club look | P2 | S |
| UX-010 | Record numbers overlap the game art on game pages | P2 | S |
| UX-011 | Guest header truncates the brand on phones | P2 | S |
| UX-012 | Sign-up asks for a confirm password with no show-password option | P2 | S |
| UX-013 | Nardi board is still in the old style | P2 | M |
| UX-014 | Bot level pills wrap onto two lines on phones | P2 | S |
| UX-015 | Draw offer sits below the fold on a 900 px desktop | P2 | S |
| UX-016 | Modes panel: empty tall box, player count written twice | P3 | S |
| UX-017 | Dashboard banner repeats the carousel and the tab bar | P3 | S |
| UX-018 | Landing copy undersells ("get ready to play") | P3 | S |
| UX-019 | Durak table taller than the screen: hand cut off, felt mostly empty (2026-10-30) | P1 | M |
| UX-020 | Poker: a won hand is only reported below the table, so players miss it (2026-10-30) | P1 | M |

---

### UX-001 · "Sound effects" setting does nothing — P0 · M
- **Where:** Settings → Preferences (`features/account/components/PreferencesForm.tsx`); the
  preference is stored (`UpdateSettingsRequest`, `UserResource`) but no code plays audio.
- **Now:** a checked "Sound effects — Play sounds during games" toggle.
- **Problem / why:** a control for a feature that doesn't exist. It breaks the project rule "never
  present a UI-only feature as implemented" and costs trust. Sound is also the most reliable "your
  turn" cue (see UX-004).
- **Recommendation:** implement a small sound set with the Web Audio API (no new dependency, no
  asset licensing): move, capture/take, your turn, low time (≤ 10 s), win, loss. Respect the
  preference; default volume low; never autoplay before the first user gesture.
  Fallback if not built this batch: hide the toggle.
- **Benefit:** fewer missed turns and timeouts; the setting becomes honest.
- **Depends on:** none. **Acceptance:** with sound on, each event plays once per event (not on
  re-render or reconnect); with sound off nothing plays; no console errors on browsers that block
  audio; unit test for the event→sound mapping.

### UX-002 · Statistics hide abandoned games — P1 · S
- **Where:** Dashboard "Your statistics" (`features/play/components/DashboardGames.tsx`).
- **Now:** Chess "Played 2 · Wins 0 · Losses 0 · Draws 0" (observed for the test player).
  `GET /me/stats` already returns `abandoned`; the table doesn't show it.
- **Why:** numbers that don't add up look like a bug.
- **Recommendation:** add an "Abandoned" column (or "Left / timed out"); on phones collapse to
  "W–L–D" plus played.
- **Acceptance:** for every row Played = W + L + D + Abandoned; fits 390 px without horizontal scroll.

### UX-003 · History doesn't say who won — P1 · S
- **Where:** `/history` (`features/play/components/RoomSummary.tsx`).
- **Now:** right side shows "Timed out" + date. Whether you won or lost is not visible.
- **Recommendation:** a result badge (Won green `beaten`, Lost `take` red, Draw muted, Abandoned
  muted), the end reason as small text under it, and the game icon on the left. Optional game filter
  chips when there are more than 10 rows.
- **Acceptance:** each finished row shows outcome + reason; colours meet AA; badges have text, not
  colour alone.

### UX-004 · No turn signal in a background tab — P1 · S
- **Where:** `pages/RoomPage.tsx` sets `<title>{game.name}</title>` only.
- **Now:** when the opponent moves while you're on another tab, nothing changes. Turn timers and the
  presence rule ("leaving too long loses") then end the game: every game in the test player's history
  ended "Timed out".
- **Recommendation:** title becomes "● Your turn · Chess" (translated) while it's your turn; with
  sound on, play the "your turn" sound (UX-001) when the tab is hidden.
- **Acceptance:** title switches within one update of the turn changing and reverts after you move.

### UX-005 · Profiles are empty — P1 · M
- **Where:** `/profile` (`features/account/components/ProfileView.tsx`), `/u/:username`
  (`pages/PublicProfilePage.tsx`; the public API returns no stats).
- **Now:** avatar, name, member since, last active. Nothing about games.
- **Recommendation:** own profile: per-game record cards (reuse `GameIcon`, stats from
  `/me/stats`) and the last 5 games (reuse `RoomSummary`). Public profile: the same record cards —
  needs a backend field on the public profile → PM to schedule.
- **Acceptance:** own profile shows records for games played and an empty state otherwise.

### UX-006 · Card tables repeat the players — P1 · S
- **Where:** `pages/RoomPage.tsx` renders `PlayerList` for every game; Durak and Poker already show
  each player at their seat.
- **Now:** on a phone the chip row takes up to three lines and pushes the table below the fold.
- **Recommendation:** skip `PlayerList` when the board shows seats (Durak, Poker); keep it for Chess
  (clocks) and Checkers/Nardi.
- **Acceptance:** at 390×844 a 6-player Poker table starts within the first screen; no information
  lost (names, bot badge, turn, timers are on the seats).

### UX-007 · Game over is a dead end — P1 · M
- **Where:** `RoomPage` finished state: an `Alert` plus "Play again" linking to the game page.
- **Recommendation:** game-over panel over the board: outcome in large gold/plain text, reason,
  "Play again" that immediately starts the same kind of game (same mode, table size and bot level for
  bot games; matchmaking for online games) and "Back to lobby". A true rematch against the same human
  needs backend support → PM.
- **Acceptance:** from a finished bot game, one tap starts a new game with the same settings.

### UX-008 · Bot / friend actions are only a chevron — P2 · S
- **Where:** `PlayPanel` → `ModeCard`: the action is a 44 px circle with a chevron; the label is only
  in `aria-label`.
- **Recommendation:** a visible text button ("Play" / "Create table") or make the whole card the
  button. **Acceptance:** first-time users can see the action without hovering.

### UX-009 · Default logo and name — P2 · S
- The brand comes from settings (`logo_url`, falls back to `/favicon.svg`, a purple dice) and the
  site name "Game Platform". Purple is the only off-palette colour on every page.
- **Recommendation:** replace the default `favicon.svg` with a gold mark; the owner picks a real name
  (decision). **Acceptance:** no off-palette colour in the header.

### UX-010 · Record overlaps the art — P2 · S
- Game page hero (`pages/GamePage.tsx` `GameHero`): Wins/Losses/Played sit on the artwork; on phones
  the labels collide with the card faces (Durak). Move them into a strip under the art.

### UX-011 · Guest header truncates the brand — P2 · S
- At 390 px "Game Platf…" next to Log in + Sign up. Hide the brand text below 400 px (logo only).

### UX-012 · Sign-up friction — P2 · S
- `/register`: email/phone, username, password, confirm password, then a code. Add show/hide on
  password fields; consider dropping "confirm password" when show/hide exists (owner decision). Guest
  play (no account) is an opportunity for the PM (needs backend + approval).

### UX-013 · Nardi board in the old style — P2 · M
- Chess, Checkers, Durak and Poker are in the wood/baize style; `games/nardi/NardiBoard.tsx` is not.

### UX-014 · Bot level wraps on phones — P2 · S
- In the bot `ModeCard` the three pills wrap to "Easy Medium / Hard". Let the segmented control take
  the full card width under the title on phones.

### UX-015 · Draw offer below the fold — P2 · S
- Chess at 1440×900: "Offer a draw" starts at ~890 px. Move draw controls into the side panel with
  the move list on desktop.

### UX-016 · Modes panel — P3 · S
- `GamePage` Modes/Rules grid stretches a one-mode box to the Rules height; the mode line repeats the
  player count ("2 to 6 players." and "2–6 PLAYERS"). Align to start; drop the duplicate.

### UX-017 · Dashboard banner duplicates — P3 · S
- "Choose a game" sits right above the carousel; "Profile" and "Settings" repeat the phone tab bar.
  Replace with "Continue" (active game) or the player's most played game.

### UX-018 · Landing copy — P3 · S
- "Pick a game, learn the rules and get ready to play." dates from before games were playable. Say
  what's true now: play people or bots instantly, five games, free.

---

## Addendum 2026-10-30 · Card tables (owner feedback)

Research with sources: `RESEARCH_TABLES.md`. Specs: `DESIGN_SPECIFICATIONS.md` B3a (TASK-035, TASK-036).

### UX-019 · Durak table doesn't fit and wastes the felt — P1 · M
- **Where:** `games/durak/DurakBoard.tsx`.
- **Now (measured):**
  - At 390×844 the page is 1401 px tall and my hand starts at y = 916, under the tab bar.
  - At 1440×900 the hand (782–926) is cut by the fold.
  - The oval's corners are empty, the opponent panel floats above the rim, and "The table is empty."
    sits mid-felt.
- **Problem:** the cards you play with and the one button you need are off screen. You scroll on
  every turn.
- **Recommendation:** a rounded-rectangle felt with a thin wood rail, fitted to the viewport height;
  compact seats with status bubbles; deck + sideways trump left, discard right; hand rows with
  ≥ 40 px strips; one gold action button. Owner references and other Durak apps follow the same
  anatomy.
- **Acceptance:** see TASK-035 (whole table, hand and button visible at 390×844, 820×1180, 1280×720,
  1440×900 with 2–6 players).

### UX-020 · Poker hand win goes unnoticed — P1 · M
- **Where:** `games/poker/PokerBoard.tsx` ("Last hand" panel); `PokerEngine::finishHand` deals the
  next hand in the same transition.
- **Now:** the result appears only in a panel below the action bar. The table itself never shows who
  won.
- **Problem:** winning doesn't feel like anything; players can't follow the game.
- **Recommendation:** an on-table result moment (winner glow, pot slides to the winner, "+N", hand
  name, shown cards). A short server pause between hands is flagged as a backend change.
- **Acceptance:** see TASK-036.
