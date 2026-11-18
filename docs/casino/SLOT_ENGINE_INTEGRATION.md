# Slot engine — integration

How the slotopol games run on our platform (decision: ADR 0026; source and changes: `services/slot-engine/UPSTREAM.md`;
audit: `SLOT_ENGINE_AUDIT.md`; per-game list: `GAME_FEATURES_AUDIT.md`).

```
Browser ──HTTPS──▶ Laravel API (/api/v1/slots/{slug}/engine, …/engine/spin)
                     │  users, sessions, Fishka wallet, ledger, spins, admin
                     └──internal network, X-Engine-Key──▶ slot-engine (Go) : games only
```

## Running it

- Local: `docker compose up -d slot-engine` (built from `services/slot-engine`), then
  `docker compose exec api php artisan slots:engine-sync` to add the games (repeat after an engine update).
- Config: `SLOT_ENGINE_KEY` (32+ characters, the same value for `slot-engine` and the API containers; compose sets a
  local default), `SLOT_ENGINE_URL` (default `http://slot-engine:8090`). Secrets, never admin settings.
- Production: the same image on the private network, no published port, a long random key; the API's containers get
  `SLOT_ENGINE_URL` and `SLOT_ENGINE_KEY`. Admin: Settings → Engine slots (RTP); Casino → Slot games (on/off, bets, order).

## API (ours, signed-in players)

- `GET /api/v1/slots` — every offered slot with `studio` (maker) and `kind` (`engine`).
- `GET /api/v1/slots/{slug}/engine` — reels, rows, symbols, features, `bets`, `lines` {min, max, fixed, default},
  `unit` (price at bet 1 for fixed-lines games), `state` (the screen where the player left it), `free`, `balance`.
- `POST /api/v1/slots/{slug}/engine/spin {bet, lines?, key}` — `bet` (charged), `win`, `balance`, `screen`, `wins`
  (engine win items: pay, mp, sym, num, li, xy, fs…), `free`, `next`, `free_left`, `series_win`, `round`, `replay`.
  Refusals are 422 with `insufficient_fishkas`, `slot_bet_limits`, `slot_game_off`, `slot_key_conflict`.

## Money

A paid spin is a round: `SlotWallet` bet (bet × lines, or the game's price × bet) then win; the free games and
cascade falls it brings cost nothing and pay into the same round (capped at `max_multiplier` × the round's bets).
All under the player's row lock in one transaction; an engine error or refusal rolls back (nothing charged).
Transaction ids `u{user}:{key}:bet|win` keep the ledger idempotent.

## Tests

- Engine (Go, `go test ./engine`): every game spins 60 times through saved states; free games are free and keep
  their bet; RTP of the spin path for sample games; the HTTP API needs the key and rejects unknown fields; no
  package outside `engine/server.go` imports networking, exec or SQL.
- Platform (Pest, `tests/Feature/Casino/EngineSlotsTest.php`): sync (maker, verified only, admin changes kept),
  open, charge and pay, free games into the round and across a reload, replay, engine failure, insufficient
  Fishkas, bet/lines rules, ways pricing, fixed lines, sign-in.
- Live (2026-11-17): all 322 enabled games spun once through the API with player1 — every spin answered, balance =
  previous − bet + win every time.
