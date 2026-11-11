import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import type { BoardProps } from '../registry'

export interface LudoView {
  seat: number | null
  colours: number[]
  tokens: number[][]
  out: boolean[]
  to_act: number | null
  phase: 'roll' | 'move'
  dice: number | null
  sixes: number
  last: {
    seat: number
    token: number
    from: number
    to: number
    captured: { seat: number; token: number }[]
  } | null
  turn: number
}

type Move = { type: 'move'; token: number; to: number }

/* Board geometry (drawing only: which cell of the 15×15 board a position is shown on). The rules — what
   moves are possible, captures, safe squares — come from the server. */

/** The 52 track cells as [row, col], clockwise from colour 0's start square. */
const TRACK: [number, number][] = [
  ...[1, 2, 3, 4, 5].map((c) => [6, c] as [number, number]),
  ...[5, 4, 3, 2, 1, 0].map((r) => [r, 6] as [number, number]),
  [0, 7],
  ...[0, 1, 2, 3, 4, 5].map((r) => [r, 8] as [number, number]),
  ...[9, 10, 11, 12, 13, 14].map((c) => [6, c] as [number, number]),
  [7, 14],
  ...[14, 13, 12, 11, 10, 9].map((c) => [8, c] as [number, number]),
  ...[9, 10, 11, 12, 13, 14].map((r) => [r, 8] as [number, number]),
  [14, 7],
  ...[14, 13, 12, 11, 10, 9].map((r) => [r, 6] as [number, number]),
  ...[5, 4, 3, 2, 1, 0].map((c) => [8, c] as [number, number]),
  [7, 0],
  [6, 0],
]
/** Each colour's home column (positions 51–55), then home (56). */
const HOME_COLUMN: [number, number][][] = [
  [1, 2, 3, 4, 5].map((c) => [7, c]),
  [1, 2, 3, 4, 5].map((r) => [r, 7]),
  [13, 12, 11, 10, 9].map((c) => [7, c]),
  [13, 12, 11, 10, 9].map((r) => [r, 7]),
]
const HOME: [number, number][] = [
  [7, 6.3],
  [6.3, 7],
  [7, 7.7],
  [7.7, 7],
]
/** The four yard spots of each colour (top left, top right, bottom right, bottom left quadrants). */
const YARD_ORIGIN: [number, number][] = [
  [0, 0],
  [0, 9],
  [9, 9],
  [9, 0],
]
const YARD_SPOTS: [number, number][] = [
  [1.8, 1.8],
  [1.8, 4.2],
  [4.2, 1.8],
  [4.2, 4.2],
]
const SAFE = [0, 8, 13, 21, 26, 34, 39, 47]
const COLOURS = ['#d8443c', '#2f9a52', '#e8b923', '#2f6fd6']
const COLOUR_KEYS = ['red', 'green', 'yellow', 'blue'] as const

/** Where (row, col) a token of `colour` at `position` is drawn; yard tokens use their own spot. */
function cellOf(colour: number, position: number, token: number): [number, number] {
  if (position === -1) {
    const [r, c] = YARD_ORIGIN[colour] ?? [0, 0]
    const [dr, dc] = YARD_SPOTS[token] ?? [0, 0]
    return [r + dr - 0.5, c + dc - 0.5]
  }
  if (position <= 50) return TRACK[(position + 13 * colour) % 52] ?? [0, 0]
  if (position <= 55) return HOME_COLUMN[colour]?.[position - 51] ?? [7, 7]
  return HOME[colour] ?? [7, 7]
}

/**
 * Ludo: the cross-shaped board (turned so your yard is at the bottom left), tokens that slide to their
 * squares, the die. Roll, then pick one of the tokens the server lets move (they pulse; the square they
 * would reach is marked).
 */
