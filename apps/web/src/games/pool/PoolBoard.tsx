import { useEffect, useEffectEvent, useId, useRef, useState, type PointerEvent } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import type { BoardProps } from '../registry'

type Point = [number, number]
/** [t ms, x, y, vx, vy] or, when the ball dropped into a pocket at that moment, [..., 1]. */
type Segment = [number, number, number, number, number, 1?]

export interface PoolView {
  seat: number | null
  balls: (Point | null)[] | Record<string, Point | null>
  groups: ('solids' | 'stripes' | null)[]
  to_act: number | null
  phase: 'aim' | 'rolling'
  ball_in_hand: boolean
  broken: boolean
  shot: number
  last_shot: {
    number: number
    seat: number
    potted: number[]
    foul: 'scratch' | 'no_contact' | 'wrong_ball' | 'no_rail' | null
    duration_ms: number
    segments: Record<string, Segment[]> | Segment[][]
  } | null
}

// Table geometry in millimetres (drawing only; the server plays the shots).
const W = 2540
const H = 1270
const R = 28.575
const DECEL = 420
const RAIL = 130
const POCKETS: Point[] = [
  [0, 0],
  [W / 2, -8],
  [W, 0],
  [0, H],
  [W / 2, H + 8],
  [W, H],
]
const COLOURS = [
  '#f8f5ec',
  '#f2c230',
  '#1f4fb4',
  '#c8282e',
  '#5b2a86',
  '#e8731d',
  '#1f7a3a',
  '#7a1f1f',
  '#151515',
]
const colourOf = (id: number) => COLOURS[id > 8 ? id - 8 : id] ?? '#fff'

const ballsOf = (balls: PoolView['balls']): (Point | null)[] =>
  Array.from({ length: 16 }, (_, id) => (balls as Record<string, Point | null>)[id] ?? null)

/** Where a ball is `t` ms into a shot, from its segments (null once it has dropped in). */
function positionAt(segments: Segment[], t: number): Point | null {
  let segment = segments[0]
  for (const s of segments) if (s[0] <= t) segment = s
  if (!segment) return null
  const [t0, x, y, vx, vy, potted] = segment
  if (potted && t >= t0) return null
  if (t < t0) return [x, y]
  const speed = Math.hypot(vx, vy)
  if (speed === 0) return [x, y]
  const dt = Math.min((t - t0) / 1000, speed / DECEL)
  const travel = speed * dt - 0.5 * DECEL * dt * dt
  return [x + (vx / speed) * travel, y + (vy / speed) * travel]
}

const reducedMotion = () =>
  typeof window !== 'undefined' && !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

/**
 * Plays a new shot's segments back (unless it was already over when the page opened): the balls as they
 * are `t` ms into it, or null when not animating.
 */
function useShotPlayback(view: PoolView): (Point | null)[] | null {
  const shot = view.last_shot
  const [seen] = useState(() => shot?.number ?? 0)
  const [frame, setFrame] = useState<(Point | null)[] | null>(null)
  const number = shot?.number ?? 0
  // The shot and where the balls ended up, read when a new shot starts (not on every update).
  const latest = useEffectEvent(() => ({ current: shot, final: ballsOf(view.balls) }))

  useEffect(() => {
    const { current, final } = latest()
    if (!current || number <= seen || reducedMotion()) return
    const segments = current.segments as Record<string, Segment[]>
    const start = performance.now()
    let raf = 0
    const tick = (now: number) => {
      const t = now - start
      if (t >= current.duration_ms) {
        setFrame(null)
        return
      }
      setFrame(
        final.map((ball, id) => {
          const own = segments[id]
          return own && own.length > 0 ? positionAt(own, t) : ball
        }),
      )
      raf = requestAnimationFrame(tick)
    }
    raf = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(raf)
  }, [number, seen])

  return frame
}

/**
 * 8-ball: the table (upright on phones), the balls, and — on your turn — a cue that follows your pointer,
 * a power slider and the shot button. With the cue ball in hand you first tap where to put it. Every shot
 * is played out on the server; the table replays its result.
 */
