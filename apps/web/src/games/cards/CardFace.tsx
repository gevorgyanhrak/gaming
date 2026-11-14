import { rankText, suitOf, type Suit } from './cards'

/**
 * The face of a playing card, drawn as one SVG that scales with its box (every size, the same look): a large
 * index in two corners (rank over suit), the classic pip layouts on number cards, one big pip on an ace, and
 * a framed portrait on court cards — the English-pattern court art by Dmitry Fomin (CC0, Wikimedia Commons;
 * see courts/README.md), cropped to its frame. Suits are paths, not glyphs, so no system shows them as emoji.
 */

/** Card coordinates: 250 × 357 (the boxes' 1 : 1.43). */
const W = 250
const H = 357

/** Suit shapes in a 100 × 100 box, centred on 50, 50. */
const SUIT_PATHS: Record<Suit, string> = {
  H: 'M50 94C40 82 4 58 4 30 4 13 16 3 29 3c9 0 17 5 21 14C54 8 62 3 71 3c13 0 25 10 25 27 0 28-36 52-46 64Z',
  D: 'M50 2C60 20 74 36 90 50 74 64 60 80 50 98 40 80 26 64 10 50 26 36 40 20 50 2Z',
  S: 'M50 3C38 21 4 40 4 63c0 14 10 24 23 24 9 0 16-4 20-10-1 9-5 15-13 20h32c-8-5-12-11-13-20 4 6 11 10 20 10 13 0 23-10 23-24C96 40 62 21 50 3Z',
  C: 'M50 4c-12 0-21 9-21 21 0 6 2 11 6 15-3-1-6-2-9-2-12 0-22 9-22 21s10 22 22 22c9 0 16-5 20-12-1 11-5 19-14 26h36c-9-7-13-15-14-26 4 7 11 12 20 12 12 0 22-10 22-22S86 38 74 38c-3 0-6 1-9 2 4-4 6-9 6-15C71 13 62 4 50 4Z',
}

const RED = 'var(--color-suit-red)'
const BLACK = 'var(--color-suit-black)'

/** Pip centres for number cards, in the pip area (x: 0 left column, 0.5 middle, 1 right; y: 0 top … 1 bottom). */
const LAYOUTS: Record<string, [number, number][]> = {
  '2': [
    [0.5, 0],
    [0.5, 1],
  ],
  '3': [
    [0.5, 0],
    [0.5, 0.5],
    [0.5, 1],
  ],
  '4': [
    [0, 0],
    [1, 0],
    [0, 1],
    [1, 1],
  ],
  '5': [
    [0, 0],
    [1, 0],
    [0.5, 0.5],
    [0, 1],
    [1, 1],
  ],
  '6': [
    [0, 0],
    [1, 0],
    [0, 0.5],
    [1, 0.5],
    [0, 1],
    [1, 1],
  ],
  '7': [
    [0, 0],
    [1, 0],
    [0.5, 0.25],
    [0, 0.5],
    [1, 0.5],
    [0, 1],
    [1, 1],
  ],
  '8': [
    [0, 0],
    [1, 0],
    [0.5, 0.25],
    [0, 0.5],
    [1, 0.5],
    [0.5, 0.75],
    [0, 1],
    [1, 1],
  ],
  '9': [
    [0, 0],
    [1, 0],
    [0, 1 / 3],
    [1, 1 / 3],
    [0.5, 0.5],
    [0, 2 / 3],
    [1, 2 / 3],
    [0, 1],
    [1, 1],
  ],
  T: [
    [0, 0],
    [1, 0],
    [0.5, 1 / 6],
    [0, 1 / 3],
    [1, 1 / 3],
    [0, 2 / 3],
    [1, 2 / 3],
    [0.5, 5 / 6],
    [0, 1],
    [1, 1],
  ],
}

/** The centre panel (casino "jumbo index" look): a thin frame between the two big corner indexes. */
const PANEL = { x: 64, y: 70, width: 122, height: 217 }
/** Pips sit inside the panel's area (PIP wide). */
const PIP = 34
const AREA = { left: 84, right: 166, top: 92, bottom: 265 }

const courts = import.meta.glob<string>('./courts/*.svg', {
  query: '?url',
  import: 'default',
  eager: true,
})
const courtArt = (card: string) => courts[`./courts/${card[0]}${card[1]}.svg`]

/** Where the portrait sits in each court SVG (Fomin's frame, in his 360 × 540 card). */
const FRAME = { x: 30, y: 30, width: 300, height: 480 }

const FONT = "'Noto Serif Display', Georgia, serif"

