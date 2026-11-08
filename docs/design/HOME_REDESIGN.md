# Home page redesign (TASK-056)

UI/UX Agent, 2026-11-08. Everything the developer needs is in this file. Brief:
`docs/product/briefs/TASK-056-home-page-redesign.md`, direction from `docs/product/briefs/TASK-048-platform-redesign.md`.

The code blocks in this file are the ones used to build the mock I reviewed in headless Chrome at 1440, 1024, 820, 390
and 360 px (Tailwind 4 browser build, the real `index.css` tokens, the real catalog API responses in en, ru, hy). They
are a working reference; adapt them to the codebase's lint rules, but do not change colours or layout without asking.

## 1. Direction

**"Emerald daylight."** Today the page is dark brown on dark brown, and the owner's words are "too dark, visually
unattractive". The new page keeps the club idea (green baize, gold, ivory cards) and turns the lights on:

| Band | Background | Job |
|---|---|---|
| Header, footer | deep emerald | frame the page, keep the controls where they are |
| Hero | rich mid emerald with warm light behind the art | what this is, one action |
| Games | warm ivory, the largest area of the page | the lobby: categories and carousels |
| Ways to play | honey gold | short, warm close |

New art replaces the three tilted screenshots: a flat, poster-like "three arched windows" hero and one poster per game,
all drawn in SVG (section 6). Text never sits on top of art again, which also ends the unreadable "ghost" titles on the
side slides of today's carousel.

Rules this design keeps: no real money, wagers, deposits, cash prizes or VIP purchase, in words or pictures. There are no
coins, chip stacks or jackpots in the new art (the old poker scene has chip stacks; the new one has a dealer button
instead). Fishkas appear only in the existing header balance.

**Scope.** Colours and art apply to `/` only. `/games` and `/dashboard` keep their dark brown look. They do render the
same catalog block as `/` (`GameGrid`), so they get the new carousel and categories, on their own dark tokens. I checked
that combination in the mock and it reads well. Nothing else on those pages changes.

> **For `docs/team/DECISIONS.md`** (agent rule: a new visual style needs a written reason; I can only write this file, so
> please add it): `2026-11-08 · Home page (/) uses an "emerald daylight" palette (scoped --color-home-* tokens) instead of
> the card-club browns · owner said the page is too dark and asked for new background colours and images (TASK-056,
> TASK-048 brief); applies to / only until TASK-048 · owner.`
> Add the tokens and the `.home-*` surfaces to `docs/design/DESIGN_SYSTEM.md` when you ship.

## 2. Colours

Exact values; the CSS is in section 3.

| Token (`--color-…`) | Value | Tailwind | Use |
|---|---|---|---|
| `home-emerald-950` | `#083b31` | `bg-home-emerald-950` | header |
| `home-emerald-900` | `#0b4f41` | | frame, behind status strips, footer |
| `home-emerald-800` | `#0e6251` | | hero gradient ends, surfaces on emerald |
| `home-emerald-700` | `#107560` | | hero gradient middle (the lightest stop under text) |
| `home-cream` | `#fff8e7` | `text-home-cream` | headings on emerald |
| `home-mint` | `#d9efe4` | | body text on emerald |
| `home-ivory` | `#f8f1e1` | | Games band |
| `home-paper` | `#fffcf4` | `bg-home-paper` | cards, pills |
| `home-sand` | `#efe3ca` | | raised surface on ivory, skeletons |
| `home-honey` | `#f3d893` | | Ways band |
| `home-ink` | `#1d2b25` | `text-home-ink` | headings on light |
| `home-ink-soft` | `#4a5a51` | | body on light |
| `home-link` | `#0b6a54` | | links, focus ring on light |
| `home-line` | `#dccbaa` | `border-home-line` | hairlines on light (decorative) |

Art colours are listed at the top of `HomeArt.tsx` and only used in the art.

**Two scopes do most of the work.** Tailwind utilities read `var(--color-…)`, so re-pointing the shared tokens inside a
wrapper re-themes every shared component in it with no markup changes (`Button` variant `secondary`, `EmptyState`,
`QueryErrorState`, `Skeleton`, `text-muted`, focus ring).

- `.home-scope-emerald` on the header, footer, side drawer panel and hero.
- `.home-scope-ivory` on the Games band and the Ways band.

Do **not** put a scope on the whole frame or the root: the Daily Bonus, Level-up and Out-of-Fishkas dialogs live inside the
frame and stay in their card-club look on every page.

> **Tailwind 4 pitfall:** theme variables that no CSS or utility references are removed from the build. A
> `style={{ '--x': 'var(--color-home-honey)' }}` in TSX does not count and silently renders nothing (it happened in my
> mock). Keep colours in CSS classes or utilities, as below.

### Contrast (WCAG, computed)

| Pair | Ratio | Needs |
|---|---|---|
| H1, cream on hero lightest stop (`home-emerald-700`) | 5.31 | 3 (large) |
| Subtitle, mint on `home-emerald-700` | 4.66 | 4.5 |
| Eyebrow, gold-light on black/15 over `home-emerald-700` | 5.33 | 4.5 |
| Gold button text, black on `gold-dark` (darkest stop) | 6.30 | 4.5 |
| Secondary button, cream on `home-emerald-800` | 6.88 | 4.5 |
| Header text, cream on `home-emerald-950` | 11.80 | 4.5 |
| "Log in", mint on `home-emerald-950` | 10.37 | 4.5 |
| Footer, mint on `home-emerald-900` | 7.88 | 4.5 |
| Headings, ink on ivory | 13.10 | 4.5 |
| Card title, ink on paper | 14.38 | 4.5 |
| Card body, ink-soft on paper | 7.13 | 4.5 |
| "1–4 of 18", ink-soft on ivory | 6.50 | 4.5 |
| "See all", `home-link` on ivory | 5.82 | 4.5 |
| Pill text, ink on paper | 14.38 | 4.5 |
| Pill count, ink at 75% on paper | 6.45 | 4.5 |
| Selected pill, black on gold | 9.79 | 4.5 |
| Selected pill count, black 75% on gold | 6.48 | 4.5 |
| PLAY, white on `accent` / `accent-light` | 6.51 / 4.99 | 4.5 |
| "Coming soon" chip, ink on paper | 14.38 | 4.5 |
| Focus ring on ivory / on emerald | 5.82 / 4.30 | 3 |
| Dark pages: muted / accent-text / fg on `surface` | 7.92 / 10.30 / 14.76 | 4.5 |

The hero's warm light is placed so it stops short of the text column at every width (at 1024 and above it is centred at
80% across, radius ending about 312 px out; below 1024 it sits at the bottom edge behind the art). Keep it that way:
with light behind the subtitle the ratio drops below 4.5 (computed: 3.93 at 12% gold).

The gold pill is under 3:1 against paper, so the selected pill also carries a check mark. Do not remove it.

## 3. CSS to add to `apps/web/src/styles/index.css`

Merge the `@theme` tokens and keyframes into the existing `@theme`, and the rules into `@layer components`.

