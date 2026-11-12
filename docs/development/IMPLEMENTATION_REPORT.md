# Implementation reports

One section per task or batch, newest first. Paths under `apps/web/src`.

## AI platform-wide (owner's "Global changes") — 2026-11-12 · DONE

1. **Play against AI** (2be2a17): `Gameplay/Bots/AiPlayers` — highest difficulty the game has bots for; difficulty
   picker and `bot_difficulty` removed (API, lobby, Play again); "bot" → "AI" in en/ru/hy ("Խաղալ AI-ի դեմ"); the
   leftover Bot pills in 7 boards removed (TD-005).
2. **People first, AI fills** (450be1c): `Matchmaker::fillWithAi` after the game's AI timeout seats everyone waiting
   (oldest first) + AI in a `matchmaking` room; offer/countdown/`accept-bot` gone; `offer` games migrated to `auto`;
   admin: "Fill the empty seats with AI" / "Keep waiting for people".
3. **AI stands in** (this commit, ADR 0021): `Gameplay/Lifecycle/AiTakeover`, `game_participants.ai_controlled`.
   Away at 3+ seats with another person → AI holds the seat (presence sweep), back on the next report or move;
   Leave → Loss, AI plays the seat on (Blot's partner no longer loses; chip tables keep cash-out); 2-player: the one
   who stays wins. Leavers get no legal actions and can't move. AI moves for a person's seat are `auto:` (no XP).

**Tests:** `tests/Feature/Gameplay/AiTakeoverTest.php` (5), matchmaking (people first, fill in a matchmaking room,
off, bigger tables), rooms (highest level, old level ignored), Presence/Blot updated to the new rules.
`make test-api` 589 passed; `make lint-api` OK; web 199 tests, typecheck, lint, format, build OK.

**Not done:** each game's own AI strategy is unchanged here (Blot's Quansh is the newest); no live multi-browser
check of a takeover yet.

## Blot bots: strategic Quansh — 2026-11-12 · DONE

**What:** `app/Games/Blot/Bot/QuanshAdvisor.php` decides Quansh from the bot's seat view only (its cards, the bazar,
scores; partners' and opponents' cards are inferred, never seen). Advanced/Expert sample the 24 unseen cards so the deal
fits every bid (Expert: every pass too) — rejection sampling, the contract holder's hand drawn leaning to trumps/aces by
the height of the bid — and play each sample out with `BlotEngine` (declarations, blot, kaput stop, scoring, match-ending
deals) with and without Quansh. Quansh when the expected gain ≥ 1.5 sai (Advanced 3) and the contract fails ≥ 50%
(Advanced 60%). Expert also lets a strong pair answer Sur and counts the match target. Intermediate weighs its own
defence + its partner's likely share against the bid (a kaput: a sure trick is enough); Beginner judges like
Intermediate but overlooks a third. `QuanshSkill::of`: Easy→Beginner, Medium→Advanced, Hard→Expert (owner's choice; the
platform keeps 3 difficulties). No random Quansh: randomness only in sampling and Beginner's oversights. ≈100 ms per
Expert decision, only when Quansh is legal.

**Analysis** (`tests/Fixtures/Blot/QuanshLab`, 300 random deals, the opener overbidding 2–6 in 40%; obvious = a
400-sample careful judgement says fail ≥ 65% and gain ≥ 4 sai, and the contract really failed; wrong = Quansh on a
made contract; sai = what the Quanshes won on the real cards):

| skill | missed obvious | Quansh calls | wrong | sai per call |
|---|---|---|---|---|
| Beginner | 4/15 (27%) | 50 | 26% | 2.7 |
| Intermediate | 1/16 (6%) | 71 | 25% | 2.9 |
| Advanced | 1/16 (6%) | 18 | 6% | 13.0 |
| Expert | 0/16 (0%) | 20 | 10% | 11.0 |

Before (old rule: 3 of J/9/A in trump; never in Boy or against a kaput; Easy never) Expert missed 35% under a
stricter-to-see definition. Tests: `tests/Unit/Games/BlotQuanshTest.php` (7: mapping, seat view only, top-trump hand
always, junk never, kaput spoiled/likely, through `BlotBot`, the lab on 40 fixed-seed deals). `make test-api` 582 passed,
`make lint-api` OK.

**Not changed:** Sur (the answer to a Quansh) keeps the old rule; play and bidding unchanged.

## Blot: dealing shown card by card; no "Bot" marks at tables — 2026-11-12 · DONE

**Dealing:** new server phase `dealing` at the start of every deal (`BlotEngine::deal`; `pauseMs` = `deal_ms` 3400;
`seatsToAct` empty, so bots wait and no turn clock runs; `resume` opens the bazar with a `dealt` event). The board shows a
closed deck in the middle and flies the 32 cards one at a time (85 ms apart, 480 ms flights with spin) in 3-2-3 order from
the dealer's left to the measured positions of the seats and your hand; your cards land face up (flip), opponents' backs
grow, a soft card sound per packet; reduced motion skips the flights. The header says "Dealing" in that pause (trait
`nextRound` may now be a function of the view).

**Bot labels:** RoomPage no longer passes `bots` to boards; `PlayerList` badge, reaction recipient label and Blot's seat
markers removed. Bots renamed to Armenian names (migration `2026_11_12_100000_armenian_bot_names`, renames only seeded
names still unchanged and free; `BotSeeder`). Kept on purpose: the lobby's "play against a bot" mode and matchmaking's
offer to play a bot (chosen by the player before the game). Dead "Bot" pill code in 7 boards → TECHNICAL_DEBT TD-005.

**Files:** `app/Games/Blot/Engine/BlotEngine.php`, `database/migrations/2026_11_12_100000_armenian_bot_names.php`,
`database/seeders/BotSeeder.php`, tests `Unit/Games/BlotEngineTest`, `Feature/Games/BlotTableTest`,
`Feature/Admin/GameAdminTest`; web `games/blot/BlotBoard(.test).tsx`, `games/registry.ts`, `pages/RoomPage(.test).tsx`,
`features/play/components/PlayerList.tsx`, `features/reactions/components/{ReactionPicker,RoomReactions}.tsx`,
`styles/index.css`.

**Checks:** `make lint-api` OK; `make test-api` 573 passed; web typecheck, lint, format, 198 tests, build OK. Live
(Armenian, player1, 1440×900 and 390×844): cards fly to each seat, hand fills face up, header "Dealing", bazar opens
≈3.1 s after the deal starts with 8 cards in hand.

**Follow-up — bids above 24 (owner, same day, replacing the "24 limit"):** number bids go up to 80 (`MAX_BID`, owner); kaput beats any number and after a kaput only another kaput, pass or Quansh. Unit tests:
24 → 25 → 30 → kaput; kaput over kaput; number after kaput refused. Board test: 26 sent as a number; over a kaput only
kaput, with the caption "Over a Kaput only another Kaput".

## Blot: kaput stop, Sur by either partner, new table, dealing and turn animation — 2026-11-12 · DONE except the "24" rule

