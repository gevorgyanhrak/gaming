import { Card, CardBack } from './HomeArt'

/*
 * TASK-056: the one big picture behind the home pages, a poker room at night, drawn from simple shapes
 * (no image files). Fixed behind the page; the sliced viewBox keeps the table in view at any width.
 */

const brass = '#d6ab4e'
const brassLight = '#f7dc8f'

/** A stack of poker chips, a game piece on the table, standing on (x, y). */
function ChipStack({
  x,
  y,
  count,
  rx,
  color,
  edge,
}: {
  x: number
  y: number
  count: number
  rx: number
  color: string
  edge: string
}) {
  const ry = rx * 0.36
  const h = rx * 0.26
  return (
    <g>
      {Array.from({ length: count }, (_, i) => {
        const cy = y - i * h
        return (
          <g key={i}>
            <path
              d={`M${x - rx} ${cy - h} v${h} a${rx} ${ry} 0 0 0 ${rx * 2} 0 v${-h}Z`}
              fill={color}
            />
            <path
              d={`M${x - rx} ${cy - h} v${h} a${rx} ${ry} 0 0 0 ${rx * 2} 0 v${-h}`}
              fill="none"
              stroke={edge}
              strokeWidth={rx * 0.22}
              strokeDasharray={`${rx * 0.34} ${rx * 0.5}`}
            />
          </g>
        )
      })}
      <ellipse cx={x} cy={y - count * h} rx={rx} ry={ry} fill={color} />
      <ellipse
        cx={x}
        cy={y - count * h}
        rx={rx * 0.62}
        ry={ry * 0.62}
        fill="none"
        stroke={edge}
        strokeWidth={rx * 0.08}
      />
    </g>
  )
}

/** A lamp hanging over the table, with its cone of light. */
function Lamp({ x, tableY }: { x: number; tableY: number }) {
  return (
    <g>
      <path
        d={`M${x - 50} 214 L${x + 50} 214 L${x + 190} ${tableY} L${x - 190} ${tableY}Z`}
        fill="url(#hb-cone)"
      />
      <rect x={x - 2} y="0" width="4" height="160" fill="#0a0604" />
      <path
        d={`M${x - 26} 160 H${x + 26} L${x + 58} 214 H${x - 58}Z`}
        fill="#123d2f"
        stroke={brass}
        strokeWidth="3"
      />
      <ellipse cx={x} cy="214" rx="58" ry="9" fill={brassLight} />
      <ellipse cx={x} cy="222" rx="170" ry="60" fill="url(#hb-glow)" />
    </g>
  )
}

/** A table further back in the room, dim, to give the picture depth. */
function FarTable({ x, y, scale }: { x: number; y: number; scale: number }) {
  return (
    <g transform={`translate(${x} ${y}) scale(${scale})`} opacity="0.75">
      <ellipse cx="0" cy="0" rx="230" ry="66" fill="#2b1408" />
      <ellipse cx="0" cy="-5" rx="200" ry="52" fill="#0f5a41" />
      <rect x="-2" y="-430" width="4" height="330" fill="#0a0604" />
      <path d="M-22 -100 H22 L48 -58 H-48Z" fill="#123d2f" stroke={brass} strokeWidth="3" />
      <ellipse cx="0" cy="-50" rx="150" ry="58" fill="url(#hb-glow)" />
    </g>
  )
}

