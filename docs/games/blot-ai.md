# Blot AI — how it decides

The blot AI (`apps/api/app/Games/Blot/Bot`) plays from its own seat view only: its cards and what the whole
table saw (cards played, announced declarations with their cards, blot once its first card fell, the bazar).
It never reads another player's hidden cards. AI always plays at the highest level (ADR 0021).

## Rule references compared (2026-11-12)

Read in full: [reblot.am](https://reblot.am/rules.php), [terz.am](https://www.terz.am/rules.php),
[blotstar.com](https://blotstar.com/am/bazar-blot-rules). The game keeps its rules (`blot-rules.md`); the AI
plays whatever the engine allows. Where the references differ from the game or each other:

| Topic | reblot.am | terz.am | blotstar | This game |
|---|---|---|---|---|
| Card points, multipliers (×1/2/4, Boy ×2/3/5) | same | contra ×2, recontra ×4 | same | same |
| Trump led: must you play a higher trump? | yes, if you can | yes, if you can | — | no: any trump (owner, 2026-11-14: follow suit with any card of it) |
| Opponent trumped, you have only lower trumps | — | — | — | any card, never forced to undertrump (owner, 2026-11-14) |
| Trumping when the partner wins | not required | not required | — | not required |
| Bidding ceiling | none stated | bids above 25 may include capot | — | 80 (owner) |
| After a kaput bid | only kaput, with a higher number | the other team can't name another trump | — | only another kaput (any suit), pass or Quansh (owner) |
| Kaput value | 25 instead of 16 ("+90") | capot 250 instead of 162 | 90 sai | 25 instead of 16 |
| Declarations count | only the pair with the best one (+ blot always) | same; overlapping ones once | same, trump wins ties | same |
| Four of a kind | J 200, 9 140, A 110, 10/K/Q 100 (Boy: A 190) | eights have "special power" | same as reblot | same as reblot (eights 0) |
| Match | 101/151/201/301 | 301 | 101/151/301+ | 301 |
| "24-point announcement limit" | not mentioned | not mentioned | not mentioned | dropped by the owner (bids go to 80) |

## How it decides

- **CardMemory** — every card played this deal (from the public `history` of tricks), cards others are known to
  hold (shown declarations, the other half of an announced blot), and what plays revealed under the rules of play:
  not following suit → none of it; not trumping when it had to → no trumps; not beating a trump when it had to →
  nothing higher (a cap on its trumps).
- **DealSampler** — deals the unseen cards so every constraint holds (most constrained cards first, each placed only
  where the rest still fit — Hall's condition over the three hidden hands, so a constraint is never dropped), then keeps
  deals that fit the bazar more often (rejection sampling on how likely each bid, pass and Quansh was with that hand).
- **Simulator** — plays a sampled deal to its end with the real engine (declarations, blot, kaput stop, scoring,
  match-ending deals), every seat using the quick card rules (`BlotBot::card`), and scores it for a pair.
- **CardSearch** — every legal card × every sampled deal, played to the end; the card with the best average result
  for the pair (ties: the card that gives away least). This plans the whole deal, not one trick: smearing points on
  a partner's sure trick, keeping a master trump for the last trick, drawing trumps before cashing an ace.
- **A decided deal is still played for points** (owner's report, 2026-11-14): when every card gives the same score
  (e.g. a doubled contract has already failed), the card search and the exact endgame break the tie by the pair's
  card points (`CardSearch::POINT`, 0.0001 sai a point — below any real difference over 40 sampled deals), then by
  the cheapest card. Before, a decided deal fell straight to "cheapest card", which looked like not counting.
- **BidPlanner** — plays each sampled deal out with every trump and Boy, then prices each number and kaput with
  the table's scoring (`BlotEngine::score`) and bids the best one when it beats letting the bazar stand.
- **QuanshAdvisor** — Quansh by the expected gain over a pass and the chance the contract fails (also used before
  the look-ahead engine); **Sur** by the same with the Quansh read as defence in the trump.
- **Thinking time** (`PacesThinking`): a forced card ~0.5–0.9 s, a choice 1–2.5 s, the bazar 1.2–3.8 s, Sur 1.8–3.4 s.

Levels: Hard (what AI plays at) samples the most (bazar 24, Sur 48, cards 40 deals); Medium fewer; Easy is the
former rule-based bot (`ClassicBlotBot`), also the arena baseline.

## Learning from self-play

The look-ahead's chance that a contract is made (share of sampled deals) is biased: its simulated players are
simpler than the real ones. Self-play measures that bias: `blot:arena N --a=raw --b=raw --record=file` notes, for
every deal, the chance the final contract's bidder gave it and whether it was made; `blot:calibrate files…` turns
the notes into ten bands (`Bot/bid-calibration.json`, `BidCalibration`, each band pulled towards its raw chance by
10 deals' worth of trust). `BidPlanner` then prices each number bid with the learned chance in place of the raw one
(kaput keeps its own rule). No table file = raw chances. Kept only if the arena shows the calibrated AI winning.

## Measuring it

`docker compose exec api php artisan blot:arena 300 --seed=1000` plays duplicate deals (each deal twice, the sides
swapped, so the cards' luck cancels) and reports sai per deal, contracts made, kaput, Quansh and Sur for each side.
Results are in `docs/development/IMPLEMENTATION_REPORT.md`.
