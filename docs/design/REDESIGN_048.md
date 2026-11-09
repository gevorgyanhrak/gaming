# TASK-048 · Platform redesign: "The Lamplit Room" (design system and page spec)

UI/UX Agent, 2026-11-09. Phase 2 of `docs/product/briefs/TASK-048-platform-redesign.md`, built on the PM's
`docs/product/research/TASK-048-market-research.md`. The developer can build from this file alone, in the order of §7.
Applying the tokens to the running app from the browser (no code changed) gave the measurements in §6.

## The direction

A card club with the lights on. Every surface comes from an object already in the product: the emerald of the baize, the walnut of the rails and the Nardi case, the brass of the lamps, the ivory of the playing cards. The room is green, the lights are warm, and white appears only in things you could pick up. It is not a black site and not a white one: mean lightness L\* lands around 35–45, between the casinos (25) and the look the owner rejected as "white is more". Art, not chrome, carries the colour. Gold marks the action and the reward and is rationed.

Hard limits that override any other line: no shop, paid VIP, cash wording or prizes of value; no invented data; Fishkas are free and shown calmly; no new runtime dependency; rules and game behaviour do not change.

### Five deviations from the research, with the reason

| # | Research | This spec | Reason |
|---|---|---|---|
| 1 | Page `emerald-700`, panels `emerald-800` | Page **`emerald-650` `#186854`**, panels **`emerald-700`**, popups `emerald-800` | Measured on the real app: with `emerald-800` panels, Wallet, Settings, History and Profile came out at L\* 29–30, far below the 35–45 target, because those pages are mostly panel. Moving one step up gives 33–43 and still passes every text pair (§1.3) |
| 2 | `#FFA3AE` loss text, "keep on panels" | **`--color-danger: #FFB9C2`** | Passes on panels (4.61) and everywhere else except the raw page field, so the rule is short and hard to break |
| 3 | Replaces `club-*`, `home-*` with new names | **Keep the class names** (`.club-room`, `.club-frame`, `.club-panel`, `.club-medal`, `.table-*`, `.poker-*`) and change their bodies | The redesign's whole value is that shared components change once; renaming more than 30 call sites adds risk for no visual gain. `.home-*` is removed (§4.1) |
| 4 | Ranks page "frontend only", showing what each rank unlocks | Ranks page shows **tiers, level ranges, the player's own position and next reward** only | `/me/level` returns only the next reward. The full reward table is in the database and has no read endpoint. Hardcoding admin-managed values would be wrong; listing every unlock needs a small additive endpoint (§4.13) |
| 5 | Rail 240 px "collapsing to 72 px" | **240 at ≥ 1280, 72 (icons with tooltips) at 1024–1279**, user can toggle | At 1024 a 240 rail leaves 784 px for content and breaks the five-poster row |

## 1. Tokens

Replace the whole `@theme { … }` block of `apps/web/src/styles/index.css` with §1.1. The class names in use today (`bg-bg`, `bg-surface`, `text-fg`, `text-muted`, `border-border`, `text-gold`, `text-accent-text`, `text-success`, …) keep working and change colour automatically, which is the point. Keep the `@keyframes` already in `@theme` unchanged below it (`drawer-in`, `fade-in`, `level-pop`, `rise`, `fishka-bump`, `fishka-delta`, `card-in`).

Tailwind v4 specifics, verified by compiling this exact block with the project's `@tailwindcss/node`: every class in this document resolves, and `bg-emerald-500` and other unlisted shades produce no CSS (so nobody can use a stray neon default by accident). `@theme static` emits every token even if unused, which `.surface-ivory` and the components need.

### 1.1 `@theme` (copy verbatim)

```css
@theme static {
  /* TASK-048 "The Lamplit Room". Contrast figures: docs/design/REDESIGN_048.md §1.3. */

  /* Only the shades below exist: Tailwind's default emerald scale is switched off. */
  --color-emerald-*: initial;

  /* ── Scales ──────────────────────────────────────────────────────────── */
  --color-emerald-900: #0e3b30; /* header, rail, footer, tab bar */
  --color-emerald-800: #114c3e; /* popups (.club-frame) */
  --color-emerald-700: #15604d; /* panels, cards, form surfaces */
  --color-emerald-650: #186854; /* the page field; raised on a panel */
  --color-emerald-600: #1c7a62; /* hover fills */
  --color-felt: #1f8a63; /* lit baize in art; large text only */
  --color-walnut-900: #2b1b12; /* game-table surrounds only */
  --color-walnut-800: #3f2a1c;
  --color-walnut-700: #5a3b27; /* section bands, filter strip */
  --color-walnut-500: #8a5e3e; /* wood highlights, never under text */
  --color-ivory-50: #fffaef; /* name plates, card faces */
  --color-ivory-100: #f6eedc; /* tiles, reward plates */
  --color-sand-300: #e4d5b7; /* ivory hover, wells on ivory */
  --color-gold-300: #f5d98a; /* small gold text, focus ring */
  --color-gold-500: #e2b84f; /* primary fill, large gold text and graphics */
  --color-gold-700: #8c6420; /* gold text on ivory */
  --color-cream: #fff8e7;
  --color-mint: #cfe4d8;
  --color-ink: #1e2a24;
  --color-ink-soft: #47564e;
  --color-claret: #6d1530; /* decoration and card backs only */

  /* ── Roles: same names as before, so every existing class follows ────── */
  --color-bg: var(--color-emerald-650);
  --color-surface: var(--color-emerald-700);
  --color-surface-raised: var(--color-emerald-650);
  --color-border: #2a7a63; /* decorative hairline, never the only edge of a control */
  --color-border-strong: #86b8a8; /* edges of inputs and other controls: 3:1 or better */
  --color-fg: var(--color-cream);
  --color-muted: var(--color-mint);
  --color-accent: var(--color-emerald-600);
  --color-accent-hover: var(--color-emerald-700);
  --color-accent-light: #24917a;
  --color-accent-text: var(--color-gold-300); /* links and focus on green and walnut */
  --color-link-on-ivory: #0e6b56;
  --color-gold: var(--color-gold-500);
  --color-gold-light: var(--color-gold-300);
  --color-gold-dark: #b98a32; /* lower gradient stop of gold fills */
  --color-success: #7fe3b0;
  --color-warning: #ffe08a;
  --color-danger: #ffb9c2;
  --color-danger-on-ivory: #a61e32;
  --color-scrim: rgb(7 30 24 / 0.72);

  /* ── Game pieces and table signals (unchanged jobs) ──────────────────── */
  --color-card: var(--color-ivory-50);
  --color-suit-red: #c1121f;
  --color-suit-black: #1b1b1b;
  --color-card-back: var(--color-claret);
  --color-turn: #f2c14e;
  --color-take: #d9483b;
  --color-beaten: #2f9e5b;
  --color-table-room: #0d3a2f; /* behind a game table: darker on purpose */

  /* Kept until Step 7 removes its one use in GameLobby. */
  --color-home-paper: var(--color-ivory-50);
  --color-home-ink: var(--color-ink);

  /* ── Type ────────────────────────────────────────────────────────────── */
  --font-sans: 'Noto Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  --font-display: 'Noto Serif Display', Georgia, 'Times New Roman', ui-serif, serif;
  --font-card: Georgia, 'Times New Roman', ui-serif, serif;

  --text-xs: 0.75rem;
  --text-xs--line-height: 1rem;
  --text-sm: 0.875rem;
  --text-sm--line-height: 1.25rem;
  --text-base: 1rem;
  --text-base--line-height: 1.5rem;
  --text-lg: 1.125rem;
  --text-lg--line-height: 1.625rem;
  --text-xl: 1.25rem;
  --text-xl--line-height: 1.75rem;
  --text-2xl: 1.5rem;
  --text-2xl--line-height: 2rem;
  --text-3xl: 1.75rem;
  --text-3xl--line-height: 2.25rem;
  --text-4xl: 2.25rem;
  --text-4xl--line-height: 2.75rem;
  --text-display-sm: 2.25rem;
  --text-display-sm--line-height: 2.75rem;
  --text-display: 3.5rem;
  --text-display--line-height: 4rem;

  /* ── Space, shape, depth ─────────────────────────────────────────────── */
  --spacing-rail: 15rem;
  --spacing-rail-icons: 4.5rem;
  --spacing-header: 4rem;
  --spacing-tabbar: 4rem;

  --radius-control: 0.75rem;
  --radius-card: 1rem;
  --radius-panel: 1.25rem;
  --radius-modal: 1.5rem;

  --shadow-panel: 0 8px 24px rgb(7 30 24 / 0.32);
  --shadow-raised: 0 14px 32px rgb(7 30 24 / 0.42);
  --shadow-modal: 0 28px 70px rgb(7 30 24 / 0.6);
  --shadow-gold: 0 6px 16px rgb(226 184 79 / 0.3);

  /* ── Motion ──────────────────────────────────────────────────────────── */
  --ease-soft: cubic-bezier(0.2, 0.8, 0.2, 1);
  --ease-pop: cubic-bezier(0.2, 0.8, 0.2, 1.4);
  --motion-press: 120ms; /* active, pressed */
  --motion-hover: 150ms; /* hover lifts, colour */
  --motion-reveal: 200ms; /* tooltip, overlay fade */
  --motion-enter: 260ms; /* sheet, drawer, dialog */

  --animate-card-in: card-in 260ms ease-out both;
  --animate-rise: rise 520ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
```