function Pip({
  suit,
  x,
  y,
  size,
  flip = false,
}: {
  suit: Suit
  x: number
  y: number
  size: number
  flip?: boolean
}) {
  const scale = size / 100
  return (
    <path
      d={SUIT_PATHS[suit]}
      transform={`translate(${x - size / 2} ${y - size / 2}) scale(${scale})${flip ? ' rotate(180 50 50)' : ''}`}
    />
  )
}

/** A rank in the tall, narrow face of casino cards (the serif squeezed sideways), centred on x. */
function Rank({ text, x, y, size }: { text: string; x: number; y: number; size: number }) {
  const squeeze = text === '10' ? 0.46 : 0.7
  return (
    <text
      transform={`translate(${x} ${y}) scale(${squeeze} 1)`}
      textAnchor="middle"
      fontFamily={FONT}
      fontWeight={800}
      fontSize={size}
      letterSpacing={text === '10' ? -4 : 0}
    >
      {text}
    </text>
  )
}

/** The corner index: a very large rank over its suit (readable from the visible strip of a fanned hand). */
function Index({ card, suit }: { card: string; suit: Suit }) {
  return (
    <g>
      <Rank text={rankText(card)} x={33} y={98} size={104} />
      <Pip suit={suit} x={33} y={128} size={42} />
    </g>
  )
}

/**
 * @param compact  the face of a small card (piles, declarations): just a large rank and a large suit, which
 *                 stay readable at 30 px wide where pips and portraits would not
 */
export function CardFace({ card, compact = false }: { card: string; compact?: boolean }) {
  const suit = suitOf(card)
  const rank = card[0] ?? ''
  const fill = suit === 'H' || suit === 'D' ? RED : BLACK
  if (compact) {
    return (
      <svg viewBox={`0 0 ${W} ${H}`} className="block size-full" aria-hidden="true">
        <rect
          x={2}
          y={2}
          width={W - 4}
          height={H - 4}
          rx={18}
          fill="#fff"
          stroke="#cfc8b8"
          strokeWidth={4}
        />
        <g fill={fill}>
          <Rank text={rankText(card)} x={80} y={150} size={170} />
          <Pip suit={suit} x={W / 2 + 22} y={262} size={150} />
        </g>
      </svg>
    )
  }
  const court = rank === 'J' || rank === 'Q' || rank === 'K'
  const art = court ? courtArt(card) : undefined
  const { x, y, width, height } = PANEL

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="block size-full" aria-hidden="true">
      <rect
        x={1}
        y={1}
        width={W - 2}
        height={H - 2}
        rx={18}
        fill="#fff"
        stroke="#cfc8b8"
        strokeWidth={2}
      />
      <g fill={fill}>
        <Index card={card} suit={suit} />

        {rank === 'A' ? (
          // The ace's panel: a double frame, a ring of dots and one large pip.
          <g>
            <rect
              x={x}
              y={y}
              width={width}
              height={height}
              fill="none"
              stroke={BLACK}
              strokeWidth={2.5}
            />
            <rect
              x={x + 6}
              y={y + 6}
              width={width - 12}
              height={height - 12}
              fill="none"
              stroke={BLACK}
              strokeWidth={1}
            />
            <circle
              cx={W / 2}
              cy={y + height / 2}
              r={46}
              fill="none"
              stroke={fill}
              strokeWidth={1.5}
            />
            {Array.from({ length: 16 }, (_, i) => {
              const a = (i * Math.PI) / 8
              return (
                <circle
                  key={i}
                  cx={W / 2 + 54 * Math.cos(a)}
                  cy={y + height / 2 + 54 * Math.sin(a)}
                  r={2.4}
                />
              )
            })}
            <Pip suit={suit} x={W / 2} y={y + height / 2} size={66} />
          </g>
        ) : null}

        {LAYOUTS[rank]?.map(([px, py], i) => (
          <Pip
            key={i}
            suit={suit}
            x={AREA.left + px * (AREA.right - AREA.left)}
            y={AREA.top + py * (AREA.bottom - AREA.top)}
            size={PIP}
            flip={py > 0.5}
          />
        ))}

        {court ? (
          <g>
            {art ? (
              <svg
                x={x}
                y={y}
                width={width}
                height={height}
                viewBox={`${FRAME.x} ${FRAME.y} ${FRAME.width} ${FRAME.height}`}
                preserveAspectRatio="xMidYMid slice"
              >
                <image href={art} width={360} height={540} />
              </svg>
            ) : (
              <Pip suit={suit} x={W / 2} y={y + height / 2} size={60} />
            )}
            <rect
              x={x}
              y={y}
              width={width}
              height={height}
              fill="none"
              stroke={BLACK}
              strokeWidth={2.5}
            />
          </g>
        ) : null}

        {/* The other corner, upside down. */}
        <g transform={`rotate(180 ${W / 2} ${H / 2})`}>
          <Index card={card} suit={suit} />
        </g>
      </g>
    </svg>
  )
}
