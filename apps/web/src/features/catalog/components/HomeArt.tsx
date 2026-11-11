import { type ReactNode } from 'react'

/*
 * TASK-056 home page art: flat "deco poster" scenes, drawn from simple shapes, no image files.
 * GamePosterScene: 320×200 poster for a game.
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
type Suit = 'heart' | 'spade' | 'diamond' | 'club'
const suitPaths: Record<Suit, ReactNode> = {
  heart: (
    <path d="M12 21C12 21 3 14.5 3 8.5A4.5 4.5 0 0 1 12 6.5 4.5 4.5 0 0 1 21 8.5C21 14.5 12 21 12 21Z" />
  ),
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
const isRed = (suit: string) => suit === 'heart' || suit === 'diamond'

function SuitMark({
  suit,
  x,
  y,
  size,
  fill,
}: {
  suit: Suit
  x: number
  y: number
  size: number
  fill?: string
}) {
  return (
    <g
      transform={`translate(${x} ${y}) scale(${size / 24})`}
      fill={fill ?? (isRed(suit) ? c.red : c.ink)}
    >
      {suitPaths[suit]}
    </g>
  )
}

/** A playing card, 56×80 before `scale`, turned about its own centre. */
export function Card({
  x,
  y,
  rank,
  suit,
  angle = 0,
  scale = 1,
}: {
  x: number
  y: number
  rank: string
  suit: Suit
  angle?: number
  scale?: number
}) {
  const ink = isRed(suit) ? c.red : c.ink
  return (
    <g transform={`translate(${x} ${y}) rotate(${angle}) scale(${scale})`}>
      <rect
        x="-28"
        y="-40"
        width="56"
        height="80"
        rx="6"
        fill={c.cream}
        stroke="rgb(29 43 37 / 0.28)"
      />
      <text x="-22" y="-23" fill={ink} fontFamily="Georgia, serif" fontSize="15" fontWeight="700">
        {rank}
      </text>
      <SuitMark suit={suit} x={-22} y={-20} size={11} />
      <SuitMark suit={suit} x={-14} y={-8} size={28} />
    </g>
  )
}

export function CardBack({
  x,
  y,
  angle = 0,
  scale = 1,
}: {
  x: number
  y: number
  angle?: number
  scale?: number
}) {
  return (
    <g transform={`translate(${x} ${y}) rotate(${angle}) scale(${scale})`}>
      <rect
        x="-28"
        y="-40"
        width="56"
        height="80"
        rx="6"
        fill={c.cream}
        stroke="rgb(29 43 37 / 0.28)"
      />
      <rect x="-23" y="-35" width="46" height="70" rx="3" fill={c.garnet} />
      <path d="M0 -26 16 0 0 26 -16 0Z" fill="none" stroke={c.gold} strokeWidth="2" />
      <circle r="4" fill={c.gold} />
    </g>
  )
}

