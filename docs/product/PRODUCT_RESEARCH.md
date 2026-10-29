# Product research: game-platform

Phase 1 (Discovery) · PM Agent · 2026-10-29 · status: RESEARCH
Labels: **Obs** = Observed on the page, **Doc** = Documented (official help/docs), **Rep** = Reported
(third party / forum / store excerpt), **Inf** = Inferred. Source IDs `[Sn]` resolve in §7; every source
was checked on 2026-10-29. "(excerpt)" = seen only through a search-result excerpt, page not fetched.
No user counts or statistics from competitors are used as evidence.

## 1. Summary

- Core play loop is solid (5 games, matchmaking, bots, private rooms, timers), but **nothing brings a
  player back after a game ends**: no rematch, no rating, no leaderboard, no replay, no friends, no
  progression. Every competitor checked has at least ratings + leaderboard [S2][S9][S16][S20].
- **Nardi ships short nardi only** (`NardiRules.php:6`); long nardi, the variant the ru/hy audience is
  offered everywhere else [S16][S17][S18], is missing. Durak lacks perevodnoy [S20][S22][S23].
- **The sound toggle is UI-only** (saved, never played) — breaks the "no UI-only features" rule → P0.
- Top 5 opportunities: OPP-001 rematch/"play again" · OPP-002 long nardi (+ match play) ·
  OPP-003 ratings + leaderboards (owner approval) · OPP-004 game-over → replay/move review ·
  OPP-005 sound effects (or remove the toggle) — then durak perevodnoy (OPP-006) and invite links (OPP-007).

## 2. Product inventory (verified with Grep/Read on 2026-10-29)

Corrections to the orchestrator brief: (a) Nardi is **short** nardi, single game (mars = 2 points,
`NardiEngine.php:141-143`); `points_to_win: 1` in `GameModeSeeder.php` is not read by the engine.
(b) **`GET /rooms/{id}/moves` does not exist** in `apps/api/routes/api.php` (only listed in
`docs/ARCHITECTURE.md` §5 — doc drift); moves are stored in `game_moves` but not exposed.

