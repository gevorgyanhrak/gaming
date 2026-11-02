# 0017. Emojis and quick messages at tables

**Status:** Accepted (2026-11-02)

## Context
The owner asked for emojis and predefined quick messages between players during games (TASK-043), with
animations that travel from the sender's seat to the receiver's, delivered in real time to everyone at the
table without duplicates, and (from a screenshot) a chat box under the table. Free-text chat was not asked
for, and it would need moderation (filters, reports, muting) that the platform doesn't have.

## Decision
- **Only a catalog.** `App\Gameplay\Reactions\ReactionCatalog` lists the emojis (12 categories, search words
  in en/ru/hy) and the predefined message keys (4 categories). The server accepts nothing else; messages
  travel as keys and each viewer reads them in their own language. `GET /reactions` serves the catalog.
- **Same delivery as room updates.** `POST /rooms/{room}/reactions` (a seated person; to one other seat or
  to everyone) broadcasts `room.reaction` on each person's private channel (`user.{id}`). No new channel,
  no client-to-client whispers: the server validates every reaction.
- **Ephemeral, with a short history.** Not stored in MySQL. The room's last 50 reactions live for 3 hours
  in the game store (Redis) with a per-room sequence number: the table chat after a reload, and catch-up
  for pages without a live connection (they poll `GET /rooms/{room}/reactions?after=seq`) or that just
  reconnected. Ids are ULIDs; the client shows each id once. Only live ones animate (catch-up animates at
  most the last three).
- **Idempotent and limited.** The client sends a `client_id`; a repeat returns the first reaction and
  broadcasts nothing. Limiter `reactions`: 3 a second and 40 a minute per player.
- **Who and when.** A person seated at the room, while it waits, while it plays and for 10 minutes after
  it ends. Bots never send. Admin setting `reactions.enabled` (public) switches the feature off.
- **Animations on the client only**, from seat anchors (`data-seat-anchor`) that each layout marks
  (player list, waiting table, poker and durak seats), with Web Animations (transforms and opacity), no
  dependency; viewers can mute others' animations (local preference).

## Consequences
- Nothing to moderate and nothing new to store; history is lost after 3 hours or a Redis flush.
- Free-text chat stays an owner decision (it needs moderation tools first).