**Rules researched** (reblot.am, terz.am, belote.club, hatsit.org, playelephant/gambler): recontra may be answered by
either player of the doubled pair; a kaput contract (25) needs all 8 tricks. No source mentions an announcement cutoff
at 24 — not implemented, asked the owner.

**Found and fixed:** (1) after Quansh only the next player of the contract pair could say Sur — if they passed, the
bidder never could; now each player of the pair in turn, then play (Sur ×4/×5 was already scored right, re-tested);
(2) a kaput contract played on after the defenders took a trick; it now stops there with "Կապուտը չեղավ" (log
`kaput_failed`), scored as a failed kaput, and isn't mistaken for Tars kaput when lost on the first trick.

**Web (owner's mockups):** the Blot table rebuilt — oval wood-rimmed felt, round avatars (initial, level badge, turn ring
with time, gold nameplate and time bar for the player to act), speech bubbles with each bazar call (♥ 12, Լավ ա), "thinking"
dots on the player to act, a fanned hand along the bottom (playable cards stand up), Մենք | Դուք scoreboard with the deal's
facts, the bazar panel (suits + Բոյ, − value + ԿՊ, Լավ ա / Քայլ), Քուանշ as its own button, Sur/Լավ ա after a Quansh.
Dealing animated (visual only): 3-2-3 packets fly from the deck to each player starting left of the dealer, your hand
fills in as dealt, opponents' backs grow, a card sound per packet (with sound on), off under reduced motion; played cards
fly in from their player's side. Not copied: VIP, buying coins, the other site's logo.

**Tests:** unit +4 (Sur by either partner and only them, once; kaput stop; first-trick loss isn't Tars kaput; kaput goes on
while every trick is won); board +2 (dealing packet by packet; Kaput failed announced) and updated bazar/terms tests.
`make test-api` → **570 passed**, `npm test` → **198 passed**, lint/types/format/build ✓. Live in Armenian at 1440×900 and
390×844 (bazar, dealing, thinking dots, banners, trick).

## Blot: Armenian terminology, table announcements, game information — 2026-11-11 · DONE (owner request)