```css
/* add inside @theme */
@theme {
  --color-home-emerald-950: #083b31;
  --color-home-emerald-900: #0b4f41;
  --color-home-emerald-800: #0e6251;
  --color-home-emerald-700: #107560;
  --color-home-cream: #fff8e7;
  --color-home-mint: #d9efe4;
  --color-home-ivory: #f8f1e1;
  --color-home-paper: #fffcf4;
  --color-home-sand: #efe3ca;
  --color-home-honey: #f3d893;
  --color-home-ink: #1d2b25;
  --color-home-ink-soft: #4a5a51;
  --color-home-link: #0b6a54;
  --color-home-line: #dccbaa;

  @keyframes home-float {
    50% {
      transform: translateY(-8px);
    }
  }
}

/* add inside @layer components */
@layer components {
  /* Re-tints every shared token-based component inside it. */
  .home-scope-emerald {
    --color-bg: var(--color-home-emerald-900);
    --color-surface: var(--color-home-emerald-800);
    --color-surface-raised: var(--color-home-emerald-700);
    --color-border: #2a8d75;
    --color-fg: var(--color-home-cream);
    --color-muted: var(--color-home-mint);
    color: var(--color-fg);
  }

  .home-scope-ivory {
    --color-bg: var(--color-home-ivory);
    --color-surface: var(--color-home-paper);
    --color-surface-raised: var(--color-home-sand);
    --color-border: var(--color-home-line);
    --color-fg: var(--color-home-ink);
    --color-muted: var(--color-home-ink-soft);
    --color-accent-text: var(--color-home-link);
    background-color: var(--color-bg);
    color: var(--color-fg);
    color-scheme: light;
  }

  /* The "ways to play" band: warm honey, ivory cards. */
  .home-band-honey {
    background: linear-gradient(120deg, var(--color-home-honey), #f7e5b5);
  }

  .home-room {
    background: linear-gradient(160deg, #eadfc6, #d5e3d3);
  }

  .home-frame {
    background: var(--color-home-emerald-900);
    border: 1px solid rgb(169 119 42 / 0.45);
    box-shadow: 0 24px 60px rgb(40 50 40 / 0.28);
  }

  /* Text sits at the left and on phones at the top; the warm light only ever falls behind the art. */
  .home-hero {
    background:
      radial-gradient(30rem 24rem at 80% 50%, rgb(255 214 130 / 0.3), transparent 65%),
      linear-gradient(140deg, var(--color-home-emerald-800), var(--color-home-emerald-700) 55%, var(--color-home-emerald-800));
  }

  @media (max-width: 1023.98px) {
    .home-hero {
      background:
        radial-gradient(30rem 16rem at 50% 100%, rgb(255 214 130 / 0.32), transparent 70%),
        linear-gradient(170deg, var(--color-home-emerald-800), var(--color-home-emerald-700) 60%);
    }
  }
}
```

## 4. App shell (`app/layouts/PublicLayout.tsx`)

The one-line condition: `const isHome = pathname === '/'`. Only these classes change, and only when `isHome`:

| Element | Today | When `isHome` |
|---|---|---|
| outer `div` | `club-room` | `home-room` |
| frame `div` | `club-frame` | `home-frame` |
| `<header>` | `bg-[#1f140f]/80` | `home-scope-emerald bg-home-emerald-950/90` |
| `<footer>` | (no background) | add `home-scope-emerald` |
| Drawer panel (`#main-menu`) | `club-frame` | `home-frame home-scope-emerald` (pass `isHome` to `Drawer`) |

Everything else stays: header height and contents, menu button, `SideNav`, phone tab bar and its `pb-16`, footer contents,
language switcher, maintenance screen. The verification banner and active-games bar stay on the frame, which is emerald
on home, so their light text stays readable; do not place them on ivory.

## 5. Page layout (`pages/LandingPage.tsx`)

Reference markup:

```tsx
export function LandingPage() {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const { data: user } = useCurrentUser()

  return (
    <>
      {config.data ? <title>{config.data.name}</title> : null}

      <section className="home-scope-emerald home-hero border-b border-gold/20">
        <Container className="grid items-center gap-8 py-12 sm:py-16 lg:grid-cols-[1fr_1.05fr] lg:gap-12 lg:py-20">
          <div className="text-center lg:text-left">
            <p className="inline-flex items-center rounded-full border border-gold/45 bg-black/15 px-3 py-1 text-xs font-semibold tracking-wide text-gold-light uppercase">
              {t('landing.eyebrow')}
            </p>
            <h1 className="mt-5 text-4xl leading-tight font-extrabold tracking-tight text-balance whitespace-pre-line text-fg sm:text-5xl lg:text-5xl xl:text-[3.25rem]">
              {t('landing.title')}
            </h1>
            <p className="mx-auto mt-5 max-w-xl text-lg text-muted lg:mx-0">{t('landing.subtitle')}</p>
            <div className="mt-8 flex flex-wrap justify-center gap-3 lg:justify-start">
              {/* Same rule as today: nothing while the account is unknown. */}
              {user ? (
                <ButtonLink to="/dashboard" variant="gold" className="min-h-11 px-7 py-3 text-base">
                  {t('landing.playNow')}
                </ButtonLink>
              ) : user === null ? (
                <ButtonLink to="/register" variant="gold" className="min-h-11 px-7 py-3 text-base">
                  {t('landing.join')}
                </ButtonLink>
              ) : null}
              {/* A plain link: the browser's own jump to the section below. */}
              <a
                href="#games"
                className="inline-flex min-h-11 items-center justify-center rounded-xl border border-border bg-surface px-7 py-3 text-base font-semibold text-fg transition hover:bg-surface-raised"
              >
                {t('landing.cta')}
              </a>
            </div>
          </div>
          <HomeHeroArt className="mx-auto block w-full max-w-[22rem] sm:max-w-[34rem] lg:max-w-none" />
        </Container>
      </section>

      <section id="games" className="home-scope-ivory scroll-mt-24 py-12 sm:py-16">
        <Container>
          <h2 className="mb-6 text-3xl font-extrabold tracking-tight text-fg">{t('landing.gamesHeading')}</h2>
          <GameGrid />
        </Container>
      </section>

      <section className="home-scope-ivory home-band-honey">
        <Container className="py-12">
          <ul className="grid gap-4 sm:grid-cols-3">
            {ways.map(({ key, Icon }) => (
              <li key={key} className="flex gap-4 rounded-2xl border border-home-line bg-home-paper p-5 shadow-sm">
                <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-home-emerald-800 text-home-cream">
                  <Icon className="size-6" />
                </span>
                <span>
                  <span className="block font-semibold text-fg">{t(`landing.ways.${key}.title`)}</span>
                  <span className="mt-1 block text-sm text-muted">{t(`landing.ways.${key}.text`)}</span>
                </span>
              </li>
            ))}
          </ul>
        </Container>
      </section>
    </>
  )
}
```

Section order changes: the games come straight after the hero; the "ways to play" cards move below them. The
`bg-home-emerald-800` utility and `.home-band-honey` rule keep those tokens in the build.

| Section | 1440 (frame 1392, content 1152) | 820 | 390 |
|---|---|---|---|
| Header | as today, 81 px | as today | as today, 65 px |
| Hero | two columns `1fr` / `1.05fr`, text left, art right (about 565 px wide), `py-20` | one column, centred text, then art capped at 34rem, `py-16` | one column, centred, art capped at 22rem, `py-12` |
| H1 | 52 px | 48 px | 36 px |
| Games | `py-16`; pills in a wrapping row; shelves of 4 slots | `py-16`; pills in one row; rail shows about 2.25 cards, `w-[44%]` | `py-12`; pills scroll sideways, rail shows about 1.25 cards, `w-[78%]` |
| Rail edges | contained in the content width | bleeds to the frame edge | bleeds to the screen edge |
| Ways | 3 columns | 3 columns | 1 column |

Long strings: checked ru and hy at 1440, 1024 (hy) and 390, ru at 360. Nothing overflows; the hy title takes 5 lines at
1024 px (its longest case), which is acceptable.

## 6. Images (`features/catalog/components/HomeArt.tsx`, new file)

Flat, poster-like scenes drawn from simple shapes: sunburst rays, arches, gold keylines. No image files, no external assets,
no suit characters (`♠` can render as a colour emoji on iOS, so suits are paths). The look is our own and is not taken
from any other site. Uploaded artwork still wins: `thumbnail_url`, when set, is shown instead of the poster.

| Scene | Picture |
|---|---|
| `HomeHeroArt` (720×520) | Three arched windows on a gold sill with a pennant frieze. Left: dark emerald chess king and a pawn on an ivory window with a checked floor. Middle and tallest: five playing cards fanned on a garnet sunburst. Right: two dice over nardi points on amber. The cards and dice drift by 8 px (`home-float`), never under reduced motion. |
| `chess` poster | Emerald, cream king, gold pawn, dark pawn, checked floor |
| `checkers` poster | Garnet, a crowned three-tier gold stack with two dark men |
| `nardi` poster | Amber, a tilted board with points, a few men, two large dice |
| `durak` poster | Teal, an attack card and its defence, the deck with the trump card crosswise under it (unique to durak) |
| `poker` poster | Plum, A♠ K♥ as hole cards, three community cards and two face down, a dealer button. No chips. |
| Game without a poster | One of four grounds picked from the slug, sunburst, the game's initial in a cream medallion |
| `board` cover / `card` cover | Shelf openers: a king with a man and a die at the sides / four suit medallions in a 2×2 block |
| Category without art | Its slug's ground, sunburst, three nested gold arches |
| `CategoryCrest` | 40 px tile beside the heading: a die, a spade, or the category's initial |