export default function PoolBoard({
  state,
  onAction,
  pending,
  players = {},
  levels = {},
}: BoardProps) {
  const { t } = useTranslation()
  const view = state.view as unknown as PoolView
  const me = view.seat
  const shoot = (state.legal_actions as unknown as { type: string }[]).find(
    (a) => a.type === 'shoot',
  ) as { type: 'shoot'; ball_in_hand: boolean; min: number; max: number } | undefined
  const narrow = useMediaQuery('(max-width: 640px)')
  const frame = useShotPlayback(view)
  const animating = frame !== null
  const balls = frame ?? ballsOf(view.balls)
  const canShoot = !!shoot && !animating

  const [angle, setAngle] = useState(0)
  const [power, setPower] = useState(60)
  const [placed, setPlaced] = useState<Point | null>(null)
  const [placing, setPlacing] = useState(() => !!shoot?.ball_in_hand)
  const inHand = !!shoot?.ball_in_hand
  const cue: Point | null = inHand ? (placed ?? balls[0] ?? [W / 4, H / 2]) : (balls[0] ?? null)
  const tableRef = useRef<SVGGElement>(null)

  // A new turn with the cue ball in hand starts by placing it.
  const turnKey = `${view.shot}-${view.to_act}`
  const [lastTurn, setLastTurn] = useState(turnKey)
  if (lastTurn !== turnKey) {
    setLastTurn(turnKey)
    setPlaced(null)
    setPlacing(inHand)
  }

  const toTable = (e: PointerEvent): Point | null => {
    const g = tableRef.current
    const matrix = g?.getScreenCTM()
    if (!g || !matrix) return null
    const p = new DOMPoint(e.clientX, e.clientY).matrixTransform(matrix.inverse())
    return [p.x, p.y]
  }
  const onPointer = (e: PointerEvent, press: boolean) => {
    if (!canShoot || !cue) return
    const p = toTable(e)
    if (!p) return
    if (placing) {
      if (press) {
        const x = Math.min(W - R, Math.max(R, p[0]))
        const y = Math.min(H - R, Math.max(R, p[1]))
        setPlaced([x, y])
        setPlacing(false)
      }
      return
    }
    if (e.pointerType === 'mouse' && !press && e.buttons === 0) {
      setAngle((Math.atan2(p[1] - cue[1], p[0] - cue[0]) * 180) / Math.PI)
    } else if (press || e.buttons > 0) {
      setAngle((Math.atan2(p[1] - cue[1], p[0] - cue[0]) * 180) / Math.PI)
    }
  }
  const fire = () => {
    if (!canShoot || !cue) return
    const rounded = Math.round(angle * 100) / 100
    onAction({
      type: 'shoot',
      angle: rounded,
      power,
      ...(inHand ? { x: Math.round(cue[0] * 10) / 10, y: Math.round(cue[1] * 10) / 10 } : {}),
    })
  }

  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const name = (seat: number) =>
    seat === me ? t('pool.you') : players[seat] || t('pool.seat', { seat: seat + 1 })
  const rad = (angle * Math.PI) / 180
  const dir: Point = [Math.cos(rad), Math.sin(rad)]
  const shot = view.last_shot
  const status =
    animating || view.phase === 'rolling'
      ? t('pool.rolling')
      : view.to_act === null
        ? ' '
        : view.to_act === me
          ? placing
            ? t('pool.placeCue')
            : t('pool.yourShot')
          : t('pool.waitingFor', { name: name(view.to_act) })

  return (
    <div className="space-y-3 text-white">
      <div className="flex flex-wrap items-stretch justify-center gap-2">
        {[0, 1].map((seat) => (
          <PlayerPlate
            key={seat}
            name={name(seat)}
            group={view.groups[seat] ?? null}
            balls={balls}
            acting={view.to_act === seat && !animating}
            level={levels[seat]}
            seconds={view.to_act === seat ? secondsLeft : null}
          />
        ))}
      </div>

      <div className="poker-stage mx-auto w-full max-w-[min(64rem,calc((100dvh-17rem)*2))] rounded-3xl p-2 max-[640px]:max-w-[min(24rem,calc((100dvh-20rem)/2))] min-[641px]:p-4">
        <svg
          viewBox={
            narrow
              ? `${-RAIL} ${-RAIL} ${H + 2 * RAIL} ${W + 2 * RAIL}`
              : `${-RAIL} ${-RAIL} ${W + 2 * RAIL} ${H + 2 * RAIL}`
          }
          className={`block w-full touch-none select-none ${canShoot ? (placing ? 'cursor-copy' : 'cursor-crosshair') : ''}`}
          role="img"
          aria-label={t('pool.table')}
          onPointerMove={(e) => onPointer(e, false)}
          onPointerDown={(e) => onPointer(e, true)}
        >
          <g ref={tableRef} transform={narrow ? `translate(${H} 0) rotate(90)` : undefined}>
            <rect
              x={-RAIL}
              y={-RAIL}
              width={W + 2 * RAIL}
              height={H + 2 * RAIL}
              rx="70"
              fill="#5a3416"
            />
            <rect
              x={-RAIL + 18}
              y={-RAIL + 18}
              width={W + 2 * RAIL - 36}
              height={H + 2 * RAIL - 36}
              rx="56"
              fill="none"
              stroke="rgb(214 171 78 / 0.45)"
              strokeWidth="5"
            />
            <rect x={-40} y={-40} width={W + 80} height={H + 80} rx="18" fill="#135a43" />
            <rect x="0" y="0" width={W} height={H} fill="url(#pool-cloth)" />
            <defs>
              <radialGradient id="pool-cloth" cx="50%" cy="45%" r="65%">
                <stop offset="0%" stopColor="#238a64" />
                <stop offset="100%" stopColor="#176b4c" />
              </radialGradient>
            </defs>
            {/* Diamonds on the rails. */}
            {[1, 2, 3, 5, 6, 7].map((i) => (
              <g key={i} fill="#f3e6c4">
                <circle cx={(W / 8) * i} cy={-RAIL / 2 - 15} r="9" />
                <circle cx={(W / 8) * i} cy={H + RAIL / 2 + 15} r="9" />
              </g>
            ))}
            {[1, 2, 3].map((i) => (
              <g key={`s${i}`} fill="#f3e6c4">
                <circle cx={-RAIL / 2 - 15} cy={(H / 4) * i} r="9" />
                <circle cx={W + RAIL / 2 + 15} cy={(H / 4) * i} r="9" />
              </g>
            ))}
            {/* Head string and foot spot. */}
            <line
              x1={W / 4}
              y1="0"
              x2={W / 4}
              y2={H}
              stroke="rgb(255 255 255 / 0.12)"
              strokeWidth="3"
            />
            <circle cx={(W * 3) / 4} cy={H / 2} r="7" fill="rgb(255 255 255 / 0.3)" />
            {POCKETS.map(([x, y], i) => (
              <circle
                key={i}
                cx={x}
                cy={y}
                r={i === 1 || i === 4 ? 56 : 64}
                fill="#0b0a09"
                stroke="#2b1a0c"
                strokeWidth="6"
              />
            ))}

            <clipPath id="pool-cloth-clip">
              <rect x="0" y="0" width={W} height={H} />
            </clipPath>
            {/* Aim: the line the cue ball would start along (on the cloth), and the cue behind it. */}
            {canShoot && cue && !placing ? (
              <g pointerEvents="none">
                <line
                  clipPath="url(#pool-cloth-clip)"
                  x1={cue[0] + dir[0] * R}
                  y1={cue[1] + dir[1] * R}
                  x2={cue[0] + dir[0] * 1500}
                  y2={cue[1] + dir[1] * 1500}
                  stroke="rgb(255 255 255 / 0.55)"
                  strokeWidth="4"
                  strokeDasharray="18 14"
                />
                <line
                  x1={cue[0] - dir[0] * (R + 20 + power * 2.2)}
                  y1={cue[1] - dir[1] * (R + 20 + power * 2.2)}
                  x2={cue[0] - dir[0] * (R + 20 + power * 2.2 + 1150)}
                  y2={cue[1] - dir[1] * (R + 20 + power * 2.2 + 1150)}
                  stroke="url(#pool-cue)"
                  strokeWidth="18"
                  strokeLinecap="round"
                />
                <defs>
                  <linearGradient
                    id="pool-cue"
                    gradientUnits="userSpaceOnUse"
                    x1={cue[0] - dir[0] * (R + 20)}
                    y1={cue[1] - dir[1] * (R + 20)}
                    x2={cue[0] - dir[0] * (R + 1300)}
                    y2={cue[1] - dir[1] * (R + 1300)}
                  >
                    <stop offset="0" stopColor="#f6efe0" />
                    <stop offset="0.04" stopColor="#2b6fb5" />
                    <stop offset="0.06" stopColor="#e9d3a5" />
                    <stop offset="0.7" stopColor="#c08a4c" />
                    <stop offset="1" stopColor="#3a1f0c" />
                  </linearGradient>
                </defs>
              </g>
            ) : null}

            {balls.map((ball, id) =>
              ball && !(id === 0 && inHand && canShoot) ? (
                <Ball key={id} id={id} at={ball} />
              ) : null,
            )}
            {inHand && canShoot && cue ? (
              <g opacity={placing ? 0.6 : 1}>
                <Ball id={0} at={cue} />
                {placing ? (
                  <circle
                    cx={cue[0]}
                    cy={cue[1]}
                    r={R + 10}
                    fill="none"
                    stroke="#fff"
                    strokeDasharray="8 8"
                    strokeWidth="3"
                  />
                ) : null}
              </g>
            ) : null}
          </g>
        </svg>
      </div>

      <div className="club-panel mx-auto flex max-w-3xl flex-col items-center gap-3 rounded-3xl p-3 min-[641px]:flex-row min-[641px]:p-4">
        {canShoot ? (
          <>
            <div className="flex items-center gap-1">
              <button
                type="button"
                onClick={() => setAngle((a) => a - 0.5)}
                aria-label={t('pool.aimLeft')}
                className="size-11 rounded-full border border-gold/40 bg-black/30 text-lg font-bold"
              >
                ↺
              </button>
              <button
                type="button"
                onClick={() => setAngle((a) => a + 0.5)}
                aria-label={t('pool.aimRight')}
                className="size-11 rounded-full border border-gold/40 bg-black/30 text-lg font-bold"
              >
                ↻
              </button>
              {inHand ? (
                <button
                  type="button"
                  aria-pressed={placing}
                  onClick={() => setPlacing((p) => !p)}
                  className="ml-1 min-h-11 rounded-full border border-gold/40 bg-black/30 px-3 text-xs font-semibold aria-pressed:border-gold aria-pressed:text-gold-light"
                >
                  {t('pool.moveCue')}
                </button>
              ) : null}
            </div>
            <label className="flex w-full flex-1 items-center gap-3 text-sm">
              <span className="shrink-0 font-semibold">{t('pool.power')}</span>
              <input
                type="range"
                min={shoot?.min ?? 1}
                max={shoot?.max ?? 100}
                value={power}
                onChange={(e) => setPower(Number(e.target.value))}
                className="h-11 min-w-0 flex-1 accent-[var(--color-gold)]"
              />
              <span className="w-8 text-right font-bold text-gold-light tabular-nums">{power}</span>
            </label>
            <button
              type="button"
              disabled={pending || placing}
              onClick={fire}
              className="w-full rounded-xl border border-white/20 bg-linear-to-b from-accent-light to-accent px-6 py-3 text-base font-extrabold text-white shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-60 min-[641px]:w-auto"
            >
              {t('pool.shoot')}
            </button>
          </>
        ) : (
          <p className="w-full text-center text-sm" aria-live="polite">
            {status}
          </p>
        )}
      </div>
      {canShoot ? (
        <p className="text-center text-xs text-white/70" aria-live="polite">
          {status}
        </p>
      ) : null}
      {shot && !animating ? (
        <p className="text-center text-sm" role="status">
          {shot.foul ? (
            <span className="rounded-full bg-[#8e3a31] px-3 py-1 font-semibold">
              {t(`pool.foul.${shot.foul}`)} ·{' '}
              {t('pool.ballInHandFor', { name: name(1 - shot.seat) })}
            </span>
          ) : shot.potted.length > 0 ? (
            <span className="text-gold-light">
              {t('pool.potted', {
                count: shot.potted.filter((b) => b !== 0).length,
                name: name(shot.seat),
              })}
            </span>
          ) : null}
        </p>
      ) : null}
    </div>
  )
}

