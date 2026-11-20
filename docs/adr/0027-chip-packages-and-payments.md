# ADR 0027 — Chip packages and payment orders (no provider connected yet)

- Status: accepted (2026-11-20)
- Context: HayBet brief item 6 asks for chip packages ($2–$15) and a complete payment architecture, ready for banks
  the owner will contract later. Until now Fishkas were never bought (house rule since ADR 0015). Asked before work
  started, the owner chose "build the architecture": packages, orders with statuses, a provider adapter and the
  purchase popup, with no provider connected and no fake successful payments. The owner checks the legal side
  (selling credits that are then played on slots and poker can be regulated as gambling, e.g. in Armenia) before
  switching purchases on.

## Decision

1. **Packages** (`chip_packages`, admin: Payments → Chip packages, audited): $2 10,000 · $5 25,000 + 2,500 ·
   $8 40,000 + 6,000 · $10 50,000 + 10,000 (recommended) · $15 75,000 + 22,500 — about 5,000 Fishkas a dollar, more
   for larger packages. Packages are never deleted (orders point at them); they are taken off sale.
2. **Orders** (`payment_orders`): ULID, the package snapshot (price, currency, Fishkas), provider and its reference,
   status `pending → processing → paid | failed | cancelled | expired | refunded` (DB CHECK; final states stay final,
   except paid → refunded), one per user and client key (a double tap or a retry returns the same order).
3. **Providers**: `Platform\Payments\PaymentProvider` (`checkout()` → redirect URL + reference;
   `parseWebhook()` verifies the provider's signature). Registered under the `payment-providers` tag; selected by the
   settings `payments.enabled` + `payments.provider`. Keys live in the environment, never in settings. **None ships.**
4. **Money in**: only `POST /payments/webhook/{provider}` with a verified event can mark an order paid. The order row
   is locked; Fishkas are credited once through `FishkaWallets::credit` (ledger type `purchase`, key
   `payment:{order}`). No endpoint, admin screen or client code can mark an order paid. A refund is recorded for the
   admin; Fishkas aren't taken back automatically.
5. **Players**: `GET /chip-packages` (`purchasable` says whether buying works), `POST /me/payments/orders`
   (422 `payments_unavailable` while off), `GET /me/payments/orders/{id}`. The wallet page and the "out of Fishkas"
   popup show the packages; while buying is off the buttons are disabled with a plain note and the free ways to get
   Fishkas. The popup also opens when the whole balance (wallet + at a table) reaches 0 — once a session, never over a
   game.
6. Fishkas are still never cashed out or sent between users; the free daily bonus, gifts and top-up stay.

## Consequences

- To sell, the owner signs with a provider; a developer adds its `PaymentProvider` (checkout + signed webhook) and
  the admin switches it on. Nothing else changes.
- Until then no money moves and every screen says purchases aren't available.