Below it keep the existing `@keyframes` block. New keyframes to add inside `@theme` (used by §2):

```css
  @keyframes sheet-up {
    from { transform: translateY(100%); }
  }
  @keyframes toast-in {
    from { opacity: 0; transform: translateY(0.75rem); }
  }
```

### 1.2 Surface classes (replace the matching `@layer components` rules)

Replace the `.club-*`, `.text-gold-gradient`, `.home-hero`, `.table-rim`, `.table-felt`, `.poker-stage`, `.poker-felt` and `.poker-chair` bodies with these, and add the new ones. **Do not touch** `.nardi-*`, `.checker-*`, `.card-back`, `.poker-rail`. Delete `.felt-table` (unused) and `.home-scope-night`, `.home-room`, `.home-frame` after §4.1.

```css
@layer components {
  /* The page: lit emerald. Pools stay faint: more light here would push small text below 4.5:1. */
  .club-room {
    background:
      radial-gradient(70rem 44rem at 24% -10rem, rgb(62 176 132 / 0.06), transparent 72%),
      radial-gradient(56rem 34rem at 92% 12rem, rgb(62 176 132 / 0.05), transparent 72%),
      var(--color-bg);
    background-attachment: fixed;
  }

  /* Popups and raised panels (name kept: nine call sites). */
  .club-frame {
    background: var(--color-emerald-800);
    border: 1px solid rgb(226 184 79 / 0.38);
    box-shadow: var(--shadow-modal);
  }

  /* Cards and panels on the page. */
  .club-panel {
    background: var(--color-surface);
    border: 1px solid rgb(226 184 79 / 0.2);
    box-shadow: var(--shadow-panel);
  }

  /* See-through panel over the home picture: 92% so the room shows but text stays readable. */
  .club-panel-glass {
    background: rgb(21 96 77 / 0.92);
    border: 1px solid rgb(226 184 79 / 0.24);
    box-shadow: var(--shadow-panel);
  }

  .club-medal {
    background: radial-gradient(circle at 35% 30%, #fbe7a8, var(--color-gold-500) 48%, var(--color-gold-dark));
    box-shadow:
      0 5px 12px rgb(7 30 24 / 0.4),
      inset 0 -3px 6px rgb(92 60 12 / 0.28);
  }

  .text-gold-gradient {
    background: linear-gradient(180deg, var(--color-gold-300) 20%, var(--color-gold-500));
    background-clip: text;
    color: transparent;
  }

  /* An ivory object: name plate, "how it works" tile, reward plate. Re-points the tokens inside it. */
  .surface-ivory {
    --color-fg: var(--color-ink);
    --color-muted: var(--color-ink-soft);
    --color-accent-text: var(--color-link-on-ivory);
    --color-border: #cdbd98;
    --color-border-strong: #7a6a48;
    --color-danger: var(--color-danger-on-ivory);
    --color-gold-light: var(--color-gold-700);
    color: var(--color-ink);
    background: var(--color-ivory-100);
    box-shadow:
      0 8px 18px rgb(7 30 24 / 0.32),
      inset 0 1px 0 rgb(255 255 255 / 0.7);
  }

  /* A walnut band: filter strip, the ladder, section bands. */
  .surface-walnut {
    background:
      repeating-linear-gradient(92deg, rgb(0 0 0 / 0.08) 0 2px, transparent 2px 11px, rgb(255 225 180 / 0.04) 11px 13px, transparent 13px 27px),
      linear-gradient(180deg, var(--color-walnut-700), #4a3120);
    border-block: 1px solid rgb(226 184 79 / 0.28);
  }

  /* The fixed picture's hero: a green scrim under the words, never a black one. */
  .home-hero {
    background: linear-gradient(90deg, rgb(14 59 48 / 0.9), rgb(14 59 48 / 0.86) 44%, rgb(14 59 48 / 0) 78%);
  }

  @media (max-width: 1023.98px) {
    .home-hero {
      background: linear-gradient(180deg, rgb(14 59 48 / 0.78), rgb(14 59 48 / 0.6) 66%, rgb(14 59 48 / 0.05));
    }
  }

  /* Behind a game table: darker on purpose, the board is the subject. */
  .table-room {
    background:
      radial-gradient(70rem 32rem at 50% -8rem, rgb(255 214 150 / 0.08), transparent 70%),
      linear-gradient(var(--color-table-room), #0b3028);
  }

  .table-rim {
    padding: 14px;
    background: linear-gradient(135deg, #9a6a38, #5a3218 35%, #a87440 55%, #4a2810 80%, #7a4a22);
    box-shadow:
      0 24px 60px rgb(7 30 24 / 0.55),
      inset 0 2px 2px rgb(255 220 170 / 0.35),
      inset 0 -3px 6px rgb(0 0 0 / 0.45);
  }

  /* The felt stays the brightest thing on the table; every stop keeps cream small text at 4.5:1 or more. */
  .table-felt {
    background:
      radial-gradient(ellipse at 50% 40%, #237a5a, #19664b 55%, #114c3a),
      repeating-linear-gradient(0deg, rgb(0 0 0 / 0.04) 0 1px, transparent 1px 3px);
    box-shadow:
      inset 0 0 0 2px rgb(0 0 0 / 0.3),
      inset 0 10px 34px rgb(0 0 0 / 0.42);
  }

  .poker-stage {
    background-color: var(--color-walnut-900);
    background-image:
      radial-gradient(circle at 50% 50%, rgb(226 184 79 / 0.1) 0 9%, transparent 10%),
      radial-gradient(ellipse 22% 14% at 50% 0%, rgb(226 184 79 / 0.1) 60%, transparent 62%),
      radial-gradient(ellipse 22% 14% at 50% 100%, rgb(226 184 79 / 0.1) 60%, transparent 62%),
      radial-gradient(ellipse 14% 22% at 0% 50%, rgb(226 184 79 / 0.1) 60%, transparent 62%),
      radial-gradient(ellipse 14% 22% at 100% 50%, rgb(226 184 79 / 0.1) 60%, transparent 62%),
      radial-gradient(ellipse at 50% 42%, var(--color-walnut-800) 0%, var(--color-walnut-900) 74%);
    background-size:
      56px 56px,
      56px 56px,
      56px 56px,
      56px 56px,
      56px 56px,
      100% 100%;
    box-shadow:
      inset 0 0 0 1px rgb(226 184 79 / 0.2),
      inset 0 0 70px rgb(20 12 7 / 0.55);
  }

  .poker-felt {
    background: radial-gradient(ellipse 60% 55% at 50% 42%, #2c9a68 0%, #1f8a63 45%, #15604d 82%, #114c3e 100%);
    box-shadow:
      inset 0 0 0 3px rgb(0 0 0 / 0.3),
      inset 0 0 0 5px rgb(226 184 79 / 0.2),
      inset 0 14px 46px rgb(0 0 0 / 0.42);
  }

  .poker-chair {
    background: linear-gradient(180deg, #4b3020 0%, var(--color-walnut-800) 60%, var(--color-walnut-900) 100%);
    box-shadow:
      0 6px 14px rgb(7 30 24 / 0.55),
      inset 0 2px 2px rgb(255 214 160 / 0.18),
      inset 0 -6px 10px rgb(0 0 0 / 0.4);
  }
}
```

