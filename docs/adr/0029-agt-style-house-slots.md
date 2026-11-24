# ADR 0029 — AGT-style house slots: Slotopol's maths, our own symbols and screen

- Status: accepted (2026-11-24)
- Context: the owner asked for slots like AGT's, free and open source, "use the logic and add symbols similar to the
  original" — after the Slotopol server (ADR 0024 history) was removed because its screens didn't look like the
  providers' games. There is no free AGT art; Slotopol (MIT, github.com/slotopol/server) has the maths of 31 AGT
  games (≈ 60 titles): symbols, pay tables, pay lines and reel strips at several RTPs.

## Decision

1. **Maths:** each game's numbers are converted from Slotopol (kept in branch
   `backup/before-slotopol-restore-2026-11-18`) by `apps/web/play/tools/slotopol_agt.py` into
   `apps/api/app/Platform/Slots/House/Agt/data/<slug>.json` (reels at the RTP closest to 96 %, at most 96.5 %). MIT
   licence shipped there (`LICENSE-SLOTOPOL`). Only numbers are taken.
2. **Engine:** one data-driven house slot, `House\Agt\AgtSlot` (ADR 0024 contract: dealt on our server through the
   slot wallet). Rules as Slotopol's scanners: lines left to right per line bet; wild `none`, `substitute` (stands
   in, pays its own run if more) or `expanding` (reels 2–4 turn wild); scatter anywhere × total bet. Games with other
   features (free spins, fill multipliers) are added only once their feature is ported and proven.
3. **Proof:** `AgtSlotsTest` computes each game's exact RTP from its data, independently of `play()`, and must equal
   Slotopol's published figure over the full line table (expanding-wild games within 0.05 %: Lucky Slot's published
   line part looks stale; the scatter part matches to the digit). The RTP on the lines we play is checked too.
4. **Screen:** one shared client (`apps/web/play/agt/`) draws any of these games from the layout the session sends
   (`HasClientLayout`; no reel strips). Symbols are our own SVG drawings in the classic glossy fruit-machine style
   (sevens, bells, fruits, star scatter, wild badge) — no copied art, logos or brand marks. Each game has a two-line
   page (`play/<slug>/index.html`: slug + colour theme) and a tile rendered by `play/tools/agt_tiles.mjs`.
5. **Names:** the plain descriptive titles ("Seven Hot", "Ice Fruits", "Lucky Slot"); the credit line under each game
   names Slotopol, not AGT, and no AGT branding is used anywhere.

## Consequences

- A new AGT-style game is: convert its data, add it to `AgtCatalog` and the seeder, write its two-line page, render
  its tile — if its rules are already supported.
- First batch: Seven Hot, Ice Fruits, Lucky Slot. Cherry Hot and Valentine's Day wait for the fill multiplier.
