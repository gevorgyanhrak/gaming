# Third-party code and art in this repository

| What | Where | Licence | Notes |
|---|---|---|---|
| slotopol/server (slot games, maths, PRNGs) | `services/slot-engine/` | MIT © schwarzlichtbezirk 2023–2026 (`services/slot-engine/LICENSE`) | Changes listed in `services/slot-engine/UPSTREAM.md`. The game and maker names are trademarks of their owners; the licence does not cover them (ADR 0026: kept by the owner's decision). |
| slotopol maths in the house slots | `apps/web/play/LICENSE-SLOTOPOL` | MIT | ADR 0024. |
| APEXSLOTS slot clients and engines | `apps/web/play/` | MIT (`LICENSE-APEXSLOTS`); art CC BY 4.0 (`LICENSE-ART-APEXSLOTS.md`, credited on the slot page) | ADR 0024. |
| Avataaars portraits (AI players) | `apps/web/public/ai/` | Avataaars by Pablo Stanley, free for personal and commercial use; generated with DiceBear (MIT) | `apps/web/public/ai/CREDITS.md`. |
| Manrope, Unbounded fonts | `apps/web/src/assets/fonts/` | SIL Open Font License 1.1 (`OFL.txt`) | ADR 0025. |
| Playing-card courts (Fomin) | `apps/web/src/games/cards/` | CC0 | Card redesign. |

Go dependencies of the slot engine (from `go.mod`): go.yaml.in/yaml/v3 (MIT/Apache-2.0), go.uber.org/atomic (MIT),
golang.org/x/crypto and golang.org/x/sys (BSD-3-Clause).