/** A ball: solid colour, or white with a coloured band (stripes), and its number. */
function Ball({ id, at }: { id: number; at: Point }) {
  const clip = useId()
  const [x, y] = at
  const colour = colourOf(id)
  const stripe = id > 8
  return (
    <g transform={`translate(${x} ${y})`}>
      <circle
        r={R}
        fill={stripe ? '#f8f5ec' : colour}
        stroke="rgb(0 0 0 / 0.35)"
        strokeWidth="1.5"
      />
      {stripe ? (
        <>
          <clipPath id={clip}>
            <circle r={R} />
          </clipPath>
          <rect
            x={-R}
            y={-R * 0.55}
            width={2 * R}
            height={R * 1.1}
            fill={colour}
            clipPath={`url(#${clip})`}
          />
        </>
      ) : null}
      {id > 0 ? (
        <>
          <circle r={R * 0.45} fill="#f8f5ec" />
          <text
            y={R * 0.2}
            textAnchor="middle"
            fontSize={R * 0.55}
            fontWeight="800"
            fill="#151515"
            fontFamily="system-ui, sans-serif"
          >
            {id}
          </text>
        </>
      ) : null}
      <circle cx={-R * 0.35} cy={-R * 0.35} r={R * 0.28} fill="#fff" opacity="0.45" />
    </g>
  )
}

