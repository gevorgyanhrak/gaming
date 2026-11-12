/**
 * The game-over trophy (drawn, no image files): gold with laurels for a win, dull bronze with a crack for a
 * loss, plain for a draw or an ended game. Two cards lean on it, as at a card table.
 */
export function ResultTrophy({ outcome }: { outcome: 'win' | 'loss' | 'neutral' }) {
  const [light, mid, dark] =
    outcome === 'win'
      ? ['#fbe3a0', '#e3b955', '#9a6a1c']
      : outcome === 'loss'
        ? ['#c99a6b', '#8a5a33', '#4a2c16']
        : ['#d6d3cc', '#9c978c', '#5d5950']
  const id = `trophy-${outcome}`
  return (
    <svg
      viewBox="0 0 160 120"
      className="mx-auto h-28 w-auto drop-shadow-[0_10px_18px_rgb(0_0_0/0.45)] sm:h-32"
      aria-hidden="true"
    >
      <defs>
        <linearGradient id={id} x1="0" x2="1" y1="0" y2="1">
          <stop offset="0" stopColor={light} />
          <stop offset="0.55" stopColor={mid} />
          <stop offset="1" stopColor={dark} />
        </linearGradient>
      </defs>
      {/* Laurels */}
      {[-1, 1].map((side) => (
        <g
          key={side}
          transform={`translate(80 0) scale(${side} 1) translate(-80 0)`}
          opacity={outcome === 'win' ? 1 : 0.55}
        >
          {[0, 1, 2, 3, 4].map((i) => (
            <ellipse
              key={i}
              cx={38 - i * 2}
              cy={92 - i * 13}
              rx="4.5"
              ry="9"
              fill={`url(#${id})`}
              transform={`rotate(${-35 + i * 8} ${38 - i * 2} ${92 - i * 13})`}
            />
          ))}
        </g>
      ))}
      {/* Cards leaning on the cup */}
      <g transform="rotate(-16 50 78)">
        <rect x="36" y="58" width="24" height="34" rx="3" fill="#f6efe1" stroke="#c9b892" />
        <text x="41" y="70" fontSize="9" fontWeight="700" fill="#1b1b1b">
          A
        </text>
        <text x="44" y="84" fontSize="12" fill="#1b1b1b">
          ♠
        </text>
      </g>
      <g transform="rotate(16 110 78)">
        <rect x="100" y="58" width="24" height="34" rx="3" fill="#f6efe1" stroke="#c9b892" />
        <text x="103" y="70" fontSize="8" fontWeight="700" fill="#b3261e">
          10
        </text>
        <text x="107" y="84" fontSize="12" fill="#b3261e">
          ♥
        </text>
      </g>
      {/* Cup */}
      <path
        d="M55 18h50v10c0 22-10 36-25 40-15-4-25-18-25-40z"
        fill={`url(#${id})`}
        stroke={dark}
        strokeWidth="1.5"
      />
      <path d="M55 24c-12 0-16 6-14 14s10 12 18 12" fill="none" stroke={mid} strokeWidth="4" />
      <path d="M105 24c12 0 16 6 14 14s-10 12-18 12" fill="none" stroke={mid} strokeWidth="4" />
      <rect x="74" y="66" width="12" height="16" fill={`url(#${id})`} />
      <rect x="62" y="82" width="36" height="9" rx="2" fill={`url(#${id})`} stroke={dark} />
      <rect x="57" y="91" width="46" height="8" rx="2" fill={dark} />
      {outcome === 'win' ? (
        <text x="80" y="47" fontSize="16" textAnchor="middle" fill={dark}>
          ♠
        </text>
      ) : null}
      {outcome === 'loss' ? (
        <path
          d="M86 19l-5 9 6 5-7 9 4 6"
          fill="none"
          stroke="#2a170c"
          strokeWidth="1.6"
          strokeLinejoin="round"
        />
      ) : null}
    </svg>
  )
}
