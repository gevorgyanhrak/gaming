# QA report — TASK-040 · Poker bots, real balance in Poker, game groups

- **Date:** 2026-11-01, about 07:50–08:45 UTC
- **Build:** `main` at b90b6f1 (d4ca892 group tiles, 7ce1f4a frontend, b90cd47 backend, edd04cf WebSocket fix)
- **Stack:** local Docker (API :8000, Horizon, Reverb, MySQL, Redis), Vite SPA :5175, headless Chrome (CDP :9333)
- **Brief:** `docs/product/briefs/TASK-040-groups-poker-bots.md`, §5 and §8
- **Method:** every result below was observed on the running stack. Money and validation checks went
  through the real HTTP API, using a Node Sanctum client through the Vite proxy. UI checks used headless
  Chrome. Ledger and DB checks used `/api/v1/me/wallet/transactions?room=` and MySQL.
- **Screenshots:** `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/qa2/`
  (file names are given in the tables; the folder is written as `qa2/`).

## Test data created (dev DB)

| User | Purpose | Balance set (tinker `adjust … 'set'`, reason "QA TASK-040") |
|---|---|---|
| q40b100 | Poker 100 | 100 |
| q40b1k | Poker 1,000 | 1,000; later 150 (bonus-at-table test) |
| q40b5k | Poker 5,000 | 5,000; later 20,000 (opponent for a UI win) |
| q40b50k | Poker 50,000 | 50,000 |
| q40b500k | Poker 500,000+ | 500,000; later 600,000 (after a 100-chip loss it was below Master) |
| q40mm1, q40mm2 | Stake games, matchmaking | 30,000 each |
| q40admin | Admin (`app:role … super_admin`) | — |

All of these have the password `password`, and their e-mails were marked verified with `forceFill`. I
also used player1 and player2 with their existing balances.

## Summary

| Area | PASS | FAIL | Notes |
|---|---|---|---|
| 1. Poker balances | 9 | 1 | Header flicker on buy-in and cash-out (BUG-2) |
| 2. Poker gameplay | 11 | 2 | Bust at a bot table (BUG-1); bot delay about 3 s, not 0.7–1.8 s (BUG-3) |
| 3. Bets and transactions | 8 | 0 | |
| 4. Other games | 13 | 0 | |
| 5. Animations | 4 | 1 | BUG-2 again: wrong "−N" badge in the header |
| 6. Admin | 4 | 0 | Values restored |
| 7. Spot checks | 5 | 2 | Badge overflow at 390 px (BUG-4); 429 on `/me` shows the page as signed out (BUG-8) |
| **Total** | **54** | **6** | Distinct bugs: 2 Major/Medium, 1 Medium, 5 Low; plus 3 observations |

The backend suite (`make test-api`): 325 passed, 0 failed.

## 1. Poker balances

| # | Scenario | Steps | Expected | Actual | Result | Evidence |
|---|---|---|---|---|---|---|
| 1.1 | 100 Fishkas: groups | Log in as q40b100, open /games/poker | Beginner open; the other 4 locked with "Needs N" | Beginner open; Intermediate "Needs 1,000", Experienced "Needs 10,000", Professional "Needs 50,000", Master "Needs 500,000", all locked | PASS | qa2/p1-groups-q40b100.png |
| 1.2 | 1,000 | same | Beginner and Intermediate open | as expected | PASS | qa2/p1-groups-q40b1k.png |
| 1.3 | 5,000 | same | Beginner and Intermediate open | as expected | PASS | qa2/p1-groups-q40b5k.png |
| 1.4 | 50,000 | same | Everything up to Professional open; Master locked | as expected | PASS | qa2/p1-groups-q40b50k.png |
| 1.5 | 500,000 | same | All 5 open | as expected | PASS | qa2/p1-groups-q40b500k.png |
| 1.6 | Server group check | q40b1k POST /rooms with group experienced, master, or nope | 422 | 422 "You need at least 10,000 / 500,000 Fishkas to play in this group."; for nope, 422 "This group is not available" | PASS | API log |
| 1.7 | Buy-in = whole balance | Start bot tables: 100 (beginner), 149 (beginner), 4,808 (intermediate, UI), 5,000, 50,000, 500,000, 600,000 (master) | Your stack = your whole wallet; wallet 0; bots get the same stack | Stacks [100,100,100], [149×3], [4,808×4], [5,000×3], [53,106×2], [600,000×6]. Wallet 0 and `at_table` = buy-in. The UI says "You bring your whole balance to the table: 4,808 Fishkas. Blinds 2/5." | PASS | qa2/p3-picker-intermediate.png, qa2/p3-table-start.png |
| 1.8 | Blinds per group | Same tables | sb = max(1, ⌊bb/2⌋), bb = minimum bet | beginner 1/1, intermediate 2/5, experienced 5/10, professional 10/20, master 50/100 | PASS | |
| 1.9 | Header during play | At the table, read the header and `/me/wallet` | wallet + chips | Header 4,808 = 0 + `at_table` 4,808 (stack 4,803 plus 5 in the pot); aria label "4,808 of them at a table". After hands it shows 4,803 → 4,793 → 9,586 → 19,232 | PASS | qa2/p4-fold-result.png |
| 1.10 | Header at buy-in and cash-out | Watch the header text every 50 ms through Play again and through the final hand | No false intermediate values | Buy-in: 19,232 → **0 "−19,232"** → 19,232 "+19,232". Win cash-out: header briefly double-counted, then showed a red **"−19,192"** badge while the panel said +14,424. See BUG-2 | **FAIL** | qa2/p5-gameover.png |
| 1.11 | Wallet and history after a win | q40b5k won a 4-seat table (buy-in 4,808, cash-out 19,232) | Panel shows +14,424; ledger has a buy-in and one cash-out | Panel "+14,424 Fishkas"; ledger `poker_bet −4,808`, `poker_winning +19,232`; /wallet lists both with running balances | PASS | qa2/p5-gameover.png, qa2/w1-wallet-q40b5k.png |
| 1.12 | Wallet and history after a loss | q40b5k went all-in for 19,232 and lost, then pressed Resign | −19,232; nothing comes back | Panel "−19,232 Fishkas"; ledger only `poker_bet −19,232`; wallet 0; the bonus becomes available | PASS | qa2/p6-after-resign.png |

