# Blot AI research 02 — belote-rl, the Coinche Deep CFR report, the Belote AI write-up

Date: 2026-11-12. Read for our Armenian Bazar Blot AI (`apps/api/app/Games/Blot/Bot`, `docs/games/blot-ai.md`).
All downloaded material was treated as data: nothing was run, built or installed.

| # | Source | What it really is |
|---|---|---|
| 1 | [abucquet/belote-rl](https://github.com/abucquet/belote-rl), cloned at `e41d0cf` (2021-12-29) | An unfinished stub. It has no learning and no results. |
| 2 | [Günlük & Saulus, "Playing Coinche with Deep Counterfactual Regret Minimization"](https://theosaulus.fr/assets/pdf/Adversarial_ML_ProjectReport.pdf), a 16-page course report (Mila). I also read its code, [theosaulus/coinche](https://github.com/theosaulus/coinche), all branches. | PPO and Deep CFR on Coinche. It reports training curves only, with no head-to-head results. |
| 3 | [Pemeja, "Belote AI"](https://engineering.kaseovo.com/projects/belote-ai/) (2026). The code link, `github.com/tpemeja/belote-ai`, returns **404**, so I checked only the page. | PIMC search, networks distilled from it, and a learned bid net. Every result comes from a paired match with a 95% CI. **This is the most useful source.** |

---

## 1. abucquet/belote-rl — nothing to reuse

The repo has 291 lines of Python: `belote.py` (175), `card_elements.py` (58), `player.py` (44) and one notebook. The README says "Run the code: TODO".

- **No agent and no training.** `player.py` says "TODO be implemented". `choose_move` always returns `valid_moves[0]`. `update_policy` sketches tabular Q-learning (`lr=0.1, gamma=0.9`, reward = "points they gained in that trick"), but the file does not parse: it has mixed tabs and spaces, an em-dash `—` used as a minus sign (line 38), and `update_policy` has no `self`.
- **Bugs in the rules engine.** `play_trick` ignores `valid_moves` and plays `hands[next_up][0]` (line 115, "will be changed later"). The partner-winning exception calls `find_winning_card(hand, …)` on the player's *hand* instead of the trick (line 60). The trump is `random.choice` with no bidding (line 144). The contract has `# TODO: assess whether the contract was completed` (line 151).
- **Encoding.** `Deck` docstring: "toArray: create an np.array with features … TODO".
- The only finished work is `Hands probabilities.ipynb`, which estimates the chance of four of a kind. Exact: 1.56% for one player. Simulated (10k deals): 1.31% for one player and about 6% for any of the four players.

**Verdict:** there are no encodings, algorithms, budgets or results to report. The repo has no evidence of any kind.

---

## 2. The Coinche report: PPO and Deep CFR (Günlük & Saulus)

**Variant.** The report uses FFB *belote contrée* scored by "points annoncés". Bids run 80–160 in steps of 10, plus capot, coinche and surcoinche. All-trump and no-trump bids are left out "for simplicity" (p.2, fn 2). There are no declarations other than belote, and only the attackers' belote counts (`env.py`: `belote_bonus = 20 if any(p.has_belote … if p.attacker)`). The report plays one deal, with no match to 1000 (p.3).

**Observation encoding** (`coinche/gym/env.py` lines 28–36). One flat `Box(-1, 250)` vector of length 37 + 32 + 32 + 32 + 2 + 5 = 140:
- bid history as 37 integer codes;
- three 32-card masks: cards played, own hand, cards in the current trick;
- the two current scores;
- `[phase, contract, atout_code, coinche_surcoinche, phase_flag]`.

The authors concede (p.8) that integer-coded bids "implicitly impl[y] a spurious order … known to be inefficient for MLPs". There is no trump-relative canonicalisation, and no record of who played which card.

**Action encoding.** One `Discrete(44)` space. In the bazar, actions are bids. In play, the first 32 actions are cards. Illegal actions are masked: §2.4 and `get_action_mask`.

**Algorithms and budgets.**
- **PPO** uses SB3 `MaskablePPO` with defaults plus "64 environments in parallel, 512 steps … mini-batch 512" (p.6). One model plays both N and S against two heuristic E/W bots. The report does not give a total step count. The repo configs say `total_timesteps: 10000` (main) and `1_000_000` (branch 7bis). The SLURM script asks for 16 h, 1 GPU and 16 CPUs (`scripts/run.sh`).
- **Deep CFR** is written from scratch with outcome plus external sampling (§4.1, Alg. 3). Table 3: 5,000 iterations, 256 traversals, MLPs `[64, 64]`, lr 3e-4, `A_samples 4`.
- In the code (`train/wrapper.py`, `DeepCFRWrapper.traverse`), the traverser expands **every** legal action, the policy is taken from `policy_net` and not from regret matching on the advantage net, and the sampled "advantage" is `util − v_all[a]`. That is not Deep CFR's instantaneous regret. Rewards are not zero-sum: each team gets its own points ≥ 0, and on branches 7/7bis an all-pass gives −1000 to everyone. CFR's guarantees hold for two-player zero-sum games, and this 4-player team game with general-sum returns is neither.

**Reward shaping.** The terminal reward is the deal score (Table 2), plus a per-trick reward weighted 0.1 (`tricks_reward_factor = 0.1`, §5.2). Appendix D tries three alternative matrices, "mirror", "no_defense" and "good", which make a failed contract negative.

**Hidden information.** None is modelled. Both methods train a policy straight on the observation. There is no determinization, belief or search at play time.

**Bidding.** Bidding is learned end to end inside the same action space. That bids are in the observation at all is presented as the contribution: other implementations give "only the last bid".

**Results: claims and evidence.**
- *Claim:* "we are able to recover a Nash equilibrium". *Evidence:* the self-play episode length drops to 4, meaning everyone passes (Fig. 1). This is the trivial collapse the authors predicted. It is not a strong strategy, and the code's −1000 all-pass penalty makes it unclear which reward produced the figure.
- *Claim:* PPO "was able to learn successful strategies". *Evidence:* training curves of reward and success rate against random, simple and stronger rule bots (Fig. 2). There are no held-out matches, no confidence intervals and no number of evaluation deals. The learned behaviour is mostly **defend only**, with a 75% / 70% defence success rate. With the original reward, it **coinches every contract**, which exploits a heuristic opponent that ignores coinche (App. D).
- *Deep CFR vs heuristics:* the report itself calls it "somewhat unsuccessful". The bids oscillate and go all-in (§5.4).
- No result compares an agent with a human or with any search agent.

**Lessons for us.**
1. Model-free RL from scratch on a contract card game collapses into passing or defending. The cause is the asymmetric penalty for a failed contract: Coinche's 160 to the defenders, and our `bid × m + 16` to them.
2. Training against a fixed rule bot teaches the model to exploit that bot's blind spots, such as ignoring coinche. That is a warning for our arena too: a gain against `ClassicBlotBot` is not a gain in strength.

---

## 3. Belote AI write-up (Pemeja): the source that matters

**Variant.** Classic Belote *à la retourne* with take-or-pass bidding: 5 cards are dealt, one card is turned up, the rest come after. The page says this is "not Coinchée or Contrée". It uses one deal as one game with a signed margin, and **no declarations**: only belote-rebelote, granted automatically, "keeps the deck worth a fixed 162 (252 on a capot)". Built on an OpenSpiel fork (`python_belote`) with 38 flat actions, a C++ twin and 42 rule tests.

**Method ladder and results.** Each result is a paired, dealer-swapped match with a 95% CI. The matches are sized in advance for 80% power to detect 5 points a deal.

| Comparison | Margin (points a deal) |
|---|---|
| Random vs random (null check, 1,199 deals) | −0.04 [−3.52, +3.45] |
| Best card rule `win_or_dump` vs `highest_value` | +45.26 [+41.92, +48.60] |
| PIMC, 4 / 8 / 16 / 32 worlds, vs `win_or_dump` (bidding pinned) | +26.03 / +27.44 / +33.44 / **+38.68** [+35.18, +42.19] |
| PlayNet (distilled PIMC) vs `win_or_dump` | +33.81. Head to head against PIMC: −5.17 [−6.59, −3.74] |
| BidNet vs `hand_strength` rule, PIMC playing (6,000 deals) | **+5.50 [+4.05, +6.96]** |
| BidNet + PlayNet vs PIMC agent (6,000 deals) | −0.63 [−2.06, +0.81], a tie, at about 0.9 ms vs 120 ms a deal |

- **PIMC.** "Samples complete deals consistent with what its seat knows, plays every legal card out in each under `win_or_dump`, and keeps the best average", using **common random numbers** and **skipping forced decisions** ("about a third"). The page notes: "32 worlds is a floor, not an optimum"; every smaller budget loses head to head to 32.
- **Hidden information.** Determinization only. The sampled worlds are **uniform over consistent deals**: voids are respected, and "ignores what the auction and discards say" is listed as a limitation. There is no ISMCTS. The only ISMCTS item is a seeding-bug fix in OpenSpiel's ISMCTSBot ([open_spiel#1584](https://github.com/google-deepmind/open_spiel/pull/1584)), which says nothing about strength.
- **PlayNet.** An MLP, 145 → 256 → 128 → 1. The 145 inputs are 132 for the position in a "trump-relative encoding" and 13 for the candidate card; the exact features aren't published. It is trained by supervision on 846,086 PIMC card-advantage labels from 25,000 deals, with the split grouped by deal. Held-out R² is 0.41. The levers tried to close the 5-point gap to the teacher:

  | Lever | Result |
  |---|---|
  | 128 label worlds | +0.018 R² |
  | 2× the data | a tie |
  | Listwise loss | a tie |
  | PlayNet as **the playout policy inside PIMC** | **+1.48, a tie, at 1.4× the cost** |
  | A value net as the leaf evaluator | **−4.03 at depth 8, −23.29 at depth 0** |

- **BidNet.** An MLP, 81 → 128 → 64 → 1. Labels come from cloning each legal bid and playing it out with PlayNet in 24 consistent re-dealt worlds, with common random numbers across bids. "Resampling cuts label noise … from 147.2 to 46.5." In other words, it is an offline copy of what our `BidPlanner` already does online.
- **Decision grading.** 411 stored positions are graded against PIMC at 128 worlds. Points given up per decision:

  | Player | Points given up per decision |
  |---|---|
  | Random | 8.25 |
  | `win_or_dump` | 4.01 |
  | PlayNet | 2.69 |
  | PIMC-32 | 0.92 |
  | PIMC-128 | 0.48 |

  The page warns that "a grader ranks players; it does not predict margins".
- **Reward shaping.** None; the target is the signed deal margin. There is no RL, PPO, DQN or self-play training.
- **A note on noise.** One match read +6.75 at one seed and +1.92 at another: "a run sized for 5 points cannot reliably see a 5-point effect". Resizing the run for 2 points settled it.

**How strong the evidence is.** It is strong for the claims made: the ladder is internal, and the margins are against that project's own rule bots and its own PIMC. There is no outside opponent and no human-strength data; the only human play is a browser demo. The code is not public (404), so I could not check the encodings or the rule engine.

---

## 4. Rule differences: none of the three plays our game

| Aspect | belote-rl (stub) | Coinche report | Pemeja | **Our Bazar Blot** (`blot-rules.md`) |
|---|---|---|---|---|
| Bidding | none (random trump) | 80–160 in 10s + capot, coinche ×2, surcoinche ×4 | take-or-pass on the turned-up card | numbers **8–80 sai**, any suit or **Boy**; partners raise; a passed player may bid again; **kaput** bid (25) |
| No-trump | — | dropped | — | **Boy**: A = 19, base ×2, Quansh ×3, Sur ×5 |
| Doubling | — | coinche/surcoinche in turn | — | **Quansh at any moment of the bazar**; Sur by either bidder, in turn |
| Declarations | — | belote, attackers only | belote only, automatic | terz/50/100/four of a kind, announced with cards shown, best pair only; blot 2 sai always |
| Contract test | — | card points ≥ bid | card points vs the other side | card points + counted declarations + blot ≥ bid × 10 |
| Failed contract | — | defenders 160 × mult | — | defenders bid × m + 16 + all declarations |
| Kaput | — | 250 | 252 deck | 25; the kaput contract **stops at the first lost trick**; Tars kaput loses the match |
| Trump obligations | overtrump not modelled | FFB: overtrump even on a trump lead (`trick.py` l.42–44) | French | beat the highest trump when trumping in; **any trump on a trump lead** (owner to decide) |
| Unit | 500 pts | one deal | one deal | match to 301 with instant win/loss conditions (`Simulator::MATCH = 150` in our value) |

The card values and orders are the same everywhere (J 20, 9 14 in trumps; 162 in total), so trick-play heuristics carry over. Bidding models, bid labels, the declaration logic and the scoring do **not** carry over. Any learned bid value has to be relabelled with `BlotEngine::score`.

---

## 5. What could help our PHP bot (~50–150 ms per move, ~48 ms measured)

**What we already have that Pemeja found works best.** Our `CardSearch` is the same thing as his strongest card player:
- PIMC over legal cards;
- the same sampled deal for every card, which gives us common random numbers;
- a forced card returns immediately (`count($cards) === 1`);
- a quick rule playout (`BlotBot::card` ≈ `win_or_dump` / `help_partner`).

We also already do what he lists as an *unmeasured* future step: worlds weighted by the auction (`DealSampler::likelihood`). The cheap, proven wins are therefore in **measurement and bidding**, not in a new search.

### (a) A better playout policy learned offline (table or linear model)
- **Evidence:** Pemeja's PlayNet as the PIMC playout policy gave **+1.48, a tie**, at 1.4× the cost. A value net at the leaf was **worse**, −4 to −23.
- **Effort:** medium to high. We would need a Python labeller, or PHP label dumps, plus a model exported to PHP arrays.
- **Risk:** low correctness risk, but the cost per playout grows.
- **Expected gain:** small, within about 1–2 sai per 10 deals.
- **How we'd benchmark it:** arena pro-vs-pro, with only `Simulator`'s policy swapped and the bazar pinned.
- **Recommendation: not now.** A cheaper variant: hand-tune 2–3 known weak spots in `BlotBot::card` (defender-side kaput defence, Boy leads) using decision grading (see below).

### (b) ISMCTS instead of PIMC
- **Evidence:** none of the three sources measures ISMCTS. Outside them, the literature finds PIMC very strong in trick-taking games, and its strategy-fusion and non-locality errors matter mostly in the last tricks (Long et al., AAAI 2010; Cowling et al., IEEE TCIAIG 2012, where ISMCTS roughly matched determinized UCT).
- **Effort:** high. We'd need a tree per information set, re-sampling at every node and UCB tuning.
- **Risk:** in PHP at about 100 ms we get a few hundred playouts. That is fewer than our current 40 deals × legal cards, so the tree would be shallow and noisy.
- **Expected gain:** uncertain, possibly negative.
- **A cheaper fix for fusion:** endgame exact search. When ≤ 3–4 tricks remain, the unseen cards are 12 or fewer, so solve each sampled deal exactly with alpha-beta instead of using the rule playout. That removes the playout error where it matters most.
- **Recommendation: skip ISMCTS. Try the exact endgame solve** (medium effort, low risk).

### (c) Learned or calibrated bid-value estimator — the best candidate
- Pemeja's only measured strength gain beyond PIMC came from bidding: **+5.50 [+4.05, +6.96]**.
- Our `BidPlanner` already prices bids online, but `IMPLEMENTATION_REPORT.md` names its weak spot: the playouts "play every seat by simple rules … optimistic on high contracts". The `KAPUT_SURE = 0.9` guard is a manual patch for the same thing.
- **Plan:** run offline in PHP with an artisan command, so no Python is needed.
  1. Label many deals by playing the contract out with full `CardSearch` at every seat, the slow teacher.
  2. Fit a small **correction** of `BidPlanner`'s estimate: per contract type (suit, Boy, kaput) and bid bucket, `true_made_rate ≈ f(estimated_made_rate)`, as an isotonic table or a logistic fit. Ship it as a PHP constant array.
  3. If that is not enough, a linear BidNet on hand features (trump length, J/9/A/10 per suit, voids, declarations held, seat, the bazar so far).
- **Effort:** medium, 1–3 days.
- **Risk:** low. It's a table lookup, and the online cost stays the same.
- **Expected gain:** fewer failed high contracts and kaput attempts. Probably the largest gain available to us.

### (d) Self-play tuning of heuristic weights
- **Targets:** `BidPlanner::MARGIN`, `SPAN`, `KAPUT_SURE`; the `DealSampler` sigmoid scales and floors (0.05, 0.1, the /15 scale, the Quansh weights `[0.15, 0.4, 0.8, 1.0]`); the `QuanshAdvisor` thresholds; sample counts.
- **Effort:** low. Expose the knobs to `blot:arena` and run coordinate search or CMA-ES.
- **Risk:** overfitting. The Coinche report shows learners exploiting a fixed rule bot, so tune **pro vs pro** (the candidate against the current version), not against `ClassicBlotBot`, and confirm on new seeds.
- **Expected gain:** small to medium per knob. Do it after (c).

### Measurement upgrades to adopt first (cheap, from Pemeja)
1. **Report a 95% CI and size the arena.** Our "+6.45 sai per deal over 300 duplicate deals" has no interval.
   - Compute the per-pair standard deviation once, then deals = (2.8 · sd / effect)².
   - Check the harness with a **null run**: pro vs pro must read ≈ 0.
2. **Pin one half.** Compare card play with the bazar fixed (both sides use the same bid policy), and compare bidding with the card policy fixed. Today, pro vs classic mixes both.
3. **Decision grading.** Store about 400 positions with their public history and grade any change by "sai given up versus `CardSearch` at 256 samples". That is a fast regression test for card-play tweaks, too small to show up in a match.
4. **A sample-count curve.** His every-smaller-budget-loses result suggests checking 40 vs 80 card samples within our latency budget, since 32 → 128 halved his per-decision loss (0.92 → 0.48).

**Python?** Only worth it if (c) needs more than a calibration table. A linear or logistic model can be fitted in PHP or in a one-off notebook and exported as coefficients. Neither source supports bringing in deep RL (PPO, Deep CFR) for this game.