**Analysis first:** terms mapped to their meaning in play (`docs/games/blot-rules.md` → Terms). Found: Blot was scored but
never announced and Reblot didn't exist; Quansh/Sur showed only as a bid bubble; kaput, chakhel and the last trick were
never announced; no shared event history (a player who looked away or reconnected couldn't tell what happened); no
opener, multiplier or tricks-left on screen; the deal result put declarations under the pair that made them even when the
other pair took them (failed or Quansh'd contract). The rules logic (bazar, trump, card order, combinations, blot, Quansh/
Sur, kaput, reverse kaput, scoring) was re-checked against the spec and its tests; the only rule change: blot becomes
public with its first card (when it is said), not after both.

**Server:** a public per-deal `log` (every bazar call; `opener`; the contract with its multiplier; declarations with their
cards; `blot` on the first of K/Q of trumps, `reblot` on the second; `chakhel` when a trick of another suit is trumped;
each trick, marked last; the deal's end with kaput / reverse kaput) in the state, so all players and reconnects see the
same history; no hidden card goes into it.

**Web:** hy terms Բազար, Կոզր, Բոյ, Քուանշ, Սուր, Կապուտ, Թարս կապուտ, Բլոտ, Ռեբլոտ, Չախել, Ձեռք, Վերջին ձեռք, Տերց, Պոլտիննիկ,
Սոտնի, Քառյակ (en/ru the same terms transliterated, with a gloss where it helps); combination labels read "Պոլտիննիկ +50"
(no more "50 50"). One banner component announces only the important moments (declaration with its cards, Blot, Reblot,
Quansh, Sur, a kaput bid, the bazar won, the deal's end) across the top of the table frame — never over the trick or cards,
3.5 s, queued, closable; a one-line ticker under the table shows the latest event and opens to the whole deal's history.
Chips: Կոզր with the suit and the multiplier (×1/×2/×4, Բոյ ×2/×3/×5), tricks won and left, who opened the bazar.

**Tests:** unit +6 (Blot→Reblot order, chakhel only on another suit, bazar/opener/contract/tricks/last trick in the log for
every seat, no hidden card in the log, an empty log each deal, blot public from its first card); board +3 (terms and chips,
Blot banner vs ticker-only pass/trick, declarations shown where they went). `make test-api` → **566 passed**, `npm test` →
**196 passed**, lint/types/format/build ✓. Live in Armenian (player1) at 1440×900 and 390×844: bazar, Poltinnik and Blot
banners, ticker history, deal result.

## Blot: declared cards shown to the whole table — 2026-11-11 · DONE (owner request)

- Server: a declaration's exact cards (from the server's own list for the hand) go to all four players the moment it is
  announced — in the view (`declared`, for every seat) and in the broadcast `declare` event; nothing else of the hand.
  Invalid declarations are refused as before. Spec updated (owner's rule; reblot.am shows them at the second trick).
- Web: a one-line announcement banner across the top of the table frame (avatar, level, name, the declaration, its cards
  turning over one by one; 3.5 s each, queued, closable) that covers neither the trick nor cards; the declared cards stay
  beside or under the player's nameplate for the deal (clear of the trick); history after a reconnect comes from the
  view; "Your combinations" shows only while you can still declare and no longer overlaps your hand.
- Tests: unit (cards to every seat at once, the event carries them, nothing else revealed); API with four players (terz,
  50, 100 announced in turn → every player was pushed the same cards and sees them; no other card of any hand leaks,
  including an unannounced terz; a fresh state request returns the history; an invalid declaration is refused and shows
  nothing); board (a new declaration is announced with its cards; older ones aren't re-announced).
  `make test-api` → **561 passed**, `npm test` → **194 passed**, lint/types/format/build ✓.
- Live with four browsers (local accounts player2–player5, two new): terz, 50 and 100 announced in turn — all four screens
  showed the same cards; a reopened page showed all three at the seats.

## Blot audit and rebuild to Armenian Bazar Blot rules — 2026-11-11 · DONE (owner request)

**Rules:** researched and written down in `docs/games/blot-rules.md` (sources: reblot.am — the most complete, used where
sources differ — belote.club, terz.am; every choice marked).

**Bugs found in the first Blot (all fixed):**
1. Scores were raw card points (162 a deal) against a 301 target that Armenian Blot counts in sai (÷ 10, 16 a deal); a
   failed or doubled hand paid 162 × 2–4, so one deal could end the match — the reported "won after the first round".
2. Wrong contract scoring: no bid added, no 16/25 base, multipliers on the wrong amounts.
3. Bazar incomplete: no no-trump (×2, ace 19), no kaput bid, contra didn't end the bidding or open recontra.
4. Declarations were granted automatically; now players announce them before their first card, from the list the
   server finds in their hand (index only — names or points from a client are refused; each once).
5. Four-of-a-kind values (9s 140, aces 110; no-trump aces 190, jacks 100) and the tie-breaks (top card, trump sequence,
   first from the dealer's left; four of a kind over a 100).
6. Forced overtrumping when trumps were led (the source allows any trump).
7. Hidden information: the view named the blot holder before K and Q of trumps were played.
8. Result screen in raw points without the bid, base or match total.

**Now:** `BlotCards` (values for trump/plain/no-trump, sai rounding 15→1 16→2, declarations with comparison keys),
`BlotEngine` (phases bidding → answer → playing → over; scoring per the spec; match end at the target with the higher
score, ties play on; reverse kaput loses, a contra'd kaput made wins; definition version 2), `BlotBot` (no-trump, contra/
recontra, declares). Board: no-trump and kaput bids, contra/recontra, a declarations panel, tricks taken, deal number,
announced declarations at the seats, and a deal result in sai (card points, tricks, contract value, declarations with
the ones not counted struck through, blot, this deal, match total, "the match goes on: first to 301").

**Tests run:** `make lint-api` ✓ · `make test-api` → **559 passed** (`Unit/Games/BlotEngineTest` 50: every card value,
trump/plain/no-trump order, trick winner, last trick, sai rounding, follow/trump/overtrump/partner rules, bazar, kaput,
contra/recontra, redeal, declarations found/invalid/values/announce-once/shown later, six comparison cases, blot, made /
failed / contra / recontra / no-trump / all tricks / kaput / reverse kaput, the biggest first deal not ending the match,
accumulation, target and tie, table target, bots over whole matches; `Feature/Games/BlotTableTest` 7: a whole deal
through the API with identical scores for all four players, rebuild from the move log, next deal; declarations only from
the server's list, once, repeated request counted once; client-sent scores refused) · web typecheck ✓ lint ✓ format ✓ ·
`npm test` → 35 files, **192 passed** (`BlotBoard.test.tsx` 5) · build ✓. Probe: 20 bot matches took 13–20 deals each.
Live 1440×900 and 390×844: bazar, a whole deal, the result screen.

**Known issues / choices:** kaput-contract scoring and "nobody bids after kaput" follow the most consistent reading of the
sources (marked in the spec). On a phone the deal result covers the side seats' plates while it is shown.

## New games 6/11 · Rummy (classic, 2–6 players) — 2026-11-11 · DONE (owner: "do it")

- Engine `app/Games/Rummy/Engine/RummyEngine.php`: one deck (two for 5–6), 10/7/6 cards, draw from stock or discard,
  meld sets (3–4, different suits) and runs (3+, aces low), lay off, discard (not the card just taken), going out
  scores the others' cards (A 1, pictures 10), to 100; the discard pile becomes a new stock once a round, and when that
  runs out the round ends with nobody scoring (found by bot games that never ended); departures; timeouts. Stake game.
  Bot `RummyBot`: takes the discard only when it completes a meld, melds longest first, lays off, discards the most
  expensive loose card (hard also avoids feeding layoffs).
- Web: `games/rummy/RummyBoard.tsx` — opponents with card counts and scores, stock and discard as buttons, melds that
  glow when the selected card can be laid off, selectable hand sorted by suit or rank, Meld / Discard enabled only for
  the server's listed moves, round result with everyone's cards; traits; art; en/ru/hy.

**Tests run:** `make lint-api` ✓ · `make test-api` → **528 passed** (new `Unit/Games/RummyEngineTest` 22, `RummyTableTest` 1,
rummy in `PlayThroughTest`) · web typecheck ✓ lint ✓ format ✓ · `npm test` → 35 files, **190 passed** (new
`RummyBoard.test.tsx` 3) · build ✓. Live 1440×900 and 390×844, 3 players, 6 turns each. Bot games: 30/30 finish.

**Known issues:** no "rummy" bonus for going out in one turn. The dev account player2 was topped up with 20,000 Fishkas
(admin credit, local only) after the test tables used its balance.

## New games 5/11 · 8-ball pool (1 v 1) — 2026-11-11 · DONE (owner: "go"; ADR 0020)

- Physics `app/Games/Pool/Engine/PoolPhysics.php` (pure, deterministic, 2 ms steps, friction, elastic collisions,
  cushions, open pocket mouths, no spin; paths as segments). Rules `PoolEngine`: rack (8 in the middle, solid and
  stripe in the back corners), break from the head spot, open table, groups on the first legal pot, fouls (scratch,
  no contact, wrong ball first, no cushion) → ball in hand anywhere, 8 wins after the group / loses early or with a
  foul, 8 on the break re-spotted, pause while the balls roll, timeout = a soft shot at a target. Bot `PoolBot`:
  ghost-ball candidates, path checks, tries the best few in the simulation, aim wobble by level.
- Platform: action fields may be finite decimals (`SubmitActionRequest`).
- Web: `games/pool/PoolBoard.tsx` — SVG table (upright on phones), solids/stripes with numbers, the shot replayed from
  segments with `requestAnimationFrame` (skipped under reduced motion), cue and aim line that follow the pointer
  (clipped to the cloth), fine aim buttons, power slider, cue-ball placement with ball in hand, player plates with
  their group's balls, foul/potted notices; traits (`capture` sound on a pot, "Balls rolling…"); art; en/ru/hy.

**Tests run:** `make lint-api` ✓ · `make test-api` → **504 passed** (new `Unit/Games/PoolEngineTest` 15, `PoolTableTest` 1,
pool in `PlayThroughTest` with bot-aimed shots) · web typecheck ✓ lint ✓ format ✓ · `npm test` → 34 files, **187 passed**
(new `PoolBoard.test.tsx` 3) · build ✓. Probe: bot games 18–32 shots, ~40 ms per simulated shot, ≤ 2.6 KB per shot.
Live 1440×900 and 390×844: break, aiming, rolling, groups.

**Known issues:** no spin, no called pocket for the 8 (agreed). The aim line shows the direction only, not where the
cue ball will meet a ball.

## New games 4/11 · Ludo (2–4 players) — 2026-11-11 · DONE (owner: "go" on the standard rules)

- Engine `app/Games/Ludo/Engine/LudoEngine.php`: four tokens, two players take opposite colours; roll (server die,
  recorded) then move {token, to} from the listed moves; a 6 to leave the yard, a 6 rolls again, the third 6 ends the
  turn; 52-square track, home column, exact roll home; captures except on the 8 safe squares (starts and stars); a
  roll without a move passes; all four home wins; departures; timeouts roll / make the first move. Stake game. Bot
  `LudoBot`: easy random, medium capture > leave yard > lead token, hard also home, safe squares, avoids danger.
- Web: `games/ludo/LudoBoard.tsx` — 15×15 SVG board (yards, track, start squares, stars, home columns, centre)
  turned so your yard is bottom left; tokens slide (CSS transform transition, off with reduced motion), stacked tokens
  offset, movable tokens pulse and their landing square is marked; die with a tumble (`animate-dice-roll`); players
  with colour, level, bot and tokens home. Geometry is drawing only; moves come from the server.

**Tests run:** `make lint-api` ✓ · `make test-api` → **487 passed** (new `Unit/Games/LudoEngineTest` 17,
`Feature/Games/LudoTableTest` 1, Ludo in `PlayThroughTest`) · web typecheck ✓ lint ✓ format ✓ · `npm test` → 33 files,
**184 passed** (new `LudoBoard.test.tsx` 3) · build ✓. Live 1440×900 and 390×844, 4 players, 12 moves each.

**Known issues:** no extra roll for a capture or for reaching home (not in the agreed rules).

## New games 3/11 · Domino (Block, 2–4 players) — 2026-11-11 · DONE (owner: "Block domino")

- Engine `app/Games/Domino/Engine/DominoEngine.php`: double-six set, 7 tiles each, no drawing; first round led by the
  highest double (or heaviest tile), later by the last winner; play {tile "a-b", end left|right}; the engine passes for
  players with no move; going out scores the others' pips, a blocked round goes to the lowest count (tie: nobody);
  to 100; pause after each round; departures (tiles leave the game, last one standing wins); timeout plays the first
  legal tile. Stake game. Bot `DominoBot`: easy random, medium sheds heavy tiles/doubles, hard also keeps matching ends.
- Web: `games/domino/DominoBoard.tsx` (SVG tiles with pips, the line wrapping on the felt with doubles crosswise, end
  slots when a tile fits both ends, other players across the top with tile counts and scores, your standing tiles,
  round result with everyone's tiles and pips); traits; art; i18n en/ru/hy. Category: Board games.

**Tests run:** `make lint-api` ✓ · `make test-api` → **468 passed** (new `Unit/Games/DominoEngineTest` 17,
`Feature/Games/DominoTableTest` 2, Domino in `PlayThroughTest`) · web typecheck ✓ lint ✓ format ✓ · `npm test` → 32
files, **181 passed** (new `DominoBoard.test.tsx` 3) · build ✓. Live 1440×900 and 390×844 with 3 players: moves, end
choice, round result.

**Known issues:** a long line wraps onto more rows in play order rather than turning like a snake.

## New games 2/11 · Blot (Bazar blot, 4 players) — 2026-11-11 · DONE (owner: "the first 4 player")

- Engine `app/Games/Blot/Engine/BlotEngine.php` (+ `BlotCards`): 32 cards, partnerships 0+2 / 1+3, bazar (bid 8–30 ×10
  with a suit, pass, contra, recontra; 4 passes redeal), follow / overtrump / must-trump unless the partner wins,
  trump J 9 A 10 K Q 8 7, last trick +10, kaput 250, declarations (terz 20, 50, 100, carré J 200 / 9 150 / others 100;
  only the best team's count), belote 20, made / failed / doubled scoring, to 301 (a tie plays on), pause after
  each hand. Stake game (pot shared by the winning pair). Bot `BlotBot` (bids on trump strength, doubles with top
  trumps, smears to a winning partner, wins cheaply; easy plays at random).
- Platform: `Gameplay/Contracts/PlaysInTeams` — resigning, leaving or timing out loses for the whole team (used in
  `GameActionService::end`). Lobby: a single-size mode (Blot: 4) starts bot games with that many seats.
- Web: `games/blot/BlotBoard.tsx` (you at the bottom, partner opposite; bazar controls, trick, last trick and the hand
  result in the middle of the felt; your hand along the near edge with only the server's playable cards enabled;
  contract, team scores, declarations and belote badges, dealer chip); traits; art; i18n en/ru/hy.

**Tests run:** `make lint-api` ✓ · `make test-api` → **448 passed** (new `Unit/Games/BlotEngineTest` 22,
`Feature/Games/BlotTableTest` 4, Blot in `PlayThroughTest`) · web typecheck ✓ lint ✓ format ✓ · `npm test` → 31 files,
**178 passed** (new `BlotBoard.test.tsx` 3) · build ✓. Live (headless Chrome) 1440×900 and 390×844: bazar, play,
declarations, a made and a failed contract.

**Known issues:** the lobby's stake line says "the winner takes the pot: 1,000" — for Blot that is each winner's share
(pot 2,000 for the pair); wording could name the team. No no-trump or kaput bids (not in this ruleset).

## New games 1/11 · Blackjack — 2026-11-11 · DONE (owner request; ADR 0019)

- Engine `app/Games/Blackjack/Engine/BlackjackEngine.php` (+ `BlackjackCards`): 1–5 seats against the dealer, bets
  within the play group's limits, hit/stand/double/split (up to 4 hands, split aces one card), dealer peek, S17
  (`dealer_hits_soft_17` option), 3:2 blackjack, 6-deck shoe reshuffled at a quarter, 10 rounds with a pause after
  each, `PlaysForChips` (whole balance as chips, cash-out on leave/end), departures, timeouts (bet min / stand).
  Bot `BlackjackBot`: basic strategy (hard), slips (medium), hits below 15 (easy).
- Platform: `Outcome::HOUSE` (the house won; also a solo resignation is now a loss, not a draw); bot rooms with
  `opponents: 0` (no difficulty needed) for modes with `min_players` 1; chips groups add `table_min_bet`/`table_max_bet`;
  `BotSeeder` teaches the sample bots games no bot plays yet. Seeded: game `blackjack` (Card games), mode `classic`.
- Web: `games/blackjack/BlackjackBoard.tsx` (half-moon table, dealer with hole card, seats round the curve, chips to
  build a bet, Hit/Stand/Double/Split, per-hand totals and results, round result with Fishkas flying to the balance);
  traits (`roundWon`, `buyIn`, `tableTerms`); lobby: 1 player = "Play against the dealer" (no difficulty), private
  tables at least 2; `GroupBadge` says "Bets from N" for blackjack; art in `GameArt`/`HomeArt`; i18n en/ru/hy.

**Tests run:** `make lint-api` ✓ (Pint, Larastan) · `make test-api` → **421 passed** (new: `Unit/Games/BlackjackEngineTest`
26, `Feature/Games/BlackjackTableTest` 6, bot-seeder test, Blackjack in `PlayThroughTest`) · web typecheck ✓ lint ✓ format ✓
· `npm test` → 30 files, **175 passed** (new `BlackjackBoard.test.tsx` 4) · build ✓. Live (headless Chrome): solo at
1440×900 and 5 seats at 1440×900 and 390×844, 3 rounds each, bets, stands, payouts and the header balance.

**Routes to review:** `/games/blackjack` (1 player → Start game; 3–5 players with bots), `/rooms/{id}`.

**Known issues:** at 5 seats on a 390 px phone neighbouring seats sit close (readable, cards may touch). No UI/UX or PM
review was run (owner asked for no extra agents). Leaving a table is labelled "Resign" (shared button).

## TASK-048 step 5 · Polish — 2026-11-10 · IN PROGRESS (QA in step 6)

- `Button` `loading` (spinner, disabled, `aria-busy`, label kept) on every button that waits on the server: auth forms,
  account forms, avatar, matchmaking accept/cancel, join by code, Play again, daily bonus, level-up, out-of-Fishkas,
  resend code. Board moves unchanged.
- Hover: scale effects (`PlayPanel` play icon, emoji picker) only with `motion-safe:`; table-size cards get a hover border.
- Lobby "All": the first game is a double-width featured poster (16:9 art; 2.4:1 at 3 columns), others stretch to match.
- Turn pill pops once when your turn starts (`animate-turn-pop`, `--ease-pop`, reduced motion off).
- Loading/error: `LevelCard` and `DailyBonusCard` hold their place with a skeleton (`isLoading`, as the queries are
  off for guests); `DashboardGames` shows an error with Try again.

**Tests run:** typecheck ✓ · lint ✓ · format ✓ · `npm test` → 29 files, **171 passed** (new: `Button.test.tsx`,
dashboard error + retry) · build ✓. Live: `/games` 1440/820/390, `/dashboard` 390, `/ranks` 1440.

**Known issues:** the dashboard's "with us since" date shows an English month in Armenian (pre-existing; for step 6).

## TASK-048 step 4 · Game redesign (tables) — 2026-11-10 · IN PROGRESS (QA in step 6)

Spec: `docs/design/REDESIGN_048.md` §4.5, §5. Rules and game logic untouched (frontend only).

- `TableBar` (`features/play/components`): at `/rooms/*` the site header gives way to one bar — back, game + mode,
  `GroupBadge` (md+), turn status (768 px+; below it sits under the bar with the group), `FishkaBalance`.
- `PlayerList`: seat plates; two players face off (you left, "vs" medal), avatar `md` with level/frame, clock,
  result (truncates on phones), turn ring + "To move". Used by Nardi, Checkers, Chess and finished card tables.
- `GameOverPanel`: floats over the board from 1024 px, `Modal` bottom sheet below, folds into a fixed strip
  ("Show the table" / "Result", Play again stays). Placed after the board inside a relative box.
- Poker: phone bet chips sit below the "Bot" pill (they overlapped). Durak/Poker hint bubbles and Durak's avatar
  tile use tokens (`ivory-100`, `ink`). Nardi: dice, pips and hint on one `club-panel` strip (walnut unchanged).
- i18n (en/ru/hy): `room.youSeat`, `room.toMove`, `room.versus`, `room.showTable`, `room.showResult`.

**Tests run:** `npm run typecheck` ✓ · `npm run lint` ✓ · `npm run format:check` ✓ · `npm test` → 28 files,
**168 passed** (new: seat-plate order and turn mark, table bar replaces header, fold/unfold of the result) ·
`npm run build` ✓. Backend untouched.

**Browser check (headless Chrome, local stack, hy UI):** Chess/Checkers/Nardi/Durak/Poker bot rooms at 1440,
820 and 390, live and finished (resigned). Durak's whole table fits at 390×844 now.

**Known issues:** Poker's action buttons are below the fold at 390×844 (the oval is width-limited; as before).
GitHub Actions not checked from this machine (`gh` not installed). Design review and PM validation come with
step 6 QA.

## TASK-056 · Home page redesign and game categories — 2026-11-08 · DONE

Brief and the owner's revision: `docs/product/briefs/TASK-056-home-page-redesign.md`. Design: `docs/design/HOME_REDESIGN.md` (partly superseded).
QA and design: `docs/qa/QA_REPORT_TASK056.md` (127/145, 7 bugs → fixed). PM: `docs/product/VALIDATION_TASK056.md` (ACCEPT WITH FOLLOW-UPS).

- `game_categories` (slug, translated name, order) and `games.game_category_id`; admin Games → Categories, audited; `category` on each game in the catalog API.
- `/` and `/dashboard`: a dark "night room" over one fixed picture drawn in SVG (`HomeBackdrop`), scope `.home-scope-night`. `/games` keeps its look until TASK-048.
- `GameLobby` replaces the ring carousel: filter pills, one scrolling shelf per category, a grid for one category (`?category=`), no autoplay; `HomeArt` posters and covers.
- Game descriptions in ru and hy; poker's line without betting words (`2026_11_08_210000`).

## TASK-047 · Level & XP progression — 2026-11-08 · DONE

Brief: `docs/product/briefs/TASK-047-levels-xp.md`. ADR 0018. QA: `docs/qa/QA_REPORT_TASK047.md`. Design:
`docs/design/REVIEW_TASK047.md`. PM: `docs/product/VALIDATION_TASK047.md` (ACCEPT WITH FOLLOW-UPS; TASK-059 and 060 done here).

**XP.** A finished game pays `xp_game` 30, plus `xp_win` 20 or `xp_draw` 10. The daily bonus pays `xp_daily` 20. Nothing for an
abandoned game, a game under `min_game_seconds` (30), or fewer than `min_game_moves` (3) of the player's own moves (resigning,
timing out, leaving and turns the server played for them don't count). Each game and day pays once. Bots earn nothing.

**Curve** (XP from level L to L+1, admin settings): below 30, `80 + 20 × (L − 1)`; from 30, `⌈640 × 1.5 × 1.1^(L − 30)⌉` (990, 1,089,
1,198…). Level 30 is 10,440 XP in total. Cap `max_level` 100. A level never goes down.

**Rewards.** `level_rewards`, one row per level, edited in the admin panel ("Level rewards"): Fishkas, items and a milestone flag.
Seeded 250 + 50 × L Fishkas; milestones at 5, 10, 15, 20, 25, 30 with bigger amounts and items. Levels without a row pay
`levels.reward_default`. Collected once from the popup through `FishkaWallets::credit` (key `level:{user}:{level}`); level-ups reached
while away wait in `level_ups`.

**Database** (`2026_11_08_100000_create_levels_tables`): `users.level`, `users.xp`, `xp_transactions`, `level_rewards`, `level_ups`,
`bot_profiles.level`, Fishka ledger type `level_reward`.

**Files.** API: `app/Platform/Levels/*`, `GameFinisher`, `GameActionService`, `LevelController`, `DailyBonusController`, user and room
resources, `SettingsCatalog`, Filament `LevelRewards`, `ManageSettings`, bot admin, `BotSeeder`. Web: `features/levels/*`,
`shared/ui/LevelBadge`, `Avatar`, `frames.ts`, poker and durak boards, `PlayerList`, `WaitingTable`, table chat, emoji picker, dashboard,
profile, menu, locales.

**Tests.** API 383, web 158, live browser run over all five games.

## TASK-040 · Poker bots, real balance in Poker, game groups — 2026-11-01 · READY_FOR_REVIEW

Brief: `docs/product/briefs/TASK-040-groups-poker-bots.md`. ADR 0016. QA: `docs/qa/QA_REPORT_TASK040.md`.

What was wrong:
- "Bots don't play poker": they did; the page never heard. The WebSocket host was pinned to an old LAN
  address and polling only ran when realtime wasn't configured at all (edd04cf).
- Poker showed 1,000: the buy-in was capped at `starting_chips` (1,000).
- No groups: one flat 500 entry fee for every game.

Built (b90cd47, 7ce1f4a, d4ca892):
- `play_groups` (10 rows, admin-editable, audited): poker Beginner…Master (min balance 100 → 500,000, min
  bet = big blind 1 → 100); other games Beginner…Master (min balance 500 → 500,000, bets 500 → 500,000).
- Entry by minimum balance (never charged); bet range validated on the server; the bet replaces the fee;
  winners share the pot, a draw returns bets, bots' stakes come from the house; matchmaking per group at
  the group's minimum bet. Poker brings the whole balance; bots get the people's average stack.
- Header = wallet + chips at a table (`at_table`); no free bonus while chips are in play; game-over panel
  shows the ledger net and flies chips in/out once per game; poker hand wins fly from your seat.

QA fixes (this commit), from 54 pass / 6 fail, 8 bugs:
1. Major · a busted player at a poker bot table got no result and stayed "in a game" →
   `GameActionService::retireBusted`: a person with 0 chips gets a loss and is freed; only bots left →
   the game ends. State payload `playing`; `useRoom` refetches the room when it turns false; panel says
   "You're out of chips" at a table that plays on. Tests: PokerTableTest (both cases). Live: 4 busts on
   6-seat tables ended at once; 390 px "You lost. −6,268 Fishkas".
2. Medium · header flicker / wrong "−19,192" at buy-in and cash-out → `useWalletSync` refetches the summary
   instead of patching `balance` alone. Test: Fishka.test ("without a false jump").
3. Medium/Low · bots ~3 s per move, stalls → own Horizon supervisor for `game` (sleep 0.25 s instead of 3 s;
   slow jobs elsewhere no longer block), game jobs retry on a lock timeout instead of losing the turn, the
   5-second sweeps run in the scheduler's process (scheduler CPU 69 % → 1.4 %, MySQL 21 % → 7 % idle).
   Live: median 3.07 s → 1.13–1.18 s (p90 1.3–1.5 s). Still seen locally: about 1 stall of ~20 s per 70
   moves, during whole-stack slowdowns of the loaded Mac (load average 12–15, Docker under QEMU); every
   container slowed at once and no code path was slow in the probes.
4. Low · group badge overflow at 390 → header block shrinks, group name truncates, bet/blinds stay whole.
   Live: badge right edge 264 of 390, no horizontal scroll.
5. Low · "You ran out of time" after leaving the page → "You left the game." when the end reason is
   `abandon` (en/ru/hy).
6. Low · insufficient-Fishkas message: unformatted number, requested group's minimum → the lowest group's
   minimum, formatted. Test: GroupsTest.
7. Low · pot/bet labels without separators → poker strings use `{{x, number}}` (page language); stacks too.
   Live: "Pot: 1,568 chips".
8. Low · a 429 on `/me` showed the player as signed out → queries retry 429 with backoff (~45 s);
   `account` limiter 30 → 60/min. Test: Fishka.test ("asks to slow down").

Design review fixes (`docs/design/REVIEW_TASK040.md`, NEEDS REVISION → all 6 fixed):
1. Medium · turn status cut on phones → on phones the group badge has its own row under the header; the
   status is `shrink-0 max-w-[55%]` and hidden for a player who is out. Live hy 390: "Ձեր քայլն է / Մնաց 23
   վայրկյան" whole, badge with the full group name.
2. Medium · header still dipped to 0 at a poker buy-in (the bet is charged a moment before the table starts)
   → `RoomStakes::atTable` counts the buy-in at a waiting chip table. Test: GroupsTest ("hasn't started").
   Deltas during play (+20 per won hand) stay: they are real changes of the chips at the table.
3. Low · locked tiles 3.3:1 → only the dot and name dim; "Needs N" and bets stay `text-muted`.
4. Low · hy badge "«…" → solved by the badge's own row on phones.
5. Low · out-of-chips heading 3 lines → "You're out of chips." / "You left this table." with "The game goes
   on for the others." below (en/ru/hy). Test: RoomPage.test.
6. Low · hy "Professional" truncated → tile names wrap. Live: whole.

QA re-run (2026-11-02): 7 of 8 bugs confirmed fixed live, BUG-8 partly; new R-1…R-4, all fixed:
- R-1 · "Log in to play" shown to a signed-in player during a 429 → `PlayPanel` and the landing page wait
  while the account is unknown; only a real guest (`null`) is asked to log in. Tests can now opt into the
  app's real retry policy (`renderRoute(path, { retries: true })`).
- R-2 · poker side-seat bets clipped at 390 (ru/hy) → phone chips show only the amount (sr-only "Bet N"),
  side seats' chips align towards the middle.
- R-3 · hy "Professional" broke mid-word → the lock icon moved to the "Needs" line.
- R-4 · a 429 on presence could lose the game → presence has its own `presence` limiter (60/min).

Not changed (observations for the owner/PM): beginner blinds 1/1 (formula), wallet dates not localized,
play-group audit rows store only new values.

## TASK-038 · QA fixes — 2026-10-31 · READY_FOR_REVIEW

From `docs/qa/QA_REPORT_FISHKA.md` (44 checks, 39 pass, 10 bugs):
1. High · rapid "Start game" clicks opened several paid games → `PlayRules::exclusively` (per-player
   cache lock) around every start: bot/private create, join, matchmaking join, accept-bot. Live: 3
   simultaneous starts → 201, 422, 422; one fee.
2. Medium · heads-up poker resign returned chips already in the pot → `GameActionService::end` settles
   chip games from the position after the leaver departs (`HandlesDepartures`); test: the pot goes to the
   other player, total conserved.
3. High · popup off-screen/under the tab bar → rendered into `<body>` with a portal (the frame's backdrop
   blur made `fixed` relative to it). Live 390: sheet 430–828, both buttons hit-testable.
4. Medium · double-click on the bonus showed an error → one request per click burst (ref guard). Live:
   one bonus entry, success state, no error.
5. Low · "You have 5,000 left" while loading → shown only while 0 < balance < fee.
6. Low · banned users saw "not active" → `User::inactiveMessage()`: "Your account has been blocked…"
   (with the end date for temporary bans), at login and when a session is cut off.
7. Low · 401 on `/me/wallet` after logout → the logout mutation is marked `meta.signsOut`; the cache
   skips the wallet refresh.
8. Low · "Total spent −0" → a plain 0 when nothing moved.
9. Low · "1 Fishkas" → plural forms (en one/other, ru one/few/many/other).
10. Low · hy dates/numbers in English in headless Chrome: the headless browser's locale data; not changed.
Not tested by QA, open: the real midnight rollover, leaving a multi-seat poker table mid-hand live.

## TASK-038 · Fishka currency — 2026-10-30 · IN_REVIEW (QA)

Backend (`apps/api`):
- Migration `2026_10_30_100000_create_fishka_tables`: `fishka_wallets`, `fishka_transactions` (CHECKs,
  unique idempotency key, indexes), `game_participants.stake`. ADR 0015.
- `Platform/Fishka/FishkaWallets` (open/welcome, claimDaily, refill + status, charge, chargeUpTo, credit,
  adjust; row lock, no negatives, idempotency, audit, `WalletUpdated` broadcast), `InsufficientFishkas`
  and `FishkaBonusUnavailable` (422 with `code`), settings `fishka.*` + admin section.
- `Gameplay/Rooms/RoomStakes` + `Contracts/PlaysForChips`; `PlayRules` affordability check; `RoomService`
  charges in `seat()` (join now transactional), poker `options.stacks`, games decided at the deal finish
  at once; refunds on waiting-room leave/close; cash-outs in `GameActionService` (depart, end, finish);
  `Matchmaker` drops players who can't pay. Poker engine: per-seat stacks, a leaver keeps their stack,
  `buyIn()`, `chipsOf()`.
- Bonuses: welcome in `RegisterController`, daily in `SessionController::store` and `MeController::show`.
- API: `GET /me/wallet`, `GET /me/wallet/transactions`, `POST /me/wallet/refill`.
- Admin: ViewUser "Fishkas" actions (add/deduct/set with reason), Fishka section, transactions relation
  manager, Fishkas column; blocking reuses ban/unban.

Frontend (`apps/web/src`): `features/wallet` (api, hooks, popup store, `FishkaIcon`, `FishkaBalance`,
`OutOfFishkasDialog`), `pages/WalletPage`, header + layout wiring, MutationCache opens the popup on
`insufficient_fishkas` and refreshes the wallet after every mutation, `ApiError.code`, lobby cost line,
en/ru/hy strings, `fishka-bump`/`fishka-delta` animations.

Tests: API `tests/Feature/Fishka/FishkaTest.php` (14), play-through accounting for all five games,
poker engine buy-in/chipsOf/leaver tests → `make test-api` **311 passed**; web `pages/Fishka.test.tsx`
(header follows `wallet.updated`, popup → bonus → play, cooldown, history) → vitest **122 passed**;
lint, PHPStan, typecheck, format, build ✓.

## B3a revision after design review — 2026-10-30 · READY_FOR_REVIEW

- TASK-035 fix 1: `useFitHeight` also re-measures when the page above changes (ResizeObserver on
  `body`, one rAF); the room header stays on one row (title whole, status truncates). 6 players, hy,
  390, 24 turn changes: table bottom ≤ 774, tab bar at 786.
- TASK-035 fix 2: compact bubbles `text-[0.6rem]`, `max-w-[calc(100%+0.5rem)]`, word wrapping only
  (the `inline-block` that defeated `line-clamp` is now only on the large bubble). hy role words
  shortened to fit a 62 px column: գրոհող, պաշտպան, վերցնում (native check). All roles one line; none
  clipped by the felt.
- TASK-035 fix 3: status hint `line-clamp-3`; 18 px above the hand for lifted cards (gap to the hint
  ≥ 16 px measured).
- TASK-036 fix 1: phones — board cards 36×50 anchored at 38 % of the table, plate ≤ 70 % wide; bets sit
  at their seat on the side facing the middle (`betBeside`: under the top seat, above yours, beside the
  others) instead of on the felt. 390, 6 seats: 0 overlaps among bets, board cards, pot/plate, info line,
  nameplates and avatars in 6 mid-hand samples and a result.
- TASK-036 fix 2: split-pot plate uses `payouts` ("Split pot: Aram, Levon — 75 chips each", or
  "Split pot: Aram 76, Levon 75" when shares differ; new `poker.result.splitShares`).
- Nit: showdown cards `sm` on desktop. Not done: the 1280×720 six-player bout height (93 px).
- PM condition (TASK-029, 2026-10-30): phone bets beside the seat checked live at 2, 4 and 6 seats at
  390. At 4 seats the side seats sit level with the board, so their bets go under the nameplate
  (`betBeside`, |sin| < 0.3); after that 0 overlaps at all three sizes.
Checks: typecheck ✓ lint ✓ format ✓ vitest 117 passed ✓ build ✓.

## B3a · TASK-035 Durak table, TASK-036 Poker hand result, B3a-1/2 + BUG-001 — 2026-10-30 · READY_FOR_REVIEW

Owner answers (2026-10-30): wood rail, green felt, server pause between hands, one tap to play.

| Item | Changes |
|---|---|
| BUG-001 | `shared/lib/uuid.ts` `randomId()` (fallback to `getRandomValues` where `crypto.randomUUID` is missing: plain HTTP on the LAN); used for `client_action_id` (pushed earlier, f40b59d) |
| B3a-1 pause (API) | New contract `Gameplay/Contracts/PausesBetweenRounds` (`pauseMs`, `resume`). `PokerEngine::finishHand` pays, busts, then stops at `street: 'over'`, `to_act: null` (game end: no pause); `pause_ms` [3000 fold, 5000 showdown] from mode options `fold_pause_ms`/`showdown_pause_ms`, hidden from views; leaving during the pause ends the game if one player is left. `GameScheduler` queues `Lifecycle/ResumeRoundJob` for a paused room; `GameActionService::resume` records a `resume` move (no participant); `RoomStates::rebuild` replays it; `RoomStates::deadline` = end of the pause (the room's `deadline`) |
| B3a-2 payouts (API) | `last_hand.payouts`: chips per seat (list indexed by seat, odd chips included) |
| TASK-036 | `games/poker/result.ts` `summarize(last_hand)` (winners + exact amounts, headline, hand or "everyone else folded", side pots, announcement); `HandResult.tsx` `ResultPlate`, `ChipFlight` (WAAPI, off under reduced motion); `PokerBoard`: during `street 'over'` the final board, the plate instead of the pot, gold glow + "+N" on winners, shown cards face up at their seats, chips to winners, one sr-only announcement, "Next hand in N seconds"; only a hand that ends while the page is open animates/announces. `handWin` sound (`sounds.ts`) via trait `roundWon`; room header says "Next hand" during the pause (trait `nextRound`, generic `room.nextRound`). TASK-029: bets at an elliptical radius on phones (`spot(seat, 20, 30)`), `max-w-[30%] truncate`; phone board cards 40 px |
| TASK-035 | `DurakBoard` rewritten: rectangle table fitted by `features/play/useFitHeight` (`[data-tabbar]` in PublicLayout), grid areas; `DurakSeat.tsx` (`CompactSeat` row on phones, `PanelSeat` on ≥ 1024 via `shared/lib/useMediaQuery`, `Bubble`, `Avatar`); `TablePiles.tsx` (deck with sideways trump + trump badge, discard); bout pairs with `PlayingCard size="fluid"` (`boutCard`: larger of one/two rows); `DurakHand.tsx` + pure `handLayout.ts` (≤ 3 rows, strips ≥ 40/44 px, each strip its own button); `useBoutMotion.ts` (played cards fly from the player; finished bouts slide to discard/taker; bout announcements). Finished table: only the loser's "durak" bubble, no status/action. Bubbles fixed for contrast (taking/out black text). `PlayingCard` sizes `xs`, `fluid`; `Button` takes `ref` |
| i18n | `poker.result.*`, `poker.nextHand(In)`, `room.nextRound`, `durak.opponents/notBeaten/deckEmpty/role.durak/bout.*` in en/ru/hy |

**Deviations from the spec:** with the server pause, the result shows for the whole pause, also after a
reload (the table *is* paused), but only a hand that ended while the page was open animates and is
announced; no client-side minimum duration is needed. `payouts` is a list by seat, not an object. On
desktop the bout area is the space between deck and discard (`left-44 right-24`) and the card size is
the larger of one row or two rows (the spec's single formula gave 60 px cards at 1440).

**Tests run:** API `make test-api` **295 passed** (new: pause/resume/payouts/game-end/leave-in-pause
engine tests; feature test pause → `ResumeRoundJob` (stale job ignored) → hand 2, `resume` move
replayed; play-through resumes pauses) · `make lint-api` ✓. Web typecheck ✓ lint ✓ format ✓ vitest
**117 passed** (new: poker result moment/fold win/reload/next hand/summarize split & side pots, `handWin`
rules, durak finished/taking/labels, `handLayout`, `fitHeight`, `randomId`) · build ✓.

**Browser check (headless Chrome, local stack):**
- Durak 3 players, 390×844: stage 184–774 (tab bar from 786), hand inside, no horizontal scroll;
  14-card hand → 2 rows of 7, min strip 44 px, `elementFromPoint` hits the right card for all 14;
  finished: only "Anna … durak" bubble, title "Won · Durak". 1440×900: stage to 888, bout cards 96 px,
  side panels, Pass/Take button right of the hand.
- Durak 6 players in hy at 390: five seats 62 px wide, avatars aligned (bubbles may wrap to 2 lines), no
  horizontal scroll; 1280×720: stage 222–708 inside 720.
- Poker 6 players: hand end at 390 → header "Next hand · 5 seconds left", plate "Igor won 120 chips ·
  with a pair", "+120", three shown hands, announcement "Hand 1: Igor won 120 chips — with a pair". My
  win at 1440 → "You win 100 chips · with a full house" (gold), "+100", 3 triangle oscillators
  (`handWin`). Bets vs nameplates: 0 overlaps at 390 and 1440 (was 1 at 390). Reduced motion: plate and
  "+N" shown, no transform animations.

**Not checked live:** a split pot (component test only); Durak with a real second person; the bout
fly/slide motion was not inspected frame by frame (it runs; nothing breaks without it).

## B2 revision after design review — 2026-10-29 · READY_FOR_REVIEW

- Root cause of TASK-008's unreadable hover (and B1's invisible icon tiles): the custom `.text-gold`
  component class shared its name with Tailwind's `text-gold` colour utility, so every element with
  gold text also got a gradient background clipped to the text. Renamed it `.text-gold-gradient`
  (`styles/index.css`) and used it only on the seven big gold headings (games, dashboard, game page,
  landing, carousel slide titles, lobby card titles, game-over win). `text-gold` is now plain gold.
  Checked: Start game at rest gold on `#33231a`, forced `:hover` black on gold, `background-clip`
  border-box.
- TASK-008: `Segmented` `fill` sets the fieldset `w-full max-w-xs`; at 390 table-size pills 59×44,
  level pills 101×44.
- TASK-024: `WaitingTable` hides the medal and dots below `sm`, title `min-w-0 break-words text-lg`;
  Share/Copy link, Copy link and Close room `min-h-11`. hy at 390: frame `scrollWidth 234 =
  clientWidth 234`, page 390, all buttons 44 px.
- Minor: `GameOverPanel` renders above `PlayerList`; no "Game over" eyebrow for a closed room;
  catalog queries keep the previous language's data during a switch (the game page only for the same
  game).
Checks: typecheck ✓ lint ✓ format ✓ vitest 99 passed ✓ build ✓.

## B2 (TASK-026, 006, 028, 007, 024, 008) + TASK-030, TASK-031 — 2026-10-29 · reviewed

Owner go-ahead for TASK-030/031 ("do all necessary things", 2026-10-29). Built to the B2 specs; the
spec's open question 1 was resolved in code: the room payload now carries `mode.id` and each bot's
`bot_difficulty` (additive API fields, `make types` committed), so "Play again" repeats the exact
level instead of remembering it on the device.

| Task | Changes |
|---|---|
| TASK-030 bot names | `apps/api/app/Gameplay/Bots/BotSelector.php`: `pickMany` seats distinct bots, borrowing bots of other levels for the game (they play at the table's level, set per seat) before repeating one; `shortages()` lists game/level pairs with too few bots → subheading on the admin Bots list (`ListBotProfiles`). `BotSeeder`: 5 bots per level (15). Ran the seeder locally |
| TASK-031 chess on time | `GameActionService::end`: a timeout with a game clock records `loss` (counts in losses); per-turn timer timeouts and leaving stay `abandoned` |
| API | `RoomResource`: `mode.id`, `participants[].bot_difficulty`; schema.d.ts regenerated |
| TASK-026 | `app/useRefreshOnLanguageChange.ts` (mounted in `PublicLayout`) invalidates `translatedQuery` keys (`features/play/api.ts`: rooms not state, me/rooms|games|stats, users) on `languageChanged`; shared `myStatsKey` / `myGamesKey` |
| TASK-028 | `features/play/turnCaption.ts` `waitingCaption` used by the RoomPage header badge and the Chess, Checkers, Nardi and Durak boards (names via the `players` prop); nothing once the game is over; `*.waiting` strings removed |
| TASK-006 | `features/play/components/GameOverPanel.tsx` replaces the finished/aborted/left alerts and the footer link: outcome, reason, Play again (bot: same mode/seats/level; matchmaking: new search with status in the panel; private/aborted: lobby only), Back to lobby, "Same settings" line; heading focused |
| TASK-007 | `pages/ProfilePage.tsx`: Records grid (`GameRecord`, shared with the dashboard via `features/play/records.ts`) and 5 recent games; empty state for new players |
| TASK-024 | `pages/JoinPage.tsx` + route `join/:code`; `withNext` (`features/auth/forms.ts`) keeps the destination through login ↔ register → verify; join notice on login/register; `WaitingTable`: Copy code, Share (Web Share) or Copy link, secondary Copy link, Close room below the table, selectable fallback field; `LinkIcon`, `ShareIcon` |
| TASK-008 | `PlayPanel` `ModeCard`: medal + title row, full-width body and visible text button (`Button` variant `outline`), labels "Start game" / "Create table"; `Segmented` `fill` (one line, 44 px on phones); table size pills show numbers |

**Tests run:** web `typecheck` ✓ `lint` ✓ `format:check` ✓ `vitest` 21 files **99 passed** (new:
`turnCaption.test.ts`, `pages/B2.test.tsx` language refetch, profile, join flows, copy link; updated
RoomPage/Play/Nardi tests) · `build` ✓. API `make lint-api` ✓ (pint, PHPStan) · `make test-api`
**291 passed** (new: distinct bots with borrowing, repeat-only-when-short + shortages, chess flag-fall
= loss with stats; updated: admin seeder list, room API fields).

**Browser check (390×844 / 1440×900, local stack):** lobby cards show text buttons and one-line size
pills; resigned a Hard checkers game → panel "You lost. · A player resigned · Same settings: Russian
draughts · Level: Hard", focus on the heading, tab "Lost · Checkers"; Play again → new room, "● Your
turn · Checkers", opponent a Hard bot (Viktor); profile records + 5 recent games + See all games;
private 4-seat Poker table: code, Copy code, Copy link, Close room, no horizontal scroll.

**Not checked live:** Web Share sheet (headless has none), matchmaking Play again, sign-up → verify →
join with a real new account, ru/hy on the new screens, TASK-031 in a live chess game (feature tests).

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