Unlayered, at the end of the file (they must sit outside `@layer` to win over utilities):

```css
/* Outside any layer, so they win over the utilities. */

/* Armenian is never set in capitals (TASK-027), and its smallest size is 13 px. */
html:lang(hy) .uppercase {
  text-transform: none;
  letter-spacing: normal;
}
html:lang(hy) {
  --text-xs: 0.8125rem;
}

/* A safety net behind the motion-safe: variants already in use. */
@media (prefers-reduced-motion: reduce) {
  *,
  ::before,
  ::after {
    animation-duration: 1ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 1ms !important;
    scroll-behavior: auto !important;
  }
}
```

Base layer stays as it is (`html { color-scheme: dark }`, body `bg-bg font-sans text-fg antialiased`, `:focus-visible` `outline-2 outline-offset-2 outline-accent-text`), and the `theme-color` meta in `index.html` becomes `#0e3b30`.

### 1.3 Contrast, computed (WCAG 2.2; text 4.5:1, large and UI 3:1)

Bold = below 4.5:1, so **large text or graphics only**.

| Text / element | emerald-650 (page) | emerald-700 (panel) | emerald-800 (popup, frame band) | emerald-900 (header, rail, inputs) | walnut-700 | walnut-800 |
|---|---|---|---|---|---|---|
| cream `fg` | 6.30 | 7.05 | 9.32 | 11.76 | 9.49 | 12.72 |
| mint `muted` | 5.00 | 5.60 | 7.40 | 9.33 | 7.53 | 10.10 |
| gold-300 `gold-light`, `accent-text` | 4.82 | 5.40 | 7.13 | 9.00 | 7.26 | 9.73 |
| gold-500 `gold` | **3.56** | **3.98** | 5.27 | 6.64 | 5.36 | 7.18 |
| `success` | **4.30** | 4.81 | 6.35 | 8.01 | 6.47 | 8.67 |
| `danger` | **4.12** | 4.61 | 6.10 | 7.69 | 6.21 | 8.32 |
| `warning` | 5.17 | 5.79 | 7.65 | 9.65 | 7.79 | 10.44 |

| Pair | Ratio |
|---|---|
| `ink` on `gold-300` / `gold-500` / `gold-dark` (primary fill, top, middle, bottom) | 10.75 / 7.93 / 4.78 |
| `ink` on `ivory-50` / `ivory-100` / `sand-300` | 14.29 / 12.88 / 10.27 |
| `ink-soft` on `ivory-50` / `ivory-100` / `sand-300` | 7.45 / 6.71 / 5.35 |
| `link-on-ivory` on `ivory-50` / `ivory-100` | 6.20 / 5.58 |
| `danger-on-ivory` on `ivory-100` | 6.37 |
| `gold-700` on `ivory-100` (gold figures on ivory) | 4.59 |
| `cream` on table felts `#237a5a` / `#19664b` / `#114c3a` | 4.95 / 6.52 / 9.36 |
| `cream` on poker felt `#2c9a68` / `#1f8a63` (large, or on a plate) | 3.34 / 4.07 |
| White on rank badge bronze `#8f5a2c` / `#6e421c` | 5.73 / 8.54 |
| White on ruby `#b83a49` / `#8a1c2a` | 5.62 / 9.21 |
| White on amethyst `#7d52c4` / `#4f2489` | 5.42 / 10.79 |
| Black on silver bottom `#8d96a3`; `ink` on rank gold `#b98a32` | 7.02 |
| `ink` on turn `#f2c14e` (your-turn badge) | 8.86 |
| Black on `take` / `beaten` (durak role bubbles, unchanged) | 4.94 / 6.16 |

| Non-text (3:1 required) | Ratio |
|---|---|
| `border-strong` on page / panel / input fill (`emerald-900`) | 3.00 / 3.36 / 5.59 |
| Focus ring `gold-300` on page / panel / popup / rail / walnut-800 | 4.82 / 5.40 / 7.13 / 9.00 / 9.73 |
| Focus ring `link-on-ivory` on `ivory-100` | 5.58 |

**Allowed pairs, as rules:**
- Body and muted text on page, panel, popup, frame, walnut: `fg` and `muted` everywhere.
- Small gold text and links: `gold-light` / `accent-text` only. `text-gold` (gold-500) is for icons, numerals at 24 px or more, and anything on `emerald-800`/`-900`.
- `text-success` and `text-danger` go on panels or darker, never directly on the page field. Field errors sit in `Card`, so they pass.
- Inside `.surface-ivory` the tokens re-point themselves (`fg` → ink, links → `link-on-ivory`), so shared text classes stay correct. Never place `text-gold`, `success` or `warning` on ivory; use `text-gold-light` (re-points to `gold-700`, 4.59) or ink.
- Small text never sits directly on bright felt (`#2c9a68`, `#1f8a63`, 3.34 / 4.07): it goes on a dark plate, as the seats and the pot do today.
- `border-border-strong` passes 3:1 against the page only at 3.00, so inputs always live on a panel (3.36) with an `emerald-900` fill (5.59).

**Fixes this table requires (Step 1):** `text-[#f87171]` in `WalletPage.tsx` (×2), `DailyBonusDialog.tsx`, `LevelUpDialog.tsx`, `GameResult.tsx` and `TableChat.tsx` → `text-danger`. In `PokerBoard.tsx` `text-gold` at lines 465 and 473 (small text on a panel) → `text-gold-light`. `Button.tsx` outline variant (§2.1) and `PublicLayout.tsx` tab link change with their components.

### 1.4 Surface proportions and limits (from research §3.2, adjusted by deviation 1)

| Surface | Tokens | Share of screen | Used for |
|---|---|---|---|
| Emerald field | `bg` (`emerald-650`), `surface` (`emerald-700`) | about 55% | Page, panels, forms, lists |
| Deep green frame | `emerald-900` | about 13% | Header, rail, footer, tab bar, input fills |
| Walnut | `walnut-700`, `walnut-800`, `.surface-walnut` | about 12% | Filter strip, ladder band, wood frames |
| Ivory | `ivory-50`, `ivory-100` | about 12%, **never over 25% at any width** | Name plates, "how it works" tiles at 1024 and up, reward plates, the Fishka balance plate on Wallet, playing cards |
| Gold | `gold-300`, `gold-500` | about 5% | Reward, rank, primary action, hairlines |
| Near-black | none | under 3% | Shadows, scrims, art only |

**Never:** ivory as a full-width band, page background or form surface. No surface darker than `emerald-900`/`walnut-800` except game tables (§5). One gold fill per view.

### 1.5 Type, space and motion in use

| Role | Class | Size / line | Notes |
|---|---|---|---|
| Display (hero, reward moments, rank names on Ranks) | `font-display text-display lg:` / `text-display-sm` under 1024 | 56/64, 36/44 | `font-bold`, Noto Serif Display, Noto Serif Armenian in hy, `text-balance`, allow wrapping: hy headlines run wider (§3) |
| H1 page title | `text-4xl font-bold max-sm:text-3xl` | 36/44, 28/36 | |
| H2 section | `text-2xl font-bold` | 24/32 | |
| H3 card | `text-lg font-bold` | 18/26 | |
| Body | `text-base` | 16/24 | `font-sans` |
| Small / caption | `text-sm`, `text-xs` | 14/20, 12/16 (13/16 in hy, via the token) | |
| Fishkas, XP, clocks | add `tabular-nums` | as context | Noto Sans digits are uniform with or without it, checked below |

Spacing stays Tailwind's 4 px scale. Page gutter: `Container` `px-4 sm:px-6`. Section gap: `space-y-10` (40 px), between cards `gap-4` (16 px), inside a panel `p-5 sm:p-6`. Radii: controls `rounded-control` 12, cards `rounded-card` 16, panels `rounded-panel` 20, popups `rounded-modal` 24. When a file is touched, replace `rounded-2xl`/`rounded-3xl` with these names; no sweep needed.

