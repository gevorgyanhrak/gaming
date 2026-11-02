# QA report — TASK-043 · Emojis, quick messages and the table chat

- **Date:** 2026-11-02, about 11:35–12:15 local (07:35–08:15 UTC).
- **Build:** `main` at 1ae8d38 (feature 491b624, fixes 1d9dcef). `make restart` was run first.
- **Brief and design:** `docs/product/briefs/TASK-043-emoji-quick-messages.md` (§8) and ADR 0017.
- **Method:** real sessions. Each player logged in through the API (Sanctum), and the session cookies were
  copied into an isolated headless-Chrome browser context (CDP :9333), one context per player.
  - Every UI action was a real click in that player's page: 😊 button → recipient → emoji, the one-tap
    quick buttons, Mute.
  - Flight direction was measured, not eyeballed. A script injected before the page loads records every
    `Element.animate` call in the reaction layer (its keyframes) together with each seat's
    `data-seat-anchor` rectangle at that moment. The first keyframe must sit on the sender's anchor and the
    landing keyframe on the receiver's (within 40 px, bubbles up to 60 px above).
  - Raw `fetch` (no client retry) was used for the rate-limit checks.
- **Evidence:**
  - Screenshots and scripts are in
    `/private/tmp/claude-501/-Users-hrak/ba69acac-3462-45f1-8ac0-4a7dd90690c5/scratchpad/qa2/`, written below
    as `qa2/`. Screenshots start with `t43-`; scripts are `t43lib.mjs`, `t43game.mjs`, `t43ux.mjs`,
    `t43net.mjs`, `t43perm.mjs`, `t43i18n.mjs` and others.
  - Logs: `qa2/t43-1440.log`, `qa2/t43-390.log`.
- **Load:** the Mac's load average was 8–14 during the run. Twice the whole stack paused for about 20–26 s,
  with no request of any kind logged by nginx; see "Environment notes".

## Summary

| Area | Result |
|---|---|
| 1. A→B and B→A in Nardi, Chess, Checkers, Durak (3 seats) and Poker (3 seats), at 1440 and 390 | **PASS** — every flight starts at the sender's seat and lands at the receiver's, on every screen; chat logs are identical on all pages |
| 2. Quick-message bubbles (name, stacking, fade, clicks pass through) | PASS, with one Low bug (BUG-1: a third stacked bubble overlaps the second at the top of the screen) |
| 3. Everyone vs one player; a bot seat | PASS |
| 4. Bursts, duplicates, rate limit, double click | PASS for bursts, duplicates and the rate limit. Low bug: a double click on a quick button sends twice (BUG-2) |
| 5. Reload, away and back, Reverb down and up | PASS (polling delivered in 1.5 s with Reverb stopped) |
| 6. Permissions: non-player, finished rooms (10 min then 409), admin switch | PASS |
| 7. Waiting rooms | PASS |
| 8. Search en/ru/hy, recents, Mute, keyboard, screen reader | PASS. Observation O-1: search doesn't find plurals ("cards", "chips") |
| 9. ru and hy at 390 | PASS — every new string is translated; no clipping, no horizontal scroll |
| 10. Performance and console | PASS — no long tasks, no errors (one known dev warning) |

**Totals:** 46 checks — 44 PASS, 2 Low bugs, 2 observations. No Critical, High or Medium bugs.

## 1. Direction and both screens

Each run used a private table at the Beginner group, with real UI clicks. A = player2, B = player1, and
C = q40mm2 for 3-seat tables. "OK" means the measured start keyframe was on the sender's anchor and the
landing keyframe on the receiver's anchor, on that viewer's screen.

