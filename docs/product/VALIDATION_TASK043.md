# PM validation — TASK-043 · Emojis, quick messages and the table chat

- **Date:** 2026-11-02 · **Build:** `main` at 54a3b6b (feature 491b624; fixes 1d9dcef, 2967f6d, 54a3b6b)
- **Against:** owner brief `docs/product/briefs/TASK-043-emoji-quick-messages.md` §1–§8 (verbatim); ADR 0017;
  `docs/team/PROGRESS.md` "Now"; QA `docs/qa/QA_REPORT_TASK043.md` (46 checks: 44 pass, 2 Low bugs, both
  fixed); design review `docs/design/REVIEW_TASK043.md` (NEEDS REVISION; 7 items fixed per "Fix status").
  Owner's screenshot of 2026-11-02 (a poker site's chat box under the table): it led to the table chat
  log. The full poker-table redesign is TASK-045.
- **PM's own checks (read-only):**
  - Web vitest **139 passed** (26 files), re-run 2026-11-02.
  - Backend tests not run (shared testing DB). `tests/Feature/Gameplay/ReactionsTest.php` has 9 cases.
  - Read `ReactionCatalog` (12 categories, 4 message groups, 21 messages), `RoomReactions`
    (`OPEN_AFTER_END_MINUTES = 10`, `KEEP = 50`) and the settings catalog (`reactions.enabled`).
  - No dependency changes in `package.json` or `composer.json` since 491b624.

## Verdict: **ACCEPT WITH FOLLOW-UPS**

Every brief requirement is Met except the "relevant social areas" part of §1, which is Partly met: there is
no social area outside the tables yet. Real-time delivery between real users was proven by QA with
isolated sessions, in all five games, at 390 and 1440, with Reverb down and up.

The follow-ups are about process, not function:
- The design review's 7 fixes (54a3b6b) were checked only by the developer: live for the bubbles and the
  chat bar, and by code reading for the 44 px targets. Neither the UI/UX agent nor QA re-ran.
- The chat moved from under the board to a bar above it after QA.
- A short re-review and smoke test close this → TASK-049.

The decisions taken without the owner are sound for a launch. Three of them should be confirmed by the
owner (§B).

## A. Requirement by requirement

### §1 Emoji system
| Requirement | Status | Evidence |
|---|---|---|
| Emoji button in the chat/game interface | Met | 😊 in the table chat bar (54a3b6b); Reactions.test › "sends an emoji to the opponent; it flies from your seat towards theirs" |
| During gameplay **and relevant social areas** | Partly | Available at the table while waiting, playing and for 10 minutes after the end (QA 6, 7). There is no other social area yet (no lobby chat, friends or profile interactions), so nothing else to add it to. Revisit with friends (B4, owner) |
| Large collection in the 12 named categories | Met | `ReactionCatalog`: smileys, reactions, funny, love, celebration, gaming, poker, victory, animals, food, sports, popular. QA 8: 200 emojis; ReactionsTest › "serves the catalog: twelve emoji categories and the quick messages" |
| Modern, easy, responsive picker | Met | Design review "What works": bottom sheet on phones, 400 px panel on desktop, 44×44 targets, category jump row |
| Recently used and search | Met | QA 8: recents first after a send; search in en/ru/hy, plurals fixed (Reactions.test › "finds emojis by a plural word too") |

### §2 Quick messages
| Requirement | Status | Evidence |
|---|---|---|
| Predefined messages with one click; every example from the brief | Met | 21 messages incl. all 15 examples (QA 8); one-tap chips in the chat; Reactions.test › "sends a quick message with one tap…" |
| Organised in categories | Met | 4 groups: greetings, praise, fun, after the game |
| No typing | Met | The server accepts only catalog keys; free text gets 422 (QA 6) |

### §3 Animated emoji reactions
| Requirement | Status | Evidence |
|---|---|---|
| Travels from X's position to Y's; direction is clear | Met | QA 1: start and landing keyframes measured on the anchors, every game, both widths, on every viewer's screen (e.g. Poker 1440 coordinates). Note QA O-2: in Chess, Checkers and Nardi the seats are the player-list chips, so flights are short hops. Accepted by design; TASK-045 and later layouts will give longer paths |
| Lands near the avatar, then disappears smoothly | Met | Design review: arc, scale 0.4 → 1.6, bounce, rise away; reduced motion shows it still |
| Fluid, no performance cost | Met | QA 10: no long tasks; transforms and opacity only |
| Both directions (A→B ❤️, B→A 😂) | Met | QA 1 table, all rows |

### §4 Quick message animations
| Requirement | Status | Evidence |
|---|---|---|
| Small floating bubble with the sender's identity | Met | QA 2 (name in gold); design review ≥ 13:1 contrast |
| Animated toward the receiver when appropriate | Met | QA 1 "Quick message" column; "to everyone" pops over the sender (QA 3) |
| No overlap; doesn't obstruct game elements | Met (developer-verified) | QA BUG-1 fixed (2967f6d). Design issue 1 (clocks, deck, trump covered) fixed in 54a3b6b: beside the seat, at most 2 per seat, seats out of view get none. Live checks by the developer only → TASK-049 |