Do not delete or change `GameArt.tsx`: game pages and the side nav still use `GameScene` and `GameIcon`. Moving from a
poster into a game page will cross-fade into the old scene; that resolves when the game page is redesigned (TASK-048).

```tsx
import { useId, type ReactNode } from 'react'

/*
 * TASK-056 home page art: flat "deco poster" scenes, drawn from simple shapes, no image files.
 * HomeHeroArt: three arched windows. GamePosterScene: 320×200 poster for a game. CategoryCover:
 * shelf opener. CategoryCrest: 40px mark beside a shelf heading.
 */

const c = {
  ink: '#1d2b25',
  cream: '#fff8e7',
  ivory: '#f6ecd6',
  gold: '#e3b655',
  goldDeep: '#a9772a',
  emerald: '#0f6a57',
  emeraldLight: '#1b8a6e',
  emeraldDeep: '#0b4f41',
  garnet: '#8d2338',
  garnetLight: '#a93a4e',
  amber: '#e0a23c',
  amberLight: '#efbd62',
  teal: '#0e7c74',
  tealLight: '#229a90',
  plum: '#55318a',
  plumLight: '#6f4aa8',
  wood: '#5a3312',
  red: '#b3172c',
}

/* Suits are paths, not ♠♥ text: iOS can draw those characters as colour emoji. 24×24 box. */
const suitPaths: Record<string, ReactNode> = {
  heart: <path d="M12 21C12 21 3 14.5 3 8.5A4.5 4.5 0 0 1 12 6.5 4.5 4.5 0 0 1 21 8.5C21 14.5 12 21 12 21Z" />,
  spade: (
    <path d="M12 2C12 2 3 9 3 14A4.2 4.2 0 0 0 10.6 16.4C10.4 18.4 9.6 20 8 22H16C14.4 20 13.6 18.4 13.4 16.4A4.2 4.2 0 0 0 21 14C21 9 12 2 12 2Z" />
  ),
  diamond: <path d="M12 2 20 12 12 22 4 12Z" />,
  club: (
    <>
      <circle cx="12" cy="7" r="4.4" />
      <circle cx="6.8" cy="13.4" r="4.4" />
      <circle cx="17.2" cy="13.4" r="4.4" />
      <path d="M11 12C11 17 10 20 8 22H16C14 20 13 17 13 12Z" />
    </>
  ),
}
type Suit = keyof typeof suitPaths & string
const isRed = (suit: string) => suit === 'heart' || suit === 'diamond'

function SuitMark({ suit, x, y, size, fill }: { suit: Suit; x: number; y: number; size: number; fill?: string }) {
  return (
    <g transform={`translate(${x} ${y}) scale(${size / 24})`} fill={fill ?? (isRed(suit) ? c.red : c.ink)}>
      {suitPaths[suit]}
    </g>
  )
}

/** A playing card, 56×80 before `scale`, turned about its own centre. */
function Card({ x, y, rank, suit, angle = 0, scale = 1 }: { x: number; y: number; rank: string; suit: Suit; angle?: number; scale?: number }) {
  const ink = isRed(suit) ? c.red : c.ink
  return (
    <g transform={`translate(${x} ${y}) rotate(${angle}) scale(${scale})`}>
      <rect x="-28" y="-40" width="56" height="80" rx="6" fill={c.cream} stroke="rgb(29 43 37 / 0.28)" />
      <text x="-22" y="-23" fill={ink} fontFamily="Georgia, serif" fontSize="15" fontWeight="700">
        {rank}
      </text>
      <SuitMark suit={suit} x={-22} y={-20} size={11} />
      <SuitMark suit={suit} x={-14} y={-8} size={28} />
    </g>
  )
}

function CardBack({ x, y, angle = 0, scale = 1 }: { x: number; y: number; angle?: number; scale?: number }) {
  return (
    <g transform={`translate(${x} ${y}) rotate(${angle}) scale(${scale})`}>
      <rect x="-28" y="-40" width="56" height="80" rx="6" fill={c.cream} stroke="rgb(29 43 37 / 0.28)" />
      <rect x="-23" y="-35" width="46" height="70" rx="3" fill={c.garnet} />
      <path d="M0 -26 16 0 0 26 -16 0Z" fill="none" stroke={c.gold} strokeWidth="2" />
      <circle r="4" fill={c.gold} />
    </g>
  )
}

/** A fan of rays rising from (cx, cy). */
function Rays({ cx, cy, count, step, length, color, opacity = 1 }: { cx: number; cy: number; count: number; step: number; length: number; color: string; opacity?: number }) {
  const start = -((count - 1) / 2) * step
  return (
    <g fill={color} opacity={opacity}>
      {Array.from({ length: count }, (_, i) => (
        <path
          key={i}
          transform={`rotate(${start + i * step} ${cx} ${cy})`}
          d={`M${cx - 3} ${cy} ${cx - step * 0.13 * length * 0.02} ${cy - length} ${cx + step * 0.13 * length * 0.02} ${cy - length} ${cx + 3} ${cy}Z`}
        />
      ))}
    </g>
  )
}

/** A chess king, 100×194 before `scale`, with its foot at (x, y). */
function King({ x, y, scale, fill, trim }: { x: number; y: number; scale: number; fill: string; trim: string }) {
  return (
    <g transform={`translate(${x - 50 * scale} ${y - 194 * scale}) scale(${scale})`}>
      <rect x="44" y="0" width="12" height="38" fill={trim} />
      <rect x="32" y="11" width="36" height="12" fill={trim} />
      <path d="M26 44H74L66 72H34Z" fill={fill} />
      <rect x="29" y="72" width="42" height="9" rx="3" fill={trim} />
      <path d="M36 81H64C66 120 72 150 86 172H14C28 150 34 120 36 81Z" fill={fill} />
      <rect x="6" y="172" width="88" height="22" rx="6" fill={fill} />
      <rect x="6" y="178" width="88" height="4" fill={trim} />
    </g>
  )
}

/** A pawn, 60×98 before `scale`, with its foot at (x, y). */
function Pawn({ x, y, scale, fill, trim }: { x: number; y: number; scale: number; fill: string; trim: string }) {
  return (
    <g transform={`translate(${x - 30 * scale} ${y - 98 * scale}) scale(${scale})`}>
      <circle cx="30" cy="19" r="17" fill={fill} />
      <rect x="15" y="35" width="30" height="7" rx="3" fill={trim} />
      <path d="M22 42H38C39 62 45 76 53 86H7C15 76 21 62 22 42Z" fill={fill} />
      <rect x="3" y="86" width="54" height="12" rx="4" fill={fill} />
    </g>
  )
}

/** A die with `value` pips, side `size`, turned about its centre. */
function Die({ x, y, size, angle, value }: { x: number; y: number; size: number; angle: number; value: 1 | 2 | 3 | 4 | 5 | 6 }) {
  const a = size * 0.26
  const b = size * 0.74
  const m = size * 0.5
  const spots: Record<number, [number, number][]> = {
    1: [[m, m]],
    2: [[a, a], [b, b]],
    3: [[a, a], [m, m], [b, b]],
    4: [[a, a], [b, a], [a, b], [b, b]],
    5: [[a, a], [b, a], [m, m], [a, b], [b, b]],
    6: [[a, a], [b, a], [a, m], [b, m], [a, b], [b, b]],
  }
  return (
    <g transform={`translate(${x - m} ${y - m}) rotate(${angle} ${m} ${m})`}>
      <rect width={size} height={size} rx={size * 0.18} fill={c.cream} stroke="rgb(29 43 37 / 0.3)" />
      {spots[value].map(([cx, cy]) => (
        <circle key={`${cx}-${cy}`} cx={cx} cy={cy} r={size * 0.095} fill={c.ink} />
      ))}
    </g>
  )
}

/** A draughts man from the side; `tiers` stacked; the top may wear a crown. */
function Man({ x, y, rx, tiers, top, side, crown = false }: { x: number; y: number; rx: number; tiers: number; top: string; side: string; crown?: boolean }) {
  const ry = rx * 0.34
  const h = rx * 0.34
  return (
    <g>
      {Array.from({ length: tiers }, (_, i) => {
        const cy = y - i * h
        return (
          <g key={i}>
            <path d={`M${x - rx} ${cy} v${h} a${rx} ${ry} 0 0 0 ${rx * 2} 0 v${-h}Z`} fill={side} />
            <ellipse cx={x} cy={cy} rx={rx} ry={ry} fill={top} />
            <ellipse cx={x} cy={cy} rx={rx * 0.62} ry={ry * 0.62} fill="none" stroke={side} strokeWidth="1.5" />
          </g>
        )
      })}
      {crown ? (
        <path
          transform={`translate(${x} ${y - (tiers - 1) * h - ry * 0.2}) scale(${rx / 40})`}
          d="M-20 8-25-14-11-4 0-20 11-4 25-14 20 8Z"
          fill={c.gold}
          stroke={c.goldDeep}
          strokeWidth="1.5"
        />
      ) : null}
    </g>
  )
}

/** A row of squares, full width, for a floor. */
function Floor({ y, size, width, a, b }: { y: number; size: number; width: number; a: string; b: string }) {
  return (
    <g>
      {Array.from({ length: Math.ceil(width / size) }, (_, i) => (
        <rect key={i} x={i * size} y={y} width={size} height={size} fill={i % 2 ? a : b} />
      ))}
    </g>
  )
}

/** A row of nardi points standing along the bottom edge. */
function Points({ y, height, x, width, count, a, b }: { y: number; height: number; x: number; width: number; count: number; a: string; b: string }) {
  const w = width / count
  return (
    <g>
      {Array.from({ length: count }, (_, i) => (
        <path key={i} d={`M${x + i * w} ${y}h${w}l${-w / 2} ${-height}Z`} fill={i % 2 ? a : b} />
      ))}
    </g>
  )
}

const arch = (x: number, w: number, top: number, bottom: number) =>
  `M${x} ${bottom}V${top + w / 2}A${w / 2} ${w / 2} 0 0 1 ${x + w} ${top + w / 2}V${bottom}Z`

/**
 * The hero picture: three arched windows on a gold sill. Left: chess, on ivory. Middle and tallest:
 * a fan of cards against a garnet sunburst. Right: nardi, on amber.
 */
export function HomeHeroArt({ className = '' }: { className?: string }) {
  const id = useId().replace(/:/g, '')
  const left = arch(26, 196, 96, 472)
  const mid = arch(236, 248, 14, 472)
  const right = arch(498, 196, 96, 472)
  return (
    <svg viewBox="0 0 720 520" aria-hidden="true" className={className}>
      <defs>
        <clipPath id={`${id}-l`}><path d={left} /></clipPath>
        <clipPath id={`${id}-m`}><path d={mid} /></clipPath>
        <clipPath id={`${id}-r`}><path d={right} /></clipPath>
      </defs>

      {/* Left window: a king and a pawn on a checked floor. */}
      <g clipPath={`url(#${id}-l)`}>
        <rect x="26" y="96" width="196" height="376" fill={c.ivory} />
        <Rays cx={124} cy={440} count={7} step={18} length={360} color={c.amberLight} opacity={0.55} />
        <g transform="translate(26 416)">
          <Floor y={0} size={28} width={196} a={c.emerald} b={c.cream} />
          <Floor y={28} size={28} width={196} a={c.cream} b={c.emerald} />
        </g>
        <King x={136} y={426} scale={1.42} fill={c.emeraldDeep} trim={c.gold} />
        <Pawn x={62} y={428} scale={0.72} fill={c.goldDeep} trim={c.ink} />
      </g>

      {/* Middle window: five cards fanned against a sunburst. */}
      <g clipPath={`url(#${id}-m)`}>
        <rect x="236" y="14" width="248" height="458" fill={c.garnet} />
        <Rays cx={360} cy={420} count={13} step={14} length={440} color={c.garnetLight} />
        <path d={arch(262, 196, 40, 472)} fill="none" stroke={c.gold} strokeWidth="2" opacity="0.7" />
        <g className="motion-safe:animate-[home-float_7s_ease-in-out_infinite] [transform-box:fill-box]">
          {[
            ['9', 'diamond', -28, 303, 338],
            ['J', 'club', -14, 331, 314],
            ['Q', 'heart', 0, 360, 304],
            ['K', 'spade', 14, 389, 314],
            ['A', 'spade', 28, 417, 338],
          ].map(([rank, suit, angle, x, y]) => (
            <Card key={String(rank)} x={Number(x)} y={Number(y)} rank={String(rank)} suit={suit as Suit} angle={Number(angle)} scale={1.62} />
          ))}
        </g>
      </g>

      {/* Right window: two dice over nardi points. */}
      <g clipPath={`url(#${id}-r)`}>
        <rect x="498" y="96" width="196" height="376" fill={c.amber} />
        <Rays cx={596} cy={460} count={7} step={18} length={380} color={c.amberLight} />
        <Points y={472} height={160} x={498} width={196} count={6} a={c.garnet} b={c.emeraldDeep} />
        <g className="motion-safe:animate-[home-float_9s_ease-in-out_infinite_-3s] [transform-box:fill-box]">
          <Die x={566} y={246} size={84} angle={-14} value={5} />
          <Die x={628} y={322} size={72} angle={12} value={3} />
        </g>
      </g>

      {/* Gold keylines and the sill. */}
      <g fill="none" stroke={c.gold} strokeWidth="4">
        <path d={left} />
        <path d={mid} />
        <path d={right} />
      </g>
      <rect x="10" y="472" width="700" height="14" rx="3" fill={c.gold} />
      <rect x="28" y="486" width="664" height="10" fill={c.goldDeep} />
      <Points y={520} height={22} x={28} width={664} count={38} a={c.emeraldLight} b={c.gold} />
    </svg>
  )
}

