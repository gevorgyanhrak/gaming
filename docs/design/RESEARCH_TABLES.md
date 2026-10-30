# Card table research — Durak table and Poker hand result (2026-10-30)

Written by the UI/UX agent for TASK-035 (Durak table redesign) and TASK-036 (Poker hand result).
Labels: **Observed** = seen in a source (link and access date given); **Inferred** = concluded from
what was observed; **Recommendation** = our design choice. We take patterns, never assets: no card
faces, art, stickers or branding are copied.

Sources were accessed on 2026-10-30. Store pages and blogs change; the quotes below are what they said
that day.

## 1. What we have today (measured 2026-10-30, local stack, headless Chrome)

| Fact | Evidence |
|---|---|
| Durak 2 players at 390×844: page 1401 px tall; my hand starts at y = 916, under the 786 px phone tab bar; action button at 1060–1108 | `review/b3-durak2-390-full.jpg`, DOM rects |
| Durak 2 players at 1440×900: page 1207 px; hand at 782–926, cut by the 900 px fold; action button at 942–990 | `review/b3-durak2-1440-full.jpg` |
| Space for a table: 390×844 → from the room header (bottom 158 + 24 gap) to the tab bar (786) ≈ **600 px**; 1440×900 → from 222 to ≈ 867 (frame bottom) ≈ **645 px**; 1280×720 ≈ **465 px** | DOM rects |
| The Durak felt is an oval behind a 3-column grid. On desktop its corners (≈ 21 % of the box, 1 − π/4) are empty. The opponent's panel sits above the rim, not on the felt. "The table is empty." is centred on the felt | owner screenshot `refs/durak-current.png`, `DurakBoard.tsx` |
| Poker: the hand result exists only in the "Last hand" panel below the table and action bar. The owner did not notice a win | owner screenshot `refs/poker-current.png`, `PokerBoard.tsx` |
| **The next Poker hand is dealt in the same transition that ends the last one.** `finishHand()` calls `startHand()`, so no client ever sees a "hand over" state: the next snapshot already has a new hand, new hole cards and posted blinds. Only `view.last_hand` (number, board, shown, pots) carries the result | `apps/api/app/Games/Poker/Engine/PokerEngine.php` `finishHand` / `startHand` |
| After any change, bots act 0.7–1.8 s later (`gameplay.bot_delay_ms`). A client-side result moment of a few seconds will overlap the first pre-flop actions of the next hand | `config/gameplay.php`, `GameScheduler::afterChange` |
| Engine events (`hand_over`) are broadcast with the state but the client ignores them; a refetch loses them. `last_hand.number` changing between snapshots is the reliable trigger | `RoomEvents::changed`, `features/play/useRoom.ts` |
| Split pots: the engine pays odd chips to the winners closest left of the button. The view has pot amounts and winners but not each winner's exact share | `PokerEngine::pay`, `last_hand.pots` |
| Durak is strictly turn by turn; the defender beats only the latest attack, so a card never needs a target. Turn time 45 s, Poker 30 s | `DurakEngine` doc comment, `GameModeSeeder` |

## 2. Durak

### Observed

| Source | What it shows |
|---|---|
| Owner reference 1 (`refs/durak-ref-green.png`, supplied 2026-10-30) | Full-bleed green felt and no table rim. Opponents sit in translucent panels: top centre and the left and right edges. Each panel has an avatar, a name and a row of card backs. The deck lies under the trump card, which is turned sideways, left of the bout. Attack/defence pairs are overlapping cards in the centre. Each player has a speech-bubble status ("Beaten", "I'm the defender"). Your hand is a straight overlapping row at the bottom. Above it is a hint bubble ("You may play a card with the same rank as the cards on the table"); under it is one wide yellow action button ("Beaten"). A sticker panel sits bottom left (out of scope) |
| Owner reference 2 (`refs/durak-ref-blue.png`) | Full-bleed blue felt. A compact top bar holds the round counter, a deck counter ("36"), the trump suit (♣) and a status in words ("Я отбиваюсь" / "I'm defending"). Opponents are small round avatars, each with a mini row of tiny card backs showing hand size. The deck has the count on its back, with the trump card under it, rotated. The middle is mostly empty, and your hand is large cards in a straight row at the bottom |
| Durak Online 3D, App Store listing — https://apps.apple.com/us/app/-/id1160728947 | Actions are named buttons: players "press "Pass"" when done and "click "Take"" to pick up. The listing also describes a "Unique 3D game table" and stickers/emotes at the table |
| Durak online offline, listing — https://mwm.ai/apps/durak-online-offline/1293634236 | "One touch play", "Easiest card coverage", "streamlined action buttons at the bottom", "clearly displays opponent hand indicators", a "trump card indicator … near the draw pile" |
| Durak online, App Store listing — https://apps.apple.com/us/app/id626156555 | Matches for 2–4 players. The layout is not described |
| itch.io player report on a mobile card game — https://itch.io/post/15652143 | "with the cards partially overlapping, if I touch in just the right spot … it's possible to play two cards at once" |
| TUM poster, multi-device card game — https://campar.cs.tum.edu/pub/dippon2013its/dippon2013its.poster.pdf | Touch-through (a touch reaching a card underneath) caused problems and was removed after testing (per search summary; PDF not re-read) |
| cardsJS hand demo — https://cdn.jsdelivr.net/npm/cardsJS@1.1.1/fiddle.html | Hand spacing is set as the share of each card left visible, and the hovered card lifts |
| WCAG 2.2 SC 2.5.8 Target Size (Minimum) — https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html | AA minimum is 24 × 24 CSS px. Our own rule (agent definition) is 44 px on phones |