## 2. Poker gameplay

| # | Scenario | Steps | Expected | Actual | Result | Evidence |
|---|---|---|---|---|---|---|
| 2.1 | Bots act within seconds | 6-seat Master table, timing every state version (`ptime.mjs`) | 0.7–1.8 s per bot action | 26 bot actions: min 0.64 s, **median 3.07 s**, p90 3.57 s, max **18.9 s**. The move log shows the same: bots act about 3 s apart, with occasional 14–20 s stalls. Horizon logged 14 of 486 BotTurnJobs taking 2–25 s, and one `LockTimeoutException` in TurnTimeoutJob (08:33:03). See BUG-3 | **FAIL** (minor) | qa2/ptime-master6.log |
| 2.2 | Several rounds with check, call, raise and fold | API driver with mixed actions; UI buttons Check, "Call 5", Fold, "All-in N" | Legal options offered and accepted; streets advance | Hands went preflop → flop → turn → river → over. Raises of 10, 20 and all-in were accepted; legal ranges were reported correctly, e.g. raise min 10 / max 4,995 | PASS | qa2/pdrive logs |
| 2.3 | Everyone folds | Hands where all others fold | Uncontested pot, no showdown | e.g. `pots=[{amount:15,winners:[0],hand:null}]`, `shown=[]`; 6-human table: pots of 16, 2 and 7 won uncontested | PASS | |
| 2.4 | Showdown with different hands | ~20 showdowns, checked by hand | Correct winner | Seen: high card, pair, two pair, three of a kind, straight (8-9 on 6-7-T), flush (twice), full house. I checked 6 boards by hand and every winner was right | PASS | qa2/pdrive-showdowns.log |
| 2.5 | User win | 4-seat Intermediate table, all-in every hand | Game over: "You won!" and the right net | "You won! +14,424 Fishkas"; chips fly into the header | PASS | qa2/p5-gameover.png |
| 2.6 | User loss (busted) | All-in on hand 1 and lose | Game ends for the player with a loss panel and "−N"; the account is free | **Not shown.** The table goes on with bots only: "Waiting for Nina…", only a Resign button, no result, and new games are refused ("Finish one of your current games first"). If the tab is closed, the presence sweep ends it as "You ran out of time". See BUG-1 | **FAIL** | qa2/p5-gameover2.png, qa2/p5-busted-bottom.png, qa2/p2-busted-6seat.png |
| 2.7 | 6 seats, different stacks | Private 6-seat Beginner table: q40b1k 1,000, q40b5k 5,020, q40b50k 50,000, q40mm1 30,000, player1 5,000, player2 5,765 | Each stack = that player's wallet | Starting stacks [1,000; 5,019+1; 49,999+1; 30,000; 5,000; 5,765]. The 1k player went all-in and busted on hand 1; side pots paid out | PASS | qa2/multi.log |
| 2.8 | Play to the end; Play again | Win the 4-seat game, press Play again | New table: same group and size, whole balance | New room "Intermediate group · Blinds 2/5", 4 seats, every stack 19,232 | PASS | |
| 2.9 | Reconnect mid-hand | On hand 2, river, own turn: snapshot, `location.reload()`, snapshot | Same state | Before and after: v28, board 3H 3D 7H 8D 3S, hole 5C 9S, stacks 4,793/4,798/0/0, pot 9,641, buttons Fold / Call 4793 / Resign. Identical | PASS | qa2/p4-after-reload.png |
| 2.10 | Leave mid-hand | On the 6-human table, 4 players left at preflop or turn with chips in the pot; also Resign in the UI | Their stack goes back once; chips in the pot stay | e.g. q40mm1 left preflop with 1 committed and got 29,620 (stack); q40b5k left on the turn and got 4,808. Each has exactly one `poker_winning` | PASS | qa2/multi.log |
| 2.11 | Chips conserved | Sum of stacks before and after the 6-human game | Equal | Buy-ins 96,785 = cash-outs 96,785 | PASS | |
| 2.12 | Blinds double every 10 hands | Heads-up, check/fold, 18 hands | Doubled at hand 11 | Hands 1–10: 10/20; hand 11: 20/40 | PASS | qa2/pdrive-blinds.log |
| 2.13 | Pause after each hand | Time between `over` and the next deal | 3–5 s | 5.1 s and 6.3 s measured (this includes the bot delay); "Next hand · 4 seconds left" banner shown | PASS | qa2/p6-resign-dialog.png |

## 3. Bets and transactions

