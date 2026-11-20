# ADR 0028 — Disconnection: a 5-second return window, then AI takes the seat for good

- Status: accepted (2026-11-20); changes ADR 0021's "AI holds an away player's seat until they are back"
- Context: HayBet brief item 12. When one person disconnects, the table should keep playing: the player gets exactly
  5 seconds to return, the others see a countdown, and then an AI takes the seat for the rest of the match. It must
  be decided on the server, with no race when the player returns exactly as the time runs out, and a player who
  starts another game loses the chance to return.

## Decision

1. **Detection.** The game page reports presence every 2 s. A page that closes, or a player who navigates away from
   the table inside the app, sends `POST /rooms/{room}/away` at once (`fetch` keepalive, with the XSRF header). The
   scheduler sweeps every second and marks as disconnected anyone not seen for `presence.offline_after_seconds`
   (default 6).
2. **The window.** Disconnecting sets `game_participants.disconnected_at` (ms) under the room lock and queues
   `ReplaceAwayJob` on the game queue for `presence.return_seconds` later (default 5, admin setting, public as
   `play.return_seconds`). Everyone at the table gets `room.seats` (`disconnected`) and refetches the room, whose
   participants carry `return_until` — the countdown on the seat plate. The game is not paused.
3. **Deciding, race-free.** A presence report from that player and the job both decide under the room lock, by
   server time: back at `disconnected_at + window` or later is too late (the boundary counts as over). In time:
   `disconnected_at` is cleared (`returned`). Too late — whichever arrives first — `GameActionService::replaceAway`:
   - another person still plays (any table size, two players included) and the game isn't played for chips: the
     player's result is `abandoned`, `ai_controlled` and `replaced_at` are set, and AI plays the seat to the end
     (`replaced`);
   - a chip table: the usual departure with cash-out; nobody else playing: the usual abandonment (the game ends).
   A replaced seat is never handed back.
4. **Another game.** A presence report from another room ends any open window at once (replaced).
5. **AI moves for an inactive seat.** `submit()` accepts a move for a seat that is no longer the person's only when it
   is the server's own (AI-controlled seat, client id with the `auto:` prefix, which the HTTP API can't send — it
   requires a UUID). Before this, AI could not actually move for a seat taken over after Leave.
6. **UI.** Seat plates: "Back in N s?" while the window is open, "AI plays this seat" with the AI tag after. Table
   banners: disconnected / back / AI took the seat (small, never a popup). The games-in-progress bar has a
   "Return to the game" button and states the window.

## Consequences

- The old `matchmaking.abandon_after_seconds` setting and AiTakeover's temporary stand-in (`standIn`/`handBack`) are
  gone; Leave keeps its rules (3+ seats: AI finishes the seat; two players: the one who stays wins).
- A player who checks another page of the app during a game loses their seat after 5 s — as the owner asked.