export function HomeBackdrop({ className = '' }: { className?: string }) {
  const arches = Array.from({ length: 10 }, (_, i) => -60 + i * 180)
  return (
    <svg
      viewBox="0 0 1600 1000"
      preserveAspectRatio="xMidYMid slice"
      aria-hidden="true"
      className={className}
    >
      <defs>
        <linearGradient id="hb-wall" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#1a0a10" />
          <stop offset="1" stopColor="#4a1420" />
        </linearGradient>
        <linearGradient id="hb-floor" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#2a0f14" />
          <stop offset="1" stopColor="#0c0506" />
        </linearGradient>
        <linearGradient id="hb-rim" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#8a5226" />
          <stop offset="1" stopColor="#2b1408" />
        </linearGradient>
        <radialGradient id="hb-felt" cx="0.5" cy="0.45" r="0.62">
          <stop offset="0" stopColor="#25ad78" />
          <stop offset="0.55" stopColor="#0f7a55" />
          <stop offset="1" stopColor="#063a29" />
        </radialGradient>
        <radialGradient id="hb-glow">
          <stop offset="0" stopColor="#ffdc96" stopOpacity="0.7" />
          <stop offset="1" stopColor="#ffdc96" stopOpacity="0" />
        </radialGradient>
        <linearGradient id="hb-cone" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="#ffdc96" stopOpacity="0.3" />
          <stop offset="1" stopColor="#ffdc96" stopOpacity="0" />
        </linearGradient>
        <radialGradient id="hb-vignette" cx="0.55" cy="0.55" r="0.75">
          <stop offset="0.45" stopColor="#000" stopOpacity="0" />
          <stop offset="1" stopColor="#000" stopOpacity="0.9" />
        </radialGradient>
        <pattern id="hb-carpet" width="70" height="42" patternUnits="userSpaceOnUse">
          <path
            d="M35 2 66 21 35 40 4 21Z"
            fill="none"
            stroke={brass}
            strokeWidth="1.5"
            opacity="0.28"
          />
        </pattern>
      </defs>

      {/* The room: a panelled wall with arches and wall lights, and a patterned carpet. */}
      <rect width="1600" height="620" fill="url(#hb-wall)" />
      {arches.map((x, i) => (
        <g key={x}>
          <path
            d={`M${x + 16} 560 V170 A74 74 0 0 1 ${x + 164} 170 V560Z`}
            fill="#000"
            fillOpacity="0.2"
            stroke={brass}
            strokeOpacity="0.35"
            strokeWidth="2.5"
          />
          <path
            d={`M${x + 90} 210 l34 56 -34 56 -34 -56Z`}
            fill="none"
            stroke={brass}
            strokeOpacity="0.28"
            strokeWidth="2"
          />
          {i % 2 ? <circle cx={x + 180} cy="320" r="110" fill="url(#hb-glow)" /> : null}
        </g>
      ))}
      <rect y="556" width="1600" height="8" fill={brass} opacity="0.55" />
      <rect y="564" width="1600" height="436" fill="url(#hb-floor)" />
      <rect y="564" width="1600" height="436" fill="url(#hb-carpet)" />

      <FarTable x={150} y={560} scale={0.72} />
      <FarTable x={1500} y={552} scale={0.66} />

      {/* The big table. */}
      <ellipse cx="960" cy="812" rx="800" ry="262" fill="#000" opacity="0.55" />
      <ellipse cx="960" cy="770" rx="770" ry="262" fill="url(#hb-rim)" />
      <ellipse cx="960" cy="762" rx="735" ry="240" fill="#150b08" />
      <ellipse cx="960" cy="758" rx="680" ry="206" fill="url(#hb-felt)" />
      <ellipse
        cx="960"
        cy="758"
        rx="540"
        ry="146"
        fill="none"
        stroke={brassLight}
        strokeOpacity="0.4"
        strokeWidth="3"
      />

      <Lamp x={620} tableY={720} />
      <Lamp x={960} tableY={740} />
      <Lamp x={1300} tableY={720} />

      {/* Five cards on the felt, seen from the rail. */}
      <g transform="translate(960 726) scale(1 0.72)">
        <Card x={-208} y={0} rank="A" suit="spade" angle={-3} scale={1.5} />
        <Card x={-104} y={-4} rank="K" suit="heart" angle={2} scale={1.5} />
        <Card x={0} y={0} rank="Q" suit="diamond" angle={-1} scale={1.5} />
        <Card x={104} y={-4} rank="J" suit="club" angle={3} scale={1.5} />
        <CardBack x={208} y={0} angle={-2} scale={1.5} />
      </g>

      <ChipStack x={560} y={770} count={7} rx={34} color="#b3172c" edge="#fff8e7" />
      <ChipStack x={640} y={800} count={4} rx={34} color="#1d2b25" edge={brassLight} />
      <ChipStack x={1330} y={790} count={6} rx={34} color="#0e62a0" edge="#fff8e7" />
      <ChipStack x={1252} y={818} count={3} rx={34} color={brass} edge="#5a3312" />

      {/* The dealer button. */}
      <ellipse cx="960" cy="866" rx="40" ry="20" fill="#fff8e7" stroke={brass} strokeWidth="4" />
      <text
        x="960"
        y="875"
        textAnchor="middle"
        fontFamily="Georgia, serif"
        fontSize="25"
        fontWeight="700"
        fill="#1d2b25"
      >
        D
      </text>

      {/* Close to the viewer: a hand of cards and a tall stack. */}
      <g>
        <Card x={1356} y={928} rank="A" suit="heart" angle={-16} scale={3.1} />
        <Card x={1470} y={916} rank="A" suit="spade" angle={10} scale={3.1} />
      </g>
      <ChipStack x={250} y={980} count={8} rx={92} color="#b3172c" edge="#fff8e7" />
      <ChipStack x={78} y={1000} count={5} rx={92} color="#1d2b25" edge={brassLight} />

      <rect width="1600" height="1000" fill="url(#hb-vignette)" />
    </svg>
  )
}
