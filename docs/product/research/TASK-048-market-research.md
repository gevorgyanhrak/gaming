# TASK-048 · Market research for the platform redesign

PM Agent, 2026-11-09. Phase 1 of `docs/product/briefs/TASK-048-platform-redesign.md`. Input for the UI/UX agent's design system.
Every external finding was checked on 2026-11-09 unless the row gives another date.

**Evidence labels:** **Observed** (seen on the page in headless Chrome), **Documented** (official pages), **Reported** (third party),
**Inferred** (my reading). Product facts were verified in the repo and in the local app.

## Recommendation in one minute

**Direction: "The Lamplit Room."** A card club with the lights on: green baize, brass lamps, walnut rails, ivory playing cards. The
whole palette comes from objects already in the product (the home picture, the poker felt, the walnut Nardi board), so home, lobby and
tables finally read as one place.

- **The gap in the market.** The four casinos I could observe are near-black or navy with violet or neon-green accents. None uses
  emerald as a surface. An emerald room with gold and walnut is both what the owner asked for and visibly different.
- **Our edge is not money.** They sell jackpots, races and cashback. We can't and shouldn't. We sell real opponents in classic games
  the audience grew up with, a free status ladder, and a daily reason to return.
- **Five games is a strength.** Casinos need search and provider filters for thousands of titles. We show five large posters and get
  a player into a seat in as few steps as possible.
