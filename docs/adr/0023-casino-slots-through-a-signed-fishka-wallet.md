# ADR 0023 — Casino slots only through a signed Fishka wallet

Status: accepted (2026-11-15) · Context: owner brief 2026-11-15, Phase B; research `docs/research/casino-slots.md`

## Decision
- We don't build slot games or slot maths. Slots come from a provider through an **adapter**
  (`App\Platform\Slots\SlotAdapter`, container tag `slot-adapters`): the provider runs the game and decides results
  on its server.
- Fishkas move only through `SlotWallet`, called by the provider's server at `POST /api/v1/slots/wallet/{provider}`:
  HMAC-SHA256 over `"{timestamp}.{body}"` with the provider's secret (from the environment, never a setting),
  timestamp within 5 minutes. The browser never reports a bet, result or win.
- Every money call carries the provider's transaction id, unique per provider in `slot_transactions`; a repeat
  gets the stored answer and moves nothing (plus the ledger's idempotency key `slot:{provider}:{tx}`). Calls run
  under the player's row lock.
- Bets: open session we issued (one-off token, only its hash stored, 4 h, one per player and game), game and
  provider on, within min/max, covered by the balance. Wins: only for a round with a bet, at most
  `max_multiplier` × the round's bets. Rollback: one unsettled bet, once. Refusals are stored with a reason (admin
  "Slot wallet calls").
- A provider can be switched on only when its adapter exists and its secret is set; games are listed only then.
  **No adapter ships today**: no provider offers both a licence for our use and this wallet for free, so no slot is
  playable until the owner picks one (research options A/B).
- Ledger types `slot_bet`, `slot_win`, `slot_refund`. Fishkas stay play money: no purchase, no cash-out.

## Consequences
- Connecting a provider = its adapter class + secret in `.env` + games in the admin; nothing else changes.
- Top Winners and Big Wins can count slot wins from the ledger once slots run.
