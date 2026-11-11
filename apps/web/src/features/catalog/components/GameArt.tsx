import { useId, type CSSProperties, type ReactNode } from 'react'

/*
 * Built-in illustrations for the games we ship, used until an admin uploads artwork: a scene for
 * cards and banners (320×180) and a small icon for navigation. Drawn from simple shapes so they
 * stay crisp at any size and need no image files.
 */

const scenes: Record<string, (id: string) => ReactNode> = {
  chess: (id) => (
    <>
      <Backdrop id={id} from="#134a3a" to="#06140f" glow="#3ccf91" />
      <Board y={118} rows={3} light="#e9e2c8" dark="#2c6b52" />
      <g transform="translate(118 30)">
        <King id={id} />
      </g>
      <g transform="translate(176 62) scale(0.78)">
        <Pawn id={id} dark />
      </g>
      <g transform="translate(64 70) scale(0.7)">
        <Pawn id={id} />
      </g>
    </>
  ),
  checkers: (id) => (
    <>
      <Backdrop id={id} from="#5a1626" to="#170509" glow="#ff6b6b" />
      <Board y={112} rows={4} light="#f0d9b5" dark="#7a3b2e" />
      <Stack x={112} y={104} count={3} top="#c62f3a" side="#8e1c26" crown />
      <Stack x={196} y={110} count={2} top="#2a2a2e" side="#141416" />
      <Stack x={58} y={126} count={1} top="#c62f3a" side="#8e1c26" />
      <Stack x={262} y={128} count={1} top="#2a2a2e" side="#141416" />
    </>
  ),
  nardi: (id) => (
    <>
      <Backdrop id={id} from="#5b3416" to="#1a0d05" glow="#f0a24b" />
      <rect
        x="28"
        y="22"
        width="264"
        height="136"
        rx="10"
        fill="#3a200c"
        stroke="#d6ab4e"
        strokeWidth="3"
      />
      <rect x="38" y="32" width="114" height="116" rx="4" fill="#e6cfa0" />
      <rect x="168" y="32" width="114" height="116" rx="4" fill="#e6cfa0" />
      {Array.from({ length: 12 }, (_, i) => {
        const x = (i < 6 ? 38 : 168) + (i % 6) * 19
        const fill = i % 2 ? '#7a2f1a' : '#2f4d3a'
        return (
          <g key={i}>
            <path d={`M${x} 32 h19 l-9.5 48 z`} fill={fill} />
            <path d={`M${x} 148 h19 l-9.5 -48 z`} fill={i % 2 ? '#2f4d3a' : '#7a2f1a'} />
          </g>
        )
      })}
      {[0, 1, 2].map((i) => (
        <circle key={`w${i}`} cx={47.5} cy={41 + i * 15} r="7" fill="#f8f4ea" stroke="#9c8b6b" />
      ))}
      {[0, 1].map((i) => (
        <circle key={`b${i}`} cx={272.5} cy={139 - i * 15} r="7" fill="#1e1e22" stroke="#555" />
      ))}
      <Die x={120} y={70} angle={-14} pips={5} />
      <Die x={170} y={78} angle={10} pips={3} />
    </>
  ),
  durak: (id) => (
    <>
      <Backdrop id={id} from="#14603a" to="#05200f" glow="#53e08f" />
      {[
        ['6', '♦', -36],
        ['9', '♣', -18],
        ['Q', '♥', 0],
        ['K', '♠', 18],
        ['A', '♠', 36],
      ].map(([rank, suit, angle]) => (
        <g key={`${rank}${suit}`} transform={`rotate(${angle} 160 210)`}>
          <Face x={133} y={40} rank={String(rank)} suit={String(suit)} />
        </g>
      ))}
    </>
  ),
  poker: (id) => (
    <>
      <Backdrop id={id} from="#2d1466" to="#0a0418" glow="#9b6bff" />
      <g transform="rotate(-12 120 100)">
        <Face x={88} y={36} rank="A" suit="♠" />
      </g>
      <g transform="rotate(8 160 100)">
        <Face x={128} y={40} rank="K" suit="♥" />
      </g>
      <Chips x={226} y={136} count={6} color="#c62f3a" />
      <Chips x={262} y={142} count={4} color="#2f6fd6" />
      <Chips x={56} y={142} count={5} color="#d6ab4e" />
    </>
  ),
  blackjack: (id) => (
    <>
      <Backdrop id={id} from="#6b1424" to="#1a0408" glow="#ff6b7d" />
      <g transform="rotate(-10 120 100)">
        <Face x={96} y={38} rank="A" suit="♠" />
      </g>
      <g transform="rotate(9 160 100)">
        <Face x={140} y={42} rank="K" suit="♥" />
      </g>
      <Chips x={250} y={142} count={5} color="#1f8a4c" />
      <Chips x={62} y={142} count={4} color="#d6ab4e" />
    </>
  ),
  blot: (id) => (
    <>
      <Backdrop id={id} from="#0f4a5c" to="#03141a" glow="#4fd2e6" />
      {[
        ['J', '♥', -30],
        ['9', '♥', -10],
        ['A', '♥', 10],
        ['K', '♥', 30],
      ].map(([rank, suit, angle]) => (
        <g key={`${rank}${suit}`} transform={`rotate(${angle} 160 220)`}>
          <Face x={132} y={42} rank={String(rank)} suit={String(suit)} />
        </g>
      ))}
    </>
  ),
  domino: (id) => (
    <>
      <Backdrop id={id} from="#3b2a14" to="#0d0904" glow="#f2c46d" />
      {[
        [78, 70, -14, 6, 4],
        [132, 58, 0, 6, 6],
        [186, 70, 14, 3, 5],
      ].map(([x, y, angle, a, b]) => (
        <g key={`${x}`} transform={`rotate(${angle} ${Number(x) + 28} ${Number(y) + 28})`}>
          <rect
            x={x}
            y={y}
            width="56"
            height="100"
            rx="8"
            fill="#f7f1e3"
            stroke="#b9a983"
            strokeWidth="2"
          />
          <line
            x1={Number(x) + 8}
            y1={Number(y) + 50}
            x2={Number(x) + 48}
            y2={Number(y) + 50}
            stroke="#9c8b6b"
            strokeWidth="2"
          />
          {[a, b].map((n, half) =>
            Array.from({ length: Number(n) }, (_, i) => (
              <circle
                key={`${half}-${i}`}
                cx={Number(x) + 16 + (i % 2) * 24}
                cy={Number(y) + 12 + half * 50 + Math.floor(i / 2) * 12}
                r="4.5"
                fill="#1d1a17"
              />
            )),
          )}
        </g>
      ))}
    </>
  ),
  ludo: (id) => (
    <>
      <Backdrop id={id} from="#1d3f8a" to="#060c1f" glow="#7aa6ff" />
      {[
        ['#d8443c', 70, 70],
        ['#2f9a52', 250, 70],
        ['#e8b923', 250, 130],
        ['#2f6fd6', 70, 130],
      ].map(([colour, x, y]) => (
        <g key={String(colour)}>
          <ellipse cx={Number(x)} cy={Number(y) + 16} rx="16" ry="5" fill="rgb(0 0 0 / 0.35)" />
          <path
            d={`M${Number(x) - 12} ${Number(y) + 14} Q${x} ${Number(y) - 30} ${Number(x) + 12} ${Number(y) + 14} Z`}
            fill={String(colour)}
          />
          <circle cx={Number(x)} cy={Number(y) - 10} r="8" fill={String(colour)} />
        </g>
      ))}
      <Die x={135} y={72} angle={-12} pips={5} />
      <Die x={180} y={84} angle={10} pips={3} />
    </>
  ),
  pool: (id) => (
    <>
      <Backdrop id={id} from="#0f5a3e" to="#03140d" glow="#47d79a" />
      {/* The rack, the cue ball and the cue. */}
      {[
        [200, 90, '#f2c230'],
        [222, 78, '#1f4fb4'],
        [222, 102, '#151515'],
        [244, 66, '#c8282e'],
        [244, 90, '#e8731d'],
        [244, 114, '#5b2a86'],
      ].map(([x, y, colour]) => (
        <g key={`${x}-${y}`}>
          <circle cx={Number(x)} cy={Number(y)} r="12" fill={String(colour)} />
          <circle cx={Number(x) - 4} cy={Number(y) - 4} r="3.5" fill="#fff" opacity="0.45" />
        </g>
      ))}
      <circle cx="100" cy="96" r="12" fill="#f8f5ec" />
      <line
        x1="84"
        y1="102"
        x2="10"
        y2="130"
        stroke="#c08a4c"
        strokeWidth="7"
        strokeLinecap="round"
      />
    </>
  ),
}

