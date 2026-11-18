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

## The games and how each is proven

All twelve APEXSLOTS games run (owner 2026-11-16: open-source slots only, no keno). Each PHP port is checked against
the RTP its source publishes, exactly unless noted (tests in `tests/Feature/Casino/HouseSlotsTest.php`):

| Game | Mechanic | RTP | How |
|---|---|---|---|
| Stellar Sevens, Nitro Hot | 3x3 lines (Nitro: full-screen fruit doubles) | 95.504233 %, 95.658244 % | every stop combination |
| Scorching Hot | 5x3, cherries from 2, star scatter | 95.655629 % | per-line law + scatter convolution |
| Tropic Escape, Pearl Reef, Discovery Voyage | doubling wild, free games (Pearl ×3, Discovery galleon wild) | 95.364527 %, 95.133189 %, 95.022795 % | lines + scatter + free games by Wald's identity |
| Golden Papyrus | book: expanding symbol | 95.111967 % | averaged over the nine symbols |
| House of Ra | best-way free games | 95.182099 % | dynamic programming over the reels |
| Samurai | wild reels in free games | 95.388785 % | per reel-and-row laws |
| The Dynasty | heirs call free games, the other is crowned | 94.138004 % | joint heir law |
| Fairytale | a drawn symbol covers the first reels | 95.566989 % | per pool symbol |
| Bumper Crop | avalanches + barn ladder | 95.548865 % | ladder exactly 50 bets; avalanches by a seeded sample and a 1,000,000-round run (symbols 67.76 ± 0.20 % vs 67.462352 %, ladder 1 in 178.22 vs 178.02) |

Wins are paid in whole Fishkas (rounded down), so small bets on 9/10/20/25-line games return slightly less than
the figure. Three clients keep a display-only helper the board needs to stage a free game (Samurai `wildReelsOf`,
The Dynasty `callerOf`, Fairytale `expansionOf` + the pool for its info screen); they never decide a pay, which the
server sends.

## Consequences

- Real slots with Fishkas, no contract, no outside calls; results can't be changed from the browser.
- The vendored clients are kept in their upstream style (excluded from our lint/format, own `tsconfig.play.json`).
- Pixi.js is a new web dependency (only the slot pages load it).
- Upstream fixes are not pulled automatically; the upstream commit is recorded in `apps/web/play/README.md`.

## Addendum 2026-11-18 — Jungle Slot (CC0 art, our maths)

After Slotopol was removed (owner: "find open-source slots and use them"), GitHub had no other open-source slot with
provider-quality art and a usable licence (see `docs/research/slot-providers-2026-11-18.md`). OpenGameArt's "Jungle
Slot Assets" by danitorres567 is a complete slot design under CC0. It became the 13th house slot, **Jungle Slot**
(`House\Games\JungleSlot`, `play/jungle-slot`): the original 1920×1080 layout rebuilt from the PSD layers, a 5×3
20-line game with a wild chest, maths ours with an exact RTP of 95.61387608 %. A house game can now carry its own
credit line (`CreditedHouseSlot`), shown under it instead of the APEXSLOTS credit.
