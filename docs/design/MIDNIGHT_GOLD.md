# Midnight Gold — the platform's identity (2026-11-17)

Owner brief: redesign the platform (home, header, banners, motion, type, colour, shared components) after
researching gaming and casino sites — **never the games** (tables, boards, rules stay exactly as they are).

## What the research said

- Casino and gaming sites default to dark chrome (deep charcoal or navy) with one strong hero offer rather than
  competing banners; the homepage reads as a lobby: header → nav → hero/promo → featured → categories.
  ([snapstudio guide](https://snapstudio-gzru.onrender.com/guides/online-casino-branding-ui-design/),
  [Limeup](https://limeup.io/blog/casino-website-design/))
- Luxury-leaning sites pair dark backgrounds with metallic accents; a display face for titles, a readable face for
  text; calmer layouts with stronger spacing are the current direction. (same sources;
  [hoteltechreport trends](https://hoteltechreport.com/he/resources/marketing/reports/websites/top-design-trends-for-casino-websites))
- GGPoker leans into video-game aesthetics — sharp graphics, modern colour schemes
  ([CogConnected](https://cogconnected.com/?p=378809)).
- Fonts: Unbounded is a display face (Latin + Cyrillic) for titles only; Manrope is a strong Cyrillic-capable text
  face and a known pairing ([madegooddesigns](https://madegooddesigns.com/unbounded-font-pairings/),
  [astastudio](https://astastudio.substack.com/p/8-cyrillic-fonts-are-pro-open-source-flawless)). Neither has
  Armenian: Noto Sans Armenian fills those letters per glyph.

Most public sources are marketing pieces; the choices below lean on what they agree on.

## The system

| | |
|---|---|
| Page | `night-850` #0d1222 with a violet and a gold light far up (`.club-room`) |
| Surfaces | panels `#151d34` with a 3.5 % top sheen and an 8 % white edge (`.club-panel`), popups `--frame-bg` |
| Primary | gold (`--btn-gold` gradient #ffe7a3 → #f5c04f → #d99a2b, ink #1a1205) — the one main action |
| Second light | violet #7c5cff (selection, eyebrows, glows) |
| Live | #34d399 (online, "playing now") |
| Text | #f6f3ec on night; muted #aab4cf (7:1 on panels); controls' edge #7584b3 (4.6:1) |
| Type | titles Unbounded (h1/h2 by default, −0.015em), text Manrope; Armenian from Noto Sans |
| Radius | control 14 px, card 18 px, panel 22 px, modal 28 px |
| Motion | rise-in on entry, hero title light sweep (6 s), aurora lights, fan float, hover lift + gold glow, banner Ken Burns + progress; all off under reduced motion |

Pieces: `HomeHero` (real numbers only: online, games, slots), `NightSky`, `SectionHeading`, `posterStyles`
(game and slot cards), `PromoBanners` (admin banners; nothing when none), header `SectionLinks` (from 1280 px).

## The games keep the Lamplit Room

`.classic-scope` (styles/index.css) holds every Lamplit Room token, the old fonts, radii, shadows and the
component variables (`--panel-*`, `--frame-*`, `--btn-*`, `--chrome-edge`). `PublicLayout` puts it on `<body>`
whenever a table is open, so the room and anything it opens (popups portal to body) look exactly as before.
Shared components that appear at tables take their look from those variables, never from fixed night colours.
Checked by pixel diff of finished chess and poker rooms before/after: only an animation frame and
anti-aliasing differ (0.019 % / 0.005 %).