- **Brightness target:** between the casinos (mean lightness L\* 25–26) and the white look the owner rejected. Mean L\* 35–45, near-black
  at most 10% of the screen (today's home is 60%).

## Method and limits

Headless Chrome with its default user agent, public pages only, 1440×900 and 390×844: no account, no login, no clicks on any competitor.
Motion was sampled by hovering a pointer over one game card. Screenshots and computed styles were read directly. Lightness is CIE L\* over
the first-viewport screenshot (0 black, 100 white; near-black = L\* below 12).

**Could not observe, so nothing below is presented as seen:**

| Site | What happened | Used instead |
|---|---|---|
| Stake | Cloudflare "Performing security verification", HTTP 403 in Chrome, WebFetch and for `robots.txt`. Not bypassed. (The certificate error from the earlier attempt did not recur.) | Reviews, labelled Reported |
| Rollbit | Same Cloudflare page, HTTP 403. Not bypassed. | Reviews, labelled Reported |
| PokerStars (added by me as a poker reference) | HTTP 403 "Access Denied" | Dropped; Replay Poker used |
| Logged-in areas of every site | Out of bounds | Lobby chat, profiles, wallets and in-game screens of competitors are **not verified (login required)** |

Duelbits' card hover could not be sampled (no matching card found by the script). Roobet's card showed no CSS transition on the sampled element, though the page runs many animations.
Most Stake and Rollbit review sites are affiliate pages. Many search results were spam on hacked domains; I did not use those.

## 1. Competitor analysis

### 1.1 Casinos (Observed unless labelled)

| | Shuffle | BC.Game | Roobet | Duelbits |
|---|---|---|---|---|
| **Source** | [home](https://shuffle.com/), [VIP](https://shuffle.com/vip-program) | [home](https://bc.game/), [VIP](https://bc.game/vip) | [home](https://roobet.com/), [VIP](https://roobet.com/vip) | [home](https://duelbits.com/en), [VIP](https://duelbits.com/en/vip) |
| **Surfaces** | `#121418`, `#080808` | `#232626`, `#323738` | `#090C1D`, `#191939` | `#0C1B26`, `#08131B` |
| **Accent** | Violet `#7717FF` | Neon green `#2CD97D` | Violet, yellow-gold CTA `#FFCE00` | Green gradient `#1DD87F`→`#15A462` |
| **Type** | Aeonik; condensed poster display in banners | Averta | Excon | Proxima Nova |
| **Home order** | 3 promo banners → lobby tabs + search → shelves (Originals, Slots, Live, Providers, Airdrop, Game Shows, Picks, New) → tournaments → SEO text | Sign-up hero → "Recent Big Wins" ticker → Casino/Sports tiles → 5 vertical tiles → shelves | Athlete hero + sign-up → jackpot counters and win ticker → Casino/Sports tiles → shelves → raffle → wins table | Hero + sign-up → Casino/Sports tiles → prize-pool banner → shelves → VIP |
| **Desktop nav** | Persistent left sidebar, grouped; header holds login | Persistent left sidebar, accordions | Persistent left sidebar, grouped cards | Persistent left sidebar, collapsible groups with a "50" challenges badge |
| **Phone nav** | Bottom bar: Menu, Search, Chat, Rewards, Sports | Menu, Explore, Casino, Sports, Chat | Menu, Search, Casino, Sports, Chat | Menu, Search, Casino, Sports, Chat |
| **Game card** | Portrait art, title in the art, coloured top tag, 8 px radius | Portrait art, live player count on the card | Portrait art, "New"/"Exclusive" tags, 12 px | Portrait art, RTP % on the card |
| **Card hover** | Card lifts 8 px in 150 ms; a hidden button fades in | Lifts 4 px in 150 ms; overlay fades in 300 ms | Not sampled | Not sampled |
| **Player interaction** | Chat in the phone bar. Contents not verified (login required) | Chat in the header and phone bar. Same limit | Chat in the header and phone bar. Same limit | Chat in the phone bar. Same limit |
| **User profiles** | Not verified (login required) | Not verified | Not verified | Not verified |
| **Loyalty** | XP "with each bet"; tiers; "reloads when you're on a bad turn of luck" | "Instant Lossback", "Reload Bonuses", VIP transfer | Invite-only, "consistent play at higher stakes" | VIP Club, personal manager, priority withdrawals |
| **Real-money hooks on the home page** | $100K race and token airdrop with day countdowns | Win ticker in local currency | $3.9M jackpot counter, weekly raffle with timer | "$400,000+ Monthly Prize Pools", RTP badges |

**Stake (Reported):** "dark-themed, minimal, and fast"; a real-time bet feed in the sidebar; lobby filters by provider, popularity and new
releases; responsive site with PWA support, no app ([DeucesCracked](https://www.deucescracked.com/casino/crypto-casinos/stake-review), affiliate).
VIP is tracked on cumulative wagering, invite-only from 10,000 points, Bronze to Obsidian ([next.io](https://next.io/win/stake/vip/), affiliate, updated 2026-11-02, with internal inconsistencies).

**Rollbit (Reported):** "dark interface", "the layout is also clear"; filters by date, name and prize pool; mobile-optimised site, no app; a
"My Level" tab, 27 levels in seven tiers unlocked by wagering ([Sportsgambler](https://www.sportsgambler.com/review/rollbit/), affiliate, updated 2026-10-16). Other reviews disagree on the tier count.

### 1.2 Free and social platforms (closer to what we are)

| Platform | Useful pattern (Observed) | Source |
|---|---|---|
| Lichess | Live "52,196 players / 21,813 games in play" beside the play area; one-tap quick pairing grid; "Challenge a friend" and "Play against computer" are peers of matchmaking | [lichess.org](https://lichess.org/), [leaderboard](https://lichess.org/player) |
| Chess.com | Task-first nav (Play, Puzzles, Learn, Train, Watch, Community); bots with personalities; leaderboards per game type. Warm grey surface, mean L\* 35, 0% near-black | [home](https://www.chess.com/), [leaderboard](https://www.chess.com/leaderboard) |
| Pogo | "Jump to" anchors by genre; "135 Playing Now!" per game; a PLAY button on each tile | [pogo.com](https://www.pogo.com/) |
| Replay Poker | "Play for Fun", "Risk free alternative to real money poker", "Players Online: 951". The old domain redirects to casino.org | [replay](https://www.casino.org/replaypoker/) |
| Board Game Arena | Almost white (mean L\* 91): the "white is more" end of the range | [boardgamearena.com](https://en.boardgamearena.com/) |

### 1.3 Brightness, measured

| Screen (1440×900, first viewport) | Mean L\* | Near-black | Light (L\* over 70) |
|---|---|---|---|
| **Ours: `/login`** | **11** | **79%** | **0%** |
| Replay Poker | 12 | 87% | 5% |
| **Ours: `/` home** | **16** | **60%** | **3%** |
| Lichess | 17 | 37% | 3% |
| **Ours: `/games`** | **20** | **49%** | **6%** |
| Shuffle / BC.Game / Roobet / Duelbits | 25 / 25 / 25 / 26 | 57 / 6 / 47 / 55% | 13 / 6 / 10 / 9% |
| Pogo | 30 | 35% | 10% |
| Chess.com | 35 | 0% | 15% |
| **Target for us** | **35–45** | **at most 10%** | **at most 25%** |
| Board Game Arena | 91 | 0% | 91% |

*Conclusion:* the owner's "too dark" is measurable. We are darker than the casinos. Their art carries the colour; ours doesn't.

### 1.4 What actually makes them attractive (Inferred from the above)

1. **Art does the selling.** Saturated poster art on quiet surfaces; the chrome stays out of the way.
2. **One loud accent**, used only for the action.
3. **Persistent navigation** on desktop and a five-slot bottom bar on phones, everywhere.
4. **The page feels alive:** counters, tickers, badges. Most of that liveliness is money; the principle (show that people are here) is not.
5. **Fast, small feedback:** 150 ms lifts rather than long animations.

## 2. Design inspiration

Take the principle, not the look. No competitor art, logo, layout, mascot or colour scheme is to be reproduced.

| Principle | Seen at | Our original version |
|---|---|---|
| Art carries the colour | All four casinos | Own SVG posters per game on emerald, using `GameArt.tsx` as a starting point |
| Fast shelf browsing | Shuffle, Duelbits | A single row of five, no endless carousels |
| Social proof of life | Lichess, Pogo, BC.Game | Real online count and, once built, per-game counts; never invented |
| Persistent nav | All four casinos | Slim walnut-and-emerald rail with the game icons |
| Quick pairing | Lichess | "Quick seat" on each poster |
| Visible progress | Shuffle, Duelbits | Rank ladder and XP bar in the rail and on home |
| Physical luxury | The product's own room | Brass hairlines, walnut, baize texture, ivory cards in place of glass and neon |

**Motion principles:** 120–200 ms for hover and press (matches the 150 ms observed); one reward moment at a time; rewards land where the value is
stored (the existing chip flight to the header is the model); everything honours [`prefers-reduced-motion`](https://developer.mozilla.org/en-US/docs/Web/CSS/@media/prefers-reduced-motion).

## 3. Recommended colour palette

### 3.1 Resolving the owner's two signals

The brief (2026-11-02) said too dark, asked for emerald, gold, warm neutrals, and "not an entirely black site". On 2026-11-08 the owner saw large
ivory areas, said "white is more", asked for one big casino/poker picture, and kept the dark room. **These are compatible.** The owner rejected both
extremes, so the answer is a coloured mid-dark: **a real, visible emerald as the main surface, with ivory only in small objects.**

### 3.2 Proportions (share of the visible screen on any non-game page)

| Surface | Tokens | Share | Used for |
|---|---|---|---|
| **Emerald field** | `emerald-700` page, `emerald-800` panels | **55%** (about 36 + 19) | Page background, panels, forms, lists |
| **Deep green frame** | `emerald-900` | **13%** | Header, rail, footer, tab bar |
| **Walnut** (the warm neutral) | `walnut-700`, `walnut-800` | **12%** | Section bands, filter strip, rails, wood frames |
| **Ivory** | `ivory-100`, `ivory-50` | **12%** | Small objects only: poster name plates, the reward plate in a dialog, "how it works" tiles, playing cards |
| **Gold** | `gold-300`, `gold-500` | **5%** | Rewards, rank, the primary action, brass hairlines |
| **Near-black** | none as a surface | **at most 3%** | Shadows, scrims and art only |

**Hard rules:** ivory is never a full-width band, never a page background, never carries a form, and never exceeds 25% of a viewport at any width.
No token is darker than `emerald-900` or `walnut-800` as a surface. Computed mean L\* for this mix is about 40, inside the target.

### 3.3 Tokens

| Token | Hex | L\* | | Token | Hex | L\* |
|---|---|---|---|---|---|---|
| `emerald-900` | `#0E3B30` | 22 | | `ivory-50` | `#FFFAEF` | 98 |
| `emerald-800` | `#114C3E` | 28 | | `ivory-100` | `#F6EEDC` | 94 |
| `emerald-700` | `#15604D` | 36 | | `sand-300` | `#E4D5B7` | 86 |
| `emerald-600` | `#1C7A62` | 46 | | `gold-300` | `#F5D98A` | 87 |
| `felt` | `#1F8A63` | 51 | | `gold-500` | `#E2B84F` | 77 |
| `walnut-800` | `#3F2A1C` | 19 | | `gold-700` | `#8C6420` | 45 |
| `walnut-700` | `#5A3B27` | 28 | | `cream` (text) | `#FFF8E7` | 98 |
| `walnut-500` | `#8A5E3E` | 44 | | `mint` (body on green) | `#CFE4D8` | 89 |
| `ink` (text on light) | `#1E2A24` | 16 | | `claret` (decor, suits) | `#6D1530` | 24 |

**Contrast, computed** against [WCAG 2.2](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) (4.5:1 text, 3:1 large text):

| Pair | Ratio | Allowed use |
|---|---|---|
| `cream` on `emerald-700` / `-800` / `-900` | 7.05 / 9.32 / 11.76 | All text |
| `mint` on `emerald-700` / `-800` | 5.60 / 7.40 | Body text |
| `gold-300` on `emerald-700` / `walnut-700` | 5.40 / 7.26 | Small gold text |
| `gold-500` on `emerald-700` | 3.98 | **Large text and graphics only** |
| `ink` on `gold-500` (primary button) | 7.93 | All text |
| `ink` on `ivory-100` | 12.88 | All text |
| Link `#0E6B56` on `ivory-100` | 5.58 | Links on light |
| `#7FE3B0` (gain) on `emerald-700` | 4.81 | Positive Fishka amounts |
| `#FFA3AE` (loss) on `emerald-800` | 5.22 | Negative amounts. **Fails on `emerald-700` (3.95)**, so keep them on panels |
| `mint` on `emerald-600` | 3.93 | **Not for body text**; use `cream` (4.95) |
| `cream` / `gold-300` on `felt` | 4.07 / 3.11 | **Large only**; small text on felt sits on a dark plate |

**Colour jobs:** gold = reward, rank, primary action. Green = place and atmosphere. Ivory = "a physical object you can pick up". Don't spend gold on navigation.

### 3.4 How today's home page fits

**Keep the idea, relight it.** One big poker-room picture stays behind `/` and `/dashboard` (`HomeBackdrop.tsx`): same room, table, lamps, cards and chips (the owner confirmed
the chips on 2026-11-09). The picture already holds the whole palette. What makes it near-black (60%) is specific and fixable:

| Today | Change |
|---|---|
| Burgundy-to-black wall (`#1a0a10`–`#4a1420`) | Deep green panelled wall, `emerald-800` to `emerald-700`; claret as a small accent |
| Vignette to 90% black | At most 35% |
| Near-black floor (`#0c0506` at the bottom) | Dark walnut carpet with the brass pattern |
| Three lamps with weak glow | Same lamps, stronger warm pools; this is what "lit" means |
| Header `#070f0c`, panels `black/75` | `emerald-900` header, `emerald-800` at 85–90% panels so the room shows through |

Other pages don't repeat the picture; they share its colours through the emerald field. Target for the picture itself: mean L\* at least 30, near-black at most 15%.
**Game tables are the one exception** to the proportions: a darker walnut surround is right there because the board is the subject. Keep owner decisions: the walnut Nardi
board, the beige and brown Chess/Checkers boards, the oval poker felt, the rectangular Durak felt.

## 4. Recommended typography

| Role | Latin, Cyrillic | Armenian | Size / line (desktop → phone) |
|---|---|---|---|
| Display (home, reward moments, rank names) | **Noto Serif Display** 600–800 | **Noto Serif Armenian** 600–800 | 56/64 → 36/44 |
| H1 / H2 / H3 | **Noto Sans** 700 | **Noto Sans Armenian** 700 | 36/44 → 28/36, 24/32, 18/26 |
| Body | Noto Sans 400 | Noto Sans Armenian 400 | 16/24 |
| Small / caption | Noto Sans 500 | Noto Sans Armenian 500 | 14/20, 12/16 (13 minimum in hy) |
| Numbers (Fishkas, XP, clocks) | Noto Sans 700, tabular | same | as context |

**Why:** the audience reads three scripts. In [Google Fonts' catalogue](https://fonts.google.com/metadata/fonts) only 8 of 1,959 families cover Armenian, and the popular "premium" choices (Inter, Manrope, Playfair Display, Cormorant, Onest) do not.
Noto is the practical choice that gives all three scripts a shared design in both sans and serif, so Armenian isn't a fallback. (Google Sans also covers all three, but it has no serif and is strongly tied to another brand.) All four Noto families are under the [SIL OFL](https://raw.githubusercontent.com/google/fonts/main/ofl/notoserifdisplay/OFL.txt). The serif is used large and rarely; it provides the "premium" note.

**Rules:** self-host subset `woff2` files with `unicode-range` so Armenian loads only when needed, with no CDN (a new third-party service would need approval) and no new runtime dependency. Keep all-caps away from Armenian: the backlog already records hy labels running together in caps (`PRODUCT_BACKLOG.md`, TASK-027). Test every layout with the longest of en/ru/hy. Check that the Noto files keep tabular figures once subset.

## 5. Homepage redesign concept

### Guest, `/`

| # | Section | Content | Surface |
|---|---|---|---|
| 1 | **Scene hero** (first viewport) | Relit picture. Eyebrow "Free to play · No real money" stays (honest, and our differentiator). Serif headline, gold "Create a free account", secondary "Browse games" | Picture |
| 2 | **The five games** | One row of large posters, names in ivory plates, overlapping the bottom of the picture so the room leads into them | Emerald panel |
| 3 | **How it works** | Three ivory tiles: free Fishkas every day · real people or bots · classic games | Emerald field |
| 4 | **The ladder** | The five ranks as a walnut band of badges, with what each unlocks | Walnut |
| 5 | Footer | Links, language | `emerald-900` |

### Signed in, `/dashboard`

| # | Section | Content | Backend |
|---|---|---|---|
| 1 | **Player bar** over the picture | Avatar, rank, XP progress, next reward | Exists (`/me/level`) |
| 2 | **Today** | Inline daily bonus card, Collect. The popup stays but fires once a visit, as now | Exists |
| 3 | **Continue** | Live game if any, else "Play again" on the last game | Exists (`/me/rooms`, `/me/games`) |
| 4 | **Games** | Five posters with Quick seat | Exists |
| 5 | **Try next** | One game not yet played, or least played | Rule on `/me/stats` |
| 6 | **Play with a friend** | Create a private table, share the link | Exists |
| 7 | Records | Compact per-game stats, link to profile | Exists |

## 6. Navigation structure

Today, desktop navigation lives only behind a hamburger. [NN/g measured](https://www.nngroup.com/articles/hamburger-menus/) hidden menus used in 27% of desktop cases against 48% for visible ones (2016).
All four observed casinos use a persistent sidebar on desktop.

| Context | Recommendation |
|---|---|
| **Desktop, 1024 px and up** | Persistent left rail, 240 px, collapsing to 72 px icons, in `emerald-900`: Home · Games · the five games with icons · Wallet · History · Ranks. Language and Settings at the bottom |
| **Header** | Page context on the left; on the right the Fishka balance (always visible) and avatar menu. Guests: Log in, gold Sign up |
| **Phone** | Bottom bar for everyone, including guests (today signed-in only). Signed in: Home · Games · Wallet · Ranks · Profile. Guest: Home · Games · Log in. History moves under Profile. 44 px minimum targets (left open by TASK-056) |
| **At a table** | No rail or tab bar. Back, game name, stake, balance only. The live-games bar stays |
| **Search** | **Not recommended.** Five games don't need it; casinos have thousands |
| **Scale** | Add a Games submenu once there are more than about eight games |

## 7. Game discovery improvements

| Item | Now | Recommendation | Backend |
|---|---|---|---|
| Game cards | 16:10 art above a dark text block with a two-line description; page subtitle "Choose a game to read its rules". The art is good; the frame around it is not | **Poster card:** portrait crop of the existing art, ivory name plate, one line, seats (2–6), Quick seat in gold. Rules move to the game page | None. All `thumbnail_url` are null today, so art is SVG or admin uploads |
| Hover / press | Art zooms 105% over 500 ms | Lift 6 px, gold edge, 150 ms, reveal Quick seat; on touch it is always visible | None |
| Quick seat | Three steps through a long page (`/games/nardi` is 2,020 px on desktop, 3,159 on phone) | Bottom sheet with PlayPanel: last-used mode and group preselected | None |
| Game page | Controls below stats and text | Play first, then Rules and Records | None |
| Featured | A large art panel beside each shelf, with no chosen feature behind it | Hero poster picked by admin sort order | None |
| Recently played | Only as history | Row or "Continue" from `/me/games` | None |
| Recommended | None | Rule-based "Try next" from `/me/stats`. Label it as a suggestion, not personalised AI | None |
| Categories | Board / Card chips | Keep, secondary | None |
| Popular | None | "Popular this week" needs a small aggregate | **Needs backend** |
| Player counts | Site-wide only, `/stats/online` (users active in 5 min); the card's "2–6 players" is seats, not people | Show site-wide only above a threshold (e.g. 20; backlog TASK-025). Per-game "playing now" excludes bots | **Per-game needs backend** |
| Favourites | None | On-device first; syncing would need a backend field. Low value at five games | Optional |

## 8. Engagement and retention opportunities

**Reading the rule:** Fishkas are free, never bought, sold, cashed out or sent. Nothing here adds value that leaves the platform.

### 8.1 Ready now (existing backend)

| Feature | Recommendation | Priority |
|---|---|---|
| Daily reward and streak | Inline card plus existing popup. Show progress as gain ("2 days to your bonus"), never fear of loss | P1 |
| Level progression | XP bar on home, seats, game-over. "+30 XP, 50 to level 8" is the natural after-game hook | P1 |
| **Free status ladder** (brief's "VIP") | **"Club ranks"**, avoiding the word VIP. `LevelBadge.tsx` already has five tiers: Bronze 1–9, Silver 10–19, Gold 20–29, Ruby 30–49, Amethyst 50+. Name them; a ladder page shows the existing frames, badges and level rewards. No perks beyond those. Display-only | P1 |
| Animated rewards | Reuse the chip flight; extend to level-up. Celebrate **net gains only** | P1 |
| After-game next step | "Play again" as a clear choice with XP progress beside it. Float the result over the board (today it pushes the table below the fold) | P1 |
| Play with friends | Promote private tables and `/join/:code` share links | P1 |
| Recent games, Try next | §7 | P1 |
| Reactions | Keep; restyle. Free-text chat remains owner question 9 | P2 |
| Bots | Keep the visible "Bot" badge; always offer the no-wait option | P2 |

### 8.2 Needs backend (not in the redesign; owner approval per `docs/team/README.md`)

Verified absent: no leaderboard, friends, missions, achievements, event or notification code. **Do not draw these as if they work** (`CLAUDE.md`: don't present a UI-only feature as implemented).

| Feature | Recommendation | Size | Value |
|---|---|---|---|
| **Leaderboards** | First to build. Per game and weekly, ranked by XP or by wins against people. **Never by Fishka balance**, which rewards chip dumping (owner question 6) and big risks. No prizes of value. Note: `GameFinisher` records stats for every human seat and I saw no bot filter, so wins need filtering | M | High |
| **Daily missions** | "Play two games", "Try a game you haven't". Backend must validate and pay through `FishkaWallets`. Avoid "win N" missions, which push risk | L | High |
| **Achievements** | Permanent, milestone-only badges on the profile | M | Medium |
| **Friends** | List, online status, invite. Largest new domain | L | High |
| **Events and banners** | Admin-scheduled banner with real start and end. Until it exists, banners only announce things that are live | S–M | Medium |
| **Notification centre** | Persisted inbox. Until then, one quiet toast for live events | M | Low |
| Per-game counts, Popular | Small additive read endpoints | S | Medium |

### 8.3 Do not adopt

| Pattern | Seen at | Why not |
|---|---|---|
| Live big-win tickers | [BC.Game](https://bc.game/), [Roobet](https://roobet.com/) | Depends on money; manufactures envy |
| Jackpot counters | [Roobet](https://roobet.com/) | Prize of value |
| Prize races with countdowns | [Shuffle](https://shuffle.com/), [Duelbits](https://duelbits.com/en) | Prizes and countdown pressure |
| Rewards after losses (reloads, lossback) | [Shuffle VIP](https://shuffle.com/vip-program), [BC.Game VIP](https://bc.game/vip) | Loss-chasing. Keep the "Out of Fishkas" popup where it is (only when a game can't start), never at game over |
| Status bought with volume or stakes | [Stake (Reported)](https://next.io/win/stake/vip/), [Roobet VIP](https://roobet.com/vip) | Pressures higher stakes. Ours comes from playing |
| Deposit prompts, RTP badges, token prices | [Shuffle](https://shuffle.com/), [Duelbits](https://duelbits.com/en), [BC.Game](https://bc.game/) | Real-money framing |
| Refer-and-earn, affiliate programmes | [Roobet](https://roobet.com/), [Shuffle](https://shuffle.com/) | Value moving between users. Invite links without reward are fine |
| Celebrating a "win" that loses overall; autoplay; speed-ups | Banned for UK online slots in 2021 ([UK Gambling Commission](https://www.gamblingcommission.gov.uk/news/article/gambling-commission-announces-package-of-changes-which-make-online-games); current status not rechecked) | Good design rule here too: no celebration unless net positive; never auto-seat the next game |
| Streak-loss urgency | [Mobbin study of 859 streaks](https://mobbin.com/blog/streaks-are-about-losing-a-study-of-859-streak-designs) | Copy that turns urgent makes people defend a number. [Duolingo](https://blog.duolingo.com/how-duolingo-streak-builds-habit) reports that allowing slack helped without encouraging days off (owner question 12) |

**Popups:** the daily bonus and level-up remain the only ones that open themselves; never stack two, never over a table. Everything else is inline.

### 8.4 Hypotheses to validate

No analytics exists in the app, and adding a third-party one would need approval. All of these can be measured from existing tables.

| Hypothesis | Measure | Source |
|---|---|---|
| Bright poster lobby and Quick seat raise exploration | Distinct games per player per week | `game_participants` |
| Inline bonus card raises daily return | Daily bonus collections per active player | `fishka_transactions` |
| XP at game-over raises session length | Games per player per session day | `game_participants` |
| Promoting private tables raises social play | Share of rooms of type `private` | `game_rooms` |

## 9. Complete list of pages and components

**Priority for this redesign:** **P0** foundation that everything else depends on, or broken · **P1** seen most, or named in the brief · **P2** supporting · **P3** rarely seen. Admin excluded.
Seen in the local app at 1440 and 390 (`/` to `/settings`, and finished rooms of all five games). Live turn controls, waiting table and matchmaking were read from code; no game was started and no bonus collected.

### 9.1 Foundation

| Item | File | Priority | Note |
|---|---|---|---|
| Design tokens, global surfaces | `apps/web/src/styles/index.css` | P0 | Replaces `club-*` and `home-*` scopes with one system |
| Fonts | new, self-hosted | P0 | §4 |
| Button, Card, Field, Badge, Alert, Segmented | `shared/ui/*` | P0 | Primitives |
| Avatar, LevelBadge, frames | `shared/ui/Avatar.tsx`, `LevelBadge.tsx`, `frames.ts` | P0 | Rank names |
| Skeleton, States (empty, error, loading) | `shared/ui/Skeleton.tsx`, `States.tsx` | P0 | |
| Icons, Container | `shared/ui/icons.tsx`, `Container.tsx` | P1 | |
| **Modal / sheet (missing)** | new | P0 | Five separate `role="dialog"` implementations today |
| **Tooltip, Toast (missing)** | new | P1 | No shared component exists |
| Logo, favicon | config, `public/` | P1 | Purple dice, off-palette; see Decisions |

### 9.2 App shell

| Item | File | Priority |
|---|---|---|
| Layout frame, header, footer | `app/layouts/PublicLayout.tsx` | P0 |
| Left rail (new), drawer, SideNav | same | P0 |
| Phone tab bar | same | P0 |
| FishkaBalance, FishkaIcon | `features/wallet/components/` | P0 |
| UserMenu | `features/auth/components/UserMenu.tsx` | P1 |
| ActiveGamesBar | `features/play/components/ActiveGamesBar.tsx` | P1 |
| VerificationBanner | `features/auth/components/VerificationBanner.tsx` | P2 |
| LanguageSwitcher | `shared/i18n/LanguageSwitcher.tsx` | P2 |

### 9.3 Pages

| Route | File | Priority | Key change |
|---|---|---|---|
| `/` | `pages/LandingPage.tsx` | P1 | §5, relit picture |
| `/dashboard` | `pages/DashboardPage.tsx` | P1 | §5 |
| `/games` | `pages/GamesPage.tsx` | P1 | Poster lobby (closes owner question 23) |
| `/games/:slug` | `pages/GamePage.tsx` | P1 | Play first |
| `/rooms/:id` | `pages/RoomPage.tsx` | P1 | Table shell, floating result |
| `/wallet` | `pages/WalletPage.tsx` | P1 | |
| `/profile`, `/u/:username` | `pages/ProfilePage.tsx`, `PublicProfilePage.tsx` | P1 | Rank, records |
| `/login`, `/register` | `pages/auth/LoginPage.tsx`, `RegisterPage.tsx` | P1 | Emerald, first impression (`/login` is 79% near-black) |
| `/verify`, `/forgot-password`, `/reset-password` | `pages/auth/` | P2 | |
| `/history` | `pages/HistoryPage.tsx` | P2 | |
| `/settings` | `pages/SettingsPage.tsx` | P2 | |
| `/join/:code` | `pages/JoinPage.tsx` | P2 | |
| `/pages/:slug` | `pages/ContentPage.tsx` | P3 | |
| 404, route error, maintenance | `pages/NotFoundPage.tsx`, `RouteErrorPage.tsx`, `features/platform/MaintenanceScreen.tsx` | P3 | |
| **Ranks (new, frontend only)** | new | P1 | §8.1 |

### 9.4 Feature components

| Area | Components (`apps/web/src/features/…`) | Priority |
|---|---|---|
| Lobby | `catalog/components/GameGrid`, `GameLobby`, `GameArtwork`, `GameArt`, `PlayerCount` | P1 |
| Home art | `catalog/components/HomeBackdrop`, `HomeArt` | P1 |
| Play flow | `play/components/PlayPanel`, `GroupPicker`, `GroupBadge`, `MatchmakingStatus`, `WaitingTable` | P1 |
| Table | `play/components/GameOverPanel`, `PlayerList`, `RoomSummary` | P1 |
| Records | `play/components/DashboardGames`, `GameRecord` | P2 |
| Rewards | `wallet/components/DailyBonusDialog`, `OutOfFishkasDialog`, `GameResult`; `levels/components/LevelUpDialog` | P1 |
| Level | `levels/components/LevelCard`, `LevelProgress` | P1 |
| Chat and reactions | `reactions/components/TableChat`, `ReactionPicker`, `ReactionLayer`, `RoomReactions` | P1 |
| Auth | `auth/components/AuthCard`, `CodeField`, `ResendCodeButton` | P1 |
| Account | `account/components/ProfileView`, `ProfileForm`, `AvatarForm`, `PreferencesForm`, `PasswordForm`, `ContactSettings` | P2 |

### 9.5 Games (`apps/web/src/games/…`; visual only, no rule changes)

| Item | Files | Priority | Note |
|---|---|---|---|
| Poker | `poker/PokerBoard`, `poker/HandResult` | P1 | Keep the oval and the phone layout (TASK-058) |
| Durak | `durak/DurakBoard`, `DurakHand`, `DurakSeat`, `TablePiles` | P1 | Keep the rectangular felt |
| Nardi | `nardi/NardiBoard`, `NardiModePreview` | P1 | Walnut stays; align seats and surround |
| Checkers (Shashki) | `checkers/CheckersBoard` | P1 | Classic colours stay |
| Chess | `chess/ChessBoard` | P2 | Not named in the brief's game list; in PROGRESS step 4 |
| Playing cards | `cards/PlayingCard` | P1 | Shared; ivory is its native surface |

Across all tables: clear seats, level and frame on every avatar, readable stake, and `TASK-037`, `TASK-053` and `TASK-061` fold in here where relevant.

### 9.6 Only if the owner approves backend work (§8.2)

Leaderboards page · Friends · Missions panel · Achievements on the profile · Notification centre · Event banner · Popular and per-game counts on cards. **Design these only after approval; do not ship placeholders.**

## Decisions needed

1. **Backend for engagement (§8.2).** The brief lists leaderboards, friends, missions and achievements; none exists, and each is a new domain needing approval. **PM recommends:** redesign first without them, then leaderboards, then missions. Without approval these are simply left out.
2. **Brand name and logo** (standing backlog question 2). The product still shows "Game Platform" and a purple dice. A premium identity can't be finished on a placeholder.
3. **Age confirmation.** `docs/ARCHITECTURE.md` §11 lists an age gate as the mitigation for simulated gambling, but `/register` has no age confirmation. A stronger casino look raises this. **PM recommends** adding one (and making sure the terms page is published; the dev app has none).

**Not blocking, but the owner should see one screenshot in Phase 2:** the relit home picture, since it changes something they kept. Existing question 12 (a grace day for the streak) is also worth answering; PM recommends yes.

> HANDOFF  TASK-048  PM → UI/UX  2026-11-09
> Need: visual direction and design system (Phase 2) from this document
> Read: §3 (palette and proportions), §4, §5–7, §8.3, §9; brief §7
> Done when: tokens and components are specified, the relit home picture is shown to the owner, and every page measures mean L\* 35–45, near-black at most 10%, light at most 25% at 1440 and 390 (tables excepted)