### Inferred

- Both references give the play area the whole screen. Neither shows an empty decorative frame. The
  common anatomy is: opponents along the top (sides when there's room), deck + trump at one edge, pairs
  in the middle, your hand as a straight row at the bottom with one button under it.
- Status lives with each player (a bubble or a word next to the avatar), not in one global line.
  Reference 2 also repeats your own status in words in the top bar.
- Nobody fans the hand in an arc. Straight overlapping rows read faster and keep tap strips
  predictable.
- Card counts are shown by small backs plus a number. Large face-down fans (as we have now) cost
  height and add nothing on a phone.
- The listings stress one touch play and few, large action buttons. Accidental double plays come from
  overlapping hit areas, so each visible strip must be exactly one card's hit area.

### Recommendation (used in TASK-035)

1. **Keep the club room; drop the oval for Durak.** The table becomes a rounded-rectangle felt with a
   thin wood rail. It fills the available width and the viewport height between the room header and
   the tab bar. Reasons:
   - the references' full-bleed felt is the whole app there, while our felt sits inside the club frame
     with a header and tab bar we keep;
   - a thin rail keeps the brand (`.table-rim`, `.table-felt`) and frames the play area;
   - the rectangle returns the oval's empty corners (≈ 21 %), so opponents sit *on* the felt like in
     both references;
   - Poker keeps its oval: seats round an oval is the poker convention and the Poker table already
     fits.
2. Opponents: compact seats on phones (avatar, name, mini backs and count, status bubble); panels on
   desktop. Status as a speech bubble per seat, as in reference 1.
3. Deck with the sideways trump at the left edge of the felt and the discard pile at the right, each
   with a count.
4. Your hand as a straight row (no arc). The overlap is limited so every visible strip is ≥ 40 px
   wide; extra rows when the hand is long. One wide gold action button under the hand on phones,
   beside it on desktop, never overlapping card hit areas.
5. The whole table, hand and button fit the viewport at 390×844, 820×1180, 1280×720 and 1440×900
   (measured fit, not a fixed height).

## 3. Poker hand end

### Observed

| Source | What it shows |
|---|---|
| Poker Club dev diary "Improving the Showdown" — https://www.pokerclubgame.com/?p=1474 | Showdown in two stages: the reveal, then the winning hand. Single winner: the winner's hole cards are shown with the community cards and the UI "call[s] out the winning hand" so it is "crystal clear". Split/side pots: all winning hands on one screen, each showing "how many chips that player wins from the pot". Aim: balance tension with "keeping pace", with "fewer pauses" |
| Poker Power, "Show cards" feature — https://www.pokerpower.com/blog/introducing-show-cards-feature | "the winner's name, their winning hand, and chip amount will appear directly in the center of the table"; "After 12 seconds, the table will reset for the next hand" |
| TheLastDance/Poker (open source) — https://github.com/TheLastDance/Poker | Showdown wait of "10 second[s]" with two players and +2 s per extra player, "Maximum could be 20 sec". "after showdown you will see animation of winning amount of winners", which also works for split pots and several winners |
| PokerKit simulation docs — https://pokerkit.readthedocs.io/en/0.0/_sources/simulation.rst.txt | The end of a hand is modelled as separate steps: "Showdown", "Chips pushing (move chips from center to the winner(s))", "Chips pulling (winner(s) pull the chips they won into the pot)" |

### Inferred

- The standard sequence is: reveal → name the winning hand → move the pot to the winner → show the
  amount at the winner → short pause → next deal.
- The result is stated **in the centre of the table**, where the eyes already are, not in a panel
  below.
- Pauses in real-money apps (10–20 s) are long because money is at stake. For a casual bot table
  with 30 s turns, 3–5 s is enough to feel it without slowing play (Recommendation, to be confirmed
  by the owner).
- Split and side pots name every winner and each share.

### Recommendation (used in TASK-036)

1. A **result moment** on the table, triggered by `last_hand.number` changing:
   - final board in the centre, with shown hands face-up at their seats;
   - the pot chips slide to the winner(s);
   - a gold glow on the winning seat, with "+N" over its stack;
   - a centre plate: "You win 150 chips · Flush" / "Anna won 150 chips · Two pair" / "Everyone else
     folded";
   - one calm win sound for my own win.

   No confetti, coin showers or "big win" wording.
2. **Backend change (flagged):** a short server pause between hands, so the result is seen by
   everyone in sync and nobody's turn clock runs during it. Without it the moment still works, but it
   plays over the first seconds of the next hand.
3. **Backend change (small, flagged):** `last_hand.payouts` (chips each seat received), so split pots
   show exact shares.
