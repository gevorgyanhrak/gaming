# 0015. Fishka wallets with a locked balance row and an append-only ledger

**Status:** Accepted (2026-10-30)

## Context
The owner introduced Fishkas, a free platform currency: welcome, daily and "out of Fishkas" bonuses,
a 500 entry fee per game, poker buy-ins with the players' own Fishkas, and admin adjustments. Balances
must never go negative, never be charged twice by repeated clicks or racing requests, and every change
must be auditable. Fishkas are never bought, sold, cashed out or sent between users.

## Decision
- One `fishka_wallets` row per user (`balance`, totals, the last daily-bonus day, the last refill) and
  an append-only `fishka_transactions` ledger (type, signed amount, balances before/after, reference,
  admin and reason, optional unique `idempotency_key`). CHECK constraints keep types valid and every
  entry consistent (`balance_after = balance_before + amount`, amount ≠ 0); balances are unsigned.
- `App\Platform\Fishka\FishkaWallets` is the only writer: each change runs in a DB transaction that
  `SELECT … FOR UPDATE`s the wallet row, re-checks the idempotency key under the lock, refuses to go
  below zero (`InsufficientFishkas`, rendered as 422 `insufficient_fishkas`), writes one ledger entry and
  broadcasts `wallet.updated` after commit. Wallets open lazily with the welcome bonus (older accounts
  get it on their next visit).
- Games pay through `App\Gameplay\Rooms\RoomStakes`, called where a person takes a seat
  (`RoomService::seat`, inside the seat's transaction): the entry fee, or for engines implementing
  `PlaysForChips` (poker) a buy-in that becomes their starting stack (`options.stacks`, so replays match)
  and is paid back via `chipsOf()` when they leave or the game ends. Keys per seat (`stake:`, `refund:`,
  `cashout:`) make every charge and payout happen once. Bots sit with house chips.
- Amounts and the day's time zone are admin settings (`fishka.*`, ADR 0013).

## Consequences
- The wallet row serialises concurrent changes for one user; different users never contend.
- Winning chips from bots creates Fishkas and losing to bots removes them (owner's choice).
- Shared gameplay code stays game-agnostic: only the `PlaysForChips` contract marks chip games.
- The rule "no entry fees" (2026-10-28) is superseded for entry fees; nothing can be bought.