### §5 Multiplayer and real time
| Requirement | Status | Evidence |
|---|---|---|
| Real-time delivery, using the existing architecture | Met | `room.reaction` on each player's private `user.{id}` channel, like room updates (ADR 0017); QA 1 |
| Everyone sees the same reaction | Met | QA 1: identical chat logs on all screens. Design-review observation: the logs are sorted by the server sequence |
| Simultaneous reactions | Met | QA 4: 10 from 3 players, each shown once on 3 pages; Reactions.test › "shows several reactions arriving at once" |
| No duplicate events | Met | `client_id` dedupe (QA 4; ReactionsTest › "delivers a repeated request once"); double-click fixed (Reactions.test › "sends a double-clicked quick message once") |
| Works for all players in the room | Met | QA 1 (3-seat Durak and Poker), QA 3 (bot seats) |
| Only players allowed by the game rules | Met | QA 6: non-player 403, finished > 10 min 409, admin switch off 403; ReactionsTest › "only lets the room's own players react, and only while it is open" |

### §6 UI/UX
| Requirement | Status | Evidence |
|---|---|---|
| Beautiful, smooth, mobile- and desktop-friendly picker | Met | Design review "What works"; QA 9 ru/hy at 390 |
| Lightweight | Met | No new dependencies (PM check); Web Animations only |
| Doesn't interfere with cards, chips, buttons or controls | Met (developer-verified) | `pointer-events: none` layer (QA 2); bubble placement fixed (see §4); 44 px targets fixed by classes, not re-measured → TASK-049 |
| Intuitive and fun; the chat visible while playing | Met (developer-verified) | Design issue 2 fixed: a chat bar between the header and the board (54a3b6b). See §B on its placement |

### §7 Technical
| Requirement | Status | Evidence |
|---|---|---|
| Analyse the architecture, reuse the infrastructure, modular, no needless dependencies | Met | ADR 0017: same channels and limiter pattern, Redis game store, `features/reactions` module, `data-seat-anchor` hooks in each layout; no dependencies added |

### §8 Testing
| Scenario | Status | Evidence |
|---|---|---|
| A→B, B→A, simultaneous, quick messages, 3+ players | Met | QA 1–4 |
| Reconnection and network interruptions | Met | QA 5: reload, away and back, Reverb stopped (polling 1.5 s), Reverb restarted |
| Mobile and desktop, positions and directions | Met | QA 1 at 390 and 1440, measured |
| No duplicates; no performance regressions | Met | QA 4, 10 |
| Review, fix and summary of files | Met | IMPLEMENTATION_REPORT (TASK-043), fix tables in QA and the review; the final owner report follows this validation (PROGRESS step 4) |

## B. Decisions taken without the owner

| Decision | PM judgement | Owner? |
|---|---|---|
| **No free-text chat**: catalog emojis and predefined messages only | Right for now. The brief asked only for emojis and predefined texts. Free text needs moderation (word filters in en/ru/hy, reports, mutes, admin review, retention rules) that doesn't exist | **Question 9** |
| **Ephemeral history**: last 50 per room for 3 hours in Redis, not in MySQL | Fine. Enough for a reload or rejoin; nothing to moderate or keep. History is gone after 3 h or a Redis flush. PROGRESS "Now" still says "a 2-minute buffer", which is stale; ADR 0017 is correct | No; noted in question 9 if free text comes |
| **Open for 10 minutes after the game** (constant `OPEN_AFTER_END_MINUTES`) | Fine: room for "GG" and "Rematch?" without endless chatting in dead rooms. It's a constant, not an admin setting; acceptable | No |
| **Rate limits** 3/s and 40/min per player | Fine: bursts pass, floods stop (QA 4), and the UI shows a friendly message | No |
| **Admin switch** `reactions.enabled`, global and audited | Fine as a kill switch. But there is no per-player control: a player can't silence one opponent's taunts (catalog includes 🤡, 😈, "Haha!", "Better luck next time!"), and staff can't stop one abusive player except by banning. Mute today hides all animations, not one person's lines | **Question 10** (taunts); per-player mute as follow-up TASK-050 |
| **Chat bar above the board** (54a3b6b), instead of a panel under the table as in the owner's screenshot | Good for usability: the review found the under-board panel was never visible while playing. It still differs from the owner's reference, and TASK-045 will redo the poker table | **Question 11** |

## C. Follow-ups (PRODUCT_BACKLOG.md)

| Item | Where |
|---|---|
| UI/UX re-review of the 7 fixes and a QA smoke test of 54a3b6b, incl. measured 44 px targets and the new chat bar in all 5 games at 390/1440 | TASK-049 (new, P2) |
| Mute one player (local, hides their flights and bubbles; chat lines collapsed) | TASK-050 (new, P2, frontend only) |
| Free text, taunting content, chat placement | Owner questions 9–11 |
| Stale "2-minute buffer" line in PROGRESS "Now" | for the main session to correct when it updates PROGRESS |