Motion: press 120 ms, hover 150 ms, reveal 200 ms, enter 260 ms, all `ease-soft`. One reward moment at a time. Rewards land where the value is stored (the existing flying chips to the balance). Hover lifts only with `motion-safe:`; the global rule in §1.2 stops anything that slips through.

## 2. Components

Unless marked **new**, these are edits to the named file in `apps/web/src/shared/ui/` or feature folder. Every control is 44 px or more on phones (`min-h-11` or `size-11`); desktop may use the small size where stated.

### 2.1 `Button.tsx` (and `ButtonLink`)

```ts
const base =
  'inline-flex min-h-11 items-center justify-center gap-2 rounded-control px-5 py-2.5 text-sm font-semibold transition-[filter,transform,background-color,border-color,color,box-shadow] duration-150 ease-soft motion-safe:active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-55 disabled:shadow-none aria-busy:pointer-events-none'

const variants = {
  /** The one main action of a view: gold. */
  primary: 'bg-linear-to-b from-gold-light to-gold text-ink shadow-gold hover:brightness-105 active:brightness-95',
  /** Same as primary: kept so existing callers don't change. */
  gold: 'bg-linear-to-b from-gold-light to-gold text-ink shadow-gold hover:brightness-105 active:brightness-95',
  /** A second way to play. */
  outline: 'border border-gold/60 bg-transparent text-accent-text hover:border-gold-light hover:bg-gold/12 active:bg-gold/20',
  /** Everything else. */
  secondary: 'border border-border-strong/70 bg-emerald-800 text-fg hover:bg-surface-raised active:bg-emerald-900',
  ghost: 'text-fg hover:bg-white/8 active:bg-white/12',
  /** Resign and other destructive confirmations. */
  danger: 'border border-danger/60 bg-danger/14 text-fg hover:bg-danger/24',
} as const
```

| State | Treatment |
|---|---|
| hover | `brightness-105` on gold; surface change on the rest; 150 ms |
| focus-visible | the global gold ring |
| active | `active:scale-[0.98]` (motion-safe), darker fill |
| disabled | `opacity-55`, no shadow, `cursor-not-allowed` |
| loading | new `loading` prop: `disabled` + `aria-busy`, shows `<Spinner />`, keeps the label: `<span aria-hidden className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none" />` |
| sizes | sm: `min-h-11 sm:min-h-9 px-3.5 py-1.5 text-[0.8125rem]`, md (default), lg `min-h-12 px-7 py-3 text-base` |

Rule: **one gold button per view.** Where a view has several actions, the first is gold and the others are `secondary` or `outline`.

### 2.2 `Field.tsx` (`TextField`, `TextAreaField`, `SelectField`)

```ts
const control =
  'min-h-11 w-full rounded-control border border-border-strong bg-emerald-900 px-3.5 py-2.5 text-fg placeholder:text-muted transition-colors duration-150 focus:border-gold-light focus:ring-2 focus:ring-gold-light/40 focus:outline-none disabled:opacity-60 aria-invalid:border-danger'
```

Label `text-sm font-medium text-fg`, hint `text-sm text-muted`, error `text-sm text-danger`. Text area `min-h-24`. Always inside a panel. Keep the select native.

### 2.3 Cards, badges and small surfaces

| Component | Classes |
|---|---|
| `Card.tsx` | `rounded-panel border border-border bg-surface p-5 shadow-panel sm:p-6` |
| `Badge.tsx` | Base stays. neutral `border-border-strong/50 bg-emerald-800 text-fg`; warning `border-warning/40 bg-warning/15 text-warning`; success `border-success/50 bg-success/15 text-fg`; danger `border-danger/50 bg-danger/15 text-fg` |
| `Alert.tsx` | danger `border-danger/40 bg-danger/10`, success `border-success/40 bg-success/10`, info `border-border bg-emerald-800`; all `text-fg rounded-control` |
| `States.tsx` `EmptyState` | `rounded-panel border border-dashed border-border-strong/40 bg-surface/60` |
| `Skeleton.tsx` | `animate-pulse rounded-lg bg-emerald-600/45` |
| `Segmented.tsx` | Wrap `rounded-control bg-emerald-900/60 border border-border p-1`; option `min-h-11 sm:min-h-9`, checked `has-checked:bg-linear-to-b has-checked:from-gold-light has-checked:to-gold has-checked:text-ink` |
| `Avatar.tsx` | Initial tile `bg-emerald-900 text-fg` (replaces `#23301b`). Use `name.charAt(0).toLocaleUpperCase()` instead of `uppercase` (§1.2 turns caps off in hy) |

### 2.4 Modal and sheet (**new** `shared/ui/Modal.tsx`)

One implementation replaces the five `role="dialog"` copies (`Drawer` goes away, §2.7): focus, Escape, inert root, body scroll lock, restoring focus. Props: `{ open, onClose, labelledBy, describedBy?, size: 'sm'|'md'|'lg', sheetOnPhone?: boolean, children }`. Renders via `createPortal` to `body`.

```
root:     fixed inset-0 z-50 flex items-end justify-center p-3 sm:items-center sm:p-4
backdrop: absolute inset-0 bg-scrim backdrop-blur-sm motion-safe:animate-[fade-in_200ms_ease-out]
panel:    club-frame relative mb-[env(safe-area-inset-bottom)] max-h-[calc(100dvh-1.5rem)] w-full overflow-y-auto rounded-modal outline-none motion-safe:animate-rise
sizes:    sm max-w-md, md max-w-lg, lg max-w-xl
sheet:    root items-end p-0 sm:p-4; panel rounded-t-modal rounded-b-none sm:rounded-modal max-h-[85dvh] motion-safe:animate-[sheet-up_260ms_cubic-bezier(0.2,0.8,0.2,1)]
```

`DailyBonusDialog`, `LevelUpDialog`, `OutOfFishkasDialog`, `ReactionPicker` move onto it; keep their content, and the rules "never stack two, never over a table". Their hard-coded `#3b2a14`/`#24180c` plates become: top `bg-emerald-800`, reward plate `surface-ivory` for amounts (`text-ink`). `text-gold-gradient` stays for the big title. Titles lose `uppercase` where the string may be Armenian (§1.2 handles it anyway).

### 2.5 Tooltip (**new** `shared/ui/Tooltip.tsx`)

CSS-only, for icon-only controls such as rail icons at 72 px:

```
wrapper: group/tip relative inline-flex
tip:     pointer-events-none absolute left-full top-1/2 z-40 ml-2 -translate-y-1/2 whitespace-nowrap rounded-lg bg-ivory-100 px-2.5 py-1.5 text-xs font-medium text-ink opacity-0 shadow-raised transition-opacity duration-200 group-hover/tip:opacity-100 group-focus-within/tip:opacity-100
```

`role="tooltip"`, `id`, trigger gets `aria-describedby`. Touch devices never rely on it: labels stay visible on the phone bar and the rail at 240 px.

### 2.6 Toast (**new** `shared/ui/Toast.tsx` plus a small `toast()` store)

One at a time, four seconds, pause on hover. Position: bottom centre above the tab bar on phones (`bottom-[calc(var(--spacing-tabbar)+0.75rem)]`), top right under the header from 1024. `role="status"`. Classes: `rounded-control border border-gold/40 bg-emerald-900 px-4 py-3 text-sm text-fg shadow-modal motion-safe:animate-[toast-in_200ms_ease-out]`. Use it only where a real event happens and no inline feedback exists (for example, invite link copied). Do not use it for achievements or promotions.

### 2.7 Navigation (`app/layouts/PublicLayout.tsx`)

**Shell.** Remove the outer frame and `sm:p-4 lg:p-6` padding (this ends the black ring around the app). `.club-room` wraps everything; home keeps `HomeBackdrop` behind it.

**Left rail, 1024 and up** (`hidden lg:flex`):

```
rail:      sticky top-0 flex h-dvh w-rail-icons shrink-0 flex-col gap-6 border-r border-gold/20 bg-emerald-900 px-3 py-4 xl:w-rail
brand:     flex h-10 items-center gap-2.5 px-2 font-bold text-fg (logo 32 px, name hidden at w-rail-icons)
link:      relative flex min-h-11 items-center gap-3 rounded-control px-3 text-sm font-medium transition-colors duration-150
active:    aria-[current=page]:bg-emerald-800 aria-[current=page]:text-fg aria-[current=page]:shadow-[inset_2px_0_0_var(--color-gold)]
inactive:  text-muted hover:bg-emerald-800/70 hover:text-fg
```

