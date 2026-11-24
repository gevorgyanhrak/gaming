/**
 * The symbols of the AGT-style games (owner, 2026-11-24: "symbols similar to the originals"), drawn here as SVG in
 * the classic glossy fruit-machine style — our own drawings, no copied art. Each is a 100×100 picture; gradient ids
 * are prefixed with the symbol's name so many can share a page.
 */

type Draw = (id: string) => string

const gloss = (id: string) =>
  `<radialGradient id="${id}-g" cx="35%" cy="28%" r="55%"><stop offset="0" stop-color="#fff" stop-opacity=".85"/><stop offset=".35" stop-color="#fff" stop-opacity=".15"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>`

const ball = (id: string, cx: number, cy: number, r: number, light: string, dark: string) =>
  `<radialGradient id="${id}" cx="35%" cy="30%" r="70%"><stop offset="0" stop-color="${light}"/><stop offset="1" stop-color="${dark}"/></radialGradient><circle cx="${cx}" cy="${cy}" r="${r}" fill="url(#${id})" stroke="rgba(0,0,0,.35)" stroke-width="1.5"/>`

const leaf = (x: number, y: number, rot: number) =>
  `<path d="M${x} ${y} q10 -14 24 -10 q-6 14 -24 10z" fill="#3fae3a" stroke="#1d5e1c" stroke-width="1.5" transform="rotate(${rot} ${x} ${y})"/>`

