import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { waitingCaption } from '@/features/play/turnCaption'
import { useCountdown } from '@/features/play/useCountdown'
import type { BoardProps } from '../registry'

interface NardiView {
  points: number[]
  bar: [number, number]
  off: [number, number]
  /** "long" for Long nardi; absent for Short. */
  variant?: 'long'
  /** Pips each player still has to travel (from the server). */
  pips?: [number, number]
  turn: number
  dice: number[]
  rolled: [number, number]
  winner: number | null
}

type Step = {
  type: 'move'
  from: number | 'bar'
  to: number | 'off'
  die: number
}

/**
 * Nardi board: a walnut case with inlaid points and carved rosettes, our own art (TASK-057). Shows the
 * server's view from the player's side (their home board bottom right) and offers exactly the server's
 * legal steps: pick a checker, then one of its highlighted targets.
 */
export default function NardiBoard({ state, onAction, pending, players }: BoardProps) {
  const { t } = useTranslation()
  const view = state.view as unknown as NardiView
  const me = state.seat ?? 0
  const steps = state.legal_actions as unknown as Step[]
  const [selected, setSelected] = useState<number | 'bar' | null>(null)
  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const board = useRef<HTMLDivElement>(null)

  const sources = new Set(steps.map((s) => s.from))
  const targets = steps.filter((s) => s.from === selected)
  // Every player sees their own way ending bottom right. Short: seat 1 sees the board turned round.
  // Long: seat 1's way runs 12 → 1, 24 → 13, so their view is turned by half a board.
  const long = view.variant === 'long'
  const way = (p: number) => (p <= 12 ? 13 - p : 37 - p) // seat 1's Long way; its own inverse
  const display = (p: number) => (me === 0 ? p : long ? 25 - way(p) : 25 - p)
  const owner = (p: number) => {
    const n = view.points[p - 1] ?? 0
    return n === 0 ? null : n > 0 ? 0 : 1
  }
  const count = (p: number) => Math.abs(view.points[p - 1] ?? 0)

  const choose = (target: number | 'off') => {
    const step = targets.find((s) => s.to === target)
    if (!step) return
    setSelected(null)
    onAction(step)
    // The target is about to be disabled: keep the keyboard on the board, not back at the page's top.
    board.current?.focus({ preventScroll: true })
  }

  const clickPoint = (p: number) => {
    if (pending) return
    if (selected !== null && targets.some((s) => s.to === p)) return choose(p)
    setSelected(sources.has(p) && selected !== p ? p : null)
  }

  // Display order: top row 13..24 left to right, bottom row 12..1 left to right.
  const top = Array.from({ length: 12 }, (_, i) => 13 + i)
  const bottom = Array.from({ length: 12 }, (_, i) => 12 - i)
  const absolute = (displayed: number) =>
    me === 0 ? displayed : long ? way(25 - displayed) : 25 - displayed

  const renderPoint = (displayed: number, row: 'top' | 'bottom') => {
    const p = absolute(displayed)
    const who = owner(p)
    const n = count(p)
    const isSource = sources.has(p)
    const isTarget = selected !== null && targets.some((s) => s.to === p)
    const maple = displayed % 2 === 0
    const shown = Math.min(n, 5)
    const number = (
      <span
        aria-hidden="true"
        className="relative z-10 h-4 shrink-0 text-center text-[0.6rem] leading-4 font-bold text-gold-light/85 tabular-nums sm:h-5 sm:text-xs sm:leading-5"
      >
        {display(p)}
      </span>
    )

    return (
      <button
        key={p}
        type="button"
        onClick={() => clickPoint(p)}
        disabled={pending || (!isSource && !isTarget)}
        aria-pressed={selected === p}
        aria-label={t('nardi.point', {
          point: display(p),
          count: n,
          owner: who === null ? t('nardi.empty') : who === me ? t('nardi.mine') : t('nardi.theirs'),
        })}
        className={`relative flex h-[var(--nardi-h)] min-w-0 flex-1 flex-col px-px sm:px-0.5 ${
          isTarget
            ? 'rounded-md bg-accent/30 shadow-[inset_0_0_0_2px_var(--color-accent-text),0_0_12px_var(--color-accent)]'
            : ''
        } ${selected === p ? 'rounded-md ring-2 ring-accent-text' : ''} disabled:cursor-default`}
      >
        {row === 'top' ? number : null}
        <span
          className={`relative flex min-h-0 flex-1 flex-col items-center gap-0.5 ${row === 'top' ? 'justify-start' : 'justify-end'}`}
        >
          <span
            aria-hidden="true"
            className={`absolute inset-x-0 inset-y-0 ${maple ? 'nardi-point-maple' : 'nardi-point-garnet'}`}
            style={{
              clipPath:
                row === 'top'
                  ? 'polygon(0 0, 100% 0, 50% 92%)'
                  : 'polygon(50% 8%, 100% 100%, 0 100%)',
            }}
          />
          {Array.from({ length: shown }, (_, i) => (
            <Stone
              key={i}
              mine={who === me}
              // The outermost stone: the end of the stack that reaches into the board.
              ready={isSource && i === (row === 'top' ? shown - 1 : 0)}
            />
          ))}
          {n > 5 ? (
            <span className="relative z-10 -mt-3 rounded-full bg-black/80 px-0.5 text-[0.6rem] leading-tight font-bold text-gold-light sm:px-1 sm:text-[0.65rem]">
              +{n - 5}
            </span>
          ) : null}
        </span>
        {row === 'bottom' ? number : null}
      </button>
    )
  }

  const myTurn = state.seats_to_act.includes(me)
  const usedDice = view.rolled.length - Math.min(view.rolled.length, view.dice.length)

  const turnShare =
    secondsLeft !== null && state.turn_seconds
      ? Math.min(1, secondsLeft / state.turn_seconds)
      : null
  const barFirst = sources.has('bar')

  // The dice as rolled (doubles play four); the ones still in the server's list stay lit.
  const shownDice =
    view.rolled[0] === view.rolled[1]
      ? [view.rolled[0], view.rolled[0], view.rolled[0], view.rolled[0]]
      : view.rolled
  const left: number[] = [...view.dice]
  const spent = shownDice.map(() => true)
  for (let i = shownDice.length - 1; i >= 0; i--) {
    const at = left.indexOf(shownDice[i] as number)
    if (at >= 0) {
      left.splice(at, 1)
      spent[i] = false
    }
  }

  const halfOf = (from: number, to: number) => (
    <div className="nardi-field relative flex min-w-0 flex-1 flex-col justify-between gap-2 rounded-lg p-0.5 sm:p-1">
      <Rosette />
      <div className="flex">{top.slice(from, to).map((d) => renderPoint(d, 'top'))}</div>
      <div className="flex">{bottom.slice(from, to).map((d) => renderPoint(d, 'bottom'))}</div>
    </div>
  )

  return (
    <div className="space-y-4">
      {/* Dice, pip counts and the hint on one strip above the walnut case. */}
      <div className="club-panel flex flex-wrap items-center justify-between gap-x-3 gap-y-2 rounded-card px-3 py-2 sm:px-4">
        <div className="flex items-center gap-2" aria-label={t('nardi.dice')}>
          {shownDice.map((die, i) => (
            <Die key={i} value={die} spent={spent[i] ?? false} />
          ))}
          <span className="sr-only">
            {t('nardi.diceLeft', { count: view.dice.length, used: usedDice })}
          </span>
        </div>
        {view.pips ? (
          <p
            className="text-sm tabular-nums"
            aria-label={t('nardi.pipsLabel', { mine: view.pips[me], theirs: view.pips[1 - me] })}
          >
            <span className="text-muted">{t('nardi.pips')}</span>{' '}
            <span className="font-bold text-gold-light">{view.pips[me]}</span>
            <span className="text-muted"> · </span>
            <span className="font-bold">{view.pips[1 - me]}</span>
          </p>
        ) : null}
        <p className="text-sm text-muted">
          {myTurn
            ? selected !== null
              ? t('nardi.pickTarget')
              : barFirst
                ? t('nardi.pickBar')
                : t('nardi.pickChecker')
            : (waitingCaption(state, players, t) ?? '')}
        </p>
      </div>

      <div className="overflow-x-auto">
        <div
          ref={board}
          tabIndex={-1}
          className="nardi-case flex flex-wrap gap-1 rounded-2xl p-1.5 outline-none sm:min-w-[36rem] sm:flex-nowrap sm:gap-2 sm:p-3"
        >
          {halfOf(0, 6)}

          {/* The spine. Short nardi's hit checkers wait here; Long nardi hits nothing. */}
          <div
            className={`nardi-spine flex flex-col items-center justify-between rounded-md py-2 text-center text-xs ${long ? 'w-2 sm:w-3' : 'w-8 sm:w-14'}`}
          >
            {long ? null : (
              <>
                <BarStack label={t('nardi.barTheirs')} count={view.bar[1 - me] ?? 0} mine={false} />
                <button
                  type="button"
                  disabled={pending || !sources.has('bar')}
                  aria-pressed={selected === 'bar'}
                  onClick={() => setSelected(selected === 'bar' ? null : 'bar')}
                  className={`rounded-lg p-1 ${selected === 'bar' ? 'ring-2 ring-accent-text' : barFirst ? 'ring-2 ring-warning' : ''}`}
                >
                  <BarStack label={t('nardi.barMine')} count={view.bar[me] ?? 0} mine />
                </button>
              </>
            )}
          </div>

          {halfOf(6, 12)}

          <div className="flex basis-full flex-row items-stretch justify-between gap-2 text-center text-xs leading-tight sm:w-24 sm:basis-auto sm:flex-col sm:text-[0.7rem]">
            <div className="nardi-tray flex min-h-0 flex-1 flex-col justify-between rounded-xl p-2">
              <p className="text-muted">{t('nardi.offTheirs')}</p>
              <TrayStack count={view.off[1 - me] ?? 0} mine={false} />
              <p className="text-lg font-bold">{view.off[1 - me]}</p>
            </div>
            <TurnRing seconds={secondsLeft} share={turnShare} />
            <button
              type="button"
              disabled={pending || !targets.some((s) => s.to === 'off')}
              onClick={() => choose('off')}
              className={`nardi-tray flex min-h-0 flex-1 flex-col justify-between rounded-xl p-2 ${targets.some((s) => s.to === 'off') ? 'ring-2 ring-accent-text' : ''}`}
            >
              <span className="block text-muted">{t('nardi.offMine')}</span>
              <TrayStack count={view.off[me] ?? 0} mine />
              <span className="block text-lg font-bold">{view.off[me]}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

function Stone({ mine, ready = false }: { mine: boolean; ready?: boolean }) {
  return (
    <span
      aria-hidden="true"
      className={`relative size-[var(--nardi-stone)] shrink-0 rounded-full ${mine ? 'nardi-stone-light' : 'nardi-stone-dark'} ${
        ready ? 'nardi-ready' : ''
      }`}
    />
  )
}

function BarStack({ label, count, mine }: { label: string; count: number; mine: boolean }) {
  return (
    <span className="flex flex-col items-center gap-1">
      <span className="sr-only text-muted sm:not-sr-only sm:max-w-12 sm:leading-tight">
        {label}
      </span>
      {Array.from({ length: Math.min(count, 4) }, (_, i) => (
        <span
          key={i}
          aria-hidden="true"
          className={`size-5 rounded-full sm:size-7 ${mine ? 'nardi-stone-light' : 'nardi-stone-dark'}`}
        />
      ))}
      <span className="font-bold">{count}</span>
    </span>
  )
}

/** Checkers borne off, stacked on edge in the tray. */
function TrayStack({ count, mine }: { count: number; mine: boolean }) {
  return (
    <span
      aria-hidden="true"
      className="my-1 flex min-h-3 flex-row justify-center gap-px overflow-hidden sm:min-h-0 sm:flex-1 sm:flex-col-reverse sm:justify-start"
    >
      {Array.from({ length: count }, (_, i) => (
        <span
          key={i}
          className={`h-3 w-1 shrink-0 rounded-sm sm:h-1 sm:w-full ${mine ? 'bg-[#eadfc6]' : 'bg-[#8a7464]'} shadow-[0_1px_0_rgb(0_0_0/0.5)]`}
        />
      ))}
    </span>
  )
}

/** The turn's time as a ring on the board; the number is also said in the page's own turn banner. */
function TurnRing({ seconds, share }: { seconds: number | null; share: number | null }) {
  if (seconds === null)
    return <span aria-hidden="true" className="w-14 shrink-0 sm:h-14 sm:w-auto" />
  const circumference = 2 * Math.PI * 24
  return (
    <svg viewBox="0 0 56 56" aria-hidden="true" className="mx-auto size-14 shrink-0 self-center">
      <circle
        cx="28"
        cy="28"
        r="27"
        fill="#1a0f08"
        stroke="var(--color-gold)"
        strokeOpacity="0.5"
      />
      <circle cx="28" cy="28" r="24" fill="none" stroke="rgb(255 255 255 / 0.14)" strokeWidth="4" />
      <circle
        cx="28"
        cy="28"
        r="24"
        fill="none"
        stroke="var(--color-turn)"
        strokeWidth="4"
        strokeLinecap="round"
        strokeDasharray={circumference}
        strokeDashoffset={circumference * (1 - (share ?? 0))}
        transform="rotate(-90 28 28)"
      />
      <text x="28" y="34" textAnchor="middle" fontSize="17" fontWeight="800" fill="#fff8e7">
        {seconds}
      </text>
    </svg>
  )
}

const PIPS: Record<number, number[]> = {
  1: [4],
  2: [0, 8],
  3: [0, 4, 8],
  4: [0, 2, 6, 8],
  5: [0, 2, 4, 6, 8],
  6: [0, 2, 3, 5, 6, 8],
}

function Die({ value, spent }: { value: number; spent: boolean }) {
  return (
    <span
      className={`nardi-die grid size-10 grid-cols-3 grid-rows-3 place-items-center rounded-lg p-1.5 sm:size-12 ${spent ? 'opacity-30' : ''}`}
    >
      <span className="sr-only">{value}</span>
      {Array.from({ length: 9 }, (_, i) => (
        <span
          key={i}
          aria-hidden="true"
          className={`size-1.5 rounded-full sm:size-2 ${PIPS[value]?.includes(i) ? 'bg-[#3a1a12]' : ''}`}
        />
      ))}
    </span>
  )
}

/** A carved rosette in the middle of each half: a six-armed eternity wheel in a ring of small diamonds. */
function Rosette() {
  return (
    <svg
      viewBox="-60 -60 120 120"
      aria-hidden="true"
      className="pointer-events-none absolute top-1/2 left-1/2 size-24 -translate-x-1/2 -translate-y-1/2 text-gold opacity-30 sm:size-36 lg:size-44"
    >
      <circle r="56" fill="none" stroke="currentColor" strokeWidth="1.5" />
      <circle r="44" fill="none" stroke="currentColor" strokeWidth="1" />
      {Array.from({ length: 24 }, (_, i) => (
        <path
          key={i}
          transform={`rotate(${i * 15}) translate(0 -50)`}
          d="M0 -3.5 2.5 0 0 3.5 -2.5 0Z"
          fill="currentColor"
        />
      ))}
      {Array.from({ length: 6 }, (_, i) => (
        <path
          key={i}
          transform={`rotate(${i * 60})`}
          d="M0 0C10 -14 30 -20 38 -4 28 -14 14 -10 0 0Z"
          fill="currentColor"
        />
      ))}
      <circle r="5" fill="currentColor" />
    </svg>
  )
}