| Game | Width | A→B ❤️ (on A / B / C) | B→A 😂 | Quick message | 3-seat extras | Chat lines on all screens | Result |
|---|---|---|---|---|---|---|---|
| Nardi | 1440 | OK / OK | OK / OK | "Good luck!" bubble A→B: OK / OK | — | identical | PASS |
| Chess | 1440 | OK / OK | OK / OK | OK / OK | — | identical | PASS |
| Checkers | 1440 | OK / OK | OK / OK | OK / OK | — | identical | PASS |
| Durak (3) | 1440 | OK / OK / OK | OK / OK / OK | to everyone: over the sender on all 3 | C→A 🔥 OK×3; B→everyone 🎉 pops over B on all 3 | identical (5 lines) | PASS* |
| Poker (3) | 1440 | OK / OK / OK | OK / OK / OK | to everyone: over the sender | C→A 🔥 OK×3; B→everyone 🎉 OK×3 | identical (5 lines) | PASS |
| Nardi | 390 | OK / OK | OK / OK | OK / OK | — | identical | PASS* |
| Chess | 390 | OK / OK | OK / OK | OK / OK | — | identical | PASS |
| Checkers | 390 | OK / OK | OK / OK | OK / OK | — | identical | PASS |
| Durak (3) | 390 | OK×3 | OK×3 | over the sender ×3 | C→A OK×3; B→everyone OK×3 | identical | PASS |
| Poker (3) | 390 | OK×3 | OK×3 | over the sender ×3 | C→A OK×3; B→everyone OK×3 | identical | PASS* |

\* On the first attempts of Durak 1440, Nardi 390 and Poker 390, some sends seemed lost. The nginx log
showed they were not lost. The POSTs completed 20–26 s late, together with every other request, including
the presence calls from all pages, after a stall in which nothing completed. They arrived after my
script had already read the chat. Re-runs passed fully. See "Environment notes".

Example of the measured coordinates, on Poker 1440, A (seat 0) sending ❤️ to B (seat 1):

| Screen | Start | Landing |
|---|---|---|
| player2 (sender, bottom) | (720,723) | (290,334) — B's seat, top-left |
| player1 (receiver) | (1150,371) | (720,690) — their own seat, bottom |
| q40mm2 (third player) | (290,371) | (1150,336) |

On Nardi, Chess and Checkers the seats are the player-list chips under the title, so flights there are
short hops between the two chips. This is by design.

**Evidence:** `qa2/t43-poker-1440-AtoB-emoji-player1.png` (the heart landing on "You"),
`qa2/t43-poker-390-A-quick-everyone-player1.png` (a "player2 · Good luck!" bubble over player2's seat), and
`qa2/t43-<game>-<width>-<step>-<player>.png` for every step and every screen.

No horizontal scroll at either width (scrollWidth = viewport on every page).

## 2. Quick-message bubbles

| Check | Steps | Actual | Result | Evidence |
|---|---|---|---|---|
| Sender's name shown | Any message | Name in gold above the text ("player1 / Good luck!") | PASS | qa2/t43-ux390-bubbles-A.png |
| Stacking | B sends 3 messages to A 350 ms apart (Chess, 390) | Three bubbles at y 92–142, 36–86 and 8–58: they stack upwards. The third is pushed against the top edge and overlaps the second by 22 px, covering that bubble's sender name. They also sit over the page title and the header | PASS with BUG-1 | qa2/t43-ux390-bubbles-A.png |
| Doesn't block clicks | `elementFromPoint` at each bubble's centre | The layer is `pointer-events: none`; the hit element is always the page underneath (header, title, game-over link, Durak card) | PASS | |
| Fades in about 3 s | Animation duration | 3,400 ms, then removed | PASS | |
| Doesn't cover controls | Bubble rectangles vs the visible buttons in `main` and the chat | Chess: none. Durak: none (over the opponent's card backs). Poker heads-up: over the game-over panel's text and link (clicks still pass through) | PASS | qa2/t43-ux390-m6-bubbles-A.png, qa2/t43-ux390-m5-bubbles-A.png |

## 3. Everyone vs one, and bot seats

