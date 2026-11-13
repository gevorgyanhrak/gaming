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

## Self-play learning of the bazar (2026-11-13)

600 duplicate deals of the AI against itself (seeds 20000–23000 × 150) gave 1,200 notes: the chance the look-ahead
gave each final contract and whether it was made. What it taught (`blot:calibrate`):

| AI's chance | contracts | it said | really made |
|---|---|---|---|
| 0–20% | 35 | 10% | 26% |
| 20–40% | 161 | 32% | 27% |
| 40–60% | 360 | 50% | 52% |
| 60–70% | 140 | 65% | 59% |
| 70–80% | 160 | 74% | 66% |
| 80–90% | 100 | 85% | 75% |
| 90–100% | 244 | 97% | 95% |

So the look-ahead is about right in the middle and 6–10 points too optimistic about solid-looking contracts.
Bidding with the learned chances against the raw ones (400 fresh duplicate deals, seeds 30000–33000):
**−0.28 ± 1.90 sai per deal** — no gain (contracts made 60% either way). The table is not shipped; the tools stay
(`blot:arena --record`, `blot:calibrate`, `BidCalibration`, read only when `Bot/bid-calibration.json` exists).
Likely why: a uniform correction also shifts what passing and the opponents' bids are worth, which it doesn't model.

## Next candidates (not done; ranked)

1. Learn the whole bid choice, not one probability: per hand features (trumps, top trumps, aces, declarations,
   seat) → realised sai of each bid, from self-play, and compare with the look-ahead's price.
2. Reuse samples between tricks of a deal (speed, then spend the time on more samples).
3. ISMCTS only after the above; tuning weights pro-vs-pro on fresh seeds.
