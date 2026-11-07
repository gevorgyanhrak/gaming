import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { waitingCaption } from '@/features/play/turnCaption'
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
 * Nardi board. Shows the server's view from the player's side (their home board bottom right)
 * and offers exactly the server's legal steps: pick a checker, then one of its highlighted targets.
 */
export default function NardiBoard({ state, onAction, pending, players }: BoardProps) {
  const { t } = useTranslation()
  const view = state.view as unknown as NardiView
  const me = state.seat ?? 0
  const steps = state.legal_actions as unknown as Step[]
  const [selected, setSelected] = useState<number | 'bar' | null>(null)

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
    const dark = displayed % 2 === 0

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
        className={`relative flex h-36 min-w-0 flex-1 flex-col items-center gap-0.5 px-px sm:h-44 sm:px-0.5 ${row === 'top' ? 'justify-start' : 'justify-end'} ${
          isTarget
            ? 'rounded-md bg-accent/30 shadow-[inset_0_0_0_2px_var(--color-accent-text),0_0_12px_var(--color-accent)]'
            : ''
        } ${selected === p ? 'ring-2 ring-accent-text' : ''} disabled:cursor-default`}
      >
        <span
          aria-hidden="true"
          className={`absolute inset-x-0.5 ${row === 'top' ? 'top-0' : 'bottom-0'} h-full ${dark ? 'bg-surface-raised' : 'bg-border/60'}`}
          style={{
            clipPath:
              row === 'top'
                ? 'polygon(0 0, 100% 0, 50% 90%)'
                : 'polygon(50% 10%, 100% 100%, 0 100%)',
          }}
        />
        {Array.from({ length: Math.min(n, 5) }, (_, i) => (
          <span
            key={i}
            aria-hidden="true"
            className={`relative size-[1.15rem] rounded-full border sm:size-7 ${who === me ? 'border-white/60 bg-accent' : 'border-black/40 bg-fg'} ${
              isSource && i === Math.min(n, 5) - 1 ? 'ring-2 ring-warning' : ''
            }`}
          />
        ))}
        {n > 5 ? <span className="relative text-xs font-bold">+{n - 5}</span> : null}
      </button>
    )
  }

  const myTurn = state.seats_to_act.includes(me)
  const usedDice = view.rolled.length - Math.min(view.rolled.length, view.dice.length)

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2" aria-label={t('nardi.dice')}>
          {(view.rolled[0] === view.rolled[1]
            ? [view.rolled[0], view.rolled[0], view.rolled[0], view.rolled[0]]
            : view.rolled
          ).map((die, i, all) => (
            <span
              key={i}
              className={`flex size-9 items-center justify-center rounded-lg border border-border text-lg font-bold ${
                i < all.length - view.dice.length ? 'opacity-30' : 'bg-surface-raised'
              }`}
            >
              {die}
            </span>
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
            ? selected === null
              ? t('nardi.pickChecker')
              : t('nardi.pickTarget')
            : (waitingCaption(state, players, t) ?? '')}
        </p>
      </div>

      <div className="overflow-x-auto">
        <div className="flex flex-wrap gap-1 rounded-2xl border border-border bg-surface p-1.5 sm:min-w-[36rem] sm:flex-nowrap sm:gap-2 sm:p-2">
          <div className="flex flex-1 flex-col gap-3">
            <div className="flex">{top.slice(0, 6).map((d) => renderPoint(d, 'top'))}</div>
            <div className="flex">{bottom.slice(0, 6).map((d) => renderPoint(d, 'bottom'))}</div>
          </div>

          {/* The bar: Short nardi's hit checkers; Long nardi hits nothing, so it is just the board's middle. */}
          <div
            className={`flex flex-col items-center justify-between rounded-xl bg-bg py-2 text-center text-xs ${long ? 'w-1.5 sm:w-2' : 'w-7 sm:w-14'}`}
          >
            {long ? null : (
              <>
                <BarStack label={t('nardi.barTheirs')} count={view.bar[1 - me] ?? 0} mine={false} />
                <button
                  type="button"
                  disabled={pending || !sources.has('bar')}
                  aria-pressed={selected === 'bar'}
                  onClick={() => setSelected(selected === 'bar' ? null : 'bar')}
                  className={`rounded-lg p-1 ${selected === 'bar' ? 'ring-2 ring-accent-text' : ''}`}
                >
                  <BarStack label={t('nardi.barMine')} count={view.bar[me] ?? 0} mine />
                </button>
              </>
            )}
          </div>

          <div className="flex flex-1 flex-col gap-3">
            <div className="flex">{top.slice(6).map((d) => renderPoint(d, 'top'))}</div>
            <div className="flex">{bottom.slice(6).map((d) => renderPoint(d, 'bottom'))}</div>
          </div>

          <div className="flex basis-full flex-row justify-between gap-2 text-center text-xs leading-tight sm:w-20 sm:basis-auto sm:flex-col sm:text-[0.7rem]">
            <div className="rounded-xl bg-bg p-2">
              <p className="text-muted">{t('nardi.offTheirs')}</p>
              <p className="text-lg font-bold">{view.off[1 - me]}</p>
            </div>
            <button
              type="button"
              disabled={pending || !targets.some((s) => s.to === 'off')}
              onClick={() => choose('off')}
              className={`rounded-xl p-2 ${targets.some((s) => s.to === 'off') ? 'bg-accent/25 ring-2 ring-accent-text' : 'bg-bg'}`}
            >
              <span className="block text-muted">{t('nardi.offMine')}</span>
              <span className="block text-lg font-bold">{view.off[me]}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

function BarStack({ label, count, mine }: { label: string; count: number; mine: boolean }) {
  return (
    <span className="flex flex-col items-center gap-1">
      <span className="sr-only text-muted sm:not-sr-only">{label}</span>
      {Array.from({ length: Math.min(count, 4) }, (_, i) => (
        <span
          key={i}
          aria-hidden="true"
          className={`size-4 rounded-full border sm:size-5 ${mine ? 'border-white/60 bg-accent' : 'border-black/40 bg-fg'}`}
        />
      ))}
      <span className="font-bold">{count}</span>
    </span>
  )
}