### Confirmed
| Area | What ships | Reference |
|---|---|---|
| Games | Nardi (short), Chess, Checkers (Russian), Durak (podkidnoy, 36 cards, 2–6p), Poker (NL Hold'em freezeout, 1000 chips, 5/10 blinds doubling every 10 hands, 2–6p) | `apps/api/app/Games/*/Engine`, `database/seeders/GameModeSeeder.php`, `PokerEngine.php:18,45` |
| Modes / clocks | Chess Blitz 5+3, Rapid 10+5; turn timers nardi 60 s, checkers 60 s, durak 45 s, poker 30 s | `GameModeSeeder.php` |
| Chess specifics | draw offers, promotion, SAN move list | `ChessEngine.php` (`offer_draw`) |
| Matchmaking | ticket queue per mode, heartbeat, bot fallback (auto/offer/off) | `routes/api.php` (matchmaking group), `MatchmakingStatus.tsx` |
| Bots | per-game bots, Easy/Medium/Hard chosen by the player | `app/Games/*/Bot`, `app/Enums/BotDifficulty.php`, `PlayPanel.tsx:43,175` |
| Private rooms | create room, invite code, join by code, copy code button | `POST /rooms`, `POST /rooms/join`, `WaitingTable.tsx:99` |
| Leave / abandon | leave endpoint; away > 15 s loses (admin setting) | `POST /rooms/{room}/leave`, `SettingsCatalog.php:49` |
| Accounts | email or phone + 6-digit code, login, reset; **verified contact required to play** | `routes/api.php` (`verified-contact` middleware) |
| Profile | avatar, bio, country; public profile with per-game stats | `/u/:username`, `PublicUserResource.php` |
| History / stats | finished games list; played/wins/losses/draws/current & best streak per game | `GET /me/games`, `GET /me/stats`, `GameStatResource.php`, `HistoryPage.tsx` |
| Active games | in-progress games bar | `ActiveGamesBar.tsx`, `GET /me/rooms` |
| Rules | static translated rules text per game | `GamePage.tsx:83-85` |
| i18n | en / ru / hy | `apps/web/src/shared/i18n/locales/*.json` |
| Admin | Filament: users, moderation, settings, bots, pages, audit log | `app/Filament`, `ARCHITECTURE.md` §9 |

### Partial
| Area | State | Reference |
|---|---|---|
| Rating | `user_game_stats.rating` column (default 1200) "Reserved for ratings (post-MVP)"; never written or shown | `migrations/2026_10_27_300000_create_game_rooms_tables.php:91-92` |
| Sound | preference saved and labelled "Play sounds during the game" (ru); **no audio code** | `PreferencesForm.tsx:41-50`; grep `Audio|.mp3|.ogg` = 0 |
| Move history | `game_moves` stored (append-only) and used to rebuild state; no read endpoint, no viewer | `GameMove.php`, `RoomStates.php`; routes grep = 0 |
| Streaks | computed and returned by the API; not rendered anywhere in the SPA | grep `current_streak` in `apps/web/src` → tests only |
| Online count | `GET /stats/online` exists; SPA never calls it (only in `schema.d.ts`) | `OnlineController.php` |
| Invites | code only; no shareable URL / deep link, no Web Share | `WaitingTable.tsx:99` |
| Nardi match | `points_to_win` config present, engine plays one game | `GameModeSeeder.php`, `NardiEngine.php:134-143` |
| Poker hand recap | previous hand's showdown kept in state (`last_hand`), no hand history | `PokerEngine.php:27-29` |

### Missing (grep = 0 in `apps/web/src` and `apps/api/app`)
Rating calculation, leaderboards, rematch, friends, chat/emotes, spectating, replay viewer, analysis,
puzzles, achievements/badges, interactive tutorials, notifications UI, sound playback, guest play,
PWA manifest/install (`apps/web/public` has only `favicon.svg`), long nardi, nardi doubling cube/match
play, durak perevodnoy / deck-size / throw-in options, checkers variants other than Russian, chess
time controls other than 5+3 / 10+5, poker sit-out / rebuy / hand history.

## 3. Competitor analysis

### 3.1 Chess — Lichess, Chess.com
| Feature | Competitor | What they do | Label | Src |
|---|---|---|---|---|
| Access | Lichess | "No registration, no ads, no plugin required" | Obs | S2 |
| Ratings | Lichess | Glicko-2, start 1500 ±1000, "?" while provisional | Doc | S2 |
| Leaderboard | Lichess | needs ≥30 rated games, a game in the last week, top 10 | Doc | S2 |
| Time controls | Lichess | UltraBullet → Correspondence; 8 variants | Doc | S1 |
| Analysis | Lichess | Stockfish analysis board, "Learn from your mistakes", PGN import/export | Doc | S1 |
| Post-game review | Chess.com | Game Review: move labels (Best…Blunder), coach "key moments", Retry best move; 1 free/day, unlimited for Platinum/Diamond | Doc | S4 |
| Learning | Lichess | Learn (basics, practice, coordinates), tactical puzzles, Puzzle Streak/Storm/Racer | Doc | S1, S3 |
| Social | Lichess | friends, messaging, teams, challenges, TV, tournaments (Arena/Swiss) | Doc | S1 |
| Fair play | Lichess | frequent abort/leave → temporary "play ban", escalating | Doc | S2 |
| Rematch | Chess.com | users describe a Rematch button after games; app requests one after bot games | Rep | S6 |
| Sound | Lichess | game sounds; FAQ explains browser autoplay block and mute icon | Doc | S2 |
| Mobile | Lichess / Chess.com | native iOS/Android apps | Doc/Obs | S1, S5 |
| Bots page | Chess.com | not verified (page body not rendered to fetch) | — | — |

### 3.2 Checkers / draughts — Lidraughts, Board Game Arena, PlayOK
| Feature | Competitor | What they do | Label | Src |
|---|---|---|---|---|
| Variants | BGA | International (10×10), Straight, Brazilian, Russian (flying kings, must capture but not max) | Doc | S8 |
| Variants | PlayOK | "russian checkers", turkish, tanzanian, draughts, draughts 100 | Obs | S12 |
| Play | Lidraughts | create game, play with a friend, play with the computer | Obs | S7 |
| Learning | Lidraughts | Learn: puzzles, practice, coordinates, study; puzzle of the day | Obs | S7 |
| Analysis | Lidraughts | analysis board, board editor, import game | Obs | S7 |
| Watch | Lidraughts | TV, current games, broadcasts | Obs | S7 |
| Ratings | BGA | Elo, start 1500, K=60 first 30 games; ranks Beginner→Master; quitting = last-place loss −10 | Doc | S9 |
| Ratings | Lidraughts | leaderboard with ratings on home page | Obs | S7 |

### 3.3 Nardi / backgammon — Yandex Games nardy, OK.ru nardy, PPNards, Backgammon Galaxy, PlayOK
| Feature | Competitor | What they do | Label | Src |
|---|---|---|---|---|
| Long + short | Yandex Games "Нарды Длинные и Короткие" | both variants; live opponents only | Obs | S16 |
| Long + short | OK.ru "Длинные и короткие нарды" | both in title; "Контроль честности" (fairness control) claim | Obs | S17 |
| Variants | PPNards (iOS) | Long, Short, Crazy, Khachapuri; Armenian language added | Obs | S18 |
| Short only | PlayOK ru/nardy | page offers only "Короткие Нарды" | Obs | S14 |
| Ratings | Yandex nardy | "Рейтинги и Топы игроков" | Obs | S16 |
| Private | Yandex nardy | private tables with password | Obs | S16 |
| Chat | Yandex nardy / PPNards | "3 вида чата"; "Playful Chat Options" | Obs | S16, S18 |
| Achievements | Yandex nardy | "система наград и достижений" | Obs | S16 |
| Friends/clubs | PPNards | invite friends, own social club, championship | Obs | S18 |
| Match play | Backgammon Galaxy | "Match Play" menu item | Obs | S15 |
| Doubling cube | PlayOK backgammon | "doubling cube option" | Obs | S13 |
| Analysis | Backgammon Galaxy | "Review every move with a world-class engine"; stats: rating, error rate, luck, rivals | Obs | S15 |
| Bots | Backgammon Galaxy | "Bots from beginner to expert" | Obs | S15 |
| Lessons | Backgammon Galaxy | rules + strategy lessons | Obs | S15 |
| Armenian UI | Backgammon Narde Online (iOS) | lists Armenian, Russian; Narde + Backgammon; password private games | Rep (excerpt) | S19 |
| Monetization (context only) | PPNards, Narde Online | diamond packs; "free credits several times a day" | Obs/Rep | S18, S19 |

### 3.4 Durak — Durak Online (R-Soft), Durak Online (Raay, Steam), blacksreds.com
| Feature | Competitor | What they do | Label | Src |
|---|---|---|---|---|
| Variants | R-Soft | podkidnoy, perevodnoy, "с шулерами" (cheating, flagged by players), "ничья" (draw) | Obs | S20 |
| Deck | R-Soft / Raay | 24, 36 or 52 cards | Obs | S20, S22 |
| Table rules | Raay | cheating on/off, neighbours-only vs all attack, throw-ins on/off, normal/fast timer | Obs | S22 |
| Players | R-Soft / Raay | 2–6 players; Raay offline with 1–3 bots | Obs | S20, S22 |
| Rating | R-Soft | rating per win → leaderboard and seasonal competition | Obs | S20 |
| Ranked | Raay | "Ranked with elo… a few weeks after release" (planned at listing time) | Obs | S22 |
| Social | R-Soft | friends, chats, gifts, achievements board, emoji | Obs | S20 |
| Private | R-Soft | password-protected private games | Obs | S20 |
| Variants | blacksreds | Durak, Transfer Durak, Durak two-against-two; "free and without registration" | Obs | S23 |
| Cosmetics | Raay | decks, card backs, backgrounds unlocked over time | Obs | S22 |
| Monetization (context) | R-Soft (2015 review) | in-game currency, daily free top-up | Rep | S21 |

### 3.5 Poker (play money) — PokerStars Play, Replay Poker
| Feature | Competitor | What they do | Label | Src |
|---|---|---|---|---|
| Formats | PokerStars Play | Hold'em, Omaha, Stud, Spin & Go, Sit & Go, MTT, heads-up | Obs | S24 |
| Refill | PokerStars Play | "15000 FREE chips every 4 hours" (+ paid chip bundles — context only) | Obs | S24 |
| Challenges | PokerStars Play | Challenges with rewards; leaderboards; table chat | Obs | S24 |
| Levels/badges | Replay Poker | levels + titles and achievement badges on profile (some secret) | Doc (excerpt) | S25 |
| Sit out | Replay Poker | disconnected players auto sit out (fold/check, still post blinds) | Doc (excerpt) | S26 |
| Rebuy | Replay Poker | tournament rebuy period; ring-game rebuy | Doc (excerpt) | S25, S26 |
| Play money only | Replay Poker | play-money site, no real-money games | Rep (excerpt) | S27 |
| WSOP social | — | not verified (not researched within budget) | — | — |

### 3.6 Platform level — Board Game Arena, PlayOK, Pogo, Lichess
| Feature | Competitor | What they do | Label | Src |
|---|---|---|---|---|
| Multi-game lobby | PlayOK | ~40 games, rooms, rankings, "extensive stats", profiles, contact lists, private messaging, game records, mobile, tournaments | Obs | S12 |
| Guest play | PlayOK / Lichess / blacksreds | guest login option / no registration | Obs | S13, S2, S23 |
| Reputation | BGA | reputation = opinions + % games finished + % without clock penalty; Karma 0–100 | Doc (excerpt) | S10 |
| Clock penalty | BGA | out of time → opponents may skip your turns | Doc (excerpt) | S11 |
| Abandon & rating | BGA | quitting = last-place loss −10 Elo; abandoned games excluded | Doc | S9 |
| Achievements | Pogo | badges from challenges/events, albums on profile (Club Pogo membership) | Rep (excerpt) | S28 |
| Accessibility | Lichess | blind mode, keyboard shortcuts, zen mode | Doc | S1, S2 |
| Pogo / BGA FAQ / PlayOK help | — | not verified (JS-only page, 404) | — | — |

## 4. Opportunities

Dependencies key: **FE** frontend only · **BE** needs backend · **OA** needs owner approval (pm-agent.md).

### 4.1 Evidence-backed

| ID | Feature | User problem | Evidence | Benefit | Business / retention value | Cx | Deps | Risks | Pri | Success metric |
|---|---|---|---|---|---|---|---|---|---|---|
| OPP-001 | Rematch / "Play again" on game-over (same opponent, sides swapped; vs bot: same bot + difficulty) | After a game the only path is back to the lobby | S6 (Rep), S22 settings carry over (Inf) | one tap to keep playing | longer sessions, games/session | M | BE (create room from finished room, both accept; bot path simpler) | double-click races (S6 reports abandon bug); multi-seat card tables | P1 | % finished games followed by another game within 2 min |
| OPP-002 | Long nardi variant as a second nardi mode | ru/hy players expect long nardi; we offer short only | S16, S17, S18 (both variants offered); S14 (PlayOK short only → gap) | matches audience habit; differentiator vs PlayOK | acquisition in the core ru/hy market | L | BE (new engine + bot) | rules ambiguity (blocking 6 in a row, first-move head rule) needs a written spec; bot quality | P1 | share of nardi games in long mode; nardi games/week |
| OPP-003 | Per-game/mode rating + leaderboard | wins/losses have no meaning; no goal | S2 (Glicko-2), S9 (BGA Elo), S16, S20 | visible progress, fairer matchmaking | primary retention loop on every competitor | L | BE + OA (new domain; column reserved) | bots and private games must be unrated; abandon handling (S9); smurfing | P1 | D7 retention of rated vs pre-launch cohort |
| OPP-004 | Post-game move list + step-through replay (chess, checkers, nardi first) | can't see how a game was won or lost | S1, S4, S7, S12 (game records), S15 | learning, sharing | engagement after loss | M | BE (read endpoint for `game_moves`, redacted for hidden info); doc drift fix | card games leak hidden info if not redacted | P2 | % finished games where replay opened |
| OPP-005 | Sound effects (move, capture, dice, card, your turn, low time) — or hide the toggle until they exist | toggle promises sound that never plays | repo rule (CLAUDE.md "UI-only"); S2 (sound is standard) | turn awareness when tab not focused | fewer timeouts | S–M | FE; asset licence (OA if third-party source) | browser autoplay block (S2); asset licensing | **P0** (rule breach) | timeout losses per 100 games; toggle usage |
| OPP-006 | Durak perevodnoy + table options (24/36/52 deck, neighbours-only, throw-in limit) | only podkidnoy; the usual alternative is missing | S20, S22, S23 | expected variant set | durak retention | M–L | BE (engine + bot) | 6-player transfer edge cases | P1 | share of durak games in perevodnoy |
| OPP-007 | Shareable invite link (`/join/<code>`, Web Share on mobile) | friend must type a code | S16, S20 (private tables are standard); link form Inf | fewer steps to a friend game | viral acquisition | S | FE (+ route; join endpoint exists) | code guessing → keep rate limits | P1 | private rooms that start a game / created |
| OPP-008 | Show streaks + best streak + win rate on dashboard/profile/history | stats exist but are hidden | API already returns them (inventory); S15 (stats pages) | free progress signal | low-cost engagement | S | FE | none | P2 | profile/history views per user |
| OPP-009 | Nardi match play to N points (3/5/7), later doubling cube | single game ends abruptly; no comeback arc | S13, S15, S19 | longer, deeper nardi sessions | session length | M | BE (`points_to_win` exists but unused) | cube adds UI complexity; timer per game vs match | P2 | avg games per nardi room |
| OPP-010 | "How to play" interactive tutorial per game (guided first game vs Easy bot) | rules page is a wall of text; new players lose to timers | S1, S3, S7, S15 | faster first win | activation | M per game | FE mostly; BE if scripted positions needed | 5 games × 3 locales of copy | P2 | first-game completion rate for new accounts |
| OPP-011 | Abandon/fair-play signal (completion % on profile; temporary queue cooldown for serial leavers) | leavers ruin games (15 s rule ends them) | S2 (play bans), S9, S10 | better match quality | trust | M | BE + OA (moderation change) | false positives from disconnects | P3 | abandon rate per 100 games |
| OPP-012 | Friends list + direct challenge | can't find previous opponents | S1, S12, S18, S20 | social re-engagement | retention | L | BE + OA (new domain) | privacy, blocking, moderation | P2 | % users with ≥1 friend; friend games/week |
| OPP-013 | Preset quick chat / emoji reactions at the table (no free text) | tables feel silent, esp. durak/poker | S16, S18, S20, S24 | social feel without moderation load | session length | M | BE (broadcast only) + OA (chat domain) | spam/abuse → rate limit, mute | P2 | % games with ≥1 reaction |
| OPP-014 | Spectating / "watch live games" | nothing to do while waiting; no showcase | S1, S7 | discovery, learning | engagement | L | BE + OA; hidden-info redaction | card games need delayed/seatless view | P3 | spectator sessions/day |
| OPP-015 | Chess/checkers puzzles + engine analysis | no practice mode between games | S1, S4, S7 | improvement loop | daily return | L | OA (third-party engine e.g. Stockfish; puzzle source) | licensing, compute cost | P3 | daily puzzle solvers |
| OPP-016 | Poker: sit-out + rejoin/next-table with fresh per-table play chips | busting ends the session; disconnect costs blinds silently | S24, S25, S26 | keeps poker players at tables | poker retention | M | BE + **OA (touches chips)** | must stay non-transferable, no carry-over, no value | P3 | poker games per poker player/week |

### 4.2 Hypotheses to validate
| ID | Hypothesis | Basis | How to validate |
|---|---|---|---|
| OPP-017 | Guest play vs bot (no account) increases activation; registration only for human games | S2, S13, S23 offer no-registration play; repo requires verified contact for any game (`verified-contact`) | funnel: landing → register → verify → first game; A/B guest bot game vs current. **OA** (auth model) |
| OPP-018 | PWA install (manifest, icons, offline shell) raises mobile return visits | competitors ship native apps (S1, S5, S15); we have no manifest | measure mobile share of sessions first; then install prompt acceptance |
| OPP-019 | Showing "N players online" (endpoint exists) increases matchmaking starts | S12 shows players online on home | A/B landing/lobby with live count; matchmaking starts per visit |
| OPP-020 | Achievements/badges (first win per game, streaks, all 5 games played) drive cross-game play | S16, S20, S25, S28 | after OPP-008: count users who play ≥2 games; **OA** (new domain) |
| OPP-021 | More chess time controls (bullet 1+0, 3+2, 15+10) increase chess volume | S1 | queue wait times per mode; requests via feedback |
| OPP-022 | Durak 2×2 team mode | S23 | survey/usage after OPP-006 |

## 5. Per-game improvement notes (each re-checked as missing in the repo)

**Nardi (short only today).** 1) Long nardi mode (OPP-002) — top priority for the ru/hy audience.
2) Match play to N points; `points_to_win` is seeded but ignored (OPP-009). 3) Doubling cube — only
after match play, short variant only (S13, S15). 4) Replay of dice/moves (OPP-004). 5) Fair dice is a
recurring trust theme (S17 advertises "Контроль честности"; store reviews of other apps complain about
dice — Rep, not cited as fact): consider a short public "how dice are generated (CSPRNG)" note — Inf.

