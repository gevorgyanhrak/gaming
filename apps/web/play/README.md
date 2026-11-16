# House slots (ADR 0024)

Each folder here is one slot game's browser client, copied from
[APEXSLOTS open-source-slots](https://github.com/Z1000k/open-source-slots) at commit
`e4d379ccd701df2c7a39db330089ad538f520258`. Its art is in `../public/play/<id>/`.

- Code and sound: MIT, `LICENSE-APEXSLOTS`. Art: CC BY 4.0, `LICENSE-ART-APEXSLOTS.md`
  ("Art by APEXSLOTS (https://apexslots.eu), CC BY 4.0"; the slot page shows the credit).
- Maths: slotopol/server by schwarzlichtbezirk, MIT, `LICENSE-SLOTOPOL`; ported to PHP in
  `apps/api/app/Platform/Slots/House/Games`.

Our changes to each client (kept small, upstream style):

- `src/game/wallet.ts` rewritten over `shared/house.ts`: the server deals every round, there is no demo.
- `src/engine/` keeps only types, lines and paytable (for the info screen): no dealing in the browser.
- Amounts are whole Fishkas; the bets are the server's (`setBets`); `asset()` and the CSS point at
  `/play/<id>/`.
