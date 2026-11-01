# Design review — TASK-040 · Groups, bets and result screens (2026-11-01)

UI/UX agent. Build: `main` at `f31322c` (frontend 7ce1f4a, d4ca892; QA fixes fd32039). Brief:
`docs/product/briefs/TASK-040-groups-poker-bots.md`. QA: `docs/qa/QA_REPORT_TASK040.md`.

## Verdict

**NEEDS REVISION**: two Medium issues (the turn badge is cut on phones, and the header still jumps
on a poker win) plus four Low ones. The rest fits the card-club design system and works in en, ru
and hy.

## What I checked

All checks ran on the local stack in headless Chrome over CDP, at 390×844 and 1440×900.
- **Test account:** `q40b50k`. Its balance was set with tinker for the runs (52,000 or 100) and set
  back to 0 at the end.
- **Table moves:** the API (`qa2/rv-poker.mjs`, `qa2/rv-bust.mjs`) played the moves while the
  browser watched. A 50 ms recorder logged every value the header pill showed.
- **Screenshots:** in `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/review/`,
  prefix `t40-`.

| Area | Where |
|---|---|
| Group picker | Poker and Checkers lobby at 390 and 1440 in en; at 390 in ru and hy |
| Room badge | Poker heads-up, en and hy at 390; Poker 4 seats, ru at 1440; Checkers Casual bet 6,000, en/ru/hy at 390 and en at 1440 |
| Results | Poker win (×2) and loss (×4) |
| Results | Busted at a 3-human private Poker table that plays on ("out of chips") |
| Results | Checkers resign (loss, chip flight counted: 6 chips) |
| Header pill | During every run above |
| Poker numbers | en and ru |

**Not seen live:**
- "You left the game." (abandon). The string exists in en/ru/hy; reaching it needs a presence
  timeout.
- A poker cash-out after leaving mid-game.
- Keyboard pass through the picker. Native radios are used, with the global focus ring on the tile
  via `has-focus-visible`.

## What works

- **Picker (all five tiles):**
  - Group colour dot, name, "From N" / "Needs N" and the bet or minimum bet on every tile.
  - Locked tiles show a lock icon and are disabled.
  - Tiles are 157×78 at 390 (2 per row) and 3 per row from `sm`.
  - No horizontal scroll in en, ru or hy.
- **Bet controls (stake games):**
  - The slider is 44 px tall, with Min/Max buttons at 44 px.
  - Range and step are right (Beginner 500–2,000, step 50).
  - The hint "The winner takes the pot: 1,000 Fishkas…" is shown.
- **Poker buy-in line:** "You bring your whole balance to the table: 52,000 Fishkas. Blinds 1/1"
  (ru "Вы приносите за стол весь баланс: 52 000 фишек…").
- **Room badge:** never overflows (right edge 249–269 at 390; QA BUG-4 is fixed). The bet/blinds
  part stays whole.
- **Game-over panel:**
  - "You won!" in the gold gradient with the trophy, or "You lost.".
  - The ±N Fishkas pill: green `text-success`, red `#f87171`.
  - The amounts match the ledger: +52,000, −52,000, −6,000, −100.
  - The chip flight to and from the header runs once (6 chips) and looks calm, in the club style.
- **"You're out of chips. The game goes on for the others.":** shown at a table that plays on, with
  "−100 Fishkas" and only "Back to lobby".
- **Poker numbers are formatted:**
  - en: "Pot: 52,020 chips", "Bet 52,000", stacks "51,980", the plate "Aram: выигрыш 104 000 фишек";
  - ru: "52 000".
- **Header pill on a loss:** moves once, with the right sign: `52,000 → 0 [−52,000]`. No jump at
  the buy-in.

## Issues

### 1 · Medium — the turn status is cut to "Your t…" on phones

| | |
|---|---|
| Seen | 390, every language. Room header: left block 251 px, status 93 px. en "Your turn (cut) · 26 seconds left (cut)"; hy "Ձեր ք… · Մնաց 5…"; ru "Осталось 53 секунды (cut)"; busted player "Waiting f…" |
| Evidence | `t40-poker-room-390-en.png`, `t40-checkers-room-390-hy.png`, `t40-poker-outofchips-390.png` |
| Cause | `pages/RoomPage.tsx` header: the title block now has the mode plus `GroupBadge` on its second line. Its intrinsic width wins, and the status (`min-w-0`) shrinks to about 93 px. Before TASK-040 "Your turn" fitted whole |
| Fix | In `RoomPage.tsx`, take `GroupBadge` out of the title block on phones. Render it as its own row under the header (`<div className="-mt-3 sm:hidden"><GroupBadge …/></div>` after `</header>`, plus `hidden sm:inline-flex` in the current spot). Give the status `shrink-0` with `max-w-[55%]`. **Check:** at 390 in en/ru/hy, "Your turn" and the seconds line are not truncated |

