# Casino slots — provider research (B0, 2026-11-15)

The owner's rules for slots (brief 2026-11-15, Phase B):
- No own slot engine ("I DO NOT want you to develop slot machine games from scratch"); use ready-made free /
  open-source / HTML5 games or providers.
- "Do not assume that a free demo means free commercial usage" — only an explicit licence counts.
- Bets in Fishkas, debited and credited by our server; results never decided or reported by the browser;
  duplicate and replayed requests refused.
- "If the provider cannot support a secure custom Fishka wallet integration, do not integrate it as a fully
  functional game... clearly document the limitation."
- No real money anywhere: no payments, no cash-out (house rule, ADR 0015).

## What we need from a provider

| Need | Why |
|---|---|
| Licence that allows commercial use on our site | owner rule |
| A **seamless wallet** that calls *our* API for bet/win (balance stays in `FishkaWallets`) | Fishkas never leave our ledger |
| Results produced on a server we trust (provider RGS or ours), never in the browser | no client-trusted results |
| Round ids / transaction ids we can make unique | replay and duplicate protection |
| A virtual-currency (non-cash) mode allowed by the contract | Fishkas are play money |

## Candidates

### 1. Commercial studios and aggregators (Pragmatic, Playtech, SoftSwiss, BlueOcean, …)
- Technically right: they all offer a seamless-wallet API (the provider calls the operator's wallet for every
  bet and win) and run results on their own certified RGS. Several license the same titles to social /
  virtual-coin casinos ([track360](https://track360.io/blog/sweepstakes-casino-game-providers-aggregators-operator-integration-2026),
  [BlueOcean docs](https://blueoceangaming.atlassian.net/wiki/x/gIASS)).
- Not free: integration is a signed contract with a setup fee and a share of revenue (vendor guides quote
  $5k–$50k setup plus 6–15 % GGR — [Source Code Lab](https://sourcecodelab.co/?p=113713), unverified vendor
  figures). Content is normally limited to licensed operators; regulators have fined suppliers whose games
  appeared on unlicensed sites ([KPMG](https://assets.kpmg.com/content/dam/kpmg/gi/pdf/tom-whitton-regulatory-scrutiny.pdf)).
- **Demo iframes** (the "free play" links on provider sites and affiliate plugins) carry no licence for an
  operator to use them, have their own fun-money balance (no Fishka wallet), and are meant for marketing
  ([UK GC](https://www.gamblingcommission.gov.uk/news/article/free-to-play-games-being-available-through-gambling-affiliates)).
  **Not usable** under the owner's rules.
- Verdict: the right long-term route, **needs the owner** (contract, fees, the provider's approval of a
  play-money social site). Our adapter interface is built so such a provider plugs in.

### 2. Stake Engine (engine.stake.com)
- A platform for independent studios to publish games **on Stake**, paid 10 % of GGR
  ([Gambling Insider](https://www.gamblinginsider.com/news/29242/stake-introduces-stake-engine-for-game-developers)).
  Its RGS and wallet belong to Stake; it is not an operator API we can plug into.
- Its **Math SDK is MIT** ([engineio/math-sdk](https://github.com/engineio/math-sdk), LICENSE "MIT License,
  Copyright (c) 2025 Stake Engine", active Nov 2026). It ships sample games (lines, ways, cluster, scatter,
  expanding wilds) and generates *all outcomes ahead of time* ("books") plus weighted lookup tables; a server
  then only draws a weighted book id. That is exactly a server-authoritative model.
- Its **Web SDK has no licence file** ([engineio/web-sdk](https://github.com/engineio/web-sdk)) → all rights
  reserved; the sample games' art and front ends **cannot** be used.
- Verdict: the math is usable (MIT); a front end is still needed.

### 3. Open-source HTML5 slot front ends
| Project | Licence | Results | Notes |
|---|---|---|---|
| [johakr/html5-slot-machine](https://github.com/johakr/html5-slot-machine) | MIT (code); icons by KPD Media, "private and commercial purposes, no attribution" | in the browser (`Math.random`) | 634★, plain JS + WAAPI; `renderSymbols(nextSymbols)` takes the stop symbols, so a server can drive it |
| [orestislef/slot](https://github.com/orestislef/slot) | MIT | server (PHP) | 1 commit, 1★ — not maintained |
| pixi-slot-machine, nuxy/slot-machine-gen, krysits/casino-client | MIT | browser | demos/libraries |
- None has a wallet; all that compute in the browser fail the "no client-trusted results" rule as shipped.
- Verdict: usable only as a **renderer** fed by our server.

## Conclusion

No ready-made provider today offers **both** a licence for our commercial use **and** a secure Fishka wallet
for free. Therefore, per the owner's rule:

1. **No slot is offered as playable** until a provider is connected through the secure wallet below. The
   casino shows slots only when an admin has added a game from an *active* provider; the lobby never shows
   placeholder or demo slots.
2. We build the **provider-neutral part** now (it is ours whatever provider is chosen):
   - `SlotProvider` adapter contract (launch a session, receive provider wallet calls);
   - `SlotWallet`: bet debit and win credit only through `FishkaWallets`, keyed by the provider's
     transaction id (unique in the DB → replays and duplicates are no-ops returning the first answer),
     rollback of a bet that never settled, every call signed (HMAC with a per-provider secret from `.env`,
     never a setting), round ids tied to a session we issued for one user;
   - admin: providers (on/off), games (title, art, provider game id, on/off, order), rounds and errors;
   - Top Winners from real `slot` ledger rows.
3. **Options for the owner** (decision needed, not taken by us):
   - **A. Commercial provider** (recommended for real slot content): sign with a social-casino-friendly
     aggregator; we write its adapter against the contract above.
   - **B. Open-source route**: MIT outcome tables from the Stake Engine Math SDK sample games, drawn by our
     server, shown by an MIT renderer (johakr) with art we are licensed to use. No own math, but it is
     integration work close to "building a slot", so it waits for the owner's OK.

## Update 2026-11-16 — the owner chose open-source code

The owner asked for open-source slots copied into our code. [Z1000k/open-source-slots](https://github.com/Z1000k/open-source-slots)
(APEXSLOTS: code MIT, art CC BY 4.0, maths from slotopol MIT) is used: each game's maths is ported to PHP and dealt
by our server, its client copied and fed by our API. jcapshaw/slots is AGPL-3.0 and is not used. See ADR 0024.

## Security checklist for any adapter
- The browser never sends an amount won or a result; only "spin with bet X" goes to the provider (or our
  server), and the win is credited by the provider's signed server call or our server's draw.
- Each wallet call: valid signature, known provider, active session belonging to that user, bet within the
  game's min/max, enough Fishkas (row lock, no negatives — `FishkaWallets`).
- `slot_transactions.provider_tx_id` is unique per provider; a repeated id returns the stored result and
  moves no Fishkas.
- Wins can't exceed the game's configured max multiplier × bet; anything else is refused and logged.
- Sessions expire; a closed session takes no bets.