const posters: Record<string, ReactNode> = {
  chess: (
    <>
      <rect width="320" height="200" fill={c.emerald} />
      <Rays cx={160} cy={210} count={11} step={16} length={260} color={c.emeraldLight} />
      <Floor y={172} size={28} width={320} a={c.emeraldDeep} b={c.ivory} />
      <King x={168} y={182} scale={0.86} fill={c.cream} trim={c.gold} />
      <Pawn x={92} y={182} scale={0.7} fill={c.gold} trim={c.goldDeep} />
      <Pawn x={244} y={182} scale={0.56} fill={c.ink} trim={c.emeraldLight} />
    </>
  ),
  checkers: (
    <>
      <rect width="320" height="200" fill={c.garnet} />
      <Rays cx={160} cy={210} count={11} step={16} length={260} color={c.garnetLight} />
      <Floor y={172} size={28} width={320} a={c.ivory} b={c.ink} />
      <Man x={160} y={138} rx={54} tiers={3} top={c.cream} side={c.gold} crown />
      <Man x={66} y={158} rx={38} tiers={1} top={c.ink} side="#3b4a44" />
      <Man x={256} y={156} rx={38} tiers={2} top={c.ink} side="#3b4a44" />
    </>
  ),
  nardi: (
    <>
      <rect width="320" height="200" fill={c.amber} />
      <Rays cx={160} cy={220} count={11} step={16} length={280} color={c.amberLight} />
      <g transform="rotate(-4 160 100)">
        <rect x="34" y="28" width="252" height="144" rx="8" fill={c.wood} />
        <rect x="44" y="38" width="110" height="124" rx="3" fill={c.ivory} />
        <rect x="166" y="38" width="110" height="124" rx="3" fill={c.ivory} />
        <Points y={162} height={52} x={44} width={110} count={6} a={c.garnet} b={c.emerald} />
        <Points y={162} height={52} x={166} width={110} count={6} a={c.emerald} b={c.garnet} />
        <g transform="rotate(180 160 100)">
          <Points y={162} height={52} x={44} width={110} count={6} a={c.emerald} b={c.garnet} />
          <Points y={162} height={52} x={166} width={110} count={6} a={c.garnet} b={c.emerald} />
        </g>
        <circle cx="53" cy="50" r="8" fill={c.cream} stroke={c.goldDeep} />
        <circle cx="53" cy="66" r="8" fill={c.cream} stroke={c.goldDeep} />
        <circle cx="267" cy="150" r="8" fill={c.ink} stroke={c.goldDeep} />
        <circle cx="267" cy="134" r="8" fill={c.ink} stroke={c.goldDeep} />
      </g>
      <Die x={136} y={110} size={46} angle={-16} value={6} />
      <Die x={190} y={94} size={46} angle={14} value={4} />
    </>
  ),
  durak: (
    <>
      <rect width="320" height="200" fill={c.teal} />
      <Rays cx={120} cy={230} count={11} step={16} length={290} color={c.tealLight} />
      {/* The deck with the trump card lying crosswise beneath it. */}
      <Card x={268} y={108} rank="7" suit="heart" angle={90} scale={0.9} />
      <CardBack x={244} y={108} scale={1} />
      <CardBack x={241} y={105} scale={1} />
      {/* An attack and its defence. */}
      <Card x={98} y={104} rank="10" suit="club" angle={-14} scale={1.45} />
      <Card x={140} y={110} rank="Q" suit="club" angle={10} scale={1.45} />
    </>
  ),
  poker: (
    <>
      <rect width="320" height="200" fill={c.plum} />
      <Rays cx={160} cy={230} count={11} step={16} length={290} color={c.plumLight} />
      <Card x={124} y={74} rank="A" suit="spade" angle={-9} scale={1.3} />
      <Card x={186} y={78} rank="K" suit="heart" angle={8} scale={1.3} />
      {/* The board: three up, two to come. */}
      {[
        ['J', 'heart'],
        ['10', 'spade'],
        ['4', 'diamond'],
      ].map(([rank, suit], i) => (
        <Card key={rank} x={50 + i * 44} y={160} rank={rank} suit={suit as Suit} scale={0.58} />
      ))}
      <CardBack x={182} y={160} scale={0.58} />
      <CardBack x={226} y={160} scale={0.58} />
      {/* The dealer button. */}
      <circle cx="280" cy="156" r="20" fill={c.cream} stroke={c.gold} strokeWidth="3" />
      <text x="280" y="163" textAnchor="middle" fontFamily="Georgia, serif" fontSize="19" fontWeight="700" fill={c.ink}>
        D
      </text>
    </>
  ),
}