/** The scene for a game, or the fallback when there is none for this slug. */
export function GameScene({
  slug,
  className = '',
  style,
  fallback = null,
}: {
  slug: string
  className?: string
  style?: CSSProperties
  fallback?: ReactNode
}) {
  const id = useId().replace(/:/g, '')
  const scene = scenes[slug]
  if (!scene) return fallback
  return (
    <svg
      viewBox="0 0 320 180"
      preserveAspectRatio="xMidYMid slice"
      aria-hidden="true"
      className={className}
      style={style}
    >
      {scene(id)}
    </svg>
  )
}

const icons: Record<string, ReactNode> = {
  chess: (
    <path
      fill="currentColor"
      d="M12 3a3 3 0 0 0-1.9 5.3C8.8 9 8 10 8 11h8c0-1-.8-2-2.1-2.7A3 3 0 0 0 12 3Zm-2.5 9.5c0 2.6-.8 4.6-2 6h9c-1.2-1.4-2-3.4-2-6h-5ZM6 19.5h12a1 1 0 0 1 1 1V21H5v-.5a1 1 0 0 1 1-1Z"
    />
  ),
  checkers: (
    <g fill="none" stroke="currentColor" strokeWidth="1.8">
      <ellipse cx="12" cy="9" rx="8" ry="3.5" />
      <path d="M4 9v3c0 1.9 3.6 3.5 8 3.5s8-1.6 8-3.5V9" />
      <path d="M4 12v3c0 1.9 3.6 3.5 8 3.5s8-1.6 8-3.5v-3" />
    </g>
  ),
  nardi: (
    <g>
      <rect
        x="4"
        y="4"
        width="16"
        height="16"
        rx="3.5"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
      />
      {[
        [8.5, 8.5],
        [15.5, 8.5],
        [12, 12],
        [8.5, 15.5],
        [15.5, 15.5],
      ].map(([cx, cy]) => (
        <circle key={`${cx}${cy}`} cx={cx} cy={cy} r="1.4" fill="currentColor" />
      ))}
    </g>
  ),
  durak: (
    <g>
      <rect
        x="5"
        y="3"
        width="14"
        height="18"
        rx="2.5"
        fill="none"
        stroke="currentColor"
        strokeWidth="1.8"
      />
      <path
        fill="currentColor"
        d="M12 7.5c1.6 2 4 3.2 4 5.2a2 2 0 0 1-3.3 1.5l.8 2.3h-3l.8-2.3A2 2 0 0 1 8 12.7c0-2 2.4-3.2 4-5.2Z"
      />
    </g>
  ),
  poker: (
    <g fill="none" stroke="currentColor" strokeWidth="1.8">
      <circle cx="12" cy="12" r="8.5" />
      <circle cx="12" cy="12" r="4.5" />
      <path d="M12 3.5v3M12 17.5v3M3.5 12h3M17.5 12h3M6 6l2.1 2.1M15.9 15.9 18 18M18 6l-2.1 2.1M8.1 15.9 6 18" />
    </g>
  ),
  blackjack: (
    <g fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round">
      <rect x="4" y="5" width="10" height="14" rx="1.5" transform="rotate(-10 9 12)" />
      <rect x="10" y="5" width="10" height="14" rx="1.5" transform="rotate(8 15 12)" />
    </g>
  ),
  blot: (
    <g fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round">
      <rect x="3" y="6" width="9" height="13" rx="1.5" transform="rotate(-14 7.5 12.5)" />
      <rect x="7.5" y="4.5" width="9" height="13" rx="1.5" />
      <rect x="12" y="6" width="9" height="13" rx="1.5" transform="rotate(14 16.5 12.5)" />
    </g>
  ),
  domino: (
    <g fill="none" stroke="currentColor" strokeWidth="1.8">
      <rect x="7" y="2.5" width="10" height="19" rx="2" />
      <path d="M8.5 12h7" />
      <circle cx="12" cy="7" r="1.2" fill="currentColor" />
      <circle cx="10" cy="15.5" r="1" fill="currentColor" />
      <circle cx="14" cy="17.5" r="1" fill="currentColor" />
    </g>
  ),
  ludo: (
    <g fill="none" stroke="currentColor" strokeWidth="1.8">
      <rect x="3.5" y="3.5" width="17" height="17" rx="2.5" />
      <path d="M12 3.5v17M3.5 12h17" />
      <circle cx="7.5" cy="7.5" r="1.6" fill="currentColor" />
      <circle cx="16.5" cy="16.5" r="1.6" fill="currentColor" />
    </g>
  ),
  pool: (
    <g fill="none" stroke="currentColor" strokeWidth="1.8">
      <circle cx="12" cy="12" r="8.5" />
      <circle cx="12" cy="12" r="3.6" />
      <text
        x="12"
        y="14"
        textAnchor="middle"
        fontSize="5"
        fontWeight="800"
        fill="currentColor"
        stroke="none"
      >
        8
      </text>
    </g>
  ),
}