### 2 · Medium — the header still jumps on a poker win

| | |
|---|---|
| Seen | Heads-up Professional table, I won (`t40-poker-over-390-en.png`). Header sequence: `52,000 → 0 [−52,000] → 52,000 [+52,000] → 52,020 [+20] → 104,000 [+51,980]`. That is four moves, the first one red, for a +52,000 win. The brief asks for one move with the right sign. A loss is correct (one move, −52,000) |
| Likely cause | At game end the wallet summary is read between "the room stops counting as `at_table`" and "the cash-out credit is committed", which gives 0. Hand-level pot changes also move `at_table` mid-game (+20) |
| Fix | Backend: report `at_table` for a finished chip room until its cash-out ledger entry exists, or do the release and the credit in one transaction before broadcasting `wallet.updated`. Frontend (`features/wallet/components/FishkaBalance.tsx`): while `at_table > 0`, don't show a delta or bump at all, and let `GameResult` drive the final change (one delta = the room's net). **Check:** with the same recorder, a win shows `52,000 → 104,000 [+52,000]` only |

### 3 · Low — "Needs N" on locked tiles is below AA

| | |
|---|---|
| Seen | Locked tiles use `opacity-55` on the whole tile: "Needs 500,000" (muted) computes to 3.3:1, and the bet line to 4.8:1 (`t40-poker-picker-390.png`) |
| Fix | `features/play/components/GroupPicker.tsx`: drop `opacity-55` from the label. Dim only the dot and the name (`opacity-60`), and keep the "Needs N" text at full `text-muted` (≥ 6.8:1). The lock icon and `cursor-not-allowed` already say it's locked |

### 4 · Low — the group name in the badge is useless when truncated (ru/hy phones)

| | |
|---|---|
| Seen | hy 390: the badge reads "«… · Խաղադրույք 6,000", only an opening quote. en "Professio…", ru similar (`t40-checkers-room-390-hy.png`) |
| Fix | With fix 1 the badge gets the full row and this mostly goes away. Also, in `GroupBadge.tsx` on phones show the bare group name (`groups.<slug>`) without the "group" wrapper and quotes, so "Սիրողական · Խաղադրույք 6,000" fits |

### 5 · Low — the out-of-chips heading is a 3-line sentence

| | |
|---|---|
| Seen | 390 en: "You're out of chips. The game goes on for the others." as the 2xl extrabold h2, 3 lines (`t40-poker-outofchips-390.png`) |
| Fix | `features/play/components/GameOverPanel.tsx`: make the heading "You're out of chips." and put "The game goes on for the others." in the reason line under it (`text-sm text-muted`), like the other outcomes. This needs two keys in en/ru/hy. Also hide the room header's "Waiting for …" badge for a player who is out (`leftTable`/`!playing`), since it isn't their game any more |

### 6 · Low — "Professional" truncates on the hy picker tile

| | |
|---|---|
| Seen | hy 390, stake games: "Պրոֆեսիո…" on the Professional tile, where the lock icon takes room (`t40-checkers-picker-390-hy.png`) |
| Fix | `GroupPicker.tsx`: allow the name to wrap (`break-words` instead of `truncate`), or move the lock icon to the second line next to "Needs N" |

## Observations (no change requested)

- **Poker table that ends because only bots are left:** the panel says "The game was played to the
  end." although the player busted on hand 1. It's accurate but flat. Consider the same
  out-of-chips wording when my result is a loss by busting.
- **Loss in a stake game:** the chips fly *out of* the header after the balance already dropped at
  the start (the bet is charged when you sit down). That's acceptable and reads as "this is what you
  lost".

## Data left tidy

- Balances set back with tinker (reason "design review"): q40b50k 0, q40b1k 148, q40mm2 18,001.
- Every table I opened was left: 0 waiting/active rooms.
- Play-group values were not touched.
- The browser is signed in as player2 again (0 Fishkas), in English.