| Check | Actual | Result |
|---|---|---|
| Emoji to everyone | Pops up over the sender's seat and rises, on every screen; logged as "player1: 🎉" | PASS |
| Message to everyone | Bubble over the sender's seat; logged without an arrow | PASS |
| Quick buttons with one opponent / at 3+ seats | One opponent: goes to that player ("You → player1: GG!"). 3+ seats: goes to everyone ("You: Good luck!") | PASS |
| Picker at a bot table | Recipients "Everyone* / David · Bot / Alex · Bot" | PASS |
| Send to a bot seat | 🤖 to David: the flight starts at my seat (720,723) and lands at David's (290,334); logged "You → David: 🤖". Bots never send | PASS (qa2/t43-bot-table.png) |

## 4. Bursts, duplicates, rate limit, double click

| Check | Steps | Actual | Result |
|---|---|---|---|
| Burst from several players | Poker, 3 players × 3 emojis at the same moment, plus a 10th | All 10 → 201. On each of the 3 pages: 10 log lines, 10 distinct, 10 distinct values animated, no duplicates | PASS |
| Same `client_id` sent 3× (the third with another emoji) | API | 201 ×3, the same id; the third returns the first (🔥), nothing is broadcast twice | PASS |
| Per-second limit (3/s) | 6 raw POSTs at once | 3×201, 3×429 "Too Many Attempts.", Retry-After 1 | PASS |
| Per-minute limit (40/min) | 45 raw POSTs about 0.55 s apart in a fresh window | 40×201; **#41 → 429**, Retry-After 36 | PASS |
| Friendly message in the UI | 5 quick clicks on "Nice move!" | 3 delivered; the panel shows "Couldn't send. Try again in a moment." (`role=alert`) | PASS (qa2/t43-ux390-ratelimit-A.png) |
| Double click on a quick button | Two clicks on "GG!" | **Two "GG!" messages delivered** (each click gets a new client id) | BUG-2 (Low) |
| Double click in the picker | One click sends and closes the picker; the page behind is `inert` | Single send | PASS |

On my first attempt both limits seemed not to apply. That was my API client retrying 429s, plus a
fixed window that spanned an earlier test. With raw requests in a fresh window both limits work exactly.

## 5. Reconnection

| Check | Steps | Actual | Result |
|---|---|---|---|
| Reload mid-game | Poker; B reloads after 10 reactions | Chat shows all 10 again; 0 animations after the reload | PASS |
| Away and back | A goes to about:blank; C sends 🌟 and "Hi!"; A reopens the room | Both in A's chat, 0 animations | PASS |
| Reverb stopped | `docker compose stop reverb`, wait 6 s, B sends 🚀 to A | Seen in A's chat after **1,501 ms**, and it animated (catch-up); C got it too | PASS |
| Reverb started again | `docker compose start reverb`, wait 12 s, C sends ⚡ | Seen on A after **424 ms**, animated | PASS |
| Consistency | All 3 pages at the end | Identical logs, 14 entries, 0 duplicates | PASS |

Reverb is running again ("Up").

## 6. Permissions

| Check | Actual | Result |
|---|---|---|
| Not a player at the room | POST 403, GET history 403 | PASS |
| Validation | Unknown emoji, free text as a message, emoji sent as `message`, kind `text`, to yourself, to an empty seat, seat −1, no client_id → 422 with clear messages | PASS |
| Finished room, within 10 minutes | Game over panel and chat still shown; UI "GG!" sent and logged | PASS (qa2/t43-finished-A-390.png) |
| Finished room, more than 10 minutes | `finished_at` moved back 11 minutes in the DB: POST 409 "This table no longer takes reactions."; after a reload the chat is hidden; GET history still 200 | PASS |
| Admin switch OFF (Admin → Settings → "Emojis and quick messages at tables", Save) | Audited (`settings.updated`). Public config `reactions:false` a few seconds later. A newly opened room has no chat panel; POST 403 "Emojis and quick messages are switched off." | PASS (qa2/t43-admin-off.png, qa2/t43-switch-off-A-390.png) |
| Switch OFF while a page is already open | The panel stays until the page refreshes its config. A send then shows "Emojis and quick messages are switched off." | PASS (acceptable) |
| Switch restored | ON, saved through the admin UI (audit id 51); config `reactions:true` | done |

