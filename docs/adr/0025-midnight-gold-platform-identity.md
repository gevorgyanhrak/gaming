# 0025 — Midnight Gold platform identity; games keep the Lamplit Room

Date: 2026-11-17 · Status: accepted (owner brief, same day)

## Context
The owner asked for a complete redesign of the platform (home, header, banners, motion, typography, colours,
shared components) and explicitly not of the games. The TASK-048 "Lamplit Room" (green, brass, walnut) styles
both, through shared tokens.

## Decision
- The platform takes a new identity, Midnight Gold (docs/design/MIDNIGHT_GOLD.md): night surfaces, gold primary,
  violet second light, Unbounded + Manrope (Noto Sans for Armenian), new radii, shadows and motion.
- Token names stay (`emerald-*` now alias the night shades) so every existing class follows without a rewrite.
- The game rooms keep the Lamplit Room through `.classic-scope` on `<body>` while a table is open. Shared
  components whose look differs between the two read it from CSS variables the scope resets.
- The old drawn home backdrop and the casino hero are replaced by `NightSky` and `HomeHero`.

## Consequences
- New shared UI must use tokens or the component variables, never fixed night colours, when it can appear at a
  table; otherwise the room changes.
- A game room's look is verified by pixel-diffing a finished room before and after a platform change.
