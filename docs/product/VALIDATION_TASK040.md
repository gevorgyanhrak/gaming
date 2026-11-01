# PM validation — TASK-040 · Poker bots, real balance in Poker, game groups

- **Date:** 2026-11-01 · **Build:** `main` at f31322c (code: b90cd47, 7ce1f4a, d4ca892, edd04cf, QA fixes fd32039)
- **Against:** owner brief `docs/product/briefs/TASK-040-groups-poker-bots.md` (verbatim) §1–§8; owner
  decisions 2026-11-01 (DECISIONS.md); ADR 0016; IMPLEMENTATION_REPORT.md#task-040; `docs/qa/QA_REPORT_TASK040.md`
  (54 pass / 6 fail, 8 bugs, "Fix status").
- **PM's own checks (read-only):** `GET /api/v1/games/{poker,nardi,chess,checkers,durak}` on the local
  stack. All 10 groups match the brief's tables exactly. I also read the code for the bot logic, group
  colours, the refill threshold and payment/transfer paths, and checked that the test names below exist.
  I ran no backend tests (shared testing DB); 325 passed per QA.

## Verdict: **ACCEPT WITH FOLLOW-UPS**

Every brief requirement is met or partly met. Nothing is "Not met". The QA run found 8 bugs; the
developer fixed all of them with tests and live checks, but **no independent QA re-run** has been done
yet. The re-run is follow-up TASK-039 (e). BUG-3 (bot stalls) does not block; see §B. Two conditions
before the owner's final report:
1. The UI/UX design review of TASK-040 is still pending (PROGRESS step 8b; nothing in DESIGN_REVIEW.md
   yet). If it requires a revision, the task returns to NEEDS_REVISION.
2. Owner question 6 now matters more: stake games let a friend receive up to 500,000 Fishkas per game
   (see §C).

## A. Requirement by requirement

Status: **Met** / **Partly** / **Not met**. Evidence: QA row (`QA x.y`), test (`File › it(...)`), live
or code check.

### §1 Poker with bots; win/lose animation
| Requirement | Status | Evidence |
|---|---|---|
| Bots move automatically, realistic, follow the rules | Met | Root cause: the page never heard the moves (socket host pinned to an old LAN IP), fixed in edd04cf; `PokerBot` uses Monte Carlo equity vs pot odds (Medium/Hard) and random play (Easy); QA 2.2, 2.4 |
| Decisions in reasonable time | Met (see §B) | QA fix status BUG-3: median 1.13–1.18 s, p90 1.3–1.5 s (was 3.07 s); ~1 stall of ~20 s per 70 moves locally |
| Game continues until the round ends; bet/check/fold/call/raise/win/lose | Met | QA 2.2, 2.3, 2.4, 2.12, 2.13; PokerTableTest › "accepts a raise of any size within the range and rejects others" |
| Win animation; Fishkas fly to the balance; amount = actual transaction | Met | QA 5.1 (+N = ledger net for poker win/loss, Checkers loss, Chess win), 5.2 (one flight), 5.4 (header = server) |
| No repeated balance updates from events/animations | Met | QA 5.3 (no flight on reload); BUG-2 fixed: Fishka.test › "moves Fishkas between a table and the wallet without a false jump"; not re-run by QA |
| Same logic on a loss | Met | QA 1.12, 4.8; BUG-1 fixed: PokerTableTest › "ends a person's game when they lose all their chips while the others play on", "ends a bot table when the person at it has lost all their chips"; RoomPage.test › "ends the game for a player who lost all their chips…"; developer live −6,268 at 390 |

### §2 Correct balance in Poker
| Requirement | Status | Evidence |
|---|---|---|
| Real backend balance; play with all Fishkas (5,000 → not 1,000) | Met | QA 1.7: buy-in = whole wallet for 100…600,000; bots get the people's average stack |
| Correct everywhere; header in sync wallet ↔ table ↔ other games | Met | QA 1.9 (header = wallet + `at_table`), 5.4; GroupsTest › "counts chips at a poker table in the wallet, and gives no free bonus while they are in play" |
| No hardcoded or stale values; updated after every transaction | Met | `wallet.updated` → refetch (BUG-2 fix); polling when the socket is down (edd04cf); QA 1.10/5.5 failed before the fix, fixed per developer (not re-run) |
| Backend is the single source of truth | Met | ADR 0015/0016: `FishkaWallets` is the only writer; UI reads `/me/wallet` |