const grounds = [
  { ground: c.emerald, ray: c.emeraldLight },
  { ground: c.garnet, ray: c.garnetLight },
  { ground: c.teal, ray: c.tealLight },
  { ground: c.plum, ray: c.plumLight },
]
/** The same slug always gets the same colour. */
export const groundFor = (slug: string) =>
  grounds[[...slug].reduce((sum, ch) => sum + ch.charCodeAt(0), 0) % grounds.length]

/** The poster for a game; a monogram poster for a game without its own art. */
export function GamePosterScene({ slug, name, className = '' }: { slug: string; name: string; className?: string }) {
  const { ground, ray } = groundFor(slug)
  return (
    <svg viewBox="0 0 320 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true" className={className}>
      {posters[slug] ?? (
        <>
          <rect width="320" height="200" fill={ground} />
          <Rays cx={160} cy={220} count={11} step={16} length={280} color={ray} />
          <circle cx="160" cy="100" r="64" fill={c.cream} />
          <circle cx="160" cy="100" r="64" fill="none" stroke={c.gold} strokeWidth="5" />
          <text x="160" y="124" textAnchor="middle" fontFamily="system-ui, 'Noto Sans Armenian', sans-serif" fontSize="68" fontWeight="800" fill={c.ink}>
            {name.charAt(0)}
          </text>
        </>
      )}
    </svg>
  )
}

const covers: Record<string, ReactNode> = {
  board: (
    <>
      <rect width="320" height="200" fill={c.emerald} />
      <Rays cx={160} cy={220} count={13} step={13} length={300} color={c.emeraldLight} />
      <Floor y={172} size={28} width={336} a={c.emeraldDeep} b={c.ivory} />
      <King x={160} y={182} scale={0.8} fill={c.cream} trim={c.gold} />
      {/* The side pieces only show when the tile is wide; at 4 columns they are cropped away whole. */}
      <Man x={46} y={152} rx={26} tiers={2} top={c.gold} side={c.goldDeep} crown />
      <Die x={274} y={128} size={40} angle={14} value={5} />
    </>
  ),
  card: (
    <>
      <rect width="320" height="200" fill={c.garnet} />
      <Rays cx={160} cy={220} count={13} step={13} length={300} color={c.garnetLight} />
      {/* A 2×2 block stays whole when a narrow tile crops the sides. */}
      {(['spade', 'heart', 'diamond', 'club'] as Suit[]).map((suit, i) => (
        <g key={suit}>
          <circle cx={123 + (i % 2) * 74} cy={63 + (i < 2 ? 0 : 74)} r={32} fill={c.cream} stroke={c.gold} strokeWidth="3" />
          <SuitMark suit={suit} x={123 + (i % 2) * 74 - 18} y={45 + (i < 2 ? 0 : 74)} size={36} />
        </g>
      ))}
    </>
  ),
}

/** Art that opens a shelf (from 1024px up): the category's own, or a generic one in its colour. */
export function CategoryCover({ slug, className = '' }: { slug: string; className?: string }) {
  const { ground, ray } = groundFor(slug)
  return (
    <svg viewBox="0 0 320 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true" className={className}>
      {covers[slug] ?? (
        <>
          <rect width="320" height="200" fill={ground} />
          <Rays cx={160} cy={220} count={13} step={13} length={300} color={ray} />
          {[0, 1, 2].map((i) => (
            <path key={i} d={arch(78 + i * 28, 164 - i * 56, 34 + i * 28, 200)} fill="none" stroke={c.gold} strokeWidth="4" />
          ))}
        </>
      )}
    </svg>
  )
}

const crests: Record<string, ReactNode> = {
  board: <Die x={20} y={20} size={24} angle={0} value={5} />,
  card: <SuitMark suit="spade" x={9} y={9} size={22} fill={c.cream} />,
}

/** A small mark beside a shelf's heading: its own for known categories, a letter otherwise. */
export function CategoryCrest({ slug, name }: { slug: string; name: string }) {
  const { ground } = slug === 'card' ? { ground: c.garnet } : slug === 'board' ? { ground: c.emerald } : groundFor(slug)
  return (
    <svg viewBox="0 0 40 40" aria-hidden="true" className="size-10 shrink-0 rounded-xl">
      <rect width="40" height="40" fill={ground} />
      {crests[slug] ?? (
        <text x="20" y="27" textAnchor="middle" fontFamily="system-ui, 'Noto Sans Armenian', sans-serif" fontSize="20" fontWeight="800" fill={c.cream}>
          {name.charAt(0)}
        </text>
      )}
    </svg>
  )
}
```

## 7. Carousel and categories (`features/catalog/components/GameLobby.tsx`, new; replaces the ring in `GameCarousel.tsx`)

Reference code, used in the mock. `GameCarousel` and `GameLobby` can stay in one file or be split:

```tsx
import {
  useCallback,
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  ViewTransition,
  type KeyboardEvent,
  type ReactElement,
} from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router'
import { ChevronIcon, PlayIcon } from '@/shared/ui/icons'
import type { GameSummary } from '../queries'
import { artTransition, titleTransition } from '../transitions'
import { CategoryCover, CategoryCrest, GamePosterScene } from './HomeArt'
import { PlayerCount } from './PlayerCount'

const OTHER = '_other'

export type GameGroup = { key: string; name: string; order: number; games: GameSummary[] }

