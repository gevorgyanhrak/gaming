# Game moments: what the brief asked, what shipped (2026-11-17)

Owner brief: research the best gaming platforms (PokerStars, GGPoker, Zynga, WSOP, 888, Chess.com, Lichess,
Backgammon Galaxy …), redesign the identity and lobby, and give every game its own environment, poker first.

## Decision: keep the identity, deepen the moments

The platform already has its identity (TASK-048 "Lamplit Room", the club look modelled on bazarblot.am, approved by
the owner) and every game already has its own environment: the poker oval with a dealer, rail and felt; the classic
beige/brown chess and checkers board in a wood frame; the carved walnut Nardi case; Blot's Armenian table with the
deal and announcements; Durak's table with bout motion. A third full re-skin in a month would churn every screen and
the owner's earlier decisions. What the platform lacked were the **moments** that the best poker clients make feel big
(all-in, the pot going to the winner) and piece motion in the board games — so the work went there.

Public sources say little about how PokerStars/GGPoker time these animations (a search found only feature notes such as
all-in equity overlays at Phenom Poker); the rules used here come from the brief and the existing design system:
big moments under ~2 s, nothing ever blocks or delays a move, one message at a time, reduced motion respected.

## What shipped

- Table banners (`features/play/banners`): ALL-IN (red plate, light sweep, chips sound), WINNER / YOU WIN (gold plate,
  burst), a quiet "Hand lost −N" only after a showdown you played (never after your own fold), "X joined / X left"
  at every game's table. One at a time; big moments go first; small ones are dropped when two wait; over the table bar
  so no seat or card is covered; announced to screen readers.
- Poker: the all-in seat's chair pulses red until the hand is settled; each betting round's bets slide into the pot;
  a newly seated player's avatar comes in from the rail.
- Chess, checkers, Nardi: the moved piece slides from its square (opponent and AI moves too), from the server's last
  move (Nardi: from the difference between two positions) — no rules on the client.

## Not done (needs the owner or a later task)

- Tournaments and "tournament starting" notices — there are no tournaments.
- Daily challenges, friends and invitations beyond invite codes, hand history — new product features.
- "Jackpot" — no jackpot exists; never shown without a real one.
- Level-up, achievements and big wins keep their own popups/feeds (LevelUpDialog, achievements, Big Wins).