## 7. Waiting rooms

On a private 3-seat Durak table with 2 of 3 seated (390 px):

| Check | Result |
|---|---|
| The chat shows for both seated players | PASS |
| A→B 👋 flies between the waiting-table seats on both screens (start and landing OK) | PASS |
| B's one-tap "Good luck!" goes to A (the only other player so far) | PASS |
| To the empty seat → 422 | PASS |
| From a person not yet seated → 403 | PASS |
| After the third player joined, the earlier chat stayed | PASS |

Evidence: qa2/t43-waiting-A-390.png, qa2/t43-waiting-B-390.png.

## 8. Search, recents, Mute, keyboard, screen readers

| Check | Actual | Result |
|---|---|---|
| Catalog | 12 categories, 200 emojis. 21 messages in 4 categories, including every example in the brief | PASS |
| Search | heart → ❤️😍🥰💕💖💔💙💚💛💜🖤🤍♥️; сердце → 9 hearts; սիրտ → ❤️💖💔; pizza / пицца → 🍕; trophy / кубок / գավաթ → 🏆; xyzzy → none, with a "no results" state. "cards" and "chips" find nothing ("card" → 🃏, "chip" → 🪙): O-1 | PASS |
| Recently used | After sending 🍀, the picker opens with "Recently used" first and 🍀 at its start | PASS |
| Mute | Others' flights stop (0 animations) while the chat still logs them; your own sends still animate; the label switches to "Unmute" (`aria-pressed=true`); after unmuting, flights return | PASS |
| Keyboard | Tab reaches "Send an emoji" (17 Tabs from the top of a chess page) and Enter opens the picker. Focus moves into the dialog and the page behind is `inert`. Tab and Shift+Tab move within the dialog (Close → recipients → tabs → search → categories → emojis). Escape closes it and focus returns to "Send an emoji". Enter on an emoji sends it | PASS |
| Screen readers | The chat is `role=log` with `aria-live=polite`, labelled "Table chat"; the section is labelled by its heading. The picker is `role=dialog` with `aria-modal` and is labelled. Tabs use `role=tab` with `aria-selected`; recipients use `aria-pressed`. The flight layer is `aria-hidden`; emoji buttons are labelled with the emoji; no unlabelled buttons | PASS |

## 9. ru and hy at 390

| Check | Result |
|---|---|
| Every new string translated: chat title, Mute, "Send an emoji", Messages, the 3 one-tap buttons, picker title, "Send to", Everyone, tabs, search, all 12 category names, all 4 message categories and all 21 messages, "You", the error messages. No Latin words left apart from player names and "GG!". Message keys are shown in the viewer's language (A's "Rematch?" reads "Реванш?" and "Ռևանշ?") | PASS |
| Layout: no clipped text in the chat panel, scrollWidth 390. The one-tap row scrolls sideways (`overflow-x-auto`); in hy the third button shows partly, as designed. hy "Արագ հաղորդագրություններ" wraps to two lines in its tab | PASS |

Evidence: `qa2/t43-i18n-{en,ru,hy}-chat-390.png`, `-picker-emoji-390.png`, `-picker-messages-390.png`.

## 10. Performance and console

| Check | Actual | Result |
|---|---|---|
| Burst of 10 during play (Poker, 3 pages in one Chrome) | No long tasks (>50 ms) recorded on A. Frame gaps median 16.7 ms, p95 100 ms, max 133 ms — this is 3 pages rendering at once on a loaded machine (load 8–14). The Mute toggle answered in the next frame (103 ms). Animations use transforms and opacity only | PASS |
| Console | Uncaught errors: none on any page in any run. `console.error` / warnings during sends, bursts and the picker: only React Router's dev warning "No `HydrateFallback` element…" (not new) | PASS |

## Bugs

### BUG-1 — Low — Three stacked message bubbles overlap at the top of a phone screen