/** Shelves come from the games themselves: a category without games never appears. */
export function groupGames(games: GameSummary[], otherName: string): GameGroup[] {
  const groups = new Map<string, GameGroup>()
  for (const game of games) {
    const key = game.category?.slug ?? OTHER
    const group = groups.get(key) ?? {
      key,
      name: game.category?.name ?? otherName,
      order: game.category?.sort_order ?? Number.POSITIVE_INFINITY,
      games: [],
    }
    group.games.push(game)
    groups.set(key, group)
  }
  return [...groups.values()].sort((a, b) => a.order - b.order || a.name.localeCompare(b.name))
}

const noMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false

/** Filter pills (one Tab stop, arrow keys between them), then shelves or one category's grid. */
export function GameLobby({ games }: { games: GameSummary[] }) {
  const { t } = useTranslation()
  const [params, setParams] = useSearchParams()
  const groups = useMemo(() => groupGames(games, t('lobby.other')), [games, t])
  const selected = groups.find((group) => group.key === params.get('category')) ?? null

  const select = (key: string | null) =>
    setParams(
      (current) => {
        if (key) current.set('category', key)
        else current.delete('category')
        return current
      },
      { replace: true, preventScrollReset: true },
    )

  if (groups.length < 2) {
    return <GameCarousel games={games} label={t('carousel.label')} />
  }

  return (
    <div>
      <CategoryPills groups={groups} total={games.length} selected={selected?.key ?? null} onSelect={select} />
      {selected ? (
        <section aria-label={selected.name} className="mt-8">
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-5">
            {selected.games.map((game) => (
              <li key={game.slug}>
                <GameCard game={game} headingLevel={3} />
              </li>
            ))}
          </ul>
        </section>
      ) : (
        <div className="mt-8 space-y-10 sm:space-y-12">
          {groups.map((group) => (
            <GameCarousel
              key={group.key}
              games={group.games}
              label={group.name}
              heading={
                <>
                  <CategoryCrest slug={group.key} name={group.name} />
                  <div className="min-w-0">
                    <h3 className="truncate text-xl font-bold text-fg">{group.name}</h3>
                    <p className="text-sm text-muted">{t('lobby.count', { count: group.games.length })}</p>
                  </div>
                </>
              }
              coverSlug={group.key}
              onSeeAll={() => select(group.key)}
            />
          ))}
        </div>
      )}
    </div>
  )
}

function CategoryPills({
  groups,
  total,
  selected,
  onSelect,
}: {
  groups: GameGroup[]
  total: number
  selected: string | null
  onSelect: (key: string | null) => void
}) {
  const { t } = useTranslation()
  const name = useId()
  const options: { key: string | null; label: string; count: number }[] = [
    { key: null, label: t('lobby.all'), count: total },
    ...groups.map((group) => ({ key: group.key, label: group.name, count: group.games.length })),
  ]

  return (
    <fieldset>
      <legend className="sr-only">{t('lobby.filterLabel')}</legend>
      {/* Phones scroll sideways rather than wrap: the rail below keeps its place. */}
      <div className="-mx-4 flex snap-x gap-2 overflow-x-auto px-4 [scrollbar-width:none] sm:-mx-6 sm:px-6 lg:mx-0 lg:flex-wrap lg:px-0 [&::-webkit-scrollbar]:hidden">
        {options.map(({ key, label, count }) => (
          <label
            key={key ?? 'all'}
            className="flex min-h-11 shrink-0 cursor-pointer snap-start items-center gap-2 rounded-full border border-border bg-surface px-4 text-sm font-semibold whitespace-nowrap text-fg transition-colors hover:border-accent-text has-checked:border-transparent has-checked:bg-linear-to-b has-checked:from-gold-light has-checked:to-gold has-checked:text-black has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-accent-text"
          >
            <input
              type="radio"
              name={name}
              checked={selected === key}
              onChange={() => onSelect(key)}
              className="sr-only"
            />
            {/* Not colour alone: gold on paper is under 3:1, so the chosen pill also gets a tick. */}
            {selected === key ? (
              <svg viewBox="0 0 24 24" aria-hidden="true" className="size-4" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round">
                <path d="m5 13 4 4L19 7" />
              </svg>
            ) : null}
            {label}
            <span className="tabular-nums opacity-75">{count}</span>
          </label>
        ))}
      </div>
    </fieldset>
  )
}

/**
 * A shelf of games on a native scrolling rail: fits as many as the width allows, swipes natively,
 * and never moves on its own. One Tab stop; arrow keys, Home and End walk the games. Arrows and the
 * "4–6 of 12" line appear only when the games don't all fit.
 */
export function GameCarousel({
  games,
  label,
  heading,
  coverSlug,
  onSeeAll,
}: {
  games: GameSummary[]
  label: string
  heading?: ReactElement
  coverSlug?: string
  onSeeAll?: () => void
}) {
  const { t } = useTranslation()
  const rail = useRef<HTMLUListElement>(null)
  const timer = useRef<number | undefined>(undefined)
  const [current, setCurrent] = useState(0)
  const [view, setView] = useState({ canPrev: false, canNext: false, from: 1, to: games.length })
  const overflowing = view.canPrev || view.canNext

  const cards = () => [...(rail.current?.querySelectorAll<HTMLElement>('[data-game-card]') ?? [])]

  const measure = useCallback(() => {
    const element = rail.current
    if (!element) return
    const box = element.getBoundingClientRect()
    const visible = cards().flatMap((card, index) => {
      const rect = card.getBoundingClientRect()
      return rect.left >= box.left - 2 && rect.right <= box.right + 2 ? [index] : []
    })
    const first = visible[0] ?? 0
    setView({
      canPrev: element.scrollLeft > 4,
      canNext: element.scrollLeft + element.clientWidth < element.scrollWidth - 4,
      from: first + 1,
      to: (visible.at(-1) ?? first) + 1,
    })
    // Tab should land on what's on screen, unless the player is already walking the rail.
    if (!element.contains(document.activeElement)) setCurrent(first)
  }, [])

  useEffect(() => {
    measure()
    window.addEventListener('resize', measure)
    return () => {
      window.removeEventListener('resize', measure)
      window.clearTimeout(timer.current)
    }
  }, [measure, games.length])

  const page = (direction: 1 | -1) =>
    rail.current?.scrollBy?.({
      left: direction * rail.current.clientWidth * 0.9,
      behavior: noMotion() ? 'auto' : 'smooth',
    })

  const onKeyDown = (event: KeyboardEvent) => {
    const list = cards()
    const at = list.indexOf(document.activeElement as HTMLElement)
    if (at < 0) return
    const to =
      event.key === 'ArrowRight' ? at + 1
      : event.key === 'ArrowLeft' ? at - 1
      : event.key === 'Home' ? 0
      : event.key === 'End' ? list.length - 1
      : -1
    if (to < 0 || to >= list.length) return
    event.preventDefault()
    setCurrent(to)
    list[to].focus({ preventScroll: true })
    list[to].scrollIntoView?.({ inline: 'nearest', block: 'nearest', behavior: noMotion() ? 'auto' : 'smooth' })
  }

  const arrow =
    'flex size-11 shrink-0 items-center justify-center rounded-full border border-border bg-surface text-fg transition-colors hover:border-accent-text aria-disabled:cursor-default aria-disabled:opacity-40 aria-disabled:hover:border-border'

  return (
    <section aria-roledescription={t('carousel.carousel')} aria-label={label}>
      <div className="mb-4 flex items-center justify-between gap-4">
        <div className="flex min-w-0 items-center gap-3">{heading}</div>
        {overflowing ? (
          <div className="flex shrink-0 gap-2">
            {/* aria-disabled, not disabled: a button that disables itself would drop keyboard focus. */}
            <button type="button" aria-label={t('carousel.previousPage')} aria-disabled={!view.canPrev} onClick={() => view.canPrev && page(-1)} className={arrow}>
              <ChevronIcon className="size-5 rotate-180" />
            </button>
            <button type="button" aria-label={t('carousel.nextPage')} aria-disabled={!view.canNext} onClick={() => view.canNext && page(1)} className={arrow}>
              <ChevronIcon className="size-5" />
            </button>
          </div>
        ) : null}
      </div>

      <ul
        ref={rail}
        onKeyDown={onKeyDown}
        onScroll={() => {
          window.clearTimeout(timer.current)
          timer.current = window.setTimeout(measure, 100)
        }}
        className="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 py-2 [scrollbar-width:none] sm:-mx-6 sm:scroll-px-6 sm:px-6 lg:-mx-2 lg:snap-proximity lg:scroll-px-2 lg:gap-5 lg:px-2 [&::-webkit-scrollbar]:hidden"
      >
        {coverSlug ? (
          // Takes up any width the cards leave free, so a two-game shelf still fills its row.
          <li aria-hidden="true" className="hidden min-w-0 snap-start overflow-hidden rounded-2xl lg:block lg:flex-[1_0_calc((100%-3.75rem)/4)]">
            <CategoryCover slug={coverSlug} className="block size-full" />
          </li>
        ) : null}
        {games.map((game, index) => (
          <li
            key={game.slug}
            aria-roledescription={t('carousel.slide')}
            aria-label={t('carousel.position', { index: index + 1, count: games.length })}
            className="w-[78%] shrink-0 snap-start sm:w-[44%] lg:w-[calc((100%-3.75rem)/4)]"
          >
            <GameCard
              game={game}
              headingLevel={heading ? 4 : 3}
              tabIndex={index === current ? 0 : -1}
              onFocus={() => setCurrent(index)}
            />
          </li>
        ))}
      </ul>

      {overflowing ? (
        <div className="mt-2 flex min-h-11 items-center justify-between gap-4">
          <p aria-live="polite" className="text-sm text-muted tabular-nums">
            {view.from === view.to
              ? t('carousel.position', { index: view.from, count: games.length })
              : t('carousel.range', { from: view.from, to: view.to, count: games.length })}
          </p>
          {onSeeAll ? (
            <button
              type="button"
              onClick={onSeeAll}
              aria-label={t('lobby.seeAllLabel', { category: label })}
              className="min-h-11 rounded-lg px-2 text-sm font-semibold text-accent-text hover:underline"
            >
              {t('lobby.seeAll')}
            </button>
          ) : null}
        </div>
      ) : null}
    </section>
  )
}