export default function LudoBoard({
  state,
  onAction,
  pending,
  players = {},
  bots = {},
  levels = {},
}: BoardProps) {
  const { t } = useTranslation()
  const view = state.view as unknown as LudoView
  const me = view.seat
  const moves = (state.legal_actions as unknown as Move[]).filter((a) => a.type === 'move')
  const canRoll = (state.legal_actions as unknown as { type: string }[]).some(
    (a) => a.type === 'roll',
  )
  const name = (seat: number) =>
    seat === me ? t('ludo.you') : players[seat] || t('ludo.seat', { seat: seat + 1 })
  const myColour = me === null ? 3 : (view.colours[me] ?? 3)
  // Turn the board so your yard (colour quadrant) is at the bottom left (colour 3's place).
  const turn = ((3 - myColour + 4) % 4) * 90
  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const turnShare =
    secondsLeft !== null && state.turn_seconds
      ? Math.min(1, secondsLeft / state.turn_seconds)
      : null

  const status =
    view.to_act === null
      ? ' '
      : view.to_act === me
        ? canRoll
          ? t('ludo.rollPrompt')
          : t('ludo.movePrompt', { dice: view.dice })
        : t('ludo.waitingFor', { name: name(view.to_act) })

  // Tokens sharing a cell sit side by side.
  const tokens = view.tokens.flatMap((list, seat) =>
    list.map((position, token) => ({ seat, token, position, colour: view.colours[seat] ?? 0 })),
  )
  const crowd = new Map<string, number>()
  const placed = tokens.map((tk) => {
    const [r, c] = cellOf(tk.colour, tk.position, tk.token)
    const key = tk.position === -1 ? `y${tk.seat}${tk.token}` : `${r},${c}`
    const index = crowd.get(key) ?? 0
    crowd.set(key, index + 1)
    return { ...tk, r, c, index }
  })

  return (
    <div className="grid gap-4 text-white min-[901px]:grid-cols-[1fr_16rem] min-[901px]:items-start">
      <div className="poker-stage mx-auto w-full max-w-[min(40rem,calc(100dvh-13rem))] rounded-3xl p-2 min-[641px]:p-4">
        <svg
          viewBox="-2 -2 154 154"
          role="img"
          aria-label={t('ludo.board')}
          className="block w-full rounded-2xl"
        >
          <rect x="-2" y="-2" width="154" height="154" rx="8" fill="#4a2c17" />
          <g style={{ transform: `rotate(${turn}deg)`, transformOrigin: '75px 75px' }}>
            <rect x="0" y="0" width="150" height="150" rx="4" fill="#f6efe0" />
            {/* Yards. */}
            {YARD_ORIGIN.map(([r, c], colour) => (
              <g key={colour}>
                <rect x={c * 10} y={r * 10} width="60" height="60" fill={COLOURS[colour]} />
                <rect x={c * 10 + 8} y={r * 10 + 8} width="44" height="44" rx="6" fill="#fdf9f0" />
                {YARD_SPOTS.map(([dr, dc], i) => (
                  <circle
                    key={i}
                    cx={(c + dc) * 10}
                    cy={(r + dr) * 10}
                    r="5.5"
                    fill="none"
                    stroke={COLOURS[colour]}
                    strokeWidth="1.5"
                  />
                ))}
              </g>
            ))}
            {/* The track, start squares in their colour, stars on the safe squares. */}
            {TRACK.map(([r, c], i) => {
              const start = i % 13 === 0 ? i / 13 : null
              return (
                <g key={i}>
                  <rect
                    x={c * 10}
                    y={r * 10}
                    width="10"
                    height="10"
                    fill={start !== null ? COLOURS[start] : '#fffdf7'}
                    stroke="#b9a983"
                    strokeWidth="0.4"
                  />
                  {SAFE.includes(i) && start === null ? (
                    <Star x={c * 10 + 5} y={r * 10 + 5} />
                  ) : null}
                </g>
              )
            })}
            {HOME_COLUMN.map((cells, colour) =>
              cells.map(([r, c]) => (
                <rect
                  key={`${colour}-${r}-${c}`}
                  x={c * 10}
                  y={r * 10}
                  width="10"
                  height="10"
                  fill={COLOURS[colour]}
                  fillOpacity="0.85"
                  stroke="#b9a983"
                  strokeWidth="0.4"
                />
              )),
            )}
            {/* The centre: four triangles, one per colour. */}
            <polygon points="60,60 75,75 60,90" fill={COLOURS[0]} />
            <polygon points="60,60 75,75 90,60" fill={COLOURS[1]} />
            <polygon points="90,60 75,75 90,90" fill={COLOURS[2]} />
            <polygon points="60,90 75,75 90,90" fill={COLOURS[3]} />

            {/* Where a movable token would land. */}
            {moves.map((m) => {
              const [r, c] = cellOf(myColour, m.to, m.token)
              return (
                <circle
                  key={`t${m.token}`}
                  cx={c * 10 + 5}
                  cy={r * 10 + 5}
                  r="3.5"
                  fill="none"
                  stroke="#1d1a17"
                  strokeWidth="1"
                  strokeDasharray="1.5 1.5"
                />
              )
            })}

            {placed.map(({ seat, token, position, colour, r, c, index }) => {
              const move = seat === me ? moves.find((m) => m.token === token) : undefined
              const offset = position === -1 ? 0 : index * 2.4 - 1.2 * (index > 0 ? 1 : 0)
              const [x, y] =
                position === -1
                  ? [c * 10 + 5, r * 10 + 5]
                  : [c * 10 + 5 + offset, r * 10 + 5 - offset]
              const label = t('ludo.token', {
                colour: t(`ludo.colour.${COLOUR_KEYS[colour] ?? 'red'}`),
                n: token + 1,
              })
              return (
                <g
                  key={`${seat}-${token}`}
                  style={{
                    transform: `translate(${x}px, ${y}px)`,
                    transition: 'transform 320ms ease-out',
                  }}
                  className="motion-reduce:transition-none"
                >
                  <g
                    role={move ? 'button' : undefined}
                    tabIndex={move && !pending ? 0 : undefined}
                    aria-label={move ? t('ludo.moveToken', { token: label }) : label}
                    onClick={() =>
                      move && !pending && onAction({ type: 'move', token, to: move.to })
                    }
                    onKeyDown={(e) => {
                      if (move && !pending && (e.key === 'Enter' || e.key === ' ')) {
                        e.preventDefault()
                        onAction({ type: 'move', token, to: move.to })
                      }
                    }}
                    className={move ? 'cursor-pointer outline-none' : undefined}
                  >
                    {move ? (
                      <circle
                        r="6.5"
                        fill="none"
                        stroke="#1d1a17"
                        strokeWidth="1.2"
                        className="motion-safe:animate-pulse"
                      />
                    ) : null}
                    <ellipse cx="0" cy="3" rx="4" ry="1.6" fill="rgb(0 0 0 / 0.3)" />
                    <circle r="4.2" fill={COLOURS[colour]} stroke="#1d1a17" strokeWidth="0.8" />
                    <circle r="2" fill="#fff" fillOpacity="0.55" />
                  </g>
                </g>
              )
            })}
          </g>
        </svg>
      </div>

      <aside className="club-panel space-y-4 rounded-3xl p-4">
        <div className="flex items-center gap-4">
          <Die value={view.dice} rollKey={view.turn * 10 + (view.dice ?? 0) + view.sixes * 100} />
          <div className="min-w-0 flex-1 space-y-2">
            <p className="text-sm text-white/85" aria-live="polite">
              {status}
            </p>
            {canRoll ? (
              <button
                type="button"
                disabled={pending}
                onClick={() => onAction({ type: 'roll' })}
                className="w-full rounded-xl border border-white/20 bg-linear-to-b from-accent-light to-accent px-4 py-3 text-base font-extrabold text-white shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-60"
              >
                {t('ludo.roll')}
              </button>
            ) : null}
          </div>
        </div>
        <ul aria-label={t('ludo.players')} className="space-y-2">
          {view.tokens.map((list, seat) => {
            const colour = view.colours[seat] ?? 0
            const home = list.filter((p) => p === 56).length
            const acting = view.to_act === seat
            return (
              <li
                key={seat}
                aria-current={acting ? 'step' : undefined}
                className={`relative flex items-center gap-2 rounded-xl border px-3 py-2 ${
                  acting ? 'border-[var(--color-turn)] bg-black/35' : 'border-white/10 bg-black/20'
                } ${view.out[seat] ? 'opacity-45' : ''}`}
                style={
                  acting && turnShare !== null
                    ? {
                        boxShadow: `inset ${Math.round(turnShare * 240)}px 0 0 rgb(242 193 78 / 0.12)`,
                      }
                    : undefined
                }
              >
                <span
                  aria-hidden="true"
                  className="size-4 shrink-0 rounded-full border border-black/40"
                  style={{ background: COLOURS[colour] }}
                />
                <span className="min-w-0 flex-1 truncate text-sm font-semibold">
                  {name(seat)}
                  {bots[seat] && seat !== me ? (
                    <span className="ml-1.5 rounded-full bg-black/50 px-1.5 py-0.5 text-[0.6rem] font-semibold uppercase">
                      {t('room.bot')}
                    </span>
                  ) : null}
                </span>
                {levels[seat] !== undefined ? <LevelBadge level={levels[seat]} size="sm" /> : null}
                <span className="text-xs text-gold-light tabular-nums">
                  {t('ludo.home', { count: home })}
                </span>
              </li>
            )
          })}
        </ul>
      </aside>
    </div>
  )
}

