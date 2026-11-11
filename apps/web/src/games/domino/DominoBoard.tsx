import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import type { BoardProps } from '../registry'

export interface DominoView {
  seat: number | null
  target: number
  scores: number[]
  out: boolean[]
  round: number
  phase: 'playing' | 'over'
  hand: string[]
  hand_sizes: number[]
  line: { tile: [number, number]; seat: number }[]
  ends: [number, number] | null
  to_act: number | null
  last_round: {
    number: number
    winner: number | null
    points: number
    blocked: boolean
    pips: Record<string, number>
    hands: string[][]
  } | null
}

type Play = { type: 'play'; tile: string; end: 'left' | 'right' }

/**
 * Block dominoes: the line on the felt (doubles crosswise), the other players across the top with the
 * tiles they hold, your tiles below. Only the server's legal moves can be made: pick a tile; when it fits
 * both ends, pick the end. After a round the table shows who scored and everyone's tiles.
 */
export default function DominoBoard({
  state,
  onAction,
  pending,
  players = {},
  bots = {},
  levels = {},
}: BoardProps) {
  const { t, i18n } = useTranslation()
  const view = state.view as unknown as DominoView
  const me = view.seat
  const moves = (state.legal_actions as unknown as Play[]).filter((a) => a.type === 'play')
  const [picked, setPicked] = useState<string | null>(null)
  const format = (n: number) => n.toLocaleString(i18n.language)
  const name = (seat: number) =>
    seat === me ? t('domino.you') : players[seat] || t('domino.seat', { seat: seat + 1 })
  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const turnShare =
    secondsLeft !== null && state.turn_seconds
      ? Math.min(1, secondsLeft / state.turn_seconds)
      : null

  const endsFor = (tile: string) => moves.filter((m) => m.tile === tile).map((m) => m.end)
  const pickedEnds = picked ? endsFor(picked) : []
  const choose = (tile: string) => {
    const ends = endsFor(tile)
    if (ends.length === 1 && ends[0]) {
      setPicked(null)
      onAction({ type: 'play', tile, end: ends[0] })
    } else if (ends.length > 1) {
      setPicked(picked === tile ? null : tile)
    }
  }
  const playAt = (end: 'left' | 'right') => {
    if (!picked) return
    onAction({ type: 'play', tile: picked, end })
    setPicked(null)
  }

  const seats = view.scores.map((_, seat) => seat)
  const others = me === null ? seats : seats.filter((s) => s !== me)
  const result = view.phase === 'over' || state.result ? view.last_round : null
  const status =
    view.phase === 'over' && !state.result
      ? secondsLeft !== null
        ? t('domino.nextRoundIn', { count: secondsLeft })
        : t('domino.nextRound')
      : view.to_act === null
        ? ' '
        : view.to_act === me
          ? picked
            ? t('domino.pickEnd')
            : t('domino.yourTurn')
          : t('domino.waitingFor', { name: name(view.to_act) })

  return (
    <div className="space-y-3 text-white">
      <div className="poker-stage relative rounded-3xl p-2 min-[641px]:p-5">
        {/* The other players, across the top. */}
        <ul
          aria-label={t('domino.players')}
          className="mb-4 flex flex-wrap justify-center gap-x-2 gap-y-4"
        >
          {others.map((seat) => (
            <li key={seat} aria-current={view.to_act === seat ? 'step' : undefined}>
              <Plate
                name={name(seat)}
                score={format(view.scores[seat] ?? 0)}
                tiles={view.hand_sizes[seat] ?? 0}
                tilesLabel={t('domino.tilesLeft', { count: view.hand_sizes[seat] ?? 0 })}
                acting={view.to_act === seat}
                share={view.to_act === seat ? turnShare : null}
                level={levels[seat]}
                bot={!!bots[seat]}
                botLabel={t('room.bot')}
                gone={view.out[seat] ?? false}
              />
            </li>
          ))}
        </ul>

        {/* The line of play. */}
        <section
          aria-label={t('domino.line')}
          className="blackjack-felt relative flex min-h-56 items-center justify-center overflow-hidden rounded-[1.6rem] p-3 shadow-[0_0_0_6px_#4a2c17,0_0_0_8px_rgb(214_171_78/0.4)] min-[641px]:min-h-72 min-[641px]:p-6"
        >
          {result ? (
            <RoundResult result={result} name={name} me={me} format={format} />
          ) : (
            <div className="flex max-w-full flex-wrap items-center justify-center gap-y-3">
              {pickedEnds.includes('left') ? (
                <EndSlot label={t('domino.leftEnd')} onClick={() => playAt('left')} />
              ) : null}
              {view.line.map(({ tile }, i) => (
                <span
                  key={`${view.round}-${i}-${tile.join('')}`}
                  className="motion-safe:animate-card-in"
                >
                  <Tile
                    a={tile[0]}
                    b={tile[1]}
                    upright={tile[0] === tile[1]}
                    size="line"
                    label={t('domino.tile', { a: tile[0], b: tile[1] })}
                  />
                </span>
              ))}
              {pickedEnds.includes('right') ? (
                <EndSlot label={t('domino.rightEnd')} onClick={() => playAt('right')} />
              ) : null}
              {view.line.length === 0 ? (
                <p className="text-sm font-semibold text-gold-light/80">{status}</p>
              ) : null}
            </div>
          )}
          <dl className="absolute top-2 left-3 flex gap-2 text-[0.7rem] text-white/70 min-[641px]:text-xs">
            <dt>{t('domino.round')}</dt>
            <dd className="font-bold text-white tabular-nums">{view.round}</dd>
            <dt className="ml-1">{t('domino.target')}</dt>
            <dd className="font-bold text-gold-light tabular-nums">{format(view.target)}</dd>
          </dl>
        </section>

        {/* You: your score and your tiles. */}
        {me !== null ? (
          <div className="mt-3 flex flex-col items-center gap-2">
            <Plate
              name={name(me)}
              score={format(view.scores[me] ?? 0)}
              tiles={0}
              tilesLabel=""
              acting={view.to_act === me}
              share={view.to_act === me ? turnShare : null}
              level={levels[me]}
              bot={false}
              botLabel=""
              gone={false}
              mine
            />
            <section
              aria-label={t('domino.yourTiles')}
              className="flex flex-wrap justify-center gap-1.5"
            >
              {view.hand.map((tile) => {
                const [a, b] = tile.split('-').map(Number) as [number, number]
                const playable = endsFor(tile).length > 0
                return (
                  <button
                    key={tile}
                    type="button"
                    disabled={!playable || pending}
                    aria-pressed={picked === tile}
                    onClick={() => choose(tile)}
                    aria-label={t('domino.tile', { a, b })}
                    className={`rounded-md transition ${
                      picked === tile
                        ? '-translate-y-2 ring-2 ring-gold'
                        : playable
                          ? '-translate-y-1 motion-safe:hover:-translate-y-2'
                          : view.to_act === me
                            ? 'brightness-50'
                            : ''
                    }`}
                  >
                    <Tile a={a} b={b} upright size="hand" />
                  </button>
                )
              })}
            </section>
          </div>
        ) : null}
      </div>
      <p className="text-center text-sm text-white/80" aria-live="polite">
        {view.line.length > 0 || result ? status : ''}
      </p>
    </div>
  )
}