/** A fan of rays rising from (cx, cy). */
function Rays({
  cx,
  cy,
  count,
  step,
  length,
  color,
  opacity = 1,
}: {
  cx: number
  cy: number
  count: number
  step: number
  length: number
  color: string
  opacity?: number
}) {
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
function King({
  x,
  y,
  scale,
  fill,
  trim,
}: {
  x: number
  y: number
  scale: number
  fill: string
  trim: string
}) {
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
function Pawn({
  x,
  y,
  scale,
  fill,
  trim,
}: {
  x: number
  y: number
  scale: number
  fill: string
  trim: string
}) {
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
function Die({
  x,
  y,
  size,
  angle,
  value,
}: {
  x: number
  y: number
  size: number
  angle: number
  value: 1 | 2 | 3 | 4 | 5 | 6
}) {
  const a = size * 0.26
  const b = size * 0.74
  const m = size * 0.5
  const spots: Record<1 | 2 | 3 | 4 | 5 | 6, [number, number][]> = {
    1: [[m, m]],
    2: [
      [a, a],
      [b, b],
    ],
    3: [
      [a, a],
      [m, m],
      [b, b],
    ],
    4: [
      [a, a],
      [b, a],
      [a, b],
      [b, b],
    ],
    5: [
      [a, a],
      [b, a],
      [m, m],
      [a, b],
      [b, b],
    ],
    6: [
      [a, a],
      [b, a],
      [a, m],
      [b, m],
      [a, b],
      [b, b],
    ],
  }
  return (
    <g transform={`translate(${x - m} ${y - m}) rotate(${angle} ${m} ${m})`}>
      <rect
        width={size}
        height={size}
        rx={size * 0.18}
        fill={c.cream}
        stroke="rgb(29 43 37 / 0.3)"
      />
      {spots[value].map(([cx, cy]) => (
        <circle key={`${cx}-${cy}`} cx={cx} cy={cy} r={size * 0.095} fill={c.ink} />
      ))}
    </g>
  )
}

/** A draughts man from the side; `tiers` stacked; the top may wear a crown. */
function Man({
  x,
  y,
  rx,
  tiers,
  top,
  side,
  crown = false,
}: {
  x: number
  y: number
  rx: number
  tiers: number
  top: string
  side: string
  crown?: boolean
}) {
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
            <ellipse
              cx={x}
              cy={cy}
              rx={rx * 0.62}
              ry={ry * 0.62}
              fill="none"
              stroke={side}
              strokeWidth="1.5"
            />
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
function Floor({
  y,
  size,
  width,
  a,
  b,
}: {
  y: number
  size: number
  width: number
  a: string
  b: string
}) {
  return (
    <g>
      {Array.from({ length: Math.ceil(width / size) }, (_, i) => (
        <rect key={i} x={i * size} y={y} width={size} height={size} fill={i % 2 ? a : b} />
      ))}
    </g>
  )
}

/** A row of nardi points standing along the bottom edge. */
function Points({
  y,
  height,
  x,
  width,
  count,
  a,
  b,
}: {
  y: number
  height: number
  x: number
  width: number
  count: number
  a: string
  b: string
}) {
  const w = width / count
  return (
    <g>
      {Array.from({ length: count }, (_, i) => (
        <path key={i} d={`M${x + i * w} ${y}h${w}l${-w / 2} ${-height}Z`} fill={i % 2 ? a : b} />
      ))}
    </g>
  )
}

const flop: [string, Suit][] = [
  ['J', 'heart'],
  ['10', 'spade'],
  ['4', 'diamond'],
]

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
      {flop.map(([rank, suit], i) => (
        <Card key={rank} x={50 + i * 44} y={160} rank={rank} suit={suit} scale={0.58} />
      ))}
      <CardBack x={182} y={160} scale={0.58} />
      <CardBack x={226} y={160} scale={0.58} />
      {/* The dealer button. */}
      <circle cx="280" cy="156" r="20" fill={c.cream} stroke={c.gold} strokeWidth="3" />
      <text
        x="280"
        y="163"
        textAnchor="middle"
        fontFamily="Georgia, serif"
        fontSize="19"
        fontWeight="700"
        fill={c.ink}
      >
        D
      </text>
    </>
  ),
  blackjack: (
    <>
      <rect width="320" height="200" fill={c.garnet} />
      <Rays cx={160} cy={230} count={11} step={16} length={290} color={c.garnetLight} />
      {/* Twenty-one: an ace and a king, and the dealer's card face down behind them. */}
      <CardBack x={226} y={84} scale={1.05} />
      <Card x={118} y={88} rank="A" suit="spade" angle={-10} scale={1.35} />
      <Card x={176} y={92} rank="K" suit="heart" angle={9} scale={1.35} />
      <circle cx="62" cy="156" r="24" fill={c.cream} stroke={c.gold} strokeWidth="3" />
      <text
        x="62"
        y="164"
        textAnchor="middle"
        fontFamily="Georgia, serif"
        fontSize="22"
        fontWeight="700"
        fill={c.ink}
      >
        21
      </text>
    </>
  ),
  ludo: (
    <>
      <rect width="320" height="200" fill={c.plum} />
      <Rays cx={160} cy={230} count={11} step={16} length={290} color={c.plumLight} />
      {/* The cross of the board and a token of each colour. */}
      <rect x="130" y="40" width="60" height="140" rx="6" fill={c.cream} />
      <rect x="90" y="80" width="140" height="60" rx="6" fill={c.cream} />
      {[
        ['#d8443c', 110, 110],
        ['#2f9a52', 160, 60],
        ['#e8b923', 210, 110],
        ['#2f6fd6', 160, 160],
      ].map(([colour, x, y]) => (
        <g key={String(colour)}>
          <circle
            cx={Number(x)}
            cy={Number(y)}
            r="15"
            fill={String(colour)}
            stroke={c.ink}
            strokeOpacity="0.4"
            strokeWidth="3"
          />
          <circle cx={Number(x) - 4} cy={Number(y) - 4} r="5" fill="#fff" fillOpacity="0.5" />
        </g>
      ))}
    </>
  ),
  domino: (
    <>
      <rect width="320" height="200" fill={c.amber} />
      <Rays cx={160} cy={230} count={11} step={16} length={290} color={c.amberLight} />
      {/* Three tiles in a line, a double crosswise in the middle. */}
      {[
        { x: 52, y: 86, w: 84, h: 42, a: 5, b: 3 },
        { x: 139, y: 64, w: 42, h: 84, a: 3, b: 3 },
        { x: 184, y: 86, w: 84, h: 42, a: 3, b: 6 },
      ].map((tile) => (
        <g key={tile.x}>
          <rect
            x={tile.x}
            y={tile.y}
            width={tile.w}
            height={tile.h}
            rx="7"
            fill={c.cream}
            stroke={c.ink}
            strokeOpacity="0.3"
            strokeWidth="2"
          />
          {tile.w > tile.h ? (
            <line
              x1={tile.x + tile.w / 2}
              y1={tile.y + 6}
              x2={tile.x + tile.w / 2}
              y2={tile.y + tile.h - 6}
              stroke={c.ink}
              strokeOpacity="0.4"
              strokeWidth="2"
            />
          ) : (
            <line
              x1={tile.x + 6}
              y1={tile.y + tile.h / 2}
              x2={tile.x + tile.w - 6}
              y2={tile.y + tile.h / 2}
              stroke={c.ink}
              strokeOpacity="0.4"
              strokeWidth="2"
            />
          )}
          {[tile.a, tile.b].map((n, half) =>
            Array.from({ length: n }, (_, i) => {
              const [cx, cy] =
                tile.w > tile.h
                  ? [tile.x + 10 + half * 42 + (i % 3) * 11, tile.y + 13 + Math.floor(i / 3) * 16]
                  : [tile.x + 10 + (i % 2) * 22, tile.y + 12 + half * 42 + Math.floor(i / 2) * 11]
              return <circle key={`${half}-${i}`} cx={cx} cy={cy} r="4" fill={c.ink} />
            }),
          )}
        </g>
      ))}
    </>
  ),
  blot: (
    <>
      <rect width="320" height="200" fill={c.teal} />
      <Rays cx={160} cy={230} count={11} step={16} length={290} color={c.tealLight} />
      {/* The best trumps: jack and nine, with the ace and ten behind them. */}
      <Card x={96} y={96} rank="A" suit="heart" angle={-18} scale={1.2} />
      <Card x={136} y={86} rank="J" suit="heart" angle={-6} scale={1.3} />
      <Card x={184} y={86} rank="9" suit="heart" angle={6} scale={1.3} />
      <Card x={226} y={96} rank="10" suit="heart" angle={18} scale={1.2} />
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
const groundFor = (slug: string) =>
  grounds[[...slug].reduce((sum, ch) => sum + ch.charCodeAt(0), 0) % grounds.length] ?? {
    ground: c.emerald,
    ray: c.emeraldLight,
  }

/** The poster for a game; a monogram poster for a game without its own art. */
export function GamePosterScene({
  slug,
  name,
  className = '',
}: {
  slug: string
  name: string
  className?: string
}) {
  const { ground, ray } = groundFor(slug)
  return (
    <svg
      viewBox="0 0 320 200"
      preserveAspectRatio="xMidYMid slice"
      aria-hidden="true"
      className={className}
    >
      {posters[slug] ?? (
        <>
          <rect width="320" height="200" fill={ground} />
          <Rays cx={160} cy={220} count={11} step={16} length={280} color={ray} />
          <circle cx="160" cy="100" r="64" fill={c.cream} />
          <circle cx="160" cy="100" r="64" fill="none" stroke={c.gold} strokeWidth="5" />
          <text
            x="160"
            y="124"
            textAnchor="middle"
            fontFamily="system-ui, 'Noto Sans Armenian', sans-serif"
            fontSize="68"
            fontWeight="800"
            fill={c.ink}
          >
            {name.charAt(0)}
          </text>
        </>
      )}
    </svg>
  )
}
