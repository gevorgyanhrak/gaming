# ADR 0024 — House slots from open-source games, dealt by our server

- Status: accepted (2026-11-16)
- Builds on: ADR 0023 (slot wallet), ADR 0015 (Fishkas are play money)

## Context

The owner wants playable slots with Fishkas from a free, ready-made source ("I don't need to write a slot, I want
to use any service with slots"; "find open-source casino code and copy the slot into my code"). The research in
`docs/research/casino-slots.md` found no free provider that both allows our use and takes a Fishka wallet. The
owner then pointed at open-source projects:

| Project | Licence | Verdict |
|---|---|---|
| [Z1000k/open-source-slots](https://github.com/Z1000k/open-source-slots) (APEXSLOTS) | code and sound MIT, art CC BY 4.0 | **used**: 12 finished games, own art, published RTP, engine tests, and a client that already has a "server deals" mode |
| [slotopol/server](https://github.com/slotopol/server) | MIT | the maths behind the above (reel strips, paytables); its own games carry commercial titles and brands, not copied |
| [johakr/html5-slot-machine](https://github.com/johakr/html5-slot-machine) | MIT, Star Wars icons | not needed (APEXSLOTS clients are richer); the icons are a trademark risk |
| [jcapshaw/slots](https://github.com/jcapshaw/slots) | AGPL-3.0 | **not usable**: it would oblige us to publish the whole platform's source |
| Stake Engine math-sdk sample games | MIT | tried first; its sample game reaches its RTP only through its optimiser, so it was dropped for APEXSLOTS |

## Decision

- A **house provider** `apexslots` (`App\Platform\Slots\House`), an ordinary `slot_providers` row with an adapter
  (`HouseSlotAdapter`), so the admin screens, `SlotWallet` and the ledger work as for any provider.
- Each game's **maths is ported to PHP** (`House\Games\<Game>`, `HouseSlot` contract: `play($random)` returns the
  round in the shape the game's client reads). The server deals with `random_int`; the browser never decides.
  Each port has a test that reproduces the published RTP (exact enumeration where the game allows it).
- **Spins** (`POST /api/v1/slots/{slug}/spin {bet, key}`, `HouseSpins`): bet within the server's ladder
  (`HouseSpins::BETS` ∩ the game's min/max), dealt under the player's row lock, bet and win moved through
  `SlotWallet::handleSession` (tx ids `u{user}:{key}:bet|win`, so `slot_transactions` stays unique), the round
  stored in `slot_spins` (unique user + key). A repeated key returns the stored round and charges nothing. A win
  above `max_multiplier` × bet is refused and the whole spin rolls back.
- **Clients**: each game's client is copied into `apps/web/play/<id>/` (built by our Vite as an extra page,
  served at `/play/<id>/`), its art into `apps/web/public/play/<id>/`. Changes to the upstream client are kept
  small and marked: no in-browser dealing (the engine copy keeps only types, lines and paytable for the info
  screen), whole Fishkas instead of money, the bets the server offers, and `play/shared/house.ts` as the bridge
  to our API. The SPA shows a game in a same-origin iframe (`/casino/slots/:slug`, `SlotPage`), which hears
  `slot:spun` (refetch the wallet) and `slot:low-balance` (the "out of Fishkas" popup).
- **Licences**: `apps/web/play/LICENSE-APEXSLOTS` (MIT), `LICENSE-ART-APEXSLOTS.md` (CC BY 4.0 — credited on
  the slot page), `LICENSE-SLOTOPOL` (MIT). Fishkas stay play money: no purchase, no cash-out.
- Games are added one at a time, each with its port, RTP test and client. The seeder (`HouseSlotSeeder`) creates
  missing games switched on and never overrides an admin's changes.

## Consequences

- Real slots with Fishkas, no contract, no outside calls; results can't be changed from the browser.
- The vendored clients are kept in their upstream style (excluded from our lint/format, own `tsconfig.play.json`).
- Pixi.js is a new web dependency (only the slot pages load it).
- Upstream fixes are not pulled automatically; the upstream commit is recorded in `apps/web/play/README.md`.
