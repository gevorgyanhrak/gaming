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
