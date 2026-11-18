# Slot providers like AGT, Pragmatic Play, Novomatic — what exists (2026-11-18)

Owner: "find open-source providers similar to AGT, Pragmatic, Novomatic" (after removing Slotopol).

## The short answer

AGT, Pragmatic Play and Novomatic are commercial studios. Their games, art and interfaces are not open source
anywhere. Repositories that offer "Novomatic games for free" are either maths clones without art (like Slotopol:
symbol numbers, no screens) or ripped graphics, which can't be used legally. Their real games are only available
through a licence, usually via a game aggregator.

## Options

| Option | What you get | Looks like real provider slots? | Cost / conditions |
|---|---|---|---|
| **Licensed aggregator** ([St8](https://st8.io/slot-games/), [SOFTSWISS Game Aggregator](https://www.softswiss.com/?p=335123), which explicitly serves social-casino / free-to-play operators) | The real Pragmatic Play and others, hosted by them; their wallet calls go to our Fishka wallet (already built: ADR 0023 signed slot wallet) | **Yes, the originals** | A business contract; fees; each provider's approval. Novomatic availability not confirmed. |
| **Licensed HTML5 slot games with full source** ([CodeCanyon](https://codecanyon.net/item/black-beard-slot-machine-premium-html5-casino-game/49603117) titles; [Code This Lab](https://showcase.codethislab.com/games/slot_ramses/readme) "Ramses", "Arabian"…) | Complete games (art, animations, sound, bonus games, source code) we own and host; integrated like APEXSLOTS (our server deals, client shows) | Yes, in style (Egyptian, fruit, pirate themes), but not the brand games | A one-off licence per game (check Regular vs Extended for a free-to-play product); quality varies by seller |
| **Stake Engine web-sdk** ([GitHub](https://github.com/StakeEngine/web-sdk)) | 5 sample games (lines, ways, cluster, scatter, price) at provider quality: PixiJS, animations, sound, 79+ assets each | Yes (one shared "mining" theme) | **No licence file:** all rights reserved. Usable only with Stake's written permission. |
| **Open source (MIT) we already have:** APEXSLOTS, 12 games ([ADR 0024](../adr/0024-house-slots-from-open-source-games.md)) | Own art (CC BY 4.0) and clients | Yes, in style | Free; already live |
| **Small MIT projects** ([johakr/html5-slot-machine](https://awesome.ecosyste.ms/projects/github.com%2Fjohakr%2Fhtml5-slot-machine), [weyoss/pixi-slot-machine](https://github.com/weyoss/pixi-slot-machine), [ktsalik/slot](https://github.com/ktsalik/slot)) | Simple 3–5 reel demos | No (basic look; johakr uses Star Wars icons, a trademark risk) | Free |
| **Free art packs + our own maths** ([Jungle Slot Assets, CC0](https://opengameart.org/content/jungle-slot-assets), [Slot Machine Resource Pack](https://opengameart.org/content/slot-machine-resource-pack) with credit) | Symbols only; games would be ours | Partly | Free; design work |

## Recommendation

- For the real AGT / Pragmatic / Novomatic games: an aggregator contract (the only legal way). The platform side is
  ready (slot provider adapters and signed wallet, ADR 0023).
- For provider-quality look without a contract: buy licences for a handful of complete HTML5 slot games and add
  them like APEXSLOTS.
- Ask Stake for permission to use the web-sdk sample games.