| # | Scenario | Steps | Expected | Actual | Result |
|---|---|---|---|---|---|
| 3.1 | Raise below the minimum or BB | Professional table (BB 20), legal raise 40–53,106: send 39, 10, 0, −50 | 422 | 422 "This move is not allowed." for each | PASS |
| 3.2 | Raise above your chips | 53,107; 999,999,999 | 422 | 422 for each | PASS |
| 3.3 | Malformed raise | `"100"` as a string, `"40.5"`, extra field, type `bet`, type `win` | 422 | 422 for each | PASS |
| 3.4 | Stale version, out of turn | `expected_version` − 1; act when it isn't your turn | 409 or 422 | 409 "The game has moved on"; 422 "It is not your turn." | PASS |
| 3.5 | Repeated action (same `client_action_id`) | Post the same min-raise twice | Applied once | Both 200; version advanced once (v2); stacks changed once | PASS |
| 3.6 | No negative balances | MySQL: `min(balance)` and `min(balance_after)` over 203 transactions | ≥ 0 | 0 and 0 | PASS |
| 3.7 | No double payouts (leave vs end race) | 5 parallel leaves on a bot table; heads-up private table where both players leave at once (×2 each) | One cash-out per seat | Bot table: statuses 409,200,409,409,200, one `poker_winning`. Heads-up: one `poker_winning` each (4,808 / 3,683); no duplicate idempotency keys in the ledger | PASS |
| 3.8 | Near-simultaneous starts | 6 parallel POST /rooms for poker; 5 parallel for Nardi with bet 3,000 | One game, one charge | Poker: one 201 and five 422 ("Finish one of your current games first"), one buy-in. Nardi: one 201, one `game_stake −3,000` | PASS |

The bonus can't be taken while chips are at a table: with wallet 0 and `at_table` 150, `refill.available`
was false and POST refill returned 422 `fishka_bonus_unavailable`. After leaving it became available.

## 4. Nardi, Checkers, Chess, Durak

| # | Scenario | Steps | Expected | Actual | Result | Evidence |
|---|---|---|---|---|---|---|
| 4.1 | Groups listed and locked by balance | q40b50k (52,706) on /games/checkers | Beginner, Casual and Experienced open; Pro and Master locked | as expected; tiles show "From / Needs" and "Bet min–max" | PASS | qa2/s1-checkers-picker.png |
| 4.2 | Bet slider | Choose Casual; Min, Max, set 6,000 | Range 2,000–10,000 | min 2000, max 10000, step 50; Min → 2000, Max → 10000; label "Your bet"; shows "The winner takes the pot: …" | PASS | qa2/s2-casual-6000.png |
| 4.3 | Bet range on the server (each of the 4 games) | Private rooms: casual 1999, 2000, 10000, 10001, −5, 0; beginner 499, 2001; experienced 10000; professional 50000; no group or bet | Inside the range → 201 and the bet is charged; outside → 422 | Identical for Nardi, Chess, Checkers and Durak. 2000 and 10000 accepted, charged and refunded on close. 1999 and 10001 → 422 "Choose a bet from 2,000 to 10,000". −5 and 0 → 422. Beginner 499 and 2001 → 422. Professional → 422 group balance. Default → beginner, bet 500 | PASS | qa2/stakebets |
| 4.4 | Bet above balance but inside the range | q40b50k (45,706): Experienced, bet 50,000 | 422 | 422 "You need at least 50,000 Fishkas to play in this group." | PASS | |
| 4.5 | Win pays the pot (human vs human) | Private 2-seat room; the opponent leaves | Winner gets bet × 2 | Nardi 3,000 → payout 6,000; Chess 2,500 → 5,000; Checkers 1,500 → 3,000; Durak 4,000 → 8,000. The loser has only the stake | PASS | |
| 4.6 | Win vs bot (house matches the bet) | Nardi casual 2,000 vs Easy (API random play) | Payout 4,000 | Won after 79 moves: `game_payout +4,000`, `game_stake −2,000` | PASS | qa2/nardi.log |
| 4.7 | Durak 3 seats: everyone but the loser shares | Durak casual 2,000, me + 2 Easy bots | Two winners share 6,000 → 3,000 each | Results me win, Alex loss, Igor win; my payout 3,000 | PASS | qa2/durak3.log |
| 4.8 | Loss returns nothing | Checkers vs bot (lost on the board); Checkers 6,000 resigned in the UI; Durak abandoned | Only the stake | Each ledger has only `game_stake`; panel "−6,000 Fishkas" | PASS | qa2/s4-checkers-lost.png |
| 4.9 | Draw returns the bets | Chess private 1,000: offer_draw + move, opponent accept_draw | Both get 1,000 back | Results draw/draw; each `game_stake −1,000`, `game_payout +1,000` ("draw, bet returned") | PASS | |
| 4.10 | Matchmaking: same group matches | q40mm1 + player2, Nardi casual; player1 + player2: Chess, Checkers, Durak beginner | One matchmaking room at the group's minimum bet | Nardi: room casual, stake 2,000, each charged 2,000. Chess and Checkers: beginner, 500. Durak: matched after 23 s (table wait 20 s), 2 seats, 500. Poker beginner: matched after 27 s, stacks 5,179 / 12,000 (each person's whole balance) | PASS | |
| 4.11 | Matchmaking: different groups don't match | q40mm1 casual + player2 beginner (Nardi), wait 8 s | No room | Both still queued and no room; A's queue shows waiting 1 | PASS | |
| 4.12 | Queue for a group you can't afford | player2 (3,567) queues experienced | 422 | 422 "You need at least 25,000 Fishkas…" | PASS | |
| 4.13 | Play again keeps the group and bet | Checkers Casual 6,000 vs bot, resign, Play again | New table, Casual, 6,000 | "Casual group · Bet 6,000" on the new room | PASS | qa2/s3b.png |

## 5. Animations