**Chess.** 1) Rematch with colours swapped (OPP-001). 2) Post-game move list/PGN download and
step-through (OPP-004); SAN list already exists in-game, so PGN export is cheap once moves are readable.
3) More time controls (OPP-021). 4) Puzzles/analysis need an engine → owner decision (OPP-015).
5) Premoves and takebacks vs bot — not verified at competitors this round; hypothesis only.

**Checkers.** 1) Rematch (OPP-001). 2) Replay (OPP-004). 3) Variants: International 10×10 and
Brazilian are standard elsewhere (S8, S12) but low priority for this audience (Inf) — P3.
4) Puzzles exist on Lidraughts (S7) — P3.

**Durak.** 1) Perevodnoy (OPP-006). 2) Table options: deck 24/52, neighbours-only, throw-in limits,
fast timer (S22). 3) Quick emotes (OPP-013) — card tables rely on them (S20, S21). 4) Teams 2×2 (OPP-022).
5) "Cheating" mode (S20, S22) — conflicts with server-as-authority design; not recommended (Inf).

**Poker.** 1) Sit-out button + auto sit-out on disconnect instead of silent check/fold (S26) — BE.
2) After busting: "join a new table" with fresh per-table chips; never refills of a balance (OPP-016, OA).
3) Hand history of the last hands (state keeps only `last_hand`). 4) Show blind level and hands to next
level (blinds double every 10 hands, `PokerEngine.php:18,45`) — FE if exposed in the view; verify.
5) Do **not** copy PokerStars chip bundles / timed free-chip collection (S24) — project rule.

