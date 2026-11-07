/**
 * A small drawing of a Nardi variant's starting position, for the "What shall we play?" picker:
 * Long — each side's fifteen on its head; Short — the 2-5-3-5 setup. Seen from the player's side.
 */
export default function NardiModePreview({ mode }: { mode: string }) {
  // [column 0..11 left to right, row: top/bottom, checkers, mine?]
  const stacks: [number, 'top' | 'bottom', number, boolean][] =
    mode === 'long'
      ? [
          [0, 'top', 15, true],
          [0, 'bottom', 15, false],
        ]
      : [
          [11, 'top', 2, false],
          [0, 'top', 5, true],
          [4, 'top', 3, false],
          [6, 'top', 5, false],
          [11, 'bottom', 2, true],
          [0, 'bottom', 5, false],
          [4, 'bottom', 3, true],
          [6, 'bottom', 5, true],
        ]
  const x = (column: number) => 10 + column * 15 + (column >= 6 ? 8 : 0)

  return (
    <svg viewBox="0 0 196 120" aria-hidden="true" className="w-full">
      <rect x="0" y="0" width="196" height="120" rx="10" fill="#4a2a14" />
      <rect x="4" y="4" width="91" height="112" rx="6" fill="#0f3b28" />
      <rect x="101" y="4" width="91" height="112" rx="6" fill="#0f3b28" />
      {Array.from({ length: 12 }, (_, c) => (
        <g key={c} fill={c % 2 ? '#c9a24a' : '#8a3324'} opacity="0.85">
          <polygon points={`${x(c) - 6},6 ${x(c) + 6},6 ${x(c)},52`} />
          <polygon points={`${x(c) - 6},114 ${x(c) + 6},114 ${x(c)},68`} />
        </g>
      ))}
      {stacks.map(([column, row, count, mine]) =>
        Array.from({ length: Math.min(count, 5) }, (_, i) => (
          <circle
            key={`${column}-${row}-${i}`}
            cx={x(column)}
            cy={row === 'top' ? 11 + i * 9 : 109 - i * 9}
            r="4.5"
            fill={mine ? '#f1ead8' : '#3a2a22'}
            stroke={mine ? '#a8956a' : '#140c08'}
            strokeWidth="1"
          />
        )),
      )}
    </svg>
  )
}