/** A small icon for a game in navigation; a letter for games without one. */
export function GameIcon({
  slug,
  name,
  className = 'size-5',
}: {
  slug: string
  name: string
  className?: string
}) {
  const icon = icons[slug]
  if (!icon) {
    return (
      <span
        aria-hidden="true"
        className={`inline-flex items-center justify-center rounded-md bg-surface-raised text-xs font-bold ${className}`}
      >
        {name.charAt(0)}
      </span>
    )
  }
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className}>
      {icon}
    </svg>
  )
}

/* Building blocks for the scenes. */

function Backdrop({ id, from, to, glow }: { id: string; from: string; to: string; glow: string }) {
  return (
    <>
      <defs>
        <linearGradient id={`${id}-bg`} x1="0" y1="0" x2="0.4" y2="1">
          <stop offset="0" stopColor={from} />
          <stop offset="1" stopColor={to} />
        </linearGradient>
        <radialGradient id={`${id}-glow`} cx="0.5" cy="0.35" r="0.6">
          <stop offset="0" stopColor={glow} stopOpacity="0.35" />
          <stop offset="1" stopColor={glow} stopOpacity="0" />
        </radialGradient>
        <linearGradient id={`${id}-gold`} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#f7dc8f" />
          <stop offset="0.55" stopColor="#d6ab4e" />
          <stop offset="1" stopColor="#8a5a1c" />
        </linearGradient>
        <linearGradient id={`${id}-ebony`} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#4a4a52" />
          <stop offset="1" stopColor="#111114" />
        </linearGradient>
      </defs>
      <rect width="320" height="180" fill={`url(#${id}-bg)`} />
      <rect width="320" height="180" fill={`url(#${id}-glow)`} />
    </>
  )
}