/** Uploaded artwork first, else the poster. Text sits below the art, never on it. */
function GameCard({
  game,
  headingLevel,
  tabIndex,
  onFocus,
}: {
  game: GameSummary
  headingLevel: 3 | 4
  tabIndex?: number
  onFocus?: () => void
}) {
  const { t } = useTranslation()
  const Title = headingLevel === 3 ? 'h3' : 'h4'
  return (
    <Link
      to={`/games/${game.slug}`}
      data-game-card=""
      tabIndex={tabIndex}
      onFocus={onFocus}
      className="group flex h-full flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-md shadow-black/10 transition-[transform,box-shadow,border-color] duration-200 hover:border-accent-text hover:shadow-xl motion-safe:hover:-translate-y-1"
    >
      <ViewTransition name={artTransition(game.slug)}>
        <div className="relative aspect-[16/10] overflow-hidden">
          {game.thumbnail_url ? (
            <img src={game.thumbnail_url} alt="" loading="lazy" className="size-full object-cover" />
          ) : (
            <GamePosterScene slug={game.slug} name={game.name} className="block size-full transition-transform duration-500 motion-safe:group-hover:scale-105" />
          )}
          {game.is_playable ? null : (
            <span className="absolute top-3 left-3">
              {/* A solid chip: a tinted Badge would not hold 4.5:1 over art. */}
              <span className="rounded-full bg-home-paper px-2.5 py-1 text-xs font-bold text-home-ink shadow-sm">
                {t('catalog.comingSoon')}
              </span>
            </span>
          )}
        </div>
      </ViewTransition>
      <div className="flex flex-1 flex-col p-4">
        <ViewTransition name={titleTransition(game.slug)}>
          <Title className="text-lg font-bold text-fg">{game.name}</Title>
        </ViewTransition>
        <p className="mt-0.5 text-sm font-medium text-muted">
          <PlayerCount game={game} />
        </p>
        <p className="mt-2 line-clamp-2 min-h-10 text-sm text-muted">{game.description}</p>
        {game.is_playable ? (
          <span
            aria-hidden="true"
            className="mt-3 inline-flex items-center gap-1.5 self-start rounded-full border border-white/15 bg-linear-to-b from-accent-light to-accent px-4 py-2 text-sm font-bold text-white uppercase"
          >
            <PlayIcon className="size-4" />
            {t('catalog.play')}
          </span>
        ) : null}
      </div>
    </Link>
  )
}
```

`GameGrid` keeps its loading, error and empty handling and now renders `<GameLobby games={games.data} />`. Delete the old
`GameCarousel.tsx` ring. Keep `data-testid="game-grid-loading"`. The loading state:

```tsx
<div data-testid="game-grid-loading">
  <div className="mb-8 flex gap-2">
    <Skeleton className="h-11 w-28 rounded-full" />
    <Skeleton className="h-11 w-36 rounded-full" />
    <Skeleton className="h-11 w-36 rounded-full" />
  </div>
  <div className="flex gap-4 overflow-hidden lg:gap-5">
    {[0, 1, 2, 3].map((i) => (
      <Skeleton key={i} className="aspect-[4/5] w-[78%] shrink-0 rounded-2xl sm:w-[44%] lg:aspect-[16/10] lg:w-[calc((100%-3.75rem)/4)]" />
    ))}
  </div>