| # | Scenario | Steps | Expected | Actual | Result | Evidence |
|---|---|---|---|---|---|---|
| 5.1 | "+N / −N" matches the ledger | Poker win, poker loss, Checkers loss, Chess win (UI) | Net of the room's ledger | +14,424 (−4,808 +19,232); −19,232; −6,000; +5,000 (−5,000 +10,000). All match | PASS | qa2/p5-gameover.png, qa2/p6-after-resign.png, qa2/s6-chess-won.png |
| 5.2 | Chips fly once | Count fixed chip elements added to `<body>` | One flight (6 chips) | 6 chips per result (poker loss, chess win) | PASS | |
| 5.3 | No flight on reload | Fresh document load of the finished room (observer injected before page scripts) | 0 chips; header steady | 0 chips; header "19,232" only; panel still "+14,424" | PASS | |
| 5.4 | Header ends at the server balance | Compare the final header with `/me/wallet` | Equal | 19,232 = 19,232; 45,706 = 45,706; 0 = 0 | PASS | |
| 5.5 | Header delta badge reflects the transaction | Watch the header during the poker result | "+40" (the last hand's net) or none | Shows **"−19,192"** in red on a win (and "−19,232 / +19,232" at every buy-in). See BUG-2 | **FAIL** | qa2/p5-gameover.png |

## 6. Admin — Games → Play groups

| # | Scenario | Steps | Expected | Actual | Result | Evidence |
|---|---|---|---|---|---|---|
| 6.1 | List | /admin/play-groups as q40admin | 10 groups in 2 sections | Poker (chips) ×5, All other games (stakes) ×5, matching the brief | PASS | qa2/a2-playgroups.png |
| 6.2 | Edit, with validation | Poker Beginner min bet 1 → 4; stake Beginner max 2000 → 400 (invalid), then max 3000 and min balance 600 | Saved; invalid value refused | Saved; "The maximum bet field must be greater than or equal to 500." for 400; `play_group.updated` audit rows (actor 16) | PASS | qa2/a3-edit-modal.png, qa2/a5-invalid.png, qa2/a6-saved-stake.png |
| 6.3 | Change applies to new tables | `/games/*` groups; new poker table; Nardi bets 2500 and 3001 | New values | API groups show 600/500/3000 and bb 4; new poker table blinds 2/4; Nardi 2500 → 201, 3001 → 422 "from 500 to 3,000" | PASS | |
| 6.4 | Restore | Set back to 1, and 500/500/2000 | Original seed values | DB matches the brief again (all 10 rows checked) | PASS | qa2/a7-restored.png |

## 7. Spot checks

| # | Scenario | Actual | Result | Evidence |
|---|---|---|---|---|
| 7.1 | Lobby 1440 and 390 (en) | Fine, no horizontal scroll | PASS | qa2/x-en-lobby-1440.png, qa2/x-en-lobby-390.png |
| 7.2 | Group picker ru and hy at 390 | Translated (e.g. "Мастер · Нужно 500 000 · Ставка 100 000–500 000"); no overflow (scrollWidth 390) | PASS | qa2/x-ru-nardi-390.png, qa2/x-hy-durak-390.png |
| 7.3 | Room header en, ru and hy | "Beginner group · Bet 500", «Новичок», «Սկսնակ» խումբ; fits at 1440 | PASS | qa2/y-*-durak-room-*.png |
| 7.4 | Room badge at 390 (en, poker) | "Beginner group · Blinds 1/1" overflows: right edge at 396 px in a 390 px viewport, text clipped | **FAIL** (BUG-4) | qa2/z-en-poker-room-390.png |
| 7.5 | Console | Only the React Router dev warning "No `HydrateFallback` element…" (not new); no exceptions | PASS | |
| 7.6 | Fast navigation | After ~10 full page loads in under a minute, `/api/v1/me` returned 429 and the lobby rendered signed out (Login / Register) for player2 until the limit reset | **FAIL** (BUG-8, Low) | qa2/x-hy-lobby-1440.png |
| 7.7 | Wallet page | History matches the ledger. Dates are English ("Nov 1, 2026, 12:41 PM") even in the hy UI (observation) | PASS | qa2/w1-wallet-q40b5k.png |

## Bugs

### BUG-1 — Major — A busted player at a poker bot table gets no result and stays stuck in the game

**Steps**
1. Start a Poker bot table, e.g. Intermediate with 3 bots.
2. Go all-in and lose the hand, so your stack is 0.

**Expected:** the game is over for you. You see the loss panel with "−N Fishkas" and chips flying out, and
you can start another game.

**Actual:**
- The table keeps dealing among the bots. Your seat shows "OUT 0" and the panel says "Waiting for Nina…".
  The only control is Resign.
- POST /rooms is refused with "Finish one of your current games first." until you resign.
- If you close the tab, the presence sweep ends the game 15 s later. The panel then reads "You ran out of
  time. A player left the game." and the result is "Timed out".
- Bots keep using queue time for a table nobody is watching.

This also applies to the brief's "same logic must work when the user loses": the loss animation is only
shown after a manual Resign.

**Evidence:** qa2/p5-gameover2.png, qa2/p5-busted-bottom.png, qa2/p2-busted-6seat.png; room
01M6B2WARPST0E969JGR1YHPB8.

**Suspected:**
- `apps/api/app/Games/Poker/Engine/PokerEngine.php` — `outcome()` only ends when one seat holds all the
  chips.
- `apps/api/app/Gameplay/Actions/GameActionService.php` — when no person with chips is left at a bot
  table, end the room, or depart the busted person and finish their participation.

### BUG-2 — Medium — The header balance flickers and shows a wrong red "−N" at poker buy-in and cash-out

**Steps**
1. Sit at a poker table, then win or lose so the game ends; or press Play again.
2. Watch the header pill.

**Expected:** the header moves from the old total to the new one once, with a delta that matches the
transaction.

**Actual:**
- At buy-in: 19,232 → "0, −19,232" → "19,232, +19,232".
- At a win cash-out: it briefly shows balance + stale `at_table` (about double), then drops with a red
  "−19,192" badge, while the panel says "+14,424".

**Cause, confirmed in code:** the `wallet.updated` handler in `apps/web/src/features/wallet/hooks.ts`
(`useWalletSync`, lines 32–36) patches `balance` from the event but keeps the old `at_table`.
`FishkaBalance.tsx` adds the two and animates every change. Suggested fix: send `at_table` in the event and
patch both, or just invalidate and refetch without patching.

**Evidence:** qa2/p5-gameover.png; recorded header sequence in this report.

### BUG-3 — Medium/Low — Bots take about 3 s per action, with occasional 15–25 s stalls

**What was promised:** a bot acts after a random 0.7–1.8 s delay.

**Observed (6-seat table, 26 bot actions):** median 3.07 s, p90 3.57 s, max 18.9 s. In separate runs, the
move log showed 14 s and 17 s gaps. Horizon logged 14 of 486 BotTurnJobs taking 2–25 s, and one
`LockTimeoutException` from `TurnTimeoutJob` at 08:33:03 (`RoomStateStore::locked` `block(5)`). In one
early run the long stall meant my driver's polls didn't run and the player was swept as abandoned.

**Likely causes:**
- The Horizon worker's default `sleep` of 3 s when the queue is empty adds up to 3 s to every delayed job.
- Long bot jobs (e.g. hard Nardi or Chess bots; one hard-bot unit test takes 9 s) share the `game` queue,
  which has at most 3 processes locally.

**Suspected:** `apps/api/config/horizon.php` (supervisor `sleep`, a dedicated `game` supervisor),
`apps/api/app/Gameplay/Lifecycle/GameScheduler.php`.

### BUG-4 — Low — The room group badge overflows at 390 px

**Steps:** open any poker room at 390×844 in English.

**Actual:** "Beginner group · Blinds 1/1" ends at x = 396 in a 390 px viewport and the text is clipped.
Longer names ("Professional group · Blinds 10/20") will be worse.

**Evidence:** qa2/z-en-poker-room-390.png.

**Suspected:** `apps/web/src/pages/RoomPage.tsx` (~line 130, the subtitle row needs `flex-wrap` and
`min-w-0`) and `apps/web/src/features/play/components/GroupBadge.tsx`.

### BUG-5 — Low — An abandoned game says "You ran out of time"

When the presence sweep ends the game because the player left the page, the panel title is "You ran out of
time." with "A player left the game." and the badge "Timed out".

**Evidence:** qa2/p2-busted-6seat.png. Probably not new in this change.

**Suspected:** `apps/web/src/features/play/components/GameOverPanel.tsx` (wording for `end_reason`
abandon).

### BUG-6 — Low — The "insufficient Fishkas" message has unformatted numbers and the wrong minimum

q40b100 with 99 Fishkas asked for Intermediate and got "You need 1000 Fishkas to play this game."
- The number has no thousands separator, unlike the group messages ("10,000").
- It names the requested group's minimum, although the player is below the lowest group (100).

**Suspected:** `apps/api/app/Gameplay/Rooms/RoomStakes.php` `ensureCanAfford()` (passes `$needed` of the
requested group) and the `InsufficientFishkas` message.

### BUG-7 — Low — Pot and bet labels at the poker table have no thousands separators

The table shows "Pot: 9641 chips" and "Bet 9611", while stacks show "4,793".

**Suspected:** `apps/web/src/games/poker/PokerBoard.tsx` line 159 (`t('poker.potAmount', { count:
view.pot })` — format the number), and the bet chip label.

### BUG-8 — Low — A 429 on GET /api/v1/me makes the SPA look signed out

The `account` limiter (30/min) is shared by `/me` and every `/me/*` call: wallet, rooms, stats and history.
About 10 full page loads within a minute reached it. The app then rendered the guest landing page, with
Login and Register, for a signed-in user. With the socket connected, normal use is well below the limit (a
game page made 2 `/me` and 2 `/me/wallet` calls a minute). When the socket is disconnected, the 5 s wallet
polling alone uses 12 of the 30 calls.

**Suspected:** `apps/api/app/Providers/AppServiceProvider.php` (`account` limiter) and
`apps/web/src/features/auth/hooks.ts` (treat 429 as "unknown, retry", not as signed out).

### Observations (not bugs; owner or PM to decide)

- **O-1.** Beginner poker blinds are 1/1, because the small blind is max(1, ⌊1/2⌋) = 1. This follows the
  formula but makes the small blind equal to the big blind.
- **O-2.** Wallet history dates aren't localized (English format in ru and hy).
- **O-3.** The admin audit row for a group edit stores only the new values, not the old ones.

## Not tested or limited

- **Chess or Checkers win against a bot:** not reached with random play (Checkers vs Easy was lost). The
  win payout was verified for those games against a human (4.5), and for Nardi and Durak against bots.
- **Draw:** only Chess by agreement. A Durak draw (nobody left holding cards) wasn't reached.
- **Real-phone network or WebSocket-host scenarios (the edd04cf fix):** not tested. Headless Chrome had a
  connected socket on localhost.
- **Bot timing:** measured on a local Docker stack under my own test load; production Horizon settings
  differ.

## Fix status (developer, 2026-11-01)

| Bug | Status | Fix | Verified |
|---|---|---|---|
| BUG-1 | Fixed | `GameActionService::retireBusted`; state `playing`; "You're out of chips" panel | PokerTableTest ×2, RoomPage.test (refetch + wording); live: 6-seat tables ended at the bust; 390 px loss panel −6,268 |
| BUG-2 | Fixed | `useWalletSync` refetches instead of patching `balance` | Fishka.test; live header 0 "−6,268" at a loss |
| BUG-3 | Improved | `supervisor-game` (sleep 0.25 s), lock-timeout retries, sweeps in-process | Live median 1.13–1.18 s (was 3.07); ~1 stall per 70 moves remains locally during machine-wide slowdowns |
| BUG-4 | Fixed | Header block shrinks; group name truncates | Live: badge right 264/390 |
| BUG-5 | Fixed | "You left the game." for `abandon` | RoomPage.test ("You left the game."); strings in en/ru/hy |
| BUG-6 | Fixed | Lowest group's minimum, formatted | GroupsTest |
| BUG-7 | Fixed | `{{x, number}}` in poker strings | PokerBoard.test; live "Pot: 1,568 chips" |
| BUG-8 | Fixed | Retry 429 with backoff; `account` 60/min | Fishka.test |

## Backend tests

`make test-api` was run once, at the end, after the live tests: **325 passed (163,067 assertions), 0
failed, 215.6 s.**

## Cleanup state

- No active or waiting rooms; no matchmaking tickets for the test accounts.
- Play-group values restored to the seed values (DB checked).
- Language set back to English.
- The browser is signed in as player2@example.test on the lobby.
- Test accounts remain in the dev DB with their final balances: q40b100 99, q40b1k 148, q40b5k 12,001,
  q40b50k 45,706, q40b500k 0, q40mm1 43,620, q40mm2 18,000, player1 5,178, player2 1,567.

## Re-run 2026-11-01

- **Build:** `main` at deb7618 (QA fixes fd32039, design fixes 288639d). `make restart` was run first.
- **Time:** about 21:40–23:35 local (17:40–19:35 UTC).
- **Evidence:** screenshots and logs are in
  `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/qa2/`. Re-run file
  names start with `rr`.
- **Method:** as in the first run. A Node API client drove the moves. Headless Chrome on CDP :9333 used a
  50 ms header sampler and a chip-flight counter, injected before page scripts.
- **Wins against bots:** the AI-assisted runs used the game's own Medium or Hard bot code to pick my moves
  (`qa2/ai.mjs`, which runs `ChessBot`/`CheckersBot` in the api container).

**Environment warning.** The Mac was under heavy load: load average 12–19 during testing, with one peak of
32. Docker runs under QEMU at ~180 % CPU, and other Chrome and Playwright processes were running.

Early in the run the Mac went into idle sleep three times, for about 15 minutes each: 21:57–22:07,
22:08–22:25 and 22:44–23:00 (`pmset -g log`: "Entering Sleep state … Maintenance Sleep"). During those
windows every container and my drivers froze. When the Mac woke, all the queued TurnTimeoutJobs ran at
once. Two heads-up attempts and one Checkers game were spoiled this way and were repeated.

From 23:00 I ran `caffeinate -dims` and there were no more sleeps. None of the sleep effects are app bugs.

### Fix verification

| Bug | Steps | Expected | Actual | Result | Evidence |
|---|---|---|---|---|---|
| BUG-1 (bot table) | player2 (60,000), UI: 5 seats, Intermediate, Easy, Start game; API all-in until bust | Result at once, room ends, a new game can start | Busted on hand 1. The API state turned `playing:false` and the panel appeared **254 ms** later: "You lost. −60,000 Fishkas", Play again. The room is `finished/completed` (bots win/loss) and there are no active rooms | PASS | qa2/rr1-result.png, qa2/rr1.log |
| BUG-1 (6 seats, 390 px) | player2 (119,980), Professional, 5 Easy bots | same | Busted on hand 1; panel shown at once ("−119,980 Fishkas"); room finished. Then: start at 0 → 422 `insufficient_fishkas` (not "finish your game"); bonus → +5,000; **new poker game → 201** | PASS | qa2/rr5-result.png, qa2/rr5.log |
| BUG-1 (private, 3 people) | Beginner table: player2 4,999 (browser, 390 px), q40mm1 30,000, q40mm2 30,000. player2 shoves; the others call | Busted person sees "You're out of chips."; the others play on | player2 tripled on hand 1 (14,997), then busted on hand 2. Panel: "You're out of chips. / The game goes on for the others. / −4,999 Fishkas / Back to lobby". The "Waiting for…" status is hidden. The room stayed `active` and the others kept playing | PASS | qa2/rr6-outofchips-390.png, qa2/rr6.log |
| BUG-2 (header) | 50 ms sampler through a buy-in, a hand won, a bust, a game won and Play again | No dip to 0, no false double or −N | **Buy-in:** 60,000 / 119,980 stayed steady (no dip). **Hand won:** 60,000 → 60,020 "+20". **Bust:** 60,000 → 0 "−60,000", once. **Heads-up game won:** 60,000 → 120,000 "+60,000", once. **Play again:** stayed at 120,000; leaving that table then gave 119,980 "−20" (the posted blind, correct). No false values seen | PASS | qa2/rr1.log, qa2/rr4-1.log, qa2/rr5.log |
| BUG-3 (bot speed) | `ptime.mjs`, 6 seats, Experienced, 120 s; load average 12.7 → 14.9 | 0.7–1.8 s | Client-side, n = 51: min 0.56 s, **median 1.15 s, p90 1.26 s**, max 21.3 s. The move log for the same room shows **every bot move 1–2 s apart** (n = 66, 1 s resolution, max 2 s). The 21 s outlier was my own GET /state stalling, not a bot; the bots' moves v57–v61 were 1 s apart in the log. Pauses between hands were 4.6–5.7 s | PASS (with that caveat) | qa2/rr-ptime.log |
| BUG-4 (badge, 390) | Poker Professional and Checkers Experienced rooms, en/ru/hy | No overflow | The badge is on its own row: en "Experienced group · Bet 10,000" right edge 246; ru 277; hy "«Փորձառու» խումբ · Խաղադրույք 10,000" 315; poker hy "«Պրոֆեսիոնալ» խումբ · Բլայնդեր 10/20" whole. scrollWidth 390 everywhere | PASS | qa2/rr-d-checkers-*.png, qa2/rr-d-poker-*.png |
| BUG-5 | player2 starts Nardi Casual 2,000 vs bot (390 px); the tab goes to about:blank for 30 s; reopen the room | "You left the game." | Abandoned 21 s after the start. Panel: "You left the game. A player left the game. −2,000 Fishkas" | PASS | qa2/rr-b5-left.png |
| BUG-6 | q40b100 (99) asks for Intermediate or Master poker, and Casual Nardi. Then the lowest poker group was raised to 1,500 for one request and put back to 100 | Lowest group's minimum, formatted | "You need 100 Fishkas…" and "You need 500 Fishkas…"; with 1,500: "You need **1,500** Fishkas to play this game." (the lowest group, formatted). Group value restored (DB checked) | PASS | |
| BUG-7 | Read poker labels | Thousands separators | en "won 44,991 chips", "+44,991", "+120,007"; ru stacks "57 980"; hy "Բանկ՝ 50 ժետոն" | PASS | qa2/rr6-outofchips-390.png, qa2/rr-d-poker-ru.png |
| BUG-8 | 70 raw GET /me as player2 (58×200, then 429 from the 59th), then open /games/poker | Not shown as signed out | **The header no longer shows Login/Register**: a skeleton while the request is retried, and "player2" once the limit resets (about 60 s). **But** the game page body showed the guest card "Play · **Log in to play**" during the retry window. See R-1 | PARTIAL | qa2/rr-b8-during.png, qa2/rr-b8-after.png |

### Design-review fixes (390 px, en/ru/hy)

| Check | Actual | Result | Evidence |
|---|---|---|---|
| Turn status never cut | Poker: en "Your turn / 26 seconds left" (241–373), ru "Ваш ход / Осталось 24 секунды", hy "Ձեր քայլն է / Մնաց 23 վայրկյան". Checkers: the same. No element overflows | PASS | qa2/rr-d-*.png |
| Badge on its own row on phones | Yes, below the title row (top 168), with the full group name | PASS | qa2/rr5-table.png, qa2/rr-d-poker-hy.png |
| Locked tiles readable | "Needs 100,000" / "Нужно 100 000" / "Անհրաժեշտ է 100,000": color rgb(194,171,146) at opacity 1 on the dark tile; the lock icon is still shown | PASS | qa2/rr-d-tiles-*.png |
| hy "Professional" tile name whole | Not truncated, but it wraps mid-word as "Պրոֆեսիոնա / լ" (one letter on the second line). See R-3 | PASS (minor) | qa2/rr-d-tiles-hy.png |
| Out-of-chips heading short | "You're out of chips." with "The game goes on for the others." below | PASS | qa2/rr6-outofchips-390.png |

### Not tested last time

| Scenario | Steps | Actual | Result | Evidence |
|---|---|---|---|---|
| Checkers WIN vs Easy | q40mm1, Casual 3,000, my moves by `CheckersBot` Medium | Won in 25 moves: `game_payout +6,000`, `game_stake −3,000` (= bet × 2). Again with player2, Experienced 10,000: won, `game_payout +20,000` | PASS | qa2/rr-checkers.log, qa2/rr-checkers2.log |
| Chess WIN vs Easy | q40b50k, Blitz, Casual 2,000, my moves by `ChessBot` Hard | Won in 5 moves: `game_payout +4,000` (= bet × 2). An earlier try was swept as abandoned because my synchronous helper blocked the presence timer (harness, fixed) | PASS | qa2/rr-chess.log |
| Durak draw | 12 two-person private Durak games (q40mm1 vs q40mm2, Beginner 500), both sides playing low cards to try for a simultaneous empty hand | No draw in 12 games: 11 ended win/loss; game 7 was abandoned because of my harness's request rate (see R-4). A draw was **not reached**; its payout path stays covered by the Chess draw (first run, 4.9) | NOT REACHED | qa2/durakdraw.log |
| "You left the game." after closing the tab | See BUG-5 | Swept about 21 s after the tab closed; panel and ledger correct | PASS | qa2/rr-b5-left.png |
| Leave a multi-seat poker table mid-hand | Same 3-person table, after player2's bust: q40mm2 left on the flop with 1 committed; the leave was sent twice in parallel | Leaves 200 and 409. q40mm2 got **one** `poker_winning +54,994` (the stack; the 1 in the pot stayed). q40mm1 won the pot (payouts [2,0,0]) and the game ended. Chips conserved: 64,999 in, 64,999 out | PASS | qa2/rr6.log |

### Regression smoke

| Check | Actual | Result | Evidence |
|---|---|---|---|
| Register | UI /register: e-mail, username q40reg2317, password; the code came from Mailpit; Verify → /dashboard, header 5,000, `registration_bonus +5,000` | PASS | qa2/rr-reg-form.png, qa2/rr-reg-verified.png |
| Log in and daily bonus | `daily_bonus_on` set to yesterday in the DB; log out; UI /login → /dashboard, header 6,000, `daily_login_bonus +1,000` | PASS | qa2/rr-login-daily.png |
| 0-Fishka popup and 5,000 bonus | q40b500k (0) on /games/nardi at 390, Start game → "Your Fishkas Have Run Out! … Get 5,000 Free Fishkas". Click → "+5,000 Fishkas! Your balance is now 5,000"; header "5,000 +5,000"; ledger `insufficient_balance_bonus +5,000`. The API path also worked after the bust (refill 201) | PASS | qa2/rr-zero-popup.png, qa2/rr-zero-bonus.png |
| Every game above Beginner | Poker Intermediate and Professional (above); Checkers Casual and Experienced (won); Chess Casual (won); Nardi Casual (BUG-5) and Experienced 10,000 vs bot (stake charged; swept by my harness, R-4); Durak Casual 4,000 with 2 bots: won with another winner, `game_payout +6,000` (= 4,000 × 3 / 2) | PASS | qa2/rr-durak.log, qa2/rr-nardi.log |

### New and remaining findings

**R-1 — Low — the game page shows "Log in to play" to a signed-in user while `/me` is being retried
(BUG-8 remainder)**
- **Steps:** use up the `account` limit for one user (more than 60 calls to `/me` within a minute), then
  open /games/poker as that user.
- **Expected:** a loading state.
- **Actual:** for about 60 s the play card shows "Log in to play", which links to /login. The header shows
  a skeleton, and the page recovers by itself.
- **Evidence:** qa2/rr-b8-during.png.
- **Suspected:** `apps/web/src/features/play/components/PlayPanel.tsx:107` (`if (!user)` treats "still
  loading or retrying" as signed out; use the query status).

**R-2 — Low — side-seat bet labels at a 4-seat poker table are clipped at 390 px in ru and hy**
- **Steps:** open a poker bot table with 3 bots at 390×844 and switch to hy (or ru).
- **Expected:** "Bet N" stays on screen.
- **Actual:**
  - hy: the right-hand seat's label shows only "Խաղադրույք" and the amount is off-screen. The left label
    "Խաղադրույք 10" starts at x = 0 and overlaps the "Hand 1 · blinds 10/20" caption.
  - ru: "Ставка 10" / "Ставка 20" touch both screen edges, with the chip icon cut.
  - en fits ("Bet 10", "Bet 20").
- **Evidence:** qa2/rr-d-poker-hy.png, qa2/rr-d-poker-ru.png, qa2/rr-d-poker-en.png.
- **Suspected:** `apps/web/src/games/poker/PokerBoard.tsx` (bet chip position `spot(seat, …)` for side
  seats has no clamp to the board width; a long hy word "Խաղադրույք"). This may predate TASK-040 — the bet
  label strings changed in fd32039.

**R-3 — Low (cosmetic) — the hy "Professional" tile name breaks mid-word**
- **Actual:** the name shows as "Պրոֆեսիոնա / լ", with one letter on the second line.
- **Evidence:** qa2/rr-d-tiles-hy.png.
- **Suspected:** `apps/web/src/features/play/components/GroupPicker.tsx` (`break-words` on a single long
  word; consider moving the lock icon to the "Needs" line or using `hyphens-auto`).

**R-4 — Observation — the presence timeout is shorter than the 429 backoff**
- Two of my API-driven games (Nardi Experienced, one Durak) were swept as abandoned. My drivers poll every
  200–350 ms, which reaches the `game` limiter (240/min); their presence calls then waited for the 429 retry
  and missed the 15 s window. This is a harness artifact and the normal page is far below the limit.
- Still worth knowing: since fd32039 the SPA retries 429 with about 45 s of backoff, while
  `matchmaking.abandon_after_seconds` is 15. Any 429 on `/presence` would cost a player the game.
- Consider exempting `/rooms/{id}/presence` from the backoff or the limiter.

### Re-run summary

- **Fixes:** 7 of 8 bugs fixed and verified live. BUG-8 is partly fixed (R-1).
- **Design review:** all 5 checks pass; the hy mid-word break is R-3.
- **Previously untested cases:** Checkers and Chess wins against Easy pay bet × 2; "You left the game."
  works; a mid-hand leave pays out once. The Durak draw was not reached in 12 games.
- **Regression smoke:** registration, login with the daily bonus, the 0-Fishka popup and the 5,000 bonus,
  and every game above Beginner all pass.
- **New findings:** R-1, R-2 and R-3 (Low), plus observation R-4.

### Re-run cleanup

- There are no waiting or active rooms (DB checked).
- Play groups are at the seed values (all 10 rows checked; the beginner poker min balance was set back to
  100).
- The browser is signed in as player2@example.test, in English, on the lobby.
- `caffeinate` was stopped at the end.
- Balances set for the re-run with `adjust(…, 'set', …, 'QA re-run')`: player2, q40b5k, q40b50k, q40mm1,
  q40mm2, q40b1k and player1. Final balances: player2 60,000, player1 30,000, q40b100 99, q40b1k 19,890,
  q40b5k 20,000, q40b50k 60,000, q40b500k 5,000, q40mm1 7,005, q40mm2 57,994.
- New account: q40reg2317 (6,000).

### Re-run fix status (developer, 2026-11-02)

| Finding | Status | Fix | Verified |
|---|---|---|---|
| R-1 | Fixed | `PlayPanel` shows a placeholder while the account is unknown (loading or a retried 429), "Log in to play" only for a real guest (`null`); the landing page's Join button likewise | Play.test ("never asks a signed-in player to log in…", fails without the fix); Fishka.test now runs with the app's real retry policy |
| R-2 | Fixed | Phone bet chips show the amount only (screen readers hear "Bet N"); side seats' chips line up towards the middle | Live hy 390, 4 seats: side bet "20" fully on screen |
| R-3 | Fixed | The lock moved to the "Needs N" line; the name has its row to itself | Live hy 390 (balance 20,000): locked "Պրոֆեսիոնալ" whole on one line |
| R-4 | Fixed | `/rooms/{id}/presence` has its own limit (`presence`, 60/min) instead of the shared `game` limit | PresenceTest ("takes presence reports even when the player has used up the game limit") |