## 6. Decisions needed from the owner

1. **Ratings domain (OPP-003):** approve ratings + leaderboards? Which system (Elo vs Glicko-2), which
   modes rated, bots/private rooms unrated?
2. **Long nardi (OPP-002):** confirm it should be the next engine and which rule set (Armenian/Russian
   long nardi — please name a reference) before the developer starts.
3. **Social domains:** friends (OPP-012), preset chat/emotes (OPP-013), spectating (OPP-014) — approve
   any? Free-text chat stays out unless you decide otherwise.
4. **Sound (OPP-005):** implement sounds now or hide the toggle until then? Approve a sound asset source
   (self-made vs licensed pack).
5. **Guest play (OPP-017):** allow bot games without an account? (Avoids SMS cost; changes auth model.)
6. **Poker after bust (OPP-016):** is "join a new table with fresh per-table chips" acceptable under the
   recreational rule? No balances, no carry-over.
7. **Engine/third-party (OPP-015):** allow Stockfish (GPL) or another engine for chess analysis/puzzles?
8. **Fair-play (OPP-011):** allow queue cooldowns for serial leavers (moderation behaviour change)?
9. **Docs:** `ARCHITECTURE.md` §5 lists `GET /rooms/{id}/moves`, which does not exist — fix the doc or
   build the endpoint (part of OPP-004)?

