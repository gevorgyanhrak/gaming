# Design system: "card club"

The look as shipped (October 2026). Source of truth for values is the code; this file says what to use
where. Change the code first, then this file.

## Mood
A warm private games club: dark wood room, gold for emphasis, green baize, brass medallions. Calm and
trustworthy, never casino-flashy: no coins, jackpots, flashing lights or "win big" language.

## Tokens (`apps/web/src/styles/index.css`, `@theme`)
| Role | Token / class | Value |
|---|---|---|
| Page background | `bg-bg` | `#140c09` |
| Surface / raised | `bg-surface` / `bg-surface-raised` | `#24170f` / `#33231a` |
| Border | `border-border` | `#4a3426` |
| Text / muted | `text-fg` / `text-muted` | `#f4ebdd` / `#c2ab92` |
| Primary action (play, save) | `accent`, `accent-light`, `accent-hover` | green `#226b37`, `#2a7f41`, `#1d5c2f` |
| Links, small emphasis | `text-accent-text` | `#e7c36a` |
| Gold emphasis | `text-gold` (plain gold), `.text-gold-gradient` (big headings only), `gold`, `gold-light`, `gold-dark` | `#d6ab4e`, `#f7dc8f`, `#b9822f` |
| Game signals | `turn`, `take`, `beaten` | `#f2c14e`, `#d9483b`, `#2f9e5b` |
| Card faces | `font-card`, `card`, `card-back` | Georgia serif, `#6d1530` back |

Contrast checked: body text and green buttons meet WCAG AA (≥ 4.5:1). Gold on gold-gradient buttons uses
black text.

## Surfaces
- `.club-room` page background, `.club-frame` the app window (sticky header, rounded, gold hairline).
- `.club-panel` cards and panels (translucent wood with a faint gold border), radius `rounded-2xl`/`3xl`.
- `.club-medal` round brass medallion behind an icon.
- `.table-rim` wood rim + `.table-felt` baize: oval for card tables (Durak, Poker, waiting table),
  square frame for Chess/Checkers boards (squares `#e3d0ac` / `#8b7355`).
- `.checker-white` / `.checker-black` raised draughts men; `.card-back` card backs.

## Components (`apps/web/src/shared/ui`)
`Button` / `ButtonLink` (variants `primary` green, `gold`, `secondary`), `Segmented` (radio pills,
checked = gold), `TextField`, `Alert`, `Badge`, `Skeleton`, `States` (`EmptyState`,
`QueryErrorState`), `Container`, `icons.tsx` (24px line icons). Game art: `GameScene`, `GameIcon`,
`GameArtwork`. Play: `PlayPanel` (`ModeCard`), `WaitingTable`, `PlayerList`, `MatchmakingStatus`.

## Patterns
- Hierarchy: page title in gold caps for game pages; section titles `text-xl font-bold`; small labels
  `text-xs uppercase tracking-wider text-muted`.
- One primary action per view (big green PLAY or gold CTA); secondary actions `secondary`.
- Your turn: gold badge in the room header + gold ring on the acting seat + countdown ring.
- Motion: `animate-rise` on entry, carousel 700 ms ease-out, view transitions for game art and title;
  everything off under `prefers-reduced-motion`.
- Layout: `Container` max width, 16 px gutter on phones; phone tab bar for signed-in users; boards are
  capped by viewport height so they fit without scrolling.
- Copy: short, plain, second person; every string in en, ru and hy.
