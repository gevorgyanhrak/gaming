# Blot AI research — unified report (2026-11-12)

Three research agents read the actual source of the requested projects (cloned read-only, nothing run) and wrote:
[01 — Belote engines](01-belote-engines.md) (NikolayIT/BelotGameEngine, eowaaruil/belote-ai),
[02 — RL and reports](02-belote-rl-and-reports.md) (abucquet/belote-rl, the Saulus Coinche report, the kaseovo
write-up), [03 — CFR and OpenSpiel](03-cfr-and-openspiel.md) (Deep-CFR, OpenSpiel ISMCTS/PIMC/trick-taking bots).

## What the evidence says

- **Sampled-deal Monte Carlo search is the strongest measured card play** in these sources (kaseovo: +38.7 points a
  deal over the best rule bot; OpenSpiel's PIMC bot). Our `CardSearch` already is that, with rollouts that never see
  hidden cards (so less "strategy fusion" than textbook PIMC).
- **The weak spot is the playout policy and the last tricks**, not the search framework. BelotGameEngine's exact
  endgame was worth +59..+90 Elo there.
- **Deep RL / Deep CFR showed no gain over search**: belote-rl is a stub; the Coinche PPO collapsed to passing or to
  doubling everything against rule bots; Deep CFR targets 2-player poker and needs cluster-scale training. Not adopted.
- **Rules differ**: French/Bulgarian Belote and Coinche bid differently (contracts, no number bids in sai, no Boy in
  Coinche), double without ending the bidding, and must overtrump on a trump lead. Nothing was ported as-is.

## Adopted (in code, benchmarked)

| Technique | Where | Source |
|---|---|---|
| Exact endgame: alpha-beta over each sampled deal when every hand has ≤ 3 cards, scored with `BlotEngine::score`, kaput stop included | `Bot/EndgameSolver`, used by `CardSearch` | BelotGameEngine, OpenSpiel PIMC |
| Equivalent cards tested once (same suit, nothing out between, same points) | `CardSearch::distinct` | BelotGameEngine, report 03 |
| Exact Blot inference: a trump K or Q played without Blot means the other isn't in that hand | `CardMemory::missing` | BelotGameEngine `PlayInference` |
| Paired 95% confidence intervals in the arena | `Arena/BlotArena` | report 02 |

**Arena (150 duplicate deals, seed 5000, `--a=pro --b=pro0`):** +2.32 ± 2.93 sai per deal for the adopted techniques —
positive, not yet significant. Earlier, pro vs the old rule bot: +6.45 sai per deal over 300 duplicate deals.

## Tried next (2026-11-13; 400 duplicate deals each, seeds 7000/8000/9000/10000 × 100, four shards)

| Candidate | Result (sai per deal, 95%) | Kept? |
|---|---|---|
| 1. Card-counting playout policy (masters, draw trumps with the master trump, load a partner's trick only when it is a master) | +0.32 ± 2.04, 15% slower; flipped two bazar scenarios towards no-trump | No — removed |
| 4. Sequential halving at the root (same sample budget, weaker half dropped each round) | +0.28 ± 2.01, ~15% slower | No — removed |
| 3. Sampler: Hall's condition over the three hidden hands while dealing; the old "drop the constraints after 30 tries" fallback is gone | correctness guarantee; the old fallback was not hit in tested positions; 0.056 vs 0.026 ms per deal (negligible) | Yes |

Reading: card play is saturated at this sample count — better rollouts and smarter sample spending don't move the
result measurably. The pro bot's lead over the rule bot came mostly from the bazar (contracts made 73% vs 57%), so the
remaining lever is bidding.

## Next candidates (not done; ranked)

1. Bid-value correction learned offline: record, in pro-vs-pro self-play, each priced contract's predicted make
   chance and whether it was made; calibrate the planner with that table (the only measured gain beyond search in
   report 02: +5.5).
2. Reuse samples between tricks of a deal (speed, then spend the time on more samples).
3. ISMCTS only after the above; tuning weights pro-vs-pro on fresh seeds.
