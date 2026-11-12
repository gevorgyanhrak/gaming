# Research 01 — Open-source Belote/Belot engines: what their AI does, and what to take

Date: 2026-11-12. Read from the source (shallow clones, never built or run):

| Repo | Commit read | Language, size | Verdict |
|---|---|---|---|
| [NikolayIT/BelotGameEngine](https://github.com/NikolayIT/BelotGameEngine) | `a4a6e4d` (2026-11-06) | C#, ~24k lines in `src/` + trainer tools + long measured write-ups | **Very relevant.** Several AI generations (rules, ISMCTS, neural + search, rules + exact endgame), all measured in mirrored-pair arenas. |
| [eowaaruil/belote-ai](https://github.com/eowaaruil/belote-ai) | `d1a1175` (2016-05-02) | C++, 1,270 lines | **Not useful as AI.** A tournament harness for French belote with a placeholder bot. |

Paths below are relative to each repo root.

---

## 1. eowaaruil/belote-ai (short)

A host program that loads four bots as shared libraries (`Makefile`, `game.cpp`) and plays French
"classique" belote to 1000 points: deal 5, an exposed card, two rounds of taking (`firstBid(Card)`,
`secondBid(Card)`), then 3 more cards. No declarations, no belote/rebelote (`README.md` lists them as TODO),
ties not handled. The only AI is the template `ai.cpp`:

```cpp
bool PLAYER_NAME::firstBid(Card c) {       // take the exposed suit only with its J and 9
    ... if (h->suit == c.suit) { if (h->value == CJ) j = true; if (h->value == C9) n = true; }
    return j && n; }
Card PLAYER_NAME::playCard() {
    if (isFirstToPlay()) return hand.front();     // leads its first card
    ... // follow with a higher trump if trumps led, else any card of the suit,
    ... // else trump if an opponent is winning, else hand.front()
```

No card memory, no inference, no search, no evaluation numbers. The only reusable idea is the harness
shape (bots disqualified for an illegal card or >10 s), which our `blot:arena` already exceeds (duplicate
deals). **Nothing to adopt.**

---

## 2. NikolayIT/BelotGameEngine — Bulgarian Belot

### 2.1 Their rules vs ours (do not assume they match)

From `etc/Rules.md` and `src/Belot.Engine/GameMechanics/*`:

| Topic | Bulgarian Belot (theirs) | Armenian Bazar Blot (ours) | Consequence for porting |
|---|---|---|---|
| Bidding | Contracts, not numbers: ♣ < ♦ < ♥ < ♠ < No trumps < All trumps; bid on **5 cards**, 3 more dealt after | Numbers 8–80 (sai) + a suit or Boy, or kaput; all **8 cards** seen before the bazar | Their bid models (point counts, `BidModel`, learned weights) price *contract kinds*, never *levels*. Our level choice has no analogue. Our bid inference is stronger (the bid was made on the whole hand). |
| Contract made | Bidders take more than defenders (else "inside"; equal = hanging points carried) | Card points + counted declarations + blot ≥ bid × 10 | Their "made/failed" thresholds and statistics don't transfer. |
| All trumps | Yes (every suit trumps, J-9 order) | No | All their all-trumps logic is irrelevant. |
| Double | Contra/re-contra as bids; bidding continues | Quansh by an opponent **at any time**, ends the bazar; Sur by either contract partner | Their doubling data (e.g. doubling an overcall cost 15.9 game points on average) is a different game. |
| Trump led | **Must play a higher trump if able** (`etc/Rules.md` §3.1) | **Any trump** (our spec; owner to decide) | Their inference "followed below the best trump → holds nothing higher" is **wrong for us when trumps are led**. |
| Void, opponent has trumped | Must overtrump if able, **otherwise any card** | Must beat if able, **otherwise any trump** | Ours reveals more: discarding a plain card after an opponent's ruff proves no trumps at all. |
| Declarations | Tierce/quarte/quint 20/50/100, carré J 200, 9 150, others 100; none in No trumps; declared by the first card | Similar values (9s 140, A 110; Boy carrés differ); declared before the first card, cards shown to all | Similar enough that their "declared cards are known" logic matches our `CardMemory`. |
| Belote/Blot | Optional to declare (all bots always do) | **Automatic** in our engine (`BlotEngine::play`, `state['belote']` set at the start of play) | Their "K/Q of trump played without a belote ⇒ the other is elsewhere" inference is **exact** for us — and we don't use it yet (see §3, item 1). |
| Scoring/match | 162/258/260 raw, rounded per contract; valat (+90); to 151, hanging points | Sai, 16/25 per deal, bid × m, to 301 | Their reward scale and match-equity table are not reusable as numbers. |
| Rank order, card points | Trump J 9 A 10 K Q 8 7 (20,14,11,10,4,3,0,0); plain A 10 K Q J 9 8 7 | Same | Zero-point cards (7, 8, plain 9) are the same — their equivalence pruning transfers 1:1. |

### 2.2 The AI generations and their measured strength

All results are mirrored pairs of whole matches (same deals, teams swapped) — the same method as our arena.

| Player | Design | Reported strength / speed |
|---|---|---|
| `SmartPlayer` | Hand rules: bid points ≥ 100 per contract; 6 play strategies (contract kind × ours/theirs) × seat position | Baseline |
| `ClaudePlayerIsmcts` | SO-ISMCTS, 100 ms a card, greedy perfect-information rollouts; Monte Carlo bidding over 300 deals | **+375 Elo** over SmartPlayer (89.7% ± 1.7, 300 games) — `NEURAL_NETWORK.md:58`, their `CLAUDE.md:217` |
| `ClaudePlayerHeuristic` | belot.bg rules for tricks 1–3, **exact minimax over sampled deals for the last 5 tricks**; bidding = ridge regression learned from replays | 0.83 ms/card; 87.7–89.2% vs SmartPlayer; ~even with ISMCTS-100 ms (`HEURISTIC_PLAYER.md`) |
| Neural "Master" | RL-trained value networks + early-trick search + 5-trick exact endgame, human-style tie-breaking, natural bidding | Strongest; 13 ms/card mean, 44 ms max |

The two lessons that matter most for us, both measured by them:
- **Exact endgames are the biggest cheap gain**: solving the last 2, 3, 4 tricks per sampled deal added
  **+26, +59, +90 Elo** over the rules; 5 tricks another +17–23; 6 tricks tied 5 (`HEURISTIC_PLAYER.md`, "How it got there").
- **A bot that plays with a human partner must bid and play "readably"**: self-play networks invented a relay
  (30% of suit bids without the trump J or 9); with a natural-bidding partner the fixed version won 62–64%
  (`HUMAN_PLAY.md`, "The networks' private bidding convention").

### 2.3 Bidding / trump choice

**Heuristic counts** (`src/AI/Belot.AI.SmartPlayer/SmartPlayer.cs:277`): J 55, 9 35, A 25, 10 20, K+Q 25,
lone K/Q 16, 7/8 15, plus side aces/tens and half the declaration points; bid if the best contract ≥ 100.

**Monte Carlo bidding** (`ClaudePlayerIsmcts.ChooseBid`, `Search/BidEvaluator.cs`): candidates are "pass"
(= the contract that stands, or a redeal worth 0) and every legal contract; each is played out on the
**same 300 random deals** by the greedy rollout policy; bid the best if it beats pass by `BidMargin` (0).
Doubles only with `MayDouble` (off by default; the neural Master uses `DoubleMargin` 2 game points to avoid
"strange" doubles). Our `BidPlanner` already has the same structure, with 24 samples.

**Bid inference** (`Search/BidModel.cs:161`): a sampled deal is accepted with the probability that a random
5 of the seat's 8 cards reproduces every bid *and pass* under SmartPlayer's rules:

```csharp
public bool FitsRandomFive(int seat, uint eightCards, Random random) {
    var five = eightCards;
    for (var drop = 0; drop < 3; drop++) { /* remove a random card */ }
    return this.Fits(seat, five);   // SmartBid(five) must equal every recorded choice
}
```

Note `UseBidInference` is **off by default** in ISMCTS. The heuristic player instead uses a soft weight
(`Neural/EndgameSearch.cs:706`): a world is ×`BidderHonourWeight` (1/3) when a seat that bid a suit holds
neither its J nor its 9 — measured +4/+8 Elo together with the signal weight (v7 row).

**Learned bidding** (`Heuristic/BidModel.cs`, `BidFeatures.cs`, `tools/NeuralTrainer/Bidding`): offline
"bidlab" replays every real bid decision with every alternative natural bid on the same cards, then fits the
gain-over-pass by ridge regression on features (trump J/9/A/10, length, side aces, voids, who bid what).
+25–28 Elo over hand-written counts. Findings that are qualitatively portable: holding the **jack of the
opponents' suit** is −6.1 for competing (pass and defend); holding the **jack of the partner's suit** −6.6 for
switching suits; competing over an opponent's suit bid generally pays because passing lets them make it.

### 2.4 Card play

**Card memory and inference** (`Search/PlayInference.cs`, `Search/RoundKnowledge.cs`, `Heuristic/CardMemory.cs`).
What the play reveals, under their rules:

```csharp
if ((type == Queen || type == King) && kind != NoTrumps && suit == beloteSuit) {
    var other = 1u << (card ^ 3);
    if (belote) known |= other;              // declared: holds the other
    else if (usePlayInference) excluded |= other;  // no belote: doesn't hold it
}
...
if (state.WinnerCard >> 3 == kind) {         // an opponent had trumped: overtrump was compulsory
    if (suit != kind || (HigherTrumps[WinnerCard] & bit) == 0) excluded |= HigherTrumps[WinnerCard];
} else if (suit != kind) excluded |= SuitMasks[kind];   // trumping was compulsory
```

Probabilities for the rules player (`Heuristic/CardMemory.cs:244`): P(seat holds card) = needs[seat]·w /
Σ needs·w over seats that may hold it; `ChanceBeat` = P(higher card of the suit) + P(void)·P(a higher
trump). These feed an **expected-gain formula** for every follow (`Heuristic/HeuristicCardPlay.cs:775`):

```csharp
var chance = winning ? (last ? 1 : 1 - m.ChanceOpponentBeats(card)) : holds;
var stake  = m.TrickPoints + m.Points(card) + rest;
var score  = ((2 * chance) - 1) * stake - this.KeepValue(card) + this.BeloteValue(card) - m.Order(card) * .01;
```

`KeepValue` (line 70) prices future value: a master = (points + 6) × P(not ruffed); a guarded ten 0.4×; a
trump 8 + 2·rank; half a belote +12. Throwing half the belote −20.

**Signals** (`Human/PlaySignals.cs`): a card thrown (not following, not trumping) while the opponents hold
the trick marks a suit with nothing in it — "don't lead it". Read in sampling as a weight 0.3 on worlds where
that partner holds an A/10 of that suit (`EndgameSearch.SignalWeight`, line 726). Measured gain small.

**Leading** (rules player): partner's call answered; a suit nobody else holds is cashed; declarers draw trumps
from the top with control, declarer's partner leads trumps at once (+12 Elo); defenders cash aces before trumps
are drawn (+24 then +14 Elo), lead trumps only with four, lead singletons to ruff.

**Rollout policy used by ISMCTS** (`Search/BelotSimulator.cs:273`) — cheap, and *uses the sampled world's
hands*, which is legitimate inside a determinization:

```csharp
if (state.TrickCards == 0) return this.ChooseLead(in state, legal, seat); // most valuable card neither opponent can beat, else cheapest
if (partnerWinning && !this.CanBeBeatenLater(in state, seat, state.WinnerCard, row))
    return this.MostValuableToGive(legal);          // smear, never a trump
for (each legal card that beats the trick)          // cheapest winner nobody after can beat
    if (cost < bestCost && !this.CanBeBeatenLater(in state, seat, card, row)) best = card;
return best >= 0 ? best : this.Cheapest(legal);     // cost = points + 12 for a trump
```

plus a random legal card 10% of the time (`RolloutRandomness`, "~52% against none, twice").

### 2.5 Search

- **ISMCTS** (`ClaudePlayerIsmcts.cs:378`): one tree over public play; each iteration re-deals unseen cards
  (constraint-consistent), descends with UCB using *availability* counts (`C = 0.7`, tuned: 0.1/0.2 < 0.4 <
  0.7 ≈ 1.2), adds one node, rolls out greedily; reward = team point difference mapped to [0,1]; plays the
  most-visited card. Time-boxed at 100 ms (C#, bitmask state); iteration counts are not reported.
- **Exact endgame** (`Neural/EndgameSearch.cs:448`): plain partnership alpha-beta per world, a
  transposition table at trick boundaries, budget 150,000 nodes / 8 ms. 3 tricks left: *all* deals
  enumerated (≤ 1,680); 4–5 tricks: 48 sampled worlds, weighted by bids and signals. Ties within 0.02 are
  broken by the rules' preference, and equal game-point results by raw card points (`RawTieBreak`, 1/1024 pt).
- **Equivalent-card pruning** (`EndgameSearch.EquivalentChoices`, line 323): zero-point cards of one suit
  (7, 8, and plain 9) with no unseen/other card between them are one choice.
- **World sampler with Hall's condition** (`Search/WorldSampler.cs:158`): cards dealt one by one to a
  seat weighted by room left; before committing a seat it checks, for each of the 7 subsets of the 3 other
  seats, that cards restricted to the subset fit its room. Never gets stuck, never drops constraints.
- **Match equity** (`Search/MatchEquity.cs`, DP over deal outcomes): tried as leaf utility, **+4 ± 4 Elo,
  not adopted**.

### 2.6 Evaluation harness

`tools/NeuralTrainer/Arena.cs` (mirrored pairs of whole matches, seeded, multithreaded), `Regret.cs`
(a strong reference grades every decision and sums loss per rule — "it showed where to look"), `Audit.cs`
(replays deals, flags human-visible mistakes such as *same-suit donations* — the ten under an ace with the
seven in hand — and checks them by double-dummy hindsight; also auction stats like "% of suit bids without J
or 9"), `BidLab.cs` (counterfactual bid replays). Every change was kept only if it won ≥ ~1 SE over 20,000
games.

---

## 3. Techniques worth adopting in our PHP engine

Ordered by value ÷ effort. "Effort" assumes the current `Bot/` structure.

### 1. Blot negative inference (exact, nearly free)
- **What**: our engine announces Blot automatically. So when seat X plays the trump K or Q and the deal's
  `belote` is not X, X does **not** hold the other honour. Also, after both have fallen with no Blot, nothing
  to learn; after one fell, whoever holds the other is not the player of the first.
- **Where**: `Search/PlayInference.cs` lines ~33–45 (excerpt in §2.4).
- **Why**: removes impossible worlds (e.g. a sampled defender "with Blot" who can't have it), sharpens trump
  counting. Our `CardMemory` currently only adds the known half for a declared holder.
- **Effort**: ~15 lines in `CardMemory::__construct`/`mayHold` + a test. **Risk**: none if the engine stays
  automatic; re-check if Blot ever becomes optional.

### 2. Stronger playout policy using the sampled hands
- **What**: inside `Simulator::finish`, every hand is known in the determinized world. Port
  `ChooseRolloutMove`: lead the most valuable card neither opponent can beat; smear only when no opponent
  after can beat the partner; win with the cheapest card no later opponent can beat; else the cheapest card
  (trump cost +12). 10% random card.
- **Where**: `Search/BelotSimulator.cs:273–420`.
- **Why**: our `BlotBot::card()` is view-only and crude (always the side ace, partner smear when `top >= 100`
  regardless of who follows), so playouts misjudge both kaput and contract making; better playouts sharpen
  CardSearch, BidPlanner and QuanshAdvisor at once. ISMCTS with exactly this policy is +375 Elo over their
  rule bot. Must respect *our* legality (any trump on a trump lead; undertrump when unable to overtrump).
- **Effort**: 1 day (new `PlayoutPolicy` used only by `Simulator`; keep `card()` for Easy). **Risk**: medium —
  "omniscient" opponents in playouts make the bot pessimistic about finesses (classic PIMC strategy fusion);
  validate with `blot:arena`. Watch PHP cost of the "can be beaten later" checks (precompute suit lists).

### 3. Exact endgame solve for the last 3 tricks (then 4 with a node budget)
- **What**: from trick 6 (≤ 12 cards), replace playouts with partnership alpha-beta per sampled world;
  zero-point equivalence pruning; tie-break equal sai by raw card points. Later try trick 5 with a node cap.
- **Where**: `Neural/EndgameSearch.cs:323` (equivalence), `:448` (Solve), `:122` (RawTieBreak).
- **Why**: measured +59 Elo (3 tricks) / +90 (4) over rules in their game; removes playout-policy noise exactly
  where last-trick +10, kaput stop and contract thresholds decide sai. Our sai rounding makes many results tie,
  so the raw-point tie-break matters (their "Master looked foolish" bug was exactly this).
- **Effort**: 1–2 days. In PHP a 3-trick solve is ≤ ~1.3k leaves before pruning — fine for 40 worlds;
  4 tricks needs a budget/TT. **Risk**: low for 3 tricks; time budget must hold on the queue worker
  (`PacesThinking` already gives 0.7–2.5 s).

### 4. Hall's-condition deal sampler
- **What**: before giving a card to a seat, check the 7 seat-subsets still fit; never drop constraints.
- **Where**: `Search/WorldSampler.cs:158–211` (excerpt-sized).
- **Why**: our `DealSampler::deal` retries 30 times then ignores all constraints on the last attempt —
  rare but silently produces impossible worlds when constraints are tight (late tricks, trump caps,
  declarations). Hall's check makes every sample valid in one pass, saving retries.
- **Effort**: half a day. **Risk**: low.

### 5. Dominance pre-filter and human-style tie tolerance
- **What**: (a) in a trick that is surely lost (an opponent wins and no card of ours can beat it, or we're last),
  never play a card when a lower card of the same suit with no more points also loses; (b) among cards whose
  averages are within a small tolerance (they used 0.3 game points for sampled values, 0.02 for exact), choose
  by a preference (keep masters/trumps/Blot halves, discard from a suit with no honours, smear on a sure
  partner trick).
- **Where**: `Human/HumanPreference.cs` (`Dominated`), `ClaudePlayerNeural.HumanStyle`; numbers in `HUMAN_PLAY.md`.
- **Why**: with 40 samples our averages are noisy; the "ten under an ace with the seven in hand" is the mistake
  human partners notice. Measured at no Elo cost by them. Also prunes candidates → fewer playouts.
- **Effort**: half a day. **Risk**: low; a wide tolerance cost them 9–13 Elo, so keep it small.

### 6. Equivalent-card pruning at the root of CardSearch
- **What**: treat 7/8/plain-9 of one suit with nothing unseen between them as one candidate.
- **Where**: `EndgameSearch.EquivalentChoices` (line 323).
- **Why**: fewer candidates × samples = more samples for the real choices at the same CPU in PHP.
- **Effort**: 2 hours. **Risk**: very low (identical outcome by construction; Blot cards are never zero-point).

### 7. Bid-honour and signal weights in the sampler
- **What**: multiply a world's likelihood by 1/3 when a seat that bid a suit holds neither its J nor its 9;
  by 0.3 per suit a partner threw away (while opponents held the trick) in which the world gives that partner
  an A or 10.
- **Where**: `Neural/EndgameSearch.cs:706` (`BidWeight`), `:726` (`SignalWeight`), `Human/PlaySignals.cs`.
- **Why**: our `DealSampler::likelihood` uses a sigmoid on `strength()`, which barely distinguishes "has the
  J/9" from "has points". With 8-card bidding the J/9 signal is even more reliable than in their 5-card bids.
- **Effort**: half a day. **Risk**: small gain (+4/+8 Elo for both there); human partners may not follow the
  discard convention — make the signal weight mild (0.5) and measure.

### 8. Counterfactual "bidlab" to tune BidPlanner (offline)
- **What**: in the arena, at each real bid decision replay the deal with every alternative bid on the same
  cards; record gain over pass by situation (opener/overcall/raise; J of the opponents' suit held, etc.).
- **Where**: `tools/NeuralTrainer/BidLab.cs`, `HEURISTIC_PLAYER.md` "What the numbers say".
- **Why**: our bazar has a much larger action space (numbers 8–80 × 5 strains × kaput) and only 24 samples;
  this finds systematic over/under-bidding (e.g. bid margins per level) and gives a cheap prior to prune which
  numbers to price. Their result: fitted weights beat hand-tuned by +25 Elo.
- **Effort**: 2–3 days (artisan command + analysis). **Risk**: low (offline); don't fit to self-play only
  (they found self-fitting exploits own habits — mix opponents).

### 9. "Natural bid" audit for human partners
- **What**: report the share of our suit bids without the trump J or 9, of Boy bids without an ace, and of
  Quansh calls; flag "same-suit donations".
- **Where**: `tools/NeuralTrainer/Audit.cs`; `HUMAN_PLAY.md`.
- **Why**: Monte Carlo bidding with a policy-played partner can learn unreadable bids; human partners then
  misplay. Cheap safety check for our platform, where AI usually sits with people.
- **Effort**: half a day in `blot:arena`. **Risk**: none.

### Weak or not applicable
- **Their bid point counts and learned weights**: contract kinds on 5 cards; no number levels, no Boy-as-×2
  scoring of ours, no kaput bid. Use only qualitatively.
- **Their doubling statistics**: their double is a bid in a continuing auction; our Quansh ends the bazar and
  zeroes the defenders if the contract is made. Not transferable.
- **Trump-led overtrump inference**: invalid under our "any trump" rule (valid only if the owner adopts the
  reblot/terz rule). Our *ruff* rule ("otherwise any trump") actually gives us a stronger inference than theirs.
- **Full ISMCTS**: likely only a modest gain over our flat PIMC once playouts and endgames are better, and the
  per-iteration overhead hurts in PHP. Revisit only after items 2–3 are measured.
- **Neural networks / PPO**: out of scope for a PHP server engine.
- **Match-equity utility**: +4 ± 4 Elo for them; our `Simulator::value` already scores match-ending deals.
- **belote-ai**: nothing beyond a harness idea we already have.
