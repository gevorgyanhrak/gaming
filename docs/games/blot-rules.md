# Bazar Blot (Armenian) — the rules this platform implements

Sources: [reblot.am rules](https://reblot.am/rules.php?lang=en) (the most complete; used where sources differ),
[belote.club Bazar Blot rules](https://home.belote.club/en/bazar-blot-rules), [terz.am rules](https://www.terz.am/rules.php?lang=en).
Choices where the sources disagree or are silent are marked **(choice)**.

## Terms (as used at the table)
| Term | Meaning |
|---|---|
| Բազար (Bazar) | the bidding |
| Կոզր (Koz) | the trump suit |
| Բոյ (Boy) | a no-trump game |
| Քուանշ (Quansh) | contra — doubles the contract |
| Սուր (Sur) | recontra — the contract pair's answer, quadruples it |
| Կապուտ (Kaput) | taking all eight tricks (also a bid) |
| Թարս կապուտ (Tars kaput) | the bidders take no trick at all |
| Բլոտ / Ռեբլոտ (Blot / Reblot) | said as the holder plays the first / the second of the trump king and queen |
| Չախել (Chakhel) | to trump a trick of another suit (having none of it) |
| Ձեռք / Վերջին ձեռք | a trick / the last trick (+10) |
| Տերց, Պոլտիննիկ, Սոտնի (Terz, Poltinnik, Sotni) | 3, 4, 5+ cards in sequence: 20, 50, 100 |
| Քառյակ (Kvaryak) | four of a kind |

## Table and deal
- 4 players, two partnerships sitting opposite (seats 0+2, 1+3). 32 cards (7–A), 8 each. Dealer rotates each deal;
  the player left of the dealer bids first and leads the first trick.
- The deal is shown before the bazar: the server keeps the table in a `dealing` phase for `deal_ms` (3.4 s; nobody
  acts, no turn clock) while every client animates the deal — 3, 2, 3 cards to each player from the dealer's left.

## Bazar (bidding)
- A bid is a number from 8 to 80 (owner, 2026-11-12; × 10 the points the pair promises) with a trump suit or **no-trump**. Each bid is at least 1
  higher than the last; suits don't rank. Partners may raise each other. A player who passed may bid again later.
- **Kaput** (25): the pair will take all 8 tricks. A bid of its own, not a number: it outbids any number, and after a
  kaput only another kaput (any player, any suit or Boy), a pass or Quansh (owner, 2026-11-12; terz.am). It still scores 25.
- **Contra (Քուանշ)** — an opponent of the pair holding the contract; it ends the bidding. Then **either player of the
  contract pair** may say **recontra (Սուր)**, in turn: the first of them after the Quansh, and if they pass, their
  partner ("ответить реконтрой может один из игроков пары" — playelephant/gambler rules). Sur ×4 (Boy ×5).
- The bidding ends after three passes following the last bid (or after contra/recontra is answered). Four passes and
  no bid: redeal, the deal moves on.

## Cards
| | J | 9 | A | 10 | K | Q | 8 | 7 |
|---|---|---|---|---|---|---|---|---|
| Trump | 20 | 14 | 11 | 10 | 4 | 3 | 0 | 0 |
| Plain (trump game) | 2 | 0 | 11 | 10 | 4 | 3 | 0 | 0 |
| No-trump | 2 | 0 | 19 | 10 | 4 | 3 | 0 | 0 |
Order: trump J 9 A 10 K Q 8 7; others A 10 K Q J 9 8 7. 152 card points + 10 for the last trick = 162.

## Play
- Follow suit if you can (any card of it). Without the led suit you must trump, beating the highest trump on the table if
  you can (otherwise any trump) — unless your partner is winning the trick, then any card. No trump in hand: any card.
  No-trump games: just follow suit.
- The highest trump wins the trick, otherwise the highest card of the led suit; the winner leads.

## Declarations
- Announced by the player before playing their first card (the server lists only combinations the player holds).
  **Owner's rule:** the cards forming it are shown to all four players the moment it is announced (reblot.am shows them
  before the second trick) — only those cards. Undeclared combinations don't count.
- Sequences in one suit (order 7 8 9 10 J Q K A): 3 = **terz** 20, 4 = **50**, 5 or more = **100** (one per run).
- Four of a kind — trump game: J 200, 9 140, A 110, 10/K/Q 100; no-trump: A 190, J/10/K/Q 100; 8s, 7s (and 9s in
  no-trump) nothing.
- Only the pair with the best single declaration scores, and then all of its declarations. Best = more points; four of
  a kind beats a sequence of equal points; then the higher top card; then a sequence in trumps; then the first holder
  counting from the dealer's left.
- **Blot** (K + Q of trumps in one hand, trump games): 20 for the holder's pair, always. "Blot" is announced as the
  holder plays the first of the two, "Reblot" with the second; from the first, everyone knows who holds it.
- Every call, the contract, declarations (with their cards), Blot/Reblot, chakhel and each trick go into the deal's
  public log, the same for all players and after a reconnect.

## Scoring a deal (in "sai", points ÷ 10)
- Card sai = card points (with the last trick) ÷ 10, a fraction of more than one half rounding up (15 → 1, 16 → 2).
  Declarations count their value ÷ 10 (terz 2, 50 → 5, …). The whole deal is worth **16** (25 when one pair takes all tricks).
- The contract is made when the bidders' card points + their counted declarations + blot ≥ bid × 10 (kaput: all 8 tricks).
- **A kaput contract stops at the first trick the defenders take** ("Կապուտը չեղավ"): the result can no longer change,
  so the deal is scored there as a failed kaput and the next deal starts. This is not Tars kaput (that needs a full deal
  in which the bidders take nothing).
- Multiplier m: trump game 1 (contra 2, recontra 4); no-trump 2 (contra 3, recontra 5).
- **Made, no contra:** bidders = bid × m + (16 − defenders' card sai) + their declarations; defenders = their card sai +
  their declarations. Taking every trick: base 25 and the defenders get no card sai.
- **Made with contra/recontra:** bidders = bid × m + 16 (25 for all tricks) + both pairs' declarations; defenders 0.
- **Failed:** bidders 0; defenders = bid × m + 16 + both pairs' declarations (25 when the bidders took no trick).
- Blot's 2 sai always go to its holder's pair. A kaput contract counts its bid as 25.

## Match
- Cumulative sai to the **target** (301; 101/151/201 as table options). After a deal, if a pair has reached the target
  and has more than the other, it wins; equal scores play on.
- The bidders taking no trick at all lose the match at once; a contra'd kaput that is made wins it at once (reblot.am).