Order: Home, Games, then the label "Games" and the five games with `GameIcon`, then (signed in) Wallet, Ranks. Bottom: Language, Settings. The collapse button sits at the very bottom (`aria-expanded`, `localStorage` key `rail`); label in a `Tooltip` when collapsed. At `lg`–`xl` the rail is icons-only by default, `xl` expands; the user toggle overrides.

**Header** (content column): `sticky top-0 z-20 flex h-header items-center justify-between gap-3 border-b border-gold/20 bg-emerald-900 px-3 sm:px-6`. Below 1024 it holds the logo and name on the left (name truncates; balance and avatar always stay). Right: `FishkaBalance`, `UserMenu`. Guests: Log in (`min-h-11` link) and Sign up (gold, `whitespace-nowrap`). The hamburger and the `Drawer` go away: the bar and rail hold everything (§2.8).

**Phone bottom bar, below 1024:** now shown to everyone.

```
bar:    fixed inset-x-0 bottom-0 z-20 flex border-t border-gold/20 bg-emerald-900 pb-[env(safe-area-inset-bottom)] lg:hidden
link:   flex min-h-16 flex-1 flex-col items-center justify-center gap-1 text-xs font-medium text-muted
active: text-gold-light (aria-current="page"); icon gets the same colour
```

Signed in: Home, Games, Wallet (`nav.walletShort`), Ranks, Profile. Guest: Home, Games, Log in. `pb-tabbar lg:pb-0` on the page container. Widths checked against 78 px per slot (§8). History moves under Profile.

**At a table** (`/rooms/:id`): no rail and no bar. `PublicLayout` renders no header; `RoomPage` renders `TableBar`: `sticky top-0 z-20 flex h-header items-center gap-3 bg-emerald-900 px-3 sm:px-6` with Back, game name, `GroupBadge` and stake, then `FishkaBalance` (`data-fishka-balance` is still what the flying chips aim at). `ActiveGamesBar` stays where it is.

`UserMenu`: panel `rounded-card border border-border bg-emerald-800 p-1.5 shadow-raised`, items `min-h-11`. Add `Wallet` and `Ranks` to it. `Language` remains in the rail bottom and in the footer on phones (`LanguageSwitcher`, `min-h-11`).

Logo: `config.data.logo_url ?? '/favicon.svg'`. Until the owner chooses a brand (open question), re-colour `public/favicon.svg` to gold dice on emerald (`#e2b84f`, `#1e2a24`, as in the preview). Change no copy.

### 2.8 `FishkaBalance.tsx`

`relative flex min-h-11 items-center gap-1.5 rounded-full border border-gold/45 bg-emerald-900/80 py-1 pr-3 pl-1.5 text-sm font-bold text-gold-light shadow-inner transition-colors duration-150 hover:border-gold`. Delta chip: `bg-emerald-900 text-success` / `text-danger` instead of `bg-black/80 text-beaten / text-take`. Behaviour, `aria-label`, `data-fishka-balance` and the bump stay. Never shown in cash terms; no currency sign.

### 2.9 `LevelBadge.tsx` and ranks

Sizes stay (`sm` becomes `h-[18px] text-[0.6875rem]` for legibility). `tier()` becomes `tierOf(level)` in **new** `features/levels/ranks.ts` returning `{ key, classes }`, with today's thresholds (1, 10, 20, 30, 50, unchanged), and gradients: bronze `from-[#8f5a2c] to-[#6e421c] text-white`, silver unchanged (`text-black`), gold `from-gold-light to-gold-dark text-ink`, ruby `from-[#b83a49] to-[#8a1c2a] text-white`, amethyst `from-[#7d52c4] to-[#4f2489] text-white`. Bronze's top stop and amethyst's top stop change to pass 4.5:1 (§1.3); ruby top too. Rank names come from i18n (§8).

### 2.10 Poster card (**new** `features/catalog/components/GamePoster.tsx`, replaces `GameCard`)

```
card:  group relative flex flex-col overflow-hidden rounded-card border border-gold/30 bg-emerald-800 shadow-raised transition-[transform,border-color,box-shadow] duration-150 ease-soft hover:border-gold hover:shadow-[var(--shadow-raised),0_0_0_1px_var(--color-gold)] motion-safe:hover:-translate-y-1.5
art:   aspect-[3/3.4] overflow-hidden (existing GamePosterScene or thumbnail_url; object-cover; no zoom)
plate: surface-ivory, flat: rounded-none shadow-none px-3 pt-2.5 pb-3; name text-base font-bold leading-[1.375rem] text-ink; seats text-xs text-ink-soft via PlayerCount (real seat range, not "people")
quick: Button sm variant gold, absolutely placed above the plate: absolute right-2.5 bottom-[3.75rem]; shown on hover/focus at pointer widths (opacity-0 group-hover:opacity-100 group-focus-within:opacity-100, always visible under hover: none)
coming soon: plate says it; no Quick seat. Existing ivory chip stays
```

Quick seat opens a bottom sheet (`Modal` with `sheetOnPhone`) with the existing `PlayPanel` (`hero={null}`) for that game, last used mode and group preselected from `localStorage` (`play.last.<slug>`, on the device only, ignored when no longer valid). Signed out, Quick seat links to `/login?next=/games/<slug>`.

## 3. Fonts

Self-hosted, committed to the repo, **no new runtime dependency and no third-party request at runtime**. Faces: **Noto Sans** and **Noto Serif Display** (with **Noto Sans Armenian** and **Noto Serif Armenian** for hy), SIL OFL 1.1.

**Files:** `apps/web/src/assets/fonts/` (`noto-sans-latin.woff2`, `noto-sans-cyrillic.woff2`, `noto-sans-armenian.woff2`, `noto-serif-display-latin.woff2`, `noto-serif-display-cyrillic.woff2`, `noto-serif-armenian.woff2`, plus `OFL.txt`, which the licence requires alongside the fonts). Import from CSS by relative URL, so Vite fingerprints them and they cache forever.

**How to get them once** (a one-time authoring step, in a throwaway directory): request Google Fonts' CSS with a modern browser `User-Agent` (`https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400..700&display=swap`, and `Noto+Serif+Display:wght@600..800`, `Noto+Sans+Armenian:wght@400..700`, `Noto+Serif+Armenian:wght@600..800`), then download the `url(...)` of the `latin`, `cyrillic` and `armenian` subsets. Variable fonts, so one file covers all weights. Don't ship `latin-ext` and `cyrillic-ext` (344 KB and 184 KB); their characters fall back to the system font.

