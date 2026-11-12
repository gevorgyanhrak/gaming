# 03 · CFR, Deep CFR and OpenSpiel: what applies to our Blot AI

Research note, 2026-11-12. Sources read (source code, shallow clones, nothing built or run):
[EricSteinberger/Deep-CFR](https://github.com/EricSteinberger/Deep-CFR) and
[google-deepmind/open_spiel](https://github.com/google-deepmind/open_spiel) (`open_spiel/algorithms`,
`open_spiel/games`, `open_spiel/bots`, `open_spiel/python/algorithms`). Paths below are relative to each repo.

## 0. What we have today (baseline for the comparison)

- `CardSearch::evaluate` is **flat Monte Carlo over determinizations**: for each of 40 sampled deals
  (`DealSampler`), every legal card is applied and the deal is **rolled out with `BlotBot::card`** for all four
  seats (`Simulator::finish`), then averaged. All cards are scored on the same worlds (common random numbers, a
  good variance reducer).
- This is *not* classic PIMC. Classic PIMC (OpenSpiel `bots/pimc_bot.cc`, GIB in bridge) **solves** each world with
  perfect-information search. Our rollouts use a policy that only reads the trick, the trump and the player's own
  (sampled) hand, so the rollout players are not clairvoyant. Consequence: we mostly do **not** suffer PIMC's
  strategy-fusion error; our error is the **weak rollout policy** ("defence in samples is weaker than real", as
  IMPLEMENTATION_REPORT already notes) plus one-ply myopia.
- Arena: +6.45 sai/deal vs Classic, ~48 ms per decision.

## 1. OpenSpiel's ISMCTS

File: `open_spiel/algorithms/is_mcts.{h,cc}` (C++), `open_spiel/python/algorithms/ismcts.py` (Python, same design).
It is Cowling et al.'s IS-MCTS.

**One tree, nodes keyed by (current player, information-state string).**

```cpp
// is_mcts.h:38-41
// The key identifying a node contains the InformationStateString or
// ObservationString, as well as the player id, because in some games the
// observation string can be the same for different players.
using ISMCTSStateKey = std::pair<Player, std::string>;
```

So an opponent's node is keyed by *that opponent's* information state in the sampled world. Opponents therefore
never "see" our cards (this removes the opponent-side clairvoyance of PIMC), but their nodes fragment by their
sampled hand.

**Belief / resampling.** Every simulation starts from a fresh determinization (or one of `max_world_samples`
cached ones, sampled with replacement):

```cpp
// is_mcts.cc:86-95 (abridged)
for (int sim = 0; ... sim < max_simulations_; ++sim) {
  std::unique_ptr<State> sampled_root_state = SampleRootState(state);
  SPIEL_CHECK_TRUE(root_infostate_key == GetStateKey(*sampled_root_state));
  RunSimulation(sampled_root_state.get());
}
```

`SampleRootState` calls the game's `State::ResampleFromInfostate` (or a custom `SetResampler` callback). The
belief is whatever the game's resampler does; there is no reweighting inside ISMCTS.

**Chance handling.** Chance nodes inside the tree are sampled from their true distribution and stepped through
(no chance nodes in the tree):

```cpp
// is_mcts.cc:343-351
if (state->IsTerminal()) {
  return state->Returns();
} else if (state->IsChanceNode()) {
  Action chance_action =
      SampleAction(state->ChanceOutcomes(), RandomNumber()).first;
  state->ApplyAction(chance_action);
  return RunSimulation(state);
}
```

**Expansion and leaf evaluation.** One new node per simulation; the leaf is valued by an `Evaluator`
(in tests `RandomRolloutEvaluator(1, seed)`, i.e. one random rollout). Unvisited legal actions are tried first, in
shuffled order (`CheckExpand`, lines 324-341). With `allow_inconsistent_action_sets` the node's actions are filtered
to the ones legal in the current world before UCB (`FilterIllegals`) — that is the "availability" problem of
ISMCTS (a card may be legal in one world and not another, e.g. opponents' follow-suit options).

**Selection: plain UCB1, ties broken at random.**

```cpp
// is_mcts.cc:300-302
double uct_val = action_and_child.second.value() +
                 uct_c_ * std::sqrt(std::log(node->total_visits) /
                                    action_and_child.second.visits);
```

Python adds PUCT with priors (`ismcts.py:301-317`). Constants used in the repo: `uct_c = 5.0`, 1000 simulations
in `is_mcts_test.cc:66-76`; `uct_c = 1.0`/`1.5`, 10–100 simulations in the Python tests. Note OpenSpiel returns
are usually in [-1, 1]; with our sai scale (±30, ±150 for a match end) `c` must be rescaled (or values normalised).

**Final policy.** `kNormalizedVisitCount` (C++ default, sampled from), `kMaxVisitCount` (Python default), or
`kMaxValue` (`is_mcts.cc:124-176`). For a bot that plays its best move, MaxVisitCount is the right choice.

**Comparison with PIMC.** OpenSpiel's `bots/pimc_bot.cc` is textbook PIMC: per determinization run alpha-beta
(2-player zero-sum) or MaxN (otherwise), then **vote** — the most frequently best action wins:

```cpp
// pimc_bot.cc:93-119 (abridged)
for (int i = 0; i < num_determinizations; ++i) {
  state = root_state.ResampleFromInfostate(player, rng_func);
  ... algorithms::MaxNSearch(*state->GetGame(), state.get(), value_function_, depth_limit_);
  action_counts[search_result.second] += 1;
}
```

Known PIMC errors (Frank & Basin 1998): *strategy fusion* (the solver may play differently in each world after
this move, though in reality it cannot tell the worlds apart) and *non-locality* (values in a world ignore that
opponents would have steered elsewhere). ISMCTS fixes strategy fusion for **our own** later moves only when our
nodes are shared across worlds — and in a trick-taking game our own later information state depends on the
cards opponents played, which differ between worlds, so with a few hundred iterations deep nodes are visited only
once or twice. In practice, at our budget, ISMCTS on Blot reduces to **UCB over the root cards + one or two
plies + rollouts** — essentially what we already do, with smarter allocation. It does not fix non-locality either.

**What OpenSpiel's trick-taking games do.**
- `games/bridge`: by default (`use_double_dummy_result = true`, `bridge.h:281`) the play phase is **skipped**:
  after the deal it calls the DDS double-dummy solver (`ComputeDoubleDummyTricks`, `bridge.cc:929`) and scores
  the auction from it. So the bridge environment is a *bidding* game valued by perfect-information solving —
  the same idea as our `BidPlanner`, but with an exact solver instead of rollouts. Its resampler
  (`bridge.cc:215-300`) deals uniformly subject only to known voids — **no inference from the auction**.
- `games/hearts`: resampler likewise ignores the passes it cannot see ("we have no way to model unobserved
  opponent pass actions anyway", `hearts.cc:651-654`). The strong Hearts bot is the external **xinxin**
  (Sturtevant) wrapped in `bots/xinxin`: `iiMonteCarlo` over worlds, each world searched by perfect-information
  **UCT** with a Hearts playout module; generator defaults `uct_num_runs = 50`, `iimc_num_worlds = 20`
  (`xinxin_game_generator.cc:33-35`). That is PIMC with UCT as the per-world solver — closest relative of our
  design, with a stronger per-world search.
- `games/euchre`, `oh_hell` implement `ResampleFromInfostate` (usable by ISMCTS); `spades`, `skat`, `tarok`
  don't. No OpenSpiel bot for them; `tarok/README.md` says "any algorithm that relies on the explicit game tree
  likely isn't viable for a game this large".

Take-away: OpenSpiel offers no magic for trick-taking games; its best practice is *determinize + strong per-world
solver*, with uniform void-only beliefs. Our `DealSampler` (bazar likelihood, trump caps, shown cards) is already
richer than OpenSpiel's resamplers.

## 2. Deep CFR / MCCFR for Blot — honest assessment

**What the Deep-CFR repo is.** A PokerRL-based implementation (`DeepCFR/workers/...`, imports
`PokerRL.game.PokerEnvStateDictEnums`); every game class is a poker variant. The traversal is outcome/external
sampling with `n_actions_traverser_samples` actions at the traverser's nodes and one sampled action elsewhere
(`MultiOutcomeSampler.py:8-26`). The paper experiment (`paper_experiment_sdcfr_vs_deepcfr_h2h.py`) is
**2-player Flop Hold'em** with `n_learner_actor_workers=20` on a 24-core machine, `n_traversals_per_iter=15000`,
`n_iterations=150`, `max_buffer_size_adv=2e6  # *20 LAs = 40M`, and evaluation of 1.5 M hands per seat.
Not reusable for Blot without writing a new environment, network inputs and a team-aware sampler.

**Why it does not fit our game.**
1. *Theory.* CFR's convergence guarantee is for 2-player zero-sum. Blot is 2 teams, but partners hold different
   private information, so it is a **team game** (an adversarial team game: the right solution concept is
   TMECor, which CFR does not compute). OpenSpiel's own MCCFR warns about n>2 players:

   ```cpp
   // external_sampling_mccfr.h:37-41
   // ... In n>2 players, this can be a problem for several
   // reasons: first, it does not compute the estimate as described by the
   // (unbiased) stochastically-weighted averaging in chapter 4 of Lanctot 2013
   ```
   Pluribus-style "it works empirically" exists for poker, but there is no evidence for partnership card games.
2. *Size.* Our hand alone is C(32,8) ≈ 10.5 M, times trump/Boy, the bazar history (bids 8–80 × 5 suits, passes,
   Quansh, Sur), declarations, and up to 31 played cards. Tabular CFR/MCCFR is impossible; abstraction-free Deep
   CFR would need a network over that state, millions of traversals per iteration (each traversal is a full deal
   through `BlotEngine::apply` in PHP — we'd have to rewrite the engine in Python/C++ for training), weeks of
   multi-core compute, and a neural network inference path in PHP at play time.
3. *Payoff.* Professional trick-taking AIs (bridge: GIB/WBridge5/Ben; skat: Kermit) are determinization + solver
   + learned inference/bidding models, not CFR.

**Verdict:** not realistic for this team, this stack or this budget. If we ever want learning, the realistic path
is **imitation of our own search** (Expert-Iteration style): log `(seat view features → chosen card / bid)` from
arena games, fit a small linear or tiny MLP model offline, and use it (a) as the rollout policy in `Simulator` and
(b) as a play-likelihood in `DealSampler`. A dot product over ~200 features is cheap in PHP.

## 3. Recommendations for our engine (50–150 ms per move, PHP)

Ordered by gain/effort. Every item keeps the engine as rules authority and uses only the seat view.

### R1 · Exact endgame search over sampled worlds (PIMC proper, late in the deal)

When every hand has ≤ 3 cards (≤ 4 when follow-suit leaves few options), solve each sampled world exactly with
team alpha-beta instead of a rollout. With 3 cards per hand the tree is at most 3!^4 = 1 296 leaves before
follow-suit pruning; alpha-beta plus move ordering visits far fewer. Value = the true deal result
(`BlotEngine::score` with declarations/blot already fixed, kaput stop, last trick +10, match end), so the
non-linear "contract made/failed" threshold is handled exactly — this is where rollouts lose the most sai.

```php
// CardSearch::evaluate — branch on cards left
if (max($view['hand_sizes']) <= self::ENDGAME_CARDS) {
    foreach ($worlds as $hands) {                         // ~20–40 sampled worlds
        $s = TrickSolver::from($view, $hands);            // compact ints, no engine arrays
        foreach ($cards as $card) $totals[$card] += $s->value($s->play($card), $team);
    }
}
// TrickSolver::search($s, $alpha, $beta): team 0/1 = max/min on score difference
//   legal(): follow suit; must trump / over-trump unless partner wins (blot-rules.md "Play")
//   order: winning cards first (cheapest winner), then point dumps on partner's trick
//   leaf: BlotEngine::score(contract, points, tricks, decl, belote, stoppedEarly)
//   transposition table keyed by (remaining cards bitmask, leader, trick-so-far, team points)
```

- Effort: 2–3 days (solver, bitmask cards, property test that `TrickSolver::legal` equals
  `BlotEngine::legalActions` on thousands of random states — required so the AI never disagrees with the engine).
- Risk: low-medium. Clairvoyance (strategy fusion) exists, but in the last 3 tricks most cards are known from
  voids/caps, so the error is small; GIB showed this works well in bridge endgames.
- Gain: largest expected per-ms gain — exact last-trick and threshold play, and it frees time budget.
- Benchmark: `blot:arena 300 --seed=1000 --a=pro --b=pro-baseline` (add a `--a=pro+endgame` variant to
  `BlotArenaCommand::pick`), compare sai/deal and contracts made, plus a unit test with known-optimal endings.

### R2 · Root bandit with common random numbers (sequential halving)

Instead of 40 worlds × every card, spend the budget where it matters: round r evaluates the surviving cards on
the same fresh batch of worlds (keeps CRN), then drops the worse half.

```php
$alive = $cards; $worlds = [];
foreach ([8, 8, 12, 16] as $batch) {                      // ~44 worlds, fewer rollouts per world late
    $new = $this->sampleWorlds($memory, $view, $batch, $random);
    foreach ($new as $w) foreach ($alive as $c) $sum[$c] += $this->rollout($w, $c);
    $n += $batch;
    if (count($alive) > 2) $alive = array_slice(sortByMean($alive, $sum, $n), 0, (int) ceil(count($alive) / 2));
}
```

- Effort: half a day. Risk: low (keep a "never drop within 0.5 sai of the best" guard).
- Gain: with 5–6 legal cards roughly 2× more worlds on the top two candidates for the same time; this is what UCB
  in ISMCTS buys at the root, without the fragmentation. Benchmark: same arena, also record ms/decision.

### R3 · Equivalent-card merging (move ordering for free)

Cards of one suit that are adjacent in rank once the played cards between them are removed, and equal in points
(e.g. trump 8 and 7; plain 9-8-7 in a trump game, all 0 points) are strategically identical: evaluate one,
play the cheapest. Typical hands shrink from 5–6 to 3–4 candidates.

- Effort: a few hours (`BlotCards::strength` + `points` already exist). Risk: very low. Gain: 20–40 % more
  worlds per candidate at the same time.

### R4 · Better determinization weighting

Today `DealSampler::sample` rejection-samples with `FIT_TRIES = 12` and falls back to the best-fitting deal, which
biases towards over-fitting deals; `deal()` fills sequentially "weighted by the room left", which is also not
uniform over consistent deals.

- (a) **Importance weights** instead of rejection: draw N consistent deals, weight each world's result by
  `likelihood()` (self-normalised). Every rollout counts; no wasted deals.
- (b) **Play inference**: multiply in P(observed card | sampled hand) for each card a player chose freely, using
  a softened `BlotBot::card` (e.g. 0.6 for the policy's card, the rest spread). This captures signals such as
  "partner smeared a 10 → probably no ace there" and "defender didn't take with A → doesn't have it". Weight per
  play clamped (≥ 0.2) because humans aren't our policy.
- (c) **World reuse** within a deal: keep last move's weighted worlds, drop those inconsistent with the new trick,
  remove the played cards and top up — saves sampling time and stabilises decisions between tricks.

```php
$w = $this->sampler->likelihood($orig, $view, true) * $this->playLikelihood($orig, $view);
$totals[$card] += $w * $value; $weights += $w;   // mean = totals / weights
```

- Effort: 1–2 days. Risk: medium (over-trusting inference against human play; tune the clamp in the arena and
  check against `ClassicBlotBot` *and* a "noisy pro" opponent so we don't overfit to our own policy).
- Gain: moderate; biggest in defence after 2–3 tricks.

### R5 · A stronger rollout policy (cheap, compounding)

Every recommendation above still scores worlds through `BlotBot::card` until the endgame solver takes over. Two
known holes: the leader never draws trumps unless it holds J, and defenders don't keep a guard. Add 3–4 rules
(bidder with ≥ 3 top trumps leads trump; don't lead a suit where the next opponent is void and holds trumps; keep
the last trump when the opponents still hold a higher one). Later, replace it with the learned imitation model of §2.

- Effort: 1 day. Risk: medium — a policy change moves all searches; arena-check every rule separately.

### R6 · ISMCTS proper — only as an experiment

If wanted: SO-ISMCTS over `Simulator`, our nodes keyed by our seat-view hash, opponents' and partner's nodes keyed by
**public** information only (OpenSpiel's `use_observation_string` idea), UCB1 with values scaled to [-1, 1]
(divide by ~40 sai, MATCH capped), `c ≈ 0.7`, availability counts for cards not legal in every world, leaf =
`Simulator::finish` rollout, final move = max visits. At ~0.3 ms per rollout we get ~300–450 iterations in
100–150 ms — too few to grow useful depth with 4 players, so expect little over R2. Effort 3–4 days, risk medium,
expected gain small; do it after R1–R4 and keep it only if the arena says so.

## 4. How to benchmark

1. Add variants to `BlotArenaCommand::pick` (e.g. `pro`, `pro-endgame`, `pro-halving`, `pro-weighted`), each a
   `BlotBot` with a flag; never compare against `classic` only — run **pro vs new-pro** on the same seeds.
2. 300 duplicate deals is enough to see ±1.5 sai/deal; for smaller effects run 1 000 deals over 3 seeds and report
   mean ± standard error per deal pair (duplicate pairs are independent samples).
3. Track per decision: average and p95 ms (budget 150 ms at p95), contracts made %, Quansh/Sur hit rate, kaputs.
4. Unit tests: endgame positions with a known best card (from `BlotProBotTest.php` style), and the solver/engine
   legality property test.

## 5. Summary table

| # | Change | Effort | Risk | Expected gain |
|---|---|---|---|---|
| R1 | Exact team alpha-beta at ≤ 3–4 cards per hand | 2–3 d | low-med | high |
| R2 | Sequential halving at the root, CRN kept | ½ d | low | medium |
| R3 | Merge equivalent cards | hours | very low | medium (time) |
| R4 | Importance weights, play inference, world reuse | 1–2 d | medium | medium |
| R5 | Stronger rollout policy | 1 d | medium | medium |
| R6 | SO-ISMCTS | 3–4 d | medium | small at our budget |
| — | Deep CFR / MCCFR | weeks + new stack | high | not justified |
