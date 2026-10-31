# Owner brief — TASK-040 · Poker bots, real balance in Poker, game groups (2026-10-31)

Pasted by the owner on 2026-10-31 ("after work start it"). Kept verbatim so any session can resume.

---

You need to review, fix, implement, and thoroughly test the following features across our gaming platform.

IMPORTANT: Do not just inspect the code and provide recommendations. You must implement all required changes, verify the complete user flows, and fix any issues you discover.

1. Fix Poker Gameplay with Bots

Currently, when a user plays Poker against bots:

* The game timer works correctly.
* However, bots are not actually playing or making their moves.

Fix the bot gameplay logic.

Requirements:

* Bots must make realistic moves automatically.
* Bots must follow the game rules.
* Bots must make decisions within a reasonable time.
* The game must continue correctly until the round ends.
* Handle all possible situations: betting, checking, folding, calling, raising, winning, and losing.

Winner Animation and Fishka Balance

When a user wins:

* Display a winning animation.
* Animate the won fishkas moving toward the user’s balance.
* Update the balance with the correct winning amount.
* Make sure the animation reflects the actual transaction.
* Do not update the balance multiple times due to repeated events or animations.

The same logic must work correctly when the user loses.

2. Fix Incorrect Fishka Balance in Poker

There is currently a balance synchronization issue.

Example:

* My actual balance is 5,000 fishkas.
* When I enter Poker, the game displays only 1,000 fishkas.

This is incorrect.

Requirements:

* Always retrieve the user’s actual available fishka balance from the backend.
* Display the correct balance everywhere.
* The player must be able to play using all available fishkas.
* Never use hardcoded balances or outdated cached values.
* Synchronize the balance between the main wallet, Poker, and all other games.
* Update the balance immediately after every transaction.

The backend must be the single source of truth for fishka balances.

3. Introduce Different Game Groups / Levels

During Poker and all other games, introduce different groups based on players’ fishka balances and betting limits.

We need five groups:

1. Beginner
2. Intermediate
3. Experienced
4. Professional
5. Master

Each group must have:

* Its own entry requirements.
* Minimum player balance.
* Minimum bet.
* Appropriate matchmaking.
* Separate game tables/rooms.
* A clear visual identity.

4. Poker Groups – Exact Requirements

| Group | Minimum Balance | Minimum Bet |
|---|---|---|
| Beginner | 100 fishkas | 1 fishka |
| Intermediate | 1,000 fishkas | 5 fishkas |
| Experienced | 10,000 fishkas | 10 fishkas |
| Professional | 50,000 fishkas | 20 fishkas |
| Master | 500,000 fishkas | 100 fishkas |

Group Logic

* Players can enter a group only if they meet its minimum balance requirement.
* Players cannot place bets below the group’s minimum bet.
* Prevent users from entering tables they cannot afford.
* Display all groups with their minimum balance and minimum bet.
* Show locked groups clearly if the user doesn’t have enough fishkas.
* Allow users to enter any group for which they meet the requirements.
* Make sure all values are validated on the backend, not just the frontend.

IMPORTANT: The minimum balance is an entry requirement, not an amount that gets deducted when entering a game.

5. Thoroughly Test Poker

Balance: users with 100, 1,000, 5,000, 50,000 and 500,000+ fishkas; balance synchronization between
wallet and Poker; balance updates after winning and losing.

Gameplay: bot moves and decisions; multiple betting rounds; check, call, raise, and fold; all players
folding; user winning; user losing; different winning combinations; multiple players with different
balances; game completion and restart; disconnections and reconnections.

Security and transactions: prevent negative balances; prevent duplicate transactions; prevent double
payouts; validate bets on the backend; handle simultaneous balance updates safely.

Do not consider Poker completed until these scenarios have been tested successfully.

6. Implement Groups for All Remaining Games

After Poker is fully fixed and tested, move to the remaining games. Apply the same group system to every
other game on the platform. Five groups: Beginner, Casual, Experienced, Professional, Master.

For these games: minimum allowed bet 500 fishkas; maximum allowed bet 500,000 fishkas.

| Group | Minimum Balance | Minimum Bet | Maximum Bet |
|---|---|---|---|
| Beginner | 500 | 500 | 2,000 |
| Casual | 5,000 | 2,000 | 10,000 |
| Experienced | 25,000 | 10,000 | 50,000 |
| Professional | 100,000 | 50,000 | 200,000 |
| Master | 500,000 | 100,000 | 500,000 |

These values should be configurable from a centralized game configuration rather than hardcoded
separately in each game. Apply to Nardi, Shashki, Durak and all other existing games. Each game has its
own rooms, matchmaking, betting validation, and balance synchronization.

7. Architecture and Code Quality

Inspect the architecture, how balances are managed, the Poker logic and bots, why the balance displays
incorrectly, and whether games share the same wallet service. Avoid duplicated business logic; use
centralized configuration for groups; keep wallet transactions consistent; validate all financial
operations on the backend; preserve existing functionality; don't break registration, login, rewards or
other games; keep the design system.

8. Final Verification and Report

Test Poker completely, the fishka balance system, all five Poker groups, all remaining games, group entry
restrictions, minimum and maximum bets, winning and losing animations, balance synchronization across all
games; fix every issue found. Do not stop after Poker.

Report: what was wrong, what was fixed, new functionality, files/components modified, tests executed,
passed/failed, remaining issues.

MOST IMPORTANT: Work autonomously. Inspect, implement, test, debug, and verify.