### §3 Five groups per game
| Requirement | Status | Evidence |
|---|---|---|
| Five groups; entry requirements; minimum balance; minimum bet | Met | Live API (PM): 5 chip groups + 5 stake groups for every game; GroupsTest › "lists the five groups of each kind on the game page" |
| Appropriate matchmaking; separate tables/rooms | Met | Queues per mode+group (ADR 0016); QA 4.10 (same group matches), 4.11 (different groups don't) |
| Clear visual identity | Partly | Name + one colour per group (`features/play/groups.ts` `groupColors`) on picker tiles and the room badge; locked tiles with a lock icon (QA 1.1). No group artwork/icons; the UI/UX review is pending and should judge "clear" |

### §4 Poker group values and logic
| Requirement | Status | Evidence |
|---|---|---|
| Table values (100/1; 1,000/5; 10,000/10; 50,000/20; 500,000/100) | Met | Live API (PM); QA 6.4 (DB restored to seed) |
| Entry only with the minimum balance; never deducted | Met | QA 1.1–1.6; GroupsTest › "lets players into every group they can afford, and no higher, without charging the minimum" |
| No bets below the group minimum | Met | Min bet = big blind; QA 1.8, 3.1; GroupsTest › "plays poker groups with their blinds and refuses groups above the balance". Observation O-1: Beginner blinds are 1/1 |
| Can't enter unaffordable tables; locked groups shown; enter any affordable group | Met | QA 1.1–1.5, 1.6, 4.12 |
| Validated on the backend | Met | QA 1.6, 3.1–3.3 (all 422) |

### §5 Poker test matrix
| Requirement | Status | Evidence |
|---|---|---|
| Balances 100 / 1,000 / 5,000 / 50,000 / 500,000+ | Met | QA 1.1–1.7 |
| Sync wallet ↔ Poker; updates after win and loss | Met | QA 1.9, 1.11, 1.12; BUG-2 fixed (not re-run) |
| Bot decisions, several rounds, check/call/raise/fold, all fold | Met | QA 2.2, 2.3 |
| User wins / loses; different combinations | Met | QA 2.4 (high card … full house; 6 boards checked by hand), 2.5; 2.6 failed → BUG-1 fixed (tests + developer live) |
| Different balances at one table; completion and restart | Met | QA 2.7, 2.8, 2.11 (chips conserved 96,785 = 96,785) |
| Disconnect / reconnect | Partly | QA 2.9 reload mid-hand identical; 2.10 leave mid-hand. A real network drop (phone/LAN, socket host) wasn't tested → TASK-039 (e) |
| No negative balances; no duplicate transactions; no double payouts; bets validated; simultaneous updates safe | Met | QA 3.5–3.8 (ledger min 0; idempotent action ids; parallel leave → one cash-out; 6 parallel starts → one buy-in) |
| "Not completed until these pass" | Partly | All rows pass after the fixes, but the 6 failed rows were re-checked by the developer, not by QA → TASK-039 (e) |

### §6 Groups for all other games
| Requirement | Status | Evidence |
|---|---|---|
| Five groups, values per table (500/500–2,000 … 500,000/100,000–500,000) | Met | Live API (PM) for Nardi, Chess, Checkers, Durak |
| Central configuration, not per game | Met | One `play_groups` table, admin-editable and audited (QA 6.1–6.4; PlayGroupAdminTest) |
| Own rooms, matchmaking, bet validation, balance sync per game | Met | QA 4.3 (bounds identical in all 4 games), 4.10–4.13 |
| Win/lose/draw money (owner decisions 2026-11-01) | Partly | Pot to the winner: QA 4.5 (all 4 vs human), 4.6 (Nardi vs bot, house matches), 4.7 (Durak split), 4.9 (Chess draw); GroupsTest › "gives the pot to the winner…", "returns the bets after a draw, once". Not live: a Chess/Checkers win vs a bot, a Durak draw → TASK-039 (e) |
| Bet replaces the 500 fee; creator picks the bet; matchmaking at the group minimum | Met | QA 4.2, 4.3, 4.10, 4.13 |

### §7 Architecture and quality
| Requirement | Status | Evidence |
|---|---|---|
| One wallet service, no duplicated logic, central group config | Met | ADR 0015 (`FishkaWallets` sole writer), ADR 0016 (`PlayGroups`/`TableTerms`, `RoomStakes`; game-agnostic via `PlaysForChips`) |
| Every financial operation validated on the backend; ledger consistent | Met | QA §3; per-seat idempotency keys |
| Nothing else broken (registration, login, rewards, other games); design system kept | Met (tests) | `make test-api` 325 passed (QA run); web vitest 128 passed (re-run by PM, 2026-11-01). Design-system judgement waits for the UI/UX review. Finding: the refill bonus still uses the old `fishka.entry_fee` (500) as its threshold (`FishkaWallets::refillStatusOf`), while the fee itself is gone for grouped tables → owner question 7 |

### §8 Final verification and report
| Requirement | Status | Evidence |
|---|---|---|
| All areas tested; every found issue fixed; don't stop after Poker | Met | QA covered Poker, all 4 other games, admin and spot checks; 8/8 bugs fixed (BUG-3 improved) |
| Report: what was wrong, what was fixed, new functionality, files, tests, remaining issues | Partly | IMPLEMENTATION_REPORT#task-040 and the QA report cover all of these. The final report **to the owner** is still to be sent (PROGRESS 8b, after the UI/UX review) |

## B. BUG-3 (bot speed): not blocking; follow-up on staging

- The brief asks for "decisions within a reasonable time". A median of ~1.15 s (p90 ≤ 1.5 s) meets that,
  and is close to the intended 0.7–1.8 s human-like delay.
- The remaining ~1 stall of ~20 s per 70 moves appeared only during machine-wide slowdowns: load
  average 12–15, Docker under QEMU, every container slowed at once, no slow code path in the probes.
  That says little about production.
- Players can't lose because of it: a 20 s stall is below the 30 s Poker turn timer, and game jobs now
  retry on a lock timeout instead of losing the turn.
- Follow-up **TASK-041**: measure bot action latency on staging (or another production-like host
  without emulation). Targets: p95 ≤ 2 s and no bot action over 5 s in 500 consecutive bot moves. Add a
  latency log/metric so this can be watched. If staging misses the targets, it becomes a P1 bug.

## C. Fishka rules check

| Rule | Holds? | Evidence |
|---|---|---|
| Free currency only (bonuses, admin adjustments) | Yes | Wallet API has only `show`, `transactions`, `refill` (bonus) (`routes/api.php`); no payment, purchase or shop code (grep) |
| Never bought or sold | Yes | Same; nothing in the UI offers it |
| Never cashed out (to anything outside the platform) | Yes | "Cash-out" only means table chips going back to the wallet |
| Never sent between users | **At risk (indirect)** | No transfer feature exists. But the owner's 2026-11-01 rule (the winner takes the pot) lets two friends move up to 500,000 Fishkas per game at a private stake table: one resigns, the other gets bet × 2. This is a much larger path than the Poker chip dumping already in owner question 6. Bonuses (5,000 refill per 24 h, 1,000 daily, 5,000 welcome) can be farmed on extra accounts and funnelled this way. Updated owner question 6 |
| House-created Fishkas vs bots | By design | Owner choice (ADR 0015/0016). Note: easy-bot wins create Fishkas |
| Content | Minor | The Poker rules text still says "play chips with no monetary value… cannot be bought, sold or transferred" (`GameSeeder.php:96`). It's true in spirit, but play is now with Fishkas → admin content update (Decisions needed 5) |

## D. Follow-ups created (PRODUCT_BACKLOG.md)

| Item | Where |
|---|---|
| Independent QA re-run of the 6 TASK-040 failed rows; not tested yet: a Chess/Checkers win vs a bot, a Durak draw, phone/LAN WebSocket and reconnect | TASK-039 (e) |
| BUG-3 latency on staging | TASK-041 (new) |
| O-1 Beginner poker blinds 1/1 | Owner question 8 (Fishka/table rules) |
| O-2 wallet dates not localized | TASK-027 (already includes /wallet dates) |
| O-3 play-group audit stores only new values | TASK-042 (new) |
| Owner questions 6–7 | Decisions needed 6 (updated with stake games) and 7 (adds the refill threshold) |
