# 0018. Levels and XP

**Status:** Accepted (2026-11-07)

## Context
The owner asked for a level and XP progression (TASK-047, brief `docs/product/briefs/TASK-047-levels-xp.md`):
every player has a level, earns XP by playing, levels up on the backend only, sees a progress bar and a
level-up popup with a reward granted exactly once, and sees levels next to avatars everywhere; bots show fixed
high levels and never earn XP or rewards. Rewards may be Fishkas or virtual items; Fishkas stay free play
currency (ADR 0015): never bought, sold, cashed out or sent.

What exists: the game finisher records results and per-game statistics inside the transaction of the final
move (`GameFinisher`); Fishkas change only through `FishkaWallets` (row lock + ledger + idempotency key); bots
are `bot_profiles` (participants point at a user or a bot profile); admin settings are read through
`Settings` (ADR 0013); the admin panel is Filament. There is no leaderboard yet; when one is built it shows
the level badge like every other avatar.

## Decision
- **Where XP and the level live.** `users.xp` (total XP, never decreases) and `users.level` (CHECK ≥ 1;
  new users start at 1). Only `App\Platform\Levels\Levels` changes them: it locks the user row, adds XP,
  recomputes the level from the total and records the change in `xp_transactions` (amount, source, the
  room or day it came from, the level before and after) with a unique idempotency key, so the same game or
  day can never pay XP twice. Nothing from a client is ever trusted: there is no endpoint that accepts XP.
- **XP sources** (admin settings, group `levels`): a finished game — `xp_game` for taking part plus
  `xp_win` for a win or `xp_draw` for a draw; an abandoned game (timed out, left) gives nothing; a game
  shorter than `min_game_seconds` gives nothing (no farming by instant resignations). Paid in the finisher's
  transaction (key `game:{room}:{user}`). Collecting the daily bonus gives `xp_daily` (key `daily:{user}:{day}`).
  Bots are never paid (they have no user row).
- **The curve** (admin settings, so it can be tuned without a deploy). XP needed to go from level L to L+1:
  - L < 30: `xp_base + xp_step × (L − 1)` — defaults 80, 20 → 80 … 640; level 30 at 10,440 XP in total,
    about 260 games at ~40 XP a game; level 5 after ~11 games.
  - L ≥ 30: `⌈(xp_base + xp_step × 29) × after_30_percent/100 × (1 + growth_percent/100)^(L − 30)⌉` —
    defaults 150 % and 10 % → 990, 1,089, 1,198, … (each level ~10 % harder than the last).
  - Capped at `max_level` (default 100). A user's level never goes down when the curve is changed later;
    it is recomputed on the next XP.
- **Rewards** (`level_rewards`, edited in the admin panel; one row per level, levels 2–30 seeded): Fishkas
  plus optional items (`items`: a list of `{kind, key}`) and a milestone flag (a bigger celebration).
  Levels without a row give `levels.reward_default` Fishkas. Seeded: 250 + 50 × L Fishkas; milestones 5 (badge
  "Rising"), 10 (emoji pack "Royal"), 15 (badge "Premium"), 20 (avatar frame "Gold"), 25 (emoji pack
  "Legend"), 30 (avatar frame "Legend" and badge "Champion"), with larger Fishka amounts.
- **Items are a code catalog** (`LevelItems`: frames, badges, emoji packs) and are *derived from the level*:
  a player owns an item when a reward row at or below their level lists it. Nothing to duplicate, nothing to
  claim twice. The frame and badge shown are the highest owned. Exclusive emojis are a catalog category whose
  emojis carry the level that unlocks them; the server refuses a locked one (`RoomReactions`).
- **Level-ups and claims.** Each level reached writes one `level_ups` row (unique `(user_id, level)`) with the
  reward as configured at that moment; several levels at once write several rows. The Fishkas are collected
  from the popup: `POST /me/level-ups/claim` credits every unclaimed row through `FishkaWallets::credit`
  (type `level_reward`, key `level:{user}:{level}`) and stamps `claimed_at`, in one transaction that locks the
  rows — a double click or two tabs pay once. Unclaimed rows are the "pending level-ups": shown on the next
  visit, however long the player was away. The rows are the reward history.
- **Real time.** After commit, `level.updated` on `user.{id}` (level, xp, pending count) so the popup opens
  without a reload; pages also read `GET /me/level`.
- **Bots** have `bot_profiles.level` (admin-editable, 10–100; existing bots back-filled with distinct values
  from 18 to 57). The same badge everywhere, no frame, no XP, no rewards.
- **Display.** `level` (and `frame`, `badge` for people) in the current user, public profiles and room
  participants; the frontend `Avatar` takes `level`/`frame` so every avatar shows the same compact badge.
- **Lock order:** user row → wallet. A game's finisher pays XP before the pot is settled; a claim locks the
  user row, then its `level_ups` rows, then the wallet; the daily bonus pays XP after its wallet transaction
  has committed. The one exception is a poker player leaving (cash-out, then their result): should it meet a
  claim by the same player at the same instant, MySQL rolls one back, and both are safe to repeat
  (idempotency keys).

## Consequences
- Tuning the curve or the rewards is an admin change, audited; it affects future level-ups only.
- A new XP source is one call to `Levels::award` with its own idempotency key and setting.
- Poker counts one game per sitting (the participant's finish), not per hand; per-hand XP can come later.
- Frames, badges and emoji packs are only what the catalog defines; new ones need code (art), not data.
