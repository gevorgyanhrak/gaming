# Owner brief — TASK-038 · Fishka currency (2026-10-30)

Summary of the owner's pasted spec (all points; wording condensed). Implemented in 53018b3; QA report
`docs/qa/QA_REPORT_FISHKA.md`; ADR 0015.

1. **Wallet** per registered user: balance, transaction history, total earned, total spent; backend is
   the source of truth.
2. **Registration bonus** 5,000 once (`registration_bonus`).
3. **Daily login bonus** 1,000 on the first login of each calendar day (`daily_login_bonus`), once per day,
   time zone handled on the backend.
4. **Game entry cost** 500 per game session: check balance first, deduct once, refuse if insufficient, no
   duplicate deductions, atomic operations.
5. **Poker**: play with own Fishkas, minimum bet 1, bets deducted and winnings credited, validated on the
   backend, can't bet more than owned, safe under simultaneous actions; no 500 entry fee unless poker
   logic requires it. (Owner chose buy-in/cash-out and bots with house chips.)
6. **Admin**: block/unblock (status Active/Blocked, blocked users can't log in or play, clear message);
   view balance, add, deduct, set exact balance, view history; each change records admin, user, before,
   after, amount, reason, time; validated on the backend.
7. **Popup** when a game can't be entered: title "Your Fishkas Have Run Out!", description "You don’t have
   enough Fishkas to play this game. Your balance has reached zero. Get a new bonus and continue
   playing!", primary "Get 5,000 Free Fishkas", secondary "Cancel"; the bonus credits exactly 5,000, is
   recorded, updates the balance immediately, allows retrying; protected against abuse (cooldown /
   eligibility), configurable, loading state, no duplicates.
8. **Global balance UI** in the header with an icon, synced after every change, animated on change,
   responsive.
9. **Database**: wallets/transactions with types registration_bonus, daily_login_bonus, game_entry_fee,
   poker_bet, poker_winning, admin_credit, admin_debit, insufficient_balance_bonus; DB transactions,
   indexes, constraints, no negative balances.
10. **Security**: backend calculations only, never trust client amounts, no duplicate transactions,
    protected admin endpoints, game-session ownership, no race conditions, auditable history.
11–12. Workflow and the 16 test scenarios (registration bonus; same-day login; next-day login; 500 can
    play; 499 can't; 0 sees popup; bonus button; repeated clicks; poker min bet 1; can't bet over balance;
    winnings credited; admin block/unblock; blocked can't play; admin add/deduct/set; history correct;
    concurrent requests safe).