function Star({ x, y }: { x: number; y: number }) {
  const points = Array.from({ length: 10 }, (_, i) => {
    const r = i % 2 === 0 ? 3.6 : 1.5
    const a = (Math.PI / 5) * i - Math.PI / 2
    return `${x + r * Math.cos(a)},${y + r * Math.sin(a)}`
  }).join(' ')
  return <polygon points={points} fill="#b9a983" />
}

const DIE_PIPS: Record<number, [number, number][]> = {
  1: [[1, 1]],
  2: [
    [0, 0],
    [2, 2],
  ],
  3: [
    [0, 0],
    [1, 1],
    [2, 2],
  ],
  4: [
    [0, 0],
    [0, 2],
    [2, 0],
    [2, 2],
  ],
  5: [
    [0, 0],
    [0, 2],
    [1, 1],
    [2, 0],
    [2, 2],
  ],
  6: [
    [0, 0],
    [0, 1],
    [0, 2],
    [2, 0],
    [2, 1],
    [2, 2],
  ],
}

/** The die; it tumbles each time a new roll arrives (not under reduced motion). */
function Die({ value, rollKey }: { value: number | null; rollKey: number }) {
  const { t } = useTranslation()
  return (
    <span
      key={rollKey}
      role="img"
      aria-label={value === null ? t('ludo.noRoll') : t('ludo.rolled', { dice: value })}
      className="block size-16 shrink-0 rounded-xl bg-[#fbf8ef] shadow-[inset_0_-3px_0_rgb(0_0_0/0.15),0_6px_14px_rgb(0_0_0/0.45)] motion-safe:animate-dice-roll"
    >
      <svg viewBox="0 0 30 30" aria-hidden="true" className="size-full">
        {(value ? (DIE_PIPS[value] ?? []) : []).map(([x, y], i) => (
          <circle key={i} cx={7 + x * 8} cy={7 + y * 8} r="2.6" fill="#1d1a17" />
        ))}
      </svg>
    </span>
  )
}