/** A board in perspective along the bottom of the scene. */
function Board({ y, rows, light, dark }: { y: number; rows: number; light: string; dark: string }) {
  const size = (180 - y) / rows
  const squares = []
  for (let r = 0; r < rows; r++) {
    for (let c = -2; c < 320 / size + 2; c++) {
      squares.push(
        <rect
          key={`${r}-${c}`}
          x={c * size}
          y={y + r * size}
          width={size}
          height={size}
          fill={(r + c) % 2 ? dark : light}
        />,
      )
    }
  }
  return (
    <g opacity="0.85" transform={`skewX(-28) translate(${(180 - y) * 0.53} 0)`}>
      {squares}
    </g>
  )
}

function Pawn({ id, dark = false }: { id: string; dark?: boolean }) {
  return (
    <g fill={`url(#${id}-${dark ? 'ebony' : 'gold'})`} stroke="rgb(0 0 0 / 0.35)" strokeWidth="1.5">
      <circle cx="30" cy="22" r="15" />
      <path d="M17 40h26l-4 6c6 12 9 26 11 40H10c2-14 5-28 11-40z" />
      <rect x="2" y="86" width="56" height="12" rx="4" />
    </g>
  )
}

function King({ id }: { id: string }) {
  return (
    <g fill={`url(#${id}-gold)`} stroke="rgb(0 0 0 / 0.35)" strokeWidth="1.5">
      <path d="M37 0h8v8h8v8h-8v8h-8v-8h-8V8h8z" />
      <path d="M22 26h38l-4 12H26z" />
      <path d="M26 40h30c2 22 6 46 14 64H12c8-18 12-42 14-64z" />
      <rect x="4" y="104" width="74" height="14" rx="5" />
    </g>
  )
}