/** A player: name, level, their group with the balls still on the table, the turn. */
function PlayerPlate({
  name,
  group,
  balls,
  acting,
  level,
  seconds,
}: {
  name: string
  group: 'solids' | 'stripes' | null
  balls: (Point | null)[]
  acting: boolean
  level?: number
  seconds: number | null
}) {
  const { t } = useTranslation()
  const ids =
    group === 'solids'
      ? [1, 2, 3, 4, 5, 6, 7]
      : group === 'stripes'
        ? [9, 10, 11, 12, 13, 14, 15]
        : []
  return (
    <div
      aria-current={acting ? 'step' : undefined}
      className={`relative flex min-w-40 items-center gap-2 rounded-xl border bg-black/35 px-3 py-2 ${acting ? 'border-[var(--color-turn)] shadow-[0_0_0_1px_var(--color-turn)]' : 'border-white/15'}`}
    >
      {level !== undefined ? <LevelBadge level={level} size="sm" /> : null}
      <div className="min-w-0">
        <p className="truncate text-sm font-semibold">{name}</p>
        <p className="flex items-center gap-1 text-xs text-white/70">
          {group === null ? (
            t('pool.openTable')
          ) : (
            <>
              <span className="sr-only">{t(`pool.group.${group}`)}</span>
              {ids.map((id) => (
                <svg
                  key={id}
                  viewBox="-30 -30 60 60"
                  aria-hidden="true"
                  className={`size-4 ${balls[id] ? '' : 'opacity-25'}`}
                >
                  <Ball id={id} at={[0, 0]} />
                </svg>
              ))}
            </>
          )}
        </p>
      </div>
      {acting && seconds !== null ? (
        <span className="ml-auto text-xs font-bold text-[var(--color-turn)] tabular-nums">
          {seconds}
        </span>
      ) : null}
    </div>
  )
}