**`@font-face`** (top of `index.css`, outside `@theme`; note the Armenian range is trimmed so a stray hyphen doesn't pull in the Armenian file):

```css
@font-face {
  font-family: 'Noto Sans';
  font-weight: 400 700;
  font-display: swap;
  src: url('../assets/fonts/noto-sans-latin.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
@font-face {
  font-family: 'Noto Sans';
  font-weight: 400 700;
  font-display: swap;
  src: url('../assets/fonts/noto-sans-cyrillic.woff2') format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}
@font-face {
  font-family: 'Noto Sans';
  font-weight: 400 700;
  font-display: swap;
  src: url('../assets/fonts/noto-sans-armenian.woff2') format('woff2');
  unicode-range: U+0530-058F, U+FB13-FB17;
}
@font-face {
  font-family: 'Noto Serif Display';
  font-weight: 600 800;
  font-display: swap;
  src: url('../assets/fonts/noto-serif-display-latin.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
@font-face {
  font-family: 'Noto Serif Display';
  font-weight: 600 800;
  font-display: swap;
  src: url('../assets/fonts/noto-serif-display-cyrillic.woff2') format('woff2');
  unicode-range: U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}
@font-face {
  font-family: 'Noto Serif Display';
  font-weight: 600 800;
  font-display: swap;
  src: url('../assets/fonts/noto-serif-armenian.woff2') format('woff2');
  unicode-range: U+0530-058F, U+FB13-FB17;
}
```

`swap` keeps text visible at once. Fixed line heights in the tokens mean a font swap can't move the layout vertically, so metric-override fallbacks aren't worth their code.

**Preload one file** in `index.html`: Noto Sans Latin (`<link rel="preload" href="/src/assets/fonts/noto-sans-latin.woff2" as="font" type="font/woff2" crossorigin>`). It is the one file every locale uses: digits, Latin usernames.

**Download cost, measured from the actual files.** The browser fetches only what the page uses; Serif loads only on pages with display type (home, reward moments, Ranks).

| Locale | Files on a page with display type | Size (compressed, `woff2`) |
|---|---|---|
| en | sans latin 35.0 + serif latin 37.8 | **73 KB** |
| ru | + sans cyrillic 19.6 + serif cyrillic 55.9 (latin also loads for digits, Latin usernames) | **148 KB** |
| hy | + sans armenian 32.7 + serif armenian 33.8 (sans latin also loads; serif latin only if the page's display text contains Latin) | **up to 139 KB** |
| Any page without display type | sans only | 35 KB en / 55 KB ru / 68 KB hy |

Total in the repo: 214 KB of `woff2` and `OFL.txt`. Nothing is added to the JS bundle.

**Verified on the real files:** Noto Sans digits have one uniform width with or without `font-variant-numeric: tabular-nums`, so `tabular-nums` keeps working; Armenian renders from the Armenian file; the preview loads both Latin faces. Armenian rendering was not reviewed by a native reader.

## 4. Pages

Widths: **1440** (rail 240, column `Container` 1152 max), **820** (no rail, bottom bar), **390** (phone, 16 px gutters). All pages get the §1 tokens at once in Step 1; only the layout changes are listed here. Unless stated, keep the existing data, states (loading, error, empty) and `aria`.

**Standard page header** (Wallet, Profile, History, Settings, Ranks): `h1` `text-3xl sm:text-4xl font-bold text-gold-gradient`, description `text-muted mt-1`, `mb-6`.

### 4.1 `/` (guest home) · `LandingPage.tsx`, `HomeBackdrop.tsx`

| | Layout |
|---|---|
| **1440** | Rail 240 + column. Picture fixed behind (relit, §6). Hero left-aligned in the column, `min-h-[40rem]`, `.home-hero` scrim. Five-poster panel overlapping the bottom by 5rem (`-mt-20`), then ivory "how it works" tiles inside the same panel. `surface-walnut` ladder band. Footer `emerald-900` |
| **820** | No rail. Hero centred, `min-h-[32rem]`, padding-bottom 7rem. Poster row scrolls sideways, 32% each, `snap-x`. "How it works" tiles are `bg-emerald-900/60` with an ivory medal (not ivory fill) so ivory stays under its limit at this width |
| **390** | Hero centred, `text-display-sm`, gold CTA and secondary stacked (both `min-h-12`, full width up to `max-w-xs`). Poster row snap at 62% each. Ladder five columns 68 px wide, `text-[0.8125rem]` names |

Hero copy order: eyebrow (`landing.eyebrow`, gold-light pill, stays), serif `h1` (`landing.title`), `sub` in cream (not `muted`), gold "Create a free account", secondary "Browse games". `LandingPage` no longer uses `.home-scope-night`; remove it, `.home-frame` and `.home-room` (the room shell is the plain `.club-room` with `HomeBackdrop` behind it, drawn fixed behind the header). Panels over the picture are `.club-panel-glass` (panel colour at 92%; the worst case behind it, the brightest felt, keeps cream 6.40 and mint 5.08, and a lamp keeps 6.05 and 4.80).

### 4.2 `/dashboard` · `DashboardPage.tsx`

Order: `Player bar` over the picture, `Today`, `Continue` (only when there is something to continue), Games (posters), `Try next`, `Play with a friend`, `Records`. No hard-coded `bg-black/*` bands: panels are `.club-panel-glass`.

| | Layout |
|---|---|
| **1440** | Player bar: `Avatar` (`lg`) + welcome line (`font-display`, 36 px, gold gradient) + `LevelProgress` in one row, Fishka balance is in the header. Today card and Continue side by side (`grid-cols-2`). Then Games, one row of five. Try next and Play with a friend side by side. Records as `GameRecord` tiles (existing) in `grid-cols-3`, link to Profile |
| **820** | Today and Continue stack. Poster row scrolls. Records `grid-cols-2` |
| **390** | Single column; Player bar stacks avatar/name over `LevelProgress`. Poster row snaps 62% |

`Today`: inline daily bonus card (`useDailyBonus`, `openDailyBonus`): gold `daily.collect` while unclaimed; once collected, `daily.collected` with a secondary `daily.open` button (same as today's Wallet card). The popup stays exactly as it fires today, and never over a table. `Continue`: `fetchMyRooms` first live room with the game's art, else the last finished game ("Play again") from `fetchMyGames`. `Try next`: first catalogue game with no row in `fetchMyStats`; if all played, the lowest `played`. Label it "Try next". Don't call it personalised. Hide if only one game exists.

### 4.3 `/games` · `GamesPage.tsx`, `GameGrid.tsx`, `GameLobby.tsx`

Replaces the two-column `GameCarousel` shelves with one poster grid, keeping `CategoryPills` as the secondary filter on a `surface-walnut` strip (`rounded-card px-4 py-2`). Filtering reorders the same posters; no separate "Board/Card" carousels.

| | Layout |
|---|---|
| **1440** | `h1` + subtitle (`catalog.subtitle` loses "to read its rules", §8). Walnut filter strip. Five posters, one row; featured (admin `sort_order` first) is a double-width tile with the poster's wide art |
| **820** | `grid-cols-3` posters, featured spans 3 wide |
| **390** | `grid-cols-2` posters (`gap-3`), featured spans 2 wide. Filter strip scrolls sideways as today |

Keep `data-game-card` on every poster (tests and flying chips rely on it). `GameCarousel` keyboard handling is no longer needed for shelves; keep Tab order natural (it's now a grid). Skeleton matches: five `aspect-[3/3.4]`.

### 4.4 `/games/:slug` · `GamePage.tsx`, `PlayPanel.tsx`

Play first: PlayPanel moves up, rules and records move below.

| | Layout |
|---|---|
| **1440** | Two columns `lg:grid-cols-[1.15fr_1fr]`. Left: `h1` gold (no `uppercase`), description, then `GameArtwork` (`aspect-[16/9]`, `rounded-panel`, `border-gold/30`, no black gradient over it; the record `dl` moves into a `.club-panel` strip beneath it). Right: PlayPanel `ModeCard`s in `.club-panel` (medal `.club-medal`, gold is the main "Find a match" button; bots and friends `outline`/`secondary`). Below: Rules and Modes panels |
| **820** | Single column, artwork capped `max-h-64`, PlayPanel directly below |
| **390** | Same; "Find a match" `w-full min-h-12`. Group picker cards `grid-cols-2`. Long text moves below the play area, so the first screen is name + art + play |

Deletes `text-gold-gradient uppercase` on the title in favour of gold gradient without caps. The art's `bg-linear-to-t from-black/85` overlay goes away (numbers no longer sit on art; this also fixes UX-010, where numbers overlapped the art).

### 4.5 `/rooms/:id` (shell) · `RoomPage.tsx`

The table's own surface is in §5. Shell: `main` gets `.table-room`. `TableBar` (§2.7). `RoomReactions` is restyled through tokens. Game over: the `GameOverPanel` no longer pushes the table down: at 1024 and up it floats above the board (`absolute inset-x-0 top-2 z-20 mx-auto w-full max-w-xl`), at 390 it is a bottom sheet (`Modal` `sheetOnPhone`, non-dismissing backdrop off, "Back to lobby" closes). "Play again" gold; the XP line (`level.toNext` style) sits next to it. Never auto-seats. Waiting and matchmaking states (`WaitingTable`, `MatchmakingStatus`) restyle through tokens; layout unchanged.

### 4.6 `/wallet` · `WalletPage.tsx`

| | Layout |
|---|---|
| **1440** | Single column `max-w-3xl`. Balance: a `surface-ivory rounded-panel` plate (`FishkaIcon` + "Balance" caption + 36 px figure in `text-ink tabular-nums`), the only ivory on this page. Earned/spent as two `bg-emerald-900/40` tiles inside `.club-panel`. `DailyBonusCard` gold button if unclaimed. History list `.club-panel` rows, gain/loss `text-success`/`text-danger` |
| **820** | Same, `max-w-none` |
| **390** | Same, single column; balance row wraps button below |

Copy keeps saying what Fishkas are ("Fishkas are free play money"). Measured (ivory plate applied in the browser): L\* 37/39/40 (1440/820/390), light under 25%.

### 4.7 `/profile`, `/u/:username` · `ProfilePage.tsx`, `PublicProfilePage.tsx`

| | Layout |
|---|---|
| **1440** | `ProfileView` header in `.club-panel` (avatar `lg` + name + rank badge + rank name from `ranks.ts`), `LevelCard` beside it (`lg:grid-cols-[1fr_1.1fr]`). Records `grid-cols-3`. On `/profile` only: a small ivory "rank plaque" (rank name in serif) so the page lifts into range. Recent games list |
| **820** | Stacks; Records `grid-cols-2` |
| **390** | Stacks; on `/profile` add links to History and Settings as `secondary` rows (`min-h-11`), since the tab bar no longer has them (§2.7) |

Public profile shows no private data; it reuses the same layout, no rank plaque.

### 4.8 `/login`, `/register` · `AuthCard.tsx`

**1440:** centred `AuthCard` `max-w-md`, panel on the field. **820/390:** same, full width within gutters, `min-h-11` inputs. Card classes `rounded-panel border border-gold/25 bg-surface p-6 sm:p-8 shadow-panel`; h1 gold gradient. **Sign up** for guests goes through `ButtonLink variant="gold"`. Measured: 34–38 mean L\*, near-black 0, against 11 and 79% today. `CodeField` and `ResendCodeButton` through tokens.

### 4.9 `/verify`, `/forgot-password`, `/reset-password`

Same `AuthCard`; no layout change. Tokens only.

### 4.10 `/history` · `HistoryPage.tsx`

Header, then `RoomSummary` rows as `.club-panel` list (`divide-y divide-gold/10 rounded-panel`). 390: rows stack result above date. Pagination `secondary` buttons, 44 px.

### 4.11 `/settings` · `SettingsPage.tsx`

Header, then the existing forms (`ProfileForm`, `AvatarForm`, `PreferencesForm`, `PasswordForm`, `ContactSettings`), each a `Card`, `max-w-3xl`. No ivory (forms stay emerald, per the research). Measured L\* 34/35/36.

### 4.12 `/join/:code`, `/pages/:slug`, 404, route error, maintenance

Join: `Card` centred with the invite and Join (gold). Content page: `max-w-3xl` prose (`text-fg`, links `text-accent-text`). 404, route error, maintenance: centred panel with `.club-medal`, and a "Back home" gold button. Tokens only.

### 4.13 `/ranks` (**new**, signed-in and guest) · new `pages/RanksPage.tsx`, route in `app/routes.tsx`

Five tiers as a `surface-walnut` band (one row of five badges; 390: five columns, 68 px, as in the preview), then (signed in) `LevelCard` and the next reward from `useLevel().data.next_reward`. Copy: tier name, level range, no perks beyond what exists. **Does not list each level's reward:** that needs a read endpoint (§9). Do not hardcode reward data. Guest: tiers only and a gold "Sign up".

## 5. Games

Rules and behaviour never change. Only the surface and framing. Tables may stay dark on purpose; `.table-room` is behind every table.

| Game | Surface | Framing |
|---|---|---|
| **Poker** (`PokerBoard`, `HandResult`) | Keep the oval and the phone layout (TASK-058). `.poker-stage` walnut `#2b1b12`→`#3f2a1c`, `.poker-felt` emerald (§1.2), chairs walnut | `.poker-rail` unchanged. Seat name plates, pot and blinds stay on dark plates. The action buttons keep their colours (red Fold, gold Check/Call, as now): they are game controls, not page actions, and the "one gold per view" rule does not apply at a table |
| **Durak** (`DurakBoard`, `DurakSeat`, `DurakHand`, `TablePiles`) | Keep the rectangular felt and `.table-rim` wood; `.table-felt` re-tinted (§1.2) | The role bubbles keep their colours. Hand and action button unchanged; "Take" gold, keep 44 px |
| **Nardi** (`NardiBoard`, `NardiModePreview`) | **Walnut stays exactly as it is** (owner decision, TASK-057): `.nardi-*` untouched | Seats, dice and pips sit on `.club-panel` / `bg-black/25` strips. Only the surround changes (`.table-room`) |
| **Shashki / Checkers** (`CheckersBoard`) | Classic `#e3d0ac` / `#8b7355` stays; `.table-rim` wood frame | "Move: White" heading uses `text-fg`; the rest via tokens |
| **Chess** (`ChessBoard`) | Same board colours and wood frame | Moves panel `.club-panel` |
| **Playing cards** | `bg-card` ivory-50 (`--color-card`), `.card-back` claret (already) | Unchanged |
| **Waiting table** (`WaitingTable`) | Oval `.table-rim` + `.table-felt` | Seats and copy through tokens |
| **Chat, reactions** | `TableChat` `.club-frame`, `ReactionPicker` via `Modal` | Through tokens; keep emoji; no new channels |

Seats on every table: avatar, level badge, frame, name, Fishkas, turn ring as today (`var(--color-turn)` unchanged). `aria-live` turn announcements stay. Measured with the tokens applied (finished rooms): 1440 L\* 24–28, near-black 0–9% (poker's stage and shadows). This is the designed exception, not a failure.

## 6. Owner preview

Files: `docs/design/preview-048-home.html` (self-contained: inline CSS, inline SVG, embedded fonts; open it in any browser), screenshots `docs/design/preview-048-home-1440.png`, `-390.png` and the scrolled views `-1440-scrolled.png`, `-390-scrolled.png`. Only the `/` guest page; `/dashboard` uses the same picture and panels.

**What the owner should be told, plainly:** it is the same room you chose: same table, same three lamps, same cards and chips. What changed is that the lights are on.

| Today | Preview | Why |
|---|---|---|
| Wall burgundy to almost black (`#1a0a10`–`#4a1420`) | Green panelled wall, `#114c3e` to `#15604d`; claret kept as a thin strip | Green is the owner's palette; this is what stops it feeling "too dark" |
| Vignette to 90% black | At most 35% | Black edges were most of the near-black |
| Near-black floor (`#0c0506`) | Walnut carpet, same brass pattern | |
| Three lamps with weak glow | Same lamps, glow opacity up from 0.7 to 0.95, cone up from 0.3 to 0.55, and a warm pool on the wall behind each lamp | This is what "lit" means |
| Near-black header and panels (`bg-black/75`) | `emerald-900` header, emerald panels at 92%, room shows through | |
| The hamburger menu | Permanent left menu at 1440, bottom bar on phones | Nothing hidden |
| Art inside dark cards | Poster cards with ivory name plates and Quick seat | |

**Measured (mean L\* / near-black / light over L\* 70):**

| | Today | Preview | Target |
|---|---|---|---|
| First view, 1440 | 16 / 60% / 3% | 37 / 0% / 10% | 35–45 / ≤ 10% / ≤ 25% |
| First view, 820 | 21 / 52% / 9% | 43 / 0% / 18% | same |
| First view, 390 | 22 / 50% / 10% | 44 / 0% / 17% | same |
| Scrolled, 1440 / 390 | n/a | 42, 43 / 0% / 20%, 18% | same |
| Picture alone | not measured | 43 at 1440, 50 at 390 / 5% near-black | L\* ≥ 30, near-black ≤ 15% |

**Not mostly white and not near-black:** ivory is limited to small plates and tiles (light 10–20% of the screen). Text over the picture, from the pixels actually behind it (worst pixel): headline at 1440 3.65:1 (large), at 390 5.08:1; subtitle cream 7.81:1 at 1440, 5.26:1 at 390; eyebrow 7.30:1 or more.

**To show the owner:** open the HTML or the four PNGs, side by side with the running `/` today. Ask one question: *"Is this the room you want?"* If the owner still wants it darker, only the `HomeBackdrop` wall, vignette and lamp values change (Step 6); nothing else is affected.

**The preview is a picture.** No real data, no login, no language switching (English only, Latin fonts embedded). Hover and Quick seat are not wired.

## 7. Build order

Each step ends with `make lint` and `make test` green, a live look at 1440, 820 and 390, and is committed on its own. Screenshot and L\* check (§6) at each step.

| Step | Do | Leaves the app |
|---|---|---|
| **1. Tokens and surfaces** | §1.1, §1.2 (except `.home-*` removal), §1.3 fixes, `index.html` meta, Armenian caps/size rule | Every page green at once, gold actions, nothing moved. Largest single change |
| **2. Fonts** | §3 | Same, new type |
| **3. Primitives** | `Button`, `Field`, `Card`, `Badge`, `Alert`, `Segmented`, `Skeleton`, `States`, `Avatar`, `LevelBadge`, `ranks.ts`, `FishkaBalance`, `UserMenu` | Controls consistent, 44 px |
| **4. Modal, Tooltip, Toast** | §2.4–2.6; migrate `DailyBonusDialog`, `LevelUpDialog`, `OutOfFishkasDialog`, `ReactionPicker`. Keep the old `Drawer` until Step 5 | Popups consistent; behaviour same |
| **5. Shell** | Rail, header, bottom bar for everyone, remove Drawer and outer frame. Tables get `TableBar` | Everything reachable; navigation visible |
| **6. Home** | Relit `HomeBackdrop` (`§6` values), `LandingPage`, remove `.home-*`. **Show the owner before going further** (§6) | `/` matches preview |
| **7. Lobby and game pages** | `GamePoster`, `GameLobby`, `GamePage`, Quick seat; update `GamesPage.test.tsx`, `GamePage.test.tsx` and any test asserting the shelves | Lobby is posters |
| **8. Dashboard** | §4.2 sections | Signed-in home |
| **9. Tables** | §5, `.table-room`, `TableBar`, `GameOverPanel` floating | Games match; rules untouched |
| **10. Remaining pages** | Wallet, Profile, History, Settings, Auth, Join, Content, Ranks (new), error pages, polish | Everything done; §8 keys in all three languages |

Each step can ship and be reviewed on its own. If the owner rejects the relit picture at Step 6, only the `HomeBackdrop` values change.

## 8. Copy keys (new or changed), en / ru / hy

Add these to `shared/i18n/locales/*.json`. Numbers measured with the real fonts at the given sizes, longest of en/ru/hy shown. Armenian wording should get a native check: I can verify width, not quality.

| Key | en | ru | hy | Width check |
|---|---|---|---|---|
| `nav.ranks` | Ranks | Ранги | Կոչումներ | 64 of 70 px at 13 px |
| `nav.walletShort` (tab bar) | Wallet | Кошелёк | Ֆիշկաներ | Today's hy "Դրամապանակ" is 101 px, too wide for the 78 px slot; this is about 58 |
| `ranks.title` | Club ranks | Ранги клуба | Ակումբի կոչումներ | |
| `ranks.bronze`, `.silver`, `.gold`, `.ruby`, `.amethyst` | Bronze, Silver, Gold, Ruby, Amethyst | Бронза, Серебро, Золото, Рубин, Аметист | Բրոնզ, Արծաթ, Ոսկի, Ռուբին, Ամեթիստ | Longest "Ամեթիստ" 67 of 68 px in the 390 ladder |
| `ranks.levels` | Levels {{from}}–{{to}} | Уровни {{from}}–{{to}} | Մակարդակներ {{from}}–{{to}} | Phone ladder wraps to two lines, allowed |
| `lobby.quickSeat` | Quick seat | Быстро за стол | Արագ խաղ | 98 px max, fits |
| `home.today`, `home.continue`, `home.tryNext`, `home.friend` | Today, Continue, Try next, Play with a friend | Сегодня, Продолжить, Попробуйте ещё, Играть с другом | Այսօր, Շարունակել, Փորձեք նաև, Խաղալ ընկերոջ հետ | |
| `catalog.subtitle` (changed) | Choose a game. | Выберите игру. | Ընտրեք խաղ։ | Today's text says "to read its rules", no longer true |

Other widths checked with the real fonts: rail labels fit 160 px in all three languages (longest hy "Խաղերի պատմություն" 159, close: it can't wrap, it truncates with `truncate` as a safeguard); `Sign up` fits at 390 even in ru ("Регистрация" 89 px); the hero button fits (234 of 302 px in ru). **The hero headline wraps at 1440 in hy** ("Ձեր խաղն արդեն սպասում է։" 826 px at 56 px against 704 available) and at 390 in all three languages, by design: don't shrink it, let it wrap with `text-balance`; the hero has room. Hy line 2 is also longer than the preview's English; test the real string at Step 6.

## 9. Reserved slots, needing the owner's approval

Not designed, not built, not drawn as if they work. The brief lists them, but the backend has none of them and each is a new domain (`docs/team/README.md`).

| Feature | Slot, only if approved | Needs |
|---|---|---|
| Leaderboards | A section below Records on `/dashboard` and a rail entry | New backend |
| Friends | Rail entry | New backend |
| Daily missions | Under Today on `/dashboard` | New backend, validated rewards |
| Achievements | A row on `/profile` | New backend |
| Each level's reward on `/ranks` | Reward list per tier | Small additive read endpoint (no new domain; PM to decide) |
| Popular games, per-game counts on posters | A line on the poster plate | Small read endpoints |

Do not ship placeholders, "Coming soon" tiles or empty sections for these. Site-wide online count (`/stats/online`, in the API schema but not used in the app today): only above the threshold from TASK-025; below it, show nothing.

## 10. Acceptance

- Every page measured in §6's way at 1440 and 390: mean L\* 35–45 (see the note), near-black ≤ 10%, light over L\* 70 ≤ 25%. Game tables excepted.
| Page (1440 / 390 unless noted) | Today, mean L\* / near-black | With the tokens, mean L\* / near-black (measured on the real page in the browser) |
|---|---|---|
| `/games` | 20 / 49% | 38 / 0%, 43 / 0% |
| `/games/poker` (old layout) | not measured | 33 / 6%, 34 / 9% (old layout; Step 7 shrinks the dark art) |
| `/wallet` (ivory plate applied) | 12 / 60% | 37 / 0%, 40 / 0% |
| `/profile` | not measured | 34 / 0%, 36 / 0% |
| `/history` | not measured | 33 / 0%, 36 / 0% |
| `/settings` | 12 / 68% | 34 / 0%, 36 / 0% (820: 35) |
| `/login` | 11 / 79% | 34 / 0%, 36 / 0% (820: 37) |
| `/register` | not measured | 35 / 0%, 38 / 0% |

- Note: with the rail, panel-only pages (History, Settings, Profile, Login) measured 33–35 at 1440 and 36–38 at 390, one to two points below target at desktop. The rail and header cover about 22% at that width. I recommend accepting 33 at 1440 for those pages, or the PM can raise the panel step further, at some cost in contrast margins.
- Every text pair is in §1.3 or fixed there. No `text-[#…]` remains except game art.
- Ivory never over 25% of a viewport at 1440, 820 or 390.
- No `uppercase` effect in hy (check a screenshot). All strings in en, ru and hy.
- 44 px targets, visible focus, keyboard through rail, tabs and `Modal`, `prefers-reduced-motion` honoured.
- Admin area is out of scope and must not regress: tokens are shared, so check it after Step 1.

## 11. What was not checked

- Live gameplay turn controls were seen only at the moment each game started; a full hand or move sequence was not played through. Durak, Poker, Nardi, Checkers and Chess finished rooms were seen with the tokens applied via the browser. Waiting table, matchmaking search and the level-up / out-of-Fishkas popups were read from code, not opened.
- The `/dashboard` new sections, `GamePoster`, `Modal`, `Tooltip` and `Toast` exist only in this spec and the preview; the rail was simulated by a coloured strip in the measurements. Numbers for those will move slightly when built.
- `/verify`, ru screens and hy layouts on real pages, keyboard-only and screen reader passes, and real phones were not checked (only headless Chrome at 390 and 820 at scale 1).
- The daily bonus popup did not open (my account's streak starts today); read from code.
- Armenian translations of new keys were written by me and need a native check. No hy page was seen with the new fonts on real content; the preview is English only.