const SYMBOLS: Record<string, Draw> = {
  seven: (id) => `
    <defs><linearGradient id="${id}-r" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff6b5b"/><stop offset=".55" stop-color="#d4141c"/><stop offset="1" stop-color="#7d0610"/></linearGradient>${gloss(id)}</defs>
    <path d="M18 14h66v14L50 90H30l30-58H18z" fill="url(#${id}-r)" stroke="#ffd35a" stroke-width="5" stroke-linejoin="round"/>
    <path d="M18 14h66v14L50 90H30l30-58H18z" fill="url(#${id}-g)"/>`,
  bell: (id) => `
    <defs><linearGradient id="${id}-b" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff3a8"/><stop offset=".5" stop-color="#f5bd1f"/><stop offset="1" stop-color="#a86a05"/></linearGradient>${gloss(id)}</defs>
    <path d="M50 12c-6 0-8 4-8 7-14 4-20 16-20 30v14l-8 10h72l-8-10V49c0-14-6-26-20-30 0-3-2-7-8-7z" fill="url(#${id}-b)" stroke="#7a4a02" stroke-width="2.5"/>
    <circle cx="50" cy="80" r="8" fill="#c07a08" stroke="#7a4a02" stroke-width="2"/>
    <path d="M50 12c-6 0-8 4-8 7-14 4-20 16-20 30v14l-8 10h72l-8-10V49c0-14-6-26-20-30 0-3-2-7-8-7z" fill="url(#${id}-g)"/>`,
  cherry: (id) => `
    <defs>${gloss(id)}</defs>
    <path d="M60 14c-6 18-22 28-32 40M60 14c2 18 8 28 14 36" fill="none" stroke="#3d7a1f" stroke-width="4" stroke-linecap="round"/>
    ${leaf(60, 16, -10)}
    ${ball(`${id}-c1`, 30, 66, 19, '#ff6a6a', '#8a0010')}${ball(`${id}-c2`, 70, 62, 19, '#ff6a6a', '#8a0010')}
    <circle cx="24" cy="58" r="6" fill="#fff" opacity=".7"/><circle cx="64" cy="54" r="6" fill="#fff" opacity=".7"/>`,
  strawberry: (id) => `
    <defs><radialGradient id="${id}-s" cx="40%" cy="35%" r="70%"><stop offset="0" stop-color="#ff7d7d"/><stop offset="1" stop-color="#a10a1c"/></radialGradient>${gloss(id)}</defs>
    <path d="M50 92C26 76 16 52 22 36c6-12 22-12 28-6 6-6 22-6 28 6 6 16-4 40-28 56z" fill="url(#${id}-s)" stroke="#5e0410" stroke-width="2"/>
    ${[...Array(10)].map((_, i) => `<ellipse cx="${32 + (i % 4) * 12 + (i % 2) * 2}" cy="${44 + Math.floor(i / 4) * 14}" rx="2" ry="3" fill="#ffe27a"/>`).join('')}
    <path d="M32 30l8-14 10 10 10-10 8 14z" fill="#3fae3a" stroke="#1d5e1c" stroke-width="2"/>
    <path d="M50 92C26 76 16 52 22 36c6-12 22-12 28-6 6-6 22-6 28 6 6 16-4 40-28 56z" fill="url(#${id}-g)"/>`,
  blueberry: (id) => `
    <defs>${gloss(id)}</defs>
    ${ball(`${id}-a`, 34, 62, 18, '#8fa8ff', '#1a2a8a')}${ball(`${id}-b`, 66, 62, 18, '#8fa8ff', '#1a2a8a')}${ball(`${id}-c`, 50, 38, 18, '#9db3ff', '#20309a')}
    <path d="M46 26l4-6 4 6" fill="none" stroke="#0c1450" stroke-width="3"/>
    <circle cx="44" cy="31" r="5" fill="#fff" opacity=".6"/><circle cx="28" cy="55" r="5" fill="#fff" opacity=".5"/><circle cx="60" cy="55" r="5" fill="#fff" opacity=".5"/>`,
  plum: (id) => `
    <defs><radialGradient id="${id}-p" cx="38%" cy="32%" r="70%"><stop offset="0" stop-color="#d58cff"/><stop offset="1" stop-color="#4a0a6e"/></radialGradient>${gloss(id)}</defs>
    <ellipse cx="50" cy="56" rx="32" ry="34" fill="url(#${id}-p)" stroke="#2a0540" stroke-width="2"/>
    <path d="M50 24c-2 14 2 40 0 64" fill="none" stroke="#3a0758" stroke-width="2" opacity=".6"/>
    <path d="M50 24l2-10" stroke="#5a3a14" stroke-width="4" stroke-linecap="round"/>${leaf(52, 16, 0)}
    <ellipse cx="50" cy="56" rx="32" ry="34" fill="url(#${id}-g)"/>`,
  pear: (id) => `
    <defs><radialGradient id="${id}-p" cx="38%" cy="40%" r="70%"><stop offset="0" stop-color="#e8ff7a"/><stop offset="1" stop-color="#6e9a0c"/></radialGradient>${gloss(id)}</defs>
    <path d="M50 18c10 0 12 14 14 22 6 6 16 14 16 28 0 16-14 24-30 24S20 84 20 68c0-14 10-22 16-28 2-8 4-22 14-22z" fill="url(#${id}-p)" stroke="#3d5806" stroke-width="2"/>
    <path d="M50 18l2-8" stroke="#5a3a14" stroke-width="4" stroke-linecap="round"/>${leaf(52, 12, -20)}
    <path d="M50 18c10 0 12 14 14 22 6 6 16 14 16 28 0 16-14 24-30 24S20 84 20 68c0-14 10-22 16-28 2-8 4-22 14-22z" fill="url(#${id}-g)"/>`,
  peach: (id) => `
    <defs><radialGradient id="${id}-p" cx="35%" cy="35%" r="75%"><stop offset="0" stop-color="#ffe0a0"/><stop offset=".55" stop-color="#ff9a4a"/><stop offset="1" stop-color="#c2361c"/></radialGradient>${gloss(id)}</defs>
    <circle cx="50" cy="56" r="34" fill="url(#${id}-p)" stroke="#7a2a0a" stroke-width="2"/>
    <path d="M50 24c-6 12-6 50 0 66" fill="none" stroke="#c2361c" stroke-width="2.5" opacity=".7"/>${leaf(50, 22, -30)}
    <circle cx="50" cy="56" r="34" fill="url(#${id}-g)"/>`,
  papaya: (id) => `
    <defs><radialGradient id="${id}-p" cx="40%" cy="40%" r="70%"><stop offset="0" stop-color="#ffd27a"/><stop offset="1" stop-color="#e2681c"/></radialGradient>${gloss(id)}</defs>
    <ellipse cx="50" cy="52" rx="24" ry="38" fill="#4c9a2a" stroke="#235a10" stroke-width="2" transform="rotate(-20 50 52)"/>
    <ellipse cx="52" cy="54" rx="18" ry="32" fill="url(#${id}-p)" transform="rotate(-20 52 54)"/>
    ${[...Array(9)].map((_, i) => `<circle cx="${46 + (i % 3) * 5}" cy="${44 + Math.floor(i / 3) * 8}" r="2.4" fill="#2a1a10"/>`).join('')}
    <ellipse cx="50" cy="52" rx="24" ry="38" fill="url(#${id}-g)" transform="rotate(-20 50 52)"/>`,
  grapes: (id) => `
    <defs>${gloss(id)}</defs>
    ${[
      [36, 34],
      [52, 32],
      [68, 36],
      [44, 48],
      [60, 48],
      [36, 62],
      [52, 62],
      [68, 60],
      [44, 76],
      [60, 76],
      [52, 88],
    ]
      .map(([x, y], i) => ball(`${id}-${i}`, x!, y!, 10, '#c89cff', '#4a1678'))
      .join('')}
    <path d="M52 22l4-12" stroke="#5a3a14" stroke-width="4" stroke-linecap="round"/>${leaf(56, 14, 10)}`,
  melon: (id) => `
    <defs>${gloss(id)}</defs>
    <path d="M10 46a40 40 0 0 0 80 0z" fill="#2f8f2a" stroke="#14500f" stroke-width="2"/>
    <path d="M15 46a35 35 0 0 0 70 0z" fill="#f0fff0"/>
    <path d="M19 46a31 31 0 0 0 62 0z" fill="#ff3b4c"/>
    ${[...Array(7)].map((_, i) => `<ellipse cx="${28 + i * 7.5}" cy="${56 + (i % 2) * 8}" rx="2" ry="3.4" fill="#1a1010"/>`).join('')}
    <path d="M10 46a40 40 0 0 0 80 0z" fill="url(#${id}-g)"/>`,
  orange: (id) => `
    <defs><radialGradient id="${id}-o" cx="38%" cy="32%" r="72%"><stop offset="0" stop-color="#ffd27a"/><stop offset="1" stop-color="#e2620c"/></radialGradient>${gloss(id)}</defs>
    <circle cx="50" cy="54" r="34" fill="url(#${id}-o)" stroke="#8a3a04" stroke-width="2"/>
    ${[...Array(14)].map((_, i) => `<circle cx="${30 + (i * 37) % 42}" cy="${36 + ((i * 23) % 38)}" r="1.4" fill="#c2560a" opacity=".6"/>`).join('')}
    <path d="M50 20l2-6" stroke="#5a3a14" stroke-width="4" stroke-linecap="round"/>${leaf(52, 16, -15)}
    <circle cx="50" cy="54" r="34" fill="url(#${id}-g)"/>`,
  lemon: (id) => `
    <defs><radialGradient id="${id}-l" cx="38%" cy="32%" r="72%"><stop offset="0" stop-color="#fffbb0"/><stop offset="1" stop-color="#d9b60a"/></radialGradient>${gloss(id)}</defs>
    <ellipse cx="50" cy="54" rx="36" ry="27" fill="url(#${id}-l)" stroke="#8a7004" stroke-width="2"/>
    <ellipse cx="50" cy="54" rx="36" ry="27" fill="url(#${id}-g)"/>`,
  bar: (id) => `
    <defs><linearGradient id="${id}-b" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3a3a3a"/><stop offset="1" stop-color="#0a0a0a"/></linearGradient></defs>
    <rect x="12" y="32" width="76" height="36" rx="6" fill="url(#${id}-b)" stroke="#ffd35a" stroke-width="4"/>
    <text x="50" y="60" text-anchor="middle" font-family="Arial Black,Arial,sans-serif" font-weight="900" font-size="26" fill="#ffd35a">BAR</text>`,
  scatter: (id) => `
    <defs><radialGradient id="${id}-s" cx="40%" cy="35%" r="70%"><stop offset="0" stop-color="#fff7b0"/><stop offset=".6" stop-color="#ffc21a"/><stop offset="1" stop-color="#c46a00"/></radialGradient></defs>
    <circle cx="50" cy="50" r="44" fill="#2a1a6a" stroke="#ffd35a" stroke-width="3"/>
    <path d="M50 12l10 26 28 2-22 17 8 27-24-16-24 16 8-27-22-17 28-2z" fill="url(#${id}-s)" stroke="#8a4a00" stroke-width="2" stroke-linejoin="round"/>`,
  wild: (id) => `
    <defs><linearGradient id="${id}-w" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff3a8"/><stop offset=".5" stop-color="#f2b81c"/><stop offset="1" stop-color="#9a5a02"/></linearGradient></defs>
    <rect x="6" y="20" width="88" height="60" rx="12" fill="#7a0a2a" stroke="url(#${id}-w)" stroke-width="5"/>
    <text x="50" y="62" text-anchor="middle" font-family="Arial Black,Arial,sans-serif" font-weight="900" font-size="30" fill="url(#${id}-w)" stroke="#4a0414" stroke-width="1.5">WILD</text>`,
}

/** The picture for a symbol (an unknown one: its name on a tile, so a new game never shows a blank). */
export function symbolSvg(name: string, id: string): string {
  const draw = SYMBOLS[name]
  const body = draw
    ? draw(id)
    : `<rect x="8" y="8" width="84" height="84" rx="14" fill="#1d2a52" stroke="#ffd35a" stroke-width="3"/><text x="50" y="58" text-anchor="middle" font-family="Arial,sans-serif" font-weight="700" font-size="16" fill="#fff">${name.toUpperCase().slice(0, 8)}</text>`
  return `<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">${body}</svg>`
}
