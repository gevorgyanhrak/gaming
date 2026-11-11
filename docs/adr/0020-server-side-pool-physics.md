# 0020. Server-side pool physics, replayed by the client from segments

**Status:** Accepted (2026-11-11)

## Context
8-ball pool (owner, 2026-11-11) needs real physics (collisions, cushions, pockets), but the server is the only
rules authority (ADR 0003): a client must never decide where balls stop. Updates reach players through Reverb,
which keeps messages small, and a shot can last 10 seconds.

## Decision
- **One shot is one action:** `{type: 'shoot', angle, power}` (+ `x, y` with the cue ball in hand). The engine runs
  `PoolPhysics::shoot` — pure PHP, fixed 2 ms steps, constant rolling deceleration, elastic collisions, cushions with
  open pocket mouths, no spin — so replays of the move log give the same table.
- **The client draws, it doesn't simulate:** the result carries each ball's path as segments `[t ms, x, y, vx, vy]`
  (one per abrupt velocity change, plus a "dropped in" marker). Between segments a ball moves along its velocity and
  slows by the same deceleration, which the board evaluates with a formula; the final positions come from the state.
  A whole shot is about 2–3 KB.
- **The table waits while the balls roll:** the engine pauses after each shot (`PausesBetweenRounds`, the shot's length
  + 0.7 s), so the next player's clock and the bots start after the animation.
- Action fields may now be finite decimals (`SubmitActionRequest`), for angles.

## Consequences
- The client's drawing can differ from the server by a fraction of a millimetre mid-shot; it snaps to the state at the end.
- Spin, jump shots and called pockets are out of scope; adding spin means changing `PoolPhysics` and the segment format.