- **Steps:** at 390×844 in Chess, the other player sends 3 quick messages to you within about 1 s.
- **Expected:** the bubbles stack without covering each other, near your seat.
- **Actual:** they stack upwards from the player-list chip into the header. The third is clamped at the
  top (`y ≥ height + 8`) and overlaps the second by 22 px, which hides that bubble's sender name. They also
  cover the page title and the header. They disappear after 3.4 s and don't block clicks.
- **Evidence:** qa2/t43-ux390-bubbles-A.png (rects 92–142, 36–86, 8–58).
- **Suspected:** `apps/web/src/features/reactions/components/ReactionLayer.tsx`, the message branch
  (`y = Math.max(dest.top - 8 - index * (h + 6), h + 8)`). When there is no room above, stack downwards
  (below the anchor) or offset sideways instead of clamping onto the previous bubble.

### BUG-2 — Low — A double click on a one-tap quick message sends it twice

- **Steps:** at a 2-player table, double-click "GG!" in the table chat.
- **Expected:** one "GG!" (the brief asks to prevent duplicate events).
- **Actual:** two "GG!" lines on both screens and two bubbles. Each click creates a new `client_id`, so
  the server's dedupe can't catch it. The picker doesn't have this problem because it closes on the first
  click.
- **Evidence:** t43ux.mjs output "double-click GG!: new entries on B = 2".
- **Suspected:** `apps/web/src/features/reactions/components/TableChat.tsx` (quick buttons → `onQuick`)
  and `RoomReactions.tsx` `deliver`. Ignore repeat taps of the same quick button for about 500 ms, or
  disable it while its send is in flight.

### Observations (no change required)

- **O-1:** emoji search is a plain substring match on singular keywords, so "cards" and "chips" find
  nothing while "card" and "chip" do. Plural keywords such as card(s) and chip(s) would help in Poker
  (`ReactionCatalog`).
- **O-2:** on Nardi, Chess and Checkers the seat anchors are the player-list chips, so flights are short
  hops of about 200 px at 1440 between two chips near the title, not across the board. This follows the
  design; mentioned for the UI review.

## Environment notes

During this run, twice the whole stack stopped completing requests for about 20–26 s (nginx logged
nothing at all, not even the 5-second presence calls from every page). Afterwards everything queued
completed at once:
- 07:39:29–07:39:55 UTC
- 07:44:51–07:45:05 UTC

Reactions sent in those windows arrived late but were not lost: 201, delivered once, in the right order.
This matches the machine-wide stalls seen in earlier QA runs on this Mac (load 8–14, Docker under QEMU) and
is not attributed to TASK-043.

## Data and cleanup

- No balances were set for this run. The existing balances of player1, player2, q40mm2, q40mm1, q40b5k and
  q40b50k were used; Beginner stakes moved between them. Final balances: player2 50,999, player1 36,000,
  q40mm2 63,995, q40mm1 8,005, q40b5k 17,000, q40b50k 65,000.
- To test the 10-minute window, one finished room's `finished_at` was moved back 11 minutes in the DB
  (a test Chess room from `t43perm.mjs`).
- The admin switch was turned off and on through Admin → Settings (audited, ids 50 and 51). It was also
  turned off and on once with `Settings::update` (actor q40admin) for the open-page check. It is **ON**
  now and the public config says `reactions:true`.
- No waiting or active rooms (DB checked). Reverb is running.
- The browser is signed in as player2@example.test, in English, on the lobby.

## Fix status (developer, 2026-11-02)

| Finding | Status | Fix | Verified |
|---|---|---|---|
| BUG-1 bubbles overlap at the top on a phone | Fixed | `ReactionLayer`: bubbles stack above the seat while there is room, the rest stack below it | Live 390: three bubbles to one seat at 36–86, 92–142, 256–306 px (no overlap) |
| BUG-2 double-clicked one-tap message sent twice | Fixed | `RoomReactions`: the same reaction to the same player within 1 s is one send (failed sends can be retried at once) | Reactions.test ("sends a double-clicked quick message once", fails without the fix) |
| Observation: plurals in search | Fixed | English plural also matches its singular ("hearts" → ❤️) | Reactions.test ("finds emojis by a plural word too") |