/** A stack of draughts, seen from the side; the top one may carry a crown. */
function Stack({
  x,
  y,
  count,
  top,
  side,
  crown = false,
}: {
  x: number
  y: number
  count: number
  top: string
  side: string
  crown?: boolean
}) {
  const rx = 30
  const ry = 11
  const h = 10
  return (
    <g>
      {Array.from({ length: count }, (_, i) => {
        const cy = y - i * h
        return (
          <g key={i}>
            <path
              d={`M${x - rx} ${cy} v${h} a${rx} ${ry} 0 0 0 ${rx * 2} 0 v-${h}`}
              fill={side}
              stroke="rgb(0 0 0 / 0.4)"
            />
            <ellipse cx={x} cy={cy} rx={rx} ry={ry} fill={top} stroke="rgb(255 255 255 / 0.18)" />
            <ellipse
              cx={x}
              cy={cy}
              rx={rx * 0.62}
              ry={ry * 0.62}
              fill="none"
              stroke="rgb(0 0 0 / 0.3)"
            />
          </g>
        )
      })}
      {crown ? (
        <path
          d={`M${x - 13} ${y - (count - 1) * h + 3} l-3 -14 l9 7 l7 -11 l7 11 l9 -7 l-3 14 z`}
          fill="#f2c14e"
          stroke="#8a5a1c"
        />
      ) : null}
    </g>
  )
}

function Die({ x, y, angle, pips }: { x: number; y: number; angle: number; pips: 3 | 5 }) {
  const spots =
    pips === 5
      ? [
          [9, 9],
          [25, 9],
          [17, 17],
          [9, 25],
          [25, 25],
        ]
      : [
          [9, 9],
          [17, 17],
          [25, 25],
        ]
  return (
    <g transform={`translate(${x} ${y}) rotate(${angle} 17 17)`}>
      <rect width="34" height="34" rx="7" fill="#fbf8ef" stroke="#8c7a58" strokeWidth="1.5" />
      {spots.map(([cx, cy]) => (
        <circle key={`${cx}${cy}`} cx={cx} cy={cy} r="3.2" fill="#2a1a10" />
      ))}
    </g>
  )
}

/** A playing card face up: rank and suit in the corner, a large suit in the middle. */
function Face({ x, y, rank, suit }: { x: number; y: number; rank: string; suit: string }) {
  const red = suit === '♥' || suit === '♦'
  const color = red ? '#c1121f' : '#1b1b1b'
  return (
    <g transform={`translate(${x} ${y})`}>
      <rect width="56" height="80" rx="6" fill="#fbf8ef" stroke="rgb(0 0 0 / 0.25)" />
      <text x="6" y="17" fill={color} fontFamily="Georgia, serif" fontSize="14" fontWeight="700">
        {rank}
      </text>
      <text x="6" y="31" fill={color} fontFamily="Georgia, serif" fontSize="13">
        {suit}
      </text>
      <text
        x="28"
        y="56"
        fill={color}
        fontFamily="Georgia, serif"
        fontSize="30"
        textAnchor="middle"
      >
        {suit}
      </text>
    </g>
  )
}

/** A stack of chips with striped edges. */
function Chips({ x, y, count, color }: { x: number; y: number; count: number; color: string }) {
  const rx = 22
  const ry = 8
  const h = 6
  return (
    <g>
      {Array.from({ length: count }, (_, i) => {
        const cy = y - i * h
        return (
          <g key={i}>
            <path d={`M${x - rx} ${cy} v${h} a${rx} ${ry} 0 0 0 ${rx * 2} 0 v-${h}`} fill={color} />
            <path
              d={`M${x - rx} ${cy + h / 2} a${rx} ${ry} 0 0 0 ${rx * 2} 0`}
              fill="none"
              stroke="#fbf8ef"
              strokeWidth={h - 1}
              strokeDasharray="5 7"
            />
            <ellipse cx={x} cy={cy} rx={rx} ry={ry} fill={color} stroke="rgb(0 0 0 / 0.3)" />
          </g>
        )
      })}
      <ellipse
        cx={x}
        cy={y - (count - 1) * h}
        rx={rx * 0.6}
        ry={ry * 0.6}
        fill="none"
        stroke="#fbf8ef"
        strokeDasharray="3 3"
      />
    </g>
  )
}