/** A gap at an end of the line where the picked tile can go. */
function EndSlot({ label, onClick }: { label: string; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="mx-1 h-10 w-16 rounded-md border-2 border-dashed border-gold bg-gold/15 text-[0.65rem] font-bold text-gold-light motion-safe:animate-pulse"
    >
      {label}
    </button>
  )
}

/** Pip positions on a 3×3 grid for 0–6. */
const PIPS: Record<number, [number, number][]> = {
  0: [],
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
    [2, 0],
    [0, 2],
    [2, 2],
  ],
  5: [
    [0, 0],
    [2, 0],
    [1, 1],
    [0, 2],
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

/** An ivory tile with two halves of pips; lying (a | b) or standing (a over b). */
export function Tile({
  a,
  b,
  upright = false,
  size,
  label,
}: {
  a: number
  b: number
  upright?: boolean
  size: 'line' | 'hand' | 'small'
  label?: string
}) {
  const half = (n: number, dx: number, dy: number) =>
    (PIPS[n] ?? []).map(([x, y], i) => (
      <circle
        key={`${dx}-${dy}-${i}`}
        cx={dx + 8 + x * 12}
        cy={dy + 8 + y * 12}
        r="3.2"
        fill="#1d1a17"
      />
    ))
  const box = {
    line: upright
      ? 'h-12 w-6 min-[641px]:h-14 min-[641px]:w-7'
      : 'h-6 w-12 min-[641px]:h-7 min-[641px]:w-14',
    hand: 'h-16 w-8 min-[641px]:h-20 min-[641px]:w-10',
    small: upright ? 'h-8 w-4' : 'h-4 w-8',
  }[size]
  return (
    <svg
      viewBox={upright ? '0 0 40 80' : '0 0 80 40'}
      role={label ? 'img' : undefined}
      aria-label={label}
      aria-hidden={label ? undefined : true}
      className={`block shrink-0 drop-shadow-[0_2px_2px_rgb(0_0_0/0.45)] ${box}`}
    >
      <rect
        x="1"
        y="1"
        width={upright ? 38 : 78}
        height={upright ? 78 : 38}
        rx="6"
        fill="#f7f1e3"
        stroke="#b9a983"
        strokeWidth="1.5"
      />
      {upright ? (
        <line x1="6" y1="40" x2="34" y2="40" stroke="#9c8b6b" strokeWidth="1.5" />
      ) : (
        <line x1="40" y1="6" x2="40" y2="34" stroke="#9c8b6b" strokeWidth="1.5" />
      )}
      {upright ? [half(a, 0, 0), half(b, 0, 40)] : [half(a, 0, 0), half(b, 40, 0)]}
    </svg>
  )
}

function RoundResult({
  result,
  name,
  me,
  format,
}: {
  result: NonNullable<DominoView['last_round']>
  name: (seat: number) => string
  me: number | null
  format: (n: number) => string
}) {
  const { t } = useTranslation()
  const won = result.winner !== null && result.winner === me
  return (
    <section
      aria-label={t('domino.roundResult')}
      className={`w-full max-w-md rounded-2xl border bg-black/45 p-3 text-sm motion-safe:animate-rise ${won ? 'border-gold shadow-[0_0_24px_rgb(214_171_78/0.4)]' : 'border-white/15'}`}
    >
      <p className="mb-2 text-center font-bold" role="status">
        {result.winner === null
          ? t('domino.blockedTie')
          : t(result.blocked ? 'domino.wonBlocked' : 'domino.wonOut', {
              name: name(result.winner),
              points: format(result.points),
            })}
      </p>
      <ul className="space-y-1.5">
        {Object.entries(result.pips).map(([seat, pips]) => (
          <li key={seat} className="flex items-center gap-2">
            <span className="w-20 truncate text-white/80">{name(Number(seat))}</span>
            <span className="flex flex-1 flex-wrap gap-0.5">
              {(result.hands[Number(seat)] ?? []).map((tile) => {
                const [a, b] = tile.split('-').map(Number) as [number, number]
                return <Tile key={tile} a={a} b={b} size="small" />
              })}
            </span>
            <span className="text-white/70 tabular-nums">{t('domino.pips', { count: pips })}</span>
          </li>
        ))}
      </ul>
    </section>
  )
}

/** A player's plate: name, score, tiles in hand, the turn ring. */
function Plate({
  name,
  score,
  tiles,
  tilesLabel,
  acting,
  share,
  level,
  bot,
  botLabel,
  gone,
  mine = false,
}: {
  name: string
  score: string
  tiles: number
  tilesLabel: string
  acting: boolean
  share: number | null
  level?: number
  bot: boolean
  botLabel: string
  gone: boolean
  mine?: boolean
}) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : mine
          ? 'var(--color-gold)'
          : 'rgb(214 171 78 / 0.35)'
  return (
    <span
      className={`relative block rounded-lg p-[2px] shadow-lg shadow-black/50 ${gone ? 'opacity-45' : ''}`}
      style={{ background: ring }}
    >
      <span className="flex items-center gap-2 rounded-[0.4rem] bg-[#1c120d]/95 px-2.5 py-1">
        <span className="max-w-24 truncate text-xs font-semibold min-[641px]:text-sm">{name}</span>
        <span className="text-xs font-extrabold text-gold-light tabular-nums">{score}</span>
        {tiles > 0 ? (
          <span className="flex items-center gap-0.5" aria-label={tilesLabel} role="img">
            <span aria-hidden="true" className="h-3.5 w-2 rounded-[2px] bg-[#f7f1e3]/90" />
            <span className="text-xs text-white/75 tabular-nums">{tiles}</span>
          </span>
        ) : null}
      </span>
      {level !== undefined ? (
        <LevelBadge level={level} size="sm" className="absolute -top-2 -left-2" />
      ) : null}
      {bot ? (
        <span className="absolute -bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-black/70 px-1.5 py-0.5 text-[0.6rem] leading-none font-semibold tracking-wide text-white/85 uppercase">
          {botLabel}
        </span>
      ) : null}
    </span>
  )
}