## 7. Sources (all checked 2026-10-29)

| ID | URL | What / how |
|---|---|---|
| S1 | https://lichess.org/features | feature/plan table — fetched |
| S2 | https://lichess.org/faq | ratings, leaderboard, play bans, sound, "No registration" — fetched |
| S3 | https://en.lichess.org/blog/V5EX3CgAAIoE7aBR/something-for-the-beginners | Learn section — excerpt |
| S4 | https://www.chess.com/terms/game-review | Game Review features and limits — fetched |
| S5 | https://www.chess.com/about | Learn/Puzzles nav, mobile apps — fetched (thin) |
| S6 | https://www.chess.com/forum/view/help-support/rematch-from-game-report ; https://www.chess.com/forum/view/general/instant-bot-rematch-option-on-app | rematch user reports — excerpt (Rep) |
| S7 | https://lidraughts.org/ | home page — fetched |
| S8 | https://en.doc.boardgamearena.com/Gamehelpcheckers | checkers variants — fetched |
| S9 | https://en.doc.boardgamearena.com/Rating | Elo (page marked deprecated) — fetched |
| S10 | https://ca.doc.boardgamearena.com/Reputation ; https://en.boardgamearena.com/news?id=304 | reputation, karma — excerpt |
| S11 | https://en.doc.boardgamearena.com/Game_clock | clock penalty — excerpt |
| S12 | https://www.playok.com/en/ | game list and features — fetched |
| S13 | https://www.playok.com/en/backgammon/ | doubling cube, guest, rankings — fetched |
| S14 | https://www.playok.com/ru/nardy/ | short nardy only — fetched |
| S15 | https://www.backgammongalaxy.com/ | match play, analysis, bots, lessons — fetched |
| S16 | https://yandex.ru/games/app/98425 | long + short nardy, ratings, private tables, chat, achievements — fetched |
| S17 | https://ok.ru/game/nard_gammon | long + short nardy on OK — fetched |
| S18 | https://apps.apple.com/us/app/-/id1595129186 | PPNards variants, clubs, Armenian — fetched |
| S19 | https://apps.apple.com/us/app/-/id1144538463 | Backgammon Narde Online — excerpt (Rep) |
| S20 | https://www.rustore.ru/catalog/app/com.rstgames.durak.rustore | Durak Online (R-Soft) — fetched |
| S21 | https://www.iphones.ru/iNotes/durak-online-ios-game-review | 2015 review of Durak Online — fetched (old) |
| S22 | https://store.steampowered.com/app/3291790 | Durak Online (Raay) table options — fetched |
| S23 | https://blacksreds.com/ | durak variants, no registration — fetched |
| S24 | https://apps.apple.com/us/app/id606944207 | PokerStars Play Money — fetched |
| S25 | https://forums.replaypoker.com/t/introducing-levels-and-achievements/5029 | levels, badges — excerpt |
| S26 | https://cs.replaypoker.com/help/tournaments | sit out, rebuy — excerpt |
| S27 | https://www.pokernews.com/free-online-games/replay-poker/ | play-money only — excerpt (Rep) |
| S28 | https://forums.ea.com/discussions/pogo-general-discussion-en/how-do-you-access-your-badges-to-see-progress-/10364184 | Pogo badges — excerpt (Rep) |

Not verified (blocked or out of budget): replaypoker.com home (HTTP 403), pogo.com (JS-only),
chess.com/play/computer (no body), BGA FAQ and PlayOK help (404), "Нарды Длинные: Арена" App Store page
(404), Durak Online Google Play page (truncated), WSOP social (not researched). No competitor user
counts are used anywhere in this document.