</div>
```

### How the carousel behaves

It is a native horizontal scroll with snap, not a transformed ring. The browser does the swiping and momentum, and 30
games cost nothing. It **never moves on its own**: autoplay is removed (with shelves it has no job, and auto-rotation
would need a pause control under the [WAI carousel pattern](https://www.w3.org/WAI/ARIA/apg/patterns/carousel/)).

| Topic | Behaviour |
|---|---|
| Sizes | Card `w-[78%]` under 640, `w-[44%]` under 1024, a quarter of the row from 1024 up. All cards the same size. The cover slot absorbs spare width, so a two-game shelf still fills the row (`lg:flex-[1_0_…]`). |
| Art | `aspect-[16/10]`; the card's text sits below on solid paper |
| Dots | Gone (30 dots are unusable). A line of text instead: "1–4 of 18", `aria-live="polite"`, updated once scrolling settles (100 ms debounce) so swipes don't flood screen readers. Single card in view: "1 of 3". |
| Arrows | 44 px, shown only when the games don't all fit; scroll by 90% of the rail, then snap. At either end: `aria-disabled`, dimmed, no action. Not `disabled`: a disabled button drops keyboard focus when it reaches the end. |
| Touch | Native swipe with `snap-mandatory`; the next card peeks. From 1024 up `snap-proximity`, because mandatory snapping fights trackpads. |
| Keyboard | One Tab stop per shelf (roving tabindex), so getting past 30 games isn't 30 Tabs. Inside: Left/Right, Home/End move focus and scroll the card into view. Arrow buttons are native buttons and do not move focus. Tab lands on the first visible card after scrolling with the buttons. |
| Focus ring | The rail is `overflow-x: auto`, which clips. `py-2` and the `lg:-mx-2 lg:px-2` gutter keep the ring whole. |
| Reduced motion | `behavior: 'auto'` for scrolling; no hover lift or art zoom (`motion-safe:` only); hero float off. I confirmed instant scrolling and `animation-name: none` under `prefers-reduced-motion`. |
| Hover | `motion-safe:hover:-translate-y-1`, border to `accent-text`, art zooms 5%. |
| View transition | Art and title keep their `artTransition`/`titleTransition` names; a game appears once per page, so names stay unique. |

### Categories

Shelves are built from the games themselves (`groupGames`), using `category` on each game (`slug`, `name`, `sort_order`,
or `null`) from the catalog API. Nothing is hard-coded. Games keep the order the API gives; shelves follow `sort_order`.

| Case | What the player sees |
|---|---|
| Two or more categories | Pills (All, then one per category, each with its count) above one shelf per category. Each shelf has a crest, name, count, its own arrows. From 1024 up it opens with the cover tile. |
| Pick a category | Replaces the shelves with that category's games as a **grid** (1 / 2 / 3 columns), all visible at once, no rail. "See all" on a shelf does the same. The choice is in the URL (`?category=board`), so Back and refresh keep it; an unknown slug falls back to All. |
| One category only | No pills, no category heading: one carousel under "Games". |
| Category with no games | Invisible. It is not in the data and gets no pill or shelf. |
| Category where every game is "Coming soon" | Normal shelf; cards carry the chip and no Play button. |
| New category, no special art | Generic cover (its slug's colour, sunburst, gold arches) and a crest with its initial. No code change needed. |
| New game, no poster, no upload | Monogram poster. Add a scene to `posters` when art is ready. |
| Game without a category (`category: null`) | Goes on a last shelf named "More games" (`lobby.other`). |
| Admin renames or re-orders | Follows on next load; names are translated server-side. |

## 8. States

| State | Behaviour |
|---|---|
| Loading | Hero renders at once (art needs no data). The Games band shows pill and card skeletons, tinted by the ivory scope. CTA rule unchanged: while the account is unknown, only "Browse games" shows. |
| API error | `QueryErrorState` inside the ivory band |
| No games | `EmptyState` with `catalog.empty` |
| Guest | Gold "Create a free account" and "Browse games"; header has Log in and Sign up |
| Signed in | Gold "Play now" to `/dashboard` and "Browse games"; header has balance and menu; frame keeps `pb-16` for the phone tab bar |
| Language switch | Names change in place (`keepPreviousData`, as today) |

## 9. Text

Keys to **change**:

| Key | en | ru | hy |
|---|---|---|---|
| `landing.title` | `Pull up a chair.\nYour game is waiting.` | `Садитесь за стол.\nВаша игра уже ждёт.` | `Նստեք սեղանի շուրջ։\nՁեր խաղն արդեն սպասում է։` |
| `landing.subtitle` | Play people online or practice against bots, free, any time. | Играйте с людьми онлайн или тренируйтесь с ботами — бесплатно, в любое время. | Խաղացեք մարդկանց հետ առցանց կամ մարզվեք բոտերի հետ՝ անվճար, ցանկացած ժամի։ |

The `\n` is a real newline in the JSON; the H1 uses `whitespace-pre-line`. These replace "Pick a game, learn the rules and
get ready to play", which UX-018 flagged, and do not name a game count or category, so they stay true as games are added.

Keys to **add**:

| Key | en | ru | hy |
|---|---|---|---|
| `lobby.all` | All games | Все игры | Բոլոր խաղերը |
| `lobby.filterLabel` | Show games by category | Показать игры по категории | Ցույց տալ խաղերն ըստ կատեգորիայի |
| `lobby.other` | More games | Другие игры | Այլ խաղեր |
| `lobby.seeAll` | See all | Показать все | Տեսնել բոլորը |
| `lobby.seeAllLabel` | See all: {{category}} | Показать все: {{category}} | Տեսնել բոլորը՝ {{category}} |
| `lobby.count` | `_one` {{count}} game, `_other` {{count}} games | `_one` {{count}} игра, `_few` {{count}} игры, `_many` {{count}} игр, `_other` {{count}} игры | `_one` {{count}} խաղ, `_other` {{count}} խաղ |
| `carousel.range` | {{from}}–{{to}} of {{count}} | {{from}}–{{to}} из {{count}} | {{from}}–{{to}}՝ {{count}}-ից |
| `carousel.previousPage` | Previous games | Предыдущие игры | Նախորդ խաղերը |
| `carousel.nextPage` | Next games | Следующие игры | Հաջորդ խաղերը |

Keys to **remove**: `carousel.previous`, `carousel.next`, `carousel.show`. All others on the page stay as they are. Category
names come from the API.

**Game descriptions need work on the data side** (seeded in `GameSeeder`, editable in admin). They exist only in English
today, so ru and hy cards show English text (this predates the redesign, but the cards make it more visible). Suggested
text, including a rewording of poker's, which says "size your bets and take the pot", wording too close to wagering for this
platform:

| Game | en | ru | hy |
|---|---|---|---|
| nardi | (unchanged) | Классическая гонка стратегии и удачи. Проведите все пятнадцать шашек по доске и выведите их раньше соперника. | Ռազմավարության ու հաջողության դասական մրցավազք։ Անցկացրեք բոլոր տասնհինգ քարերը տախտակով և հանեք դրանք մրցակցից առաջ։ |
| chess | (unchanged) | Вечная игра стратегии. Перехитрите соперника и поставьте мат его королю. | Ռազմավարության հավերժական խաղ։ Գերազանցեք մրցակցին և մատ հայտարարեք նրա արքային։ |
| checkers | (unchanged) | Русские шашки на доске 8×8. Бейте шашки соперника, проходите в дамки и очистите доску. | Ռուսական շաշկի 8×8 տախտակի վրա։ Հարվածեք մրցակցի քարերին, դուրս եկեք դամկա և մաքրեք տախտակը։ |
| durak | (unchanged) | Знаменитая карточная игра атаки и защиты. Избавьтесь от всех карт: последний с картами на руках — дурак. | Հարձակման և պաշտպանության հայտնի թղթախաղ։ Ազատվեք բոլոր թղթերից. վերջինը, ում ձեռքին թղթեր մնան, դուրակն է։ |
| poker | No-Limit Texas Hold'em with play chips. Read your opponents and make the best hand. | Безлимитный техасский холдем на игровые фишки. Читайте соперников и собирайте лучшую комбинацию. | Առանց սահմանափակման Տեխասյան հոլդեմ՝ խաղային ֆիշկաներով։ Կարդացեք մրցակիցներին և հավաքեք լավագույն համակցությունը։ |

## 10. Build list and acceptance

Files: `styles/index.css`, `app/layouts/PublicLayout.tsx`, `pages/LandingPage.tsx`, `features/catalog/components/GameGrid.tsx`,
`GameCarousel.tsx` (replaced), `HomeArt.tsx` and `GameLobby.tsx` (new), the three locale files, tests. No new dependencies.

`GameSummary` needs `category`: run `make types` so `schema.d.ts` has it before building.

Tests to update: `GamesPage.test.tsx` lines 125–135 assert the dots and "Next game"/"Previous game"; replace with the new
arrows and the status line. jsdom has no layout, `scrollBy` or `scrollIntoView` (the code guards with `?.`): to test the
arrows, mock `getBoundingClientRect` and scroll sizes, or assert the keyboard and filter behaviour instead. The "Coming
soon" and player-count assertions inside the link still hold.

Acceptance:

1. `/` shows the three bands and the header and footer in emerald; other pages keep their current backgrounds.
2. Guest, signed-in and unknown-account CTAs behave as in section 8; the Menu button, drawer, tab bar and language switcher work on `/`.
3. No horizontal page overflow at 360, 390, 820, 1024, 1440 in en, ru, hy.
4. All games appear in their category shelves, from API data; a new category with no art shows the generic cover without a code change; a category with no games shows nothing.
5. With 30 games: arrows and the status line ("1–3 of 18" with the cover tile, "1–4 of 18" after scrolling past it) work; one Tab stop per shelf; Left/Right/Home/End walk the cards with a visible, unclipped focus ring.
6. Under `prefers-reduced-motion`: no float, no hover lift, instant scrolling, no autoplay anywhere.
7. Contrast as in section 2; every control at least 44 px.
8. No chip, coin or money imagery in the new art, no wager wording added.

## 11. Patterns looked at (2026-11-08)

Observed, public pages only, no accounts: [Board Game Arena](https://boardgamearena.com/): light page, single large call to
action, plain language ("free"). [PlayOK](https://www.playok.com/en/): one list with board games and card games separated
by a simple gap, player counts per game. [WAI-ARIA carousel pattern](https://www.w3.org/WAI/ARIA/apg/patterns/carousel/) for
roles and rotation rules. Stake (`stake.com`, from the owner's list) did not load in my headless browser (certificate error),
so I did not observe it. No layout or art was taken from any of them.

## 12. Not checked

- The real `PublicLayout` drawer, `FishkaBalance` and `UserMenu` on emerald: checked through token and class reasoning and a shim of the header. Please look at them live.
- `VerificationBanner` and `ActiveGamesBar` on the emerald frame: read in code, not seen.
- View transitions from a poster into the game page: not run, the mock has no router.
- Armenian strings are mine; a native speaker should read them.
