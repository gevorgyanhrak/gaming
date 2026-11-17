import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMarkGameInProgress } from '@/features/play/gameInProgress'
import { walletKey } from '@/features/wallet/api'
import { useMessage } from '@/features/auth/forms'
import { randomId } from '@/shared/lib/uuid'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { Skeleton } from '@/shared/ui/Skeleton'
import { QueryErrorState } from '@/shared/ui/States'
import {
  engineSlotKey,
  fetchEngineSlot,
  gridOf,
  recentSlotsKey,
  spinEngineSlot,
  winningCells,
  type EngineSpin,
  type EngineWin,
} from './api'
import { symbolLook } from './symbols'

/** A cascade fall follows on its own after this long (the next drop is part of the same play). */
const FALL_DELAY_MS = 900

/**
 * An engine slot (ADR 0026): the reels the server's engine dealt, drawn with our generic symbols. The browser only asks
 * for a spin (bet, lines, a one-time key) — the engine decides the screen and the wins, the server moves the Fishkas.
 * Free games and cascade falls continue from the saved state, also after a reload; falls play on by themselves.
 */
export function EngineSlotGame({ slug }: { slug: string }) {
  const { t, i18n } = useTranslation()
  const message = useMessage()
  const queryClient = useQueryClient()
  const slot = useQuery({
    queryKey: engineSlotKey(slug),
    queryFn: ({ signal }) => fetchEngineSlot(slug, signal),
  })
  const [bet, setBet] = useState<number | null>(null)
  const [lines, setLines] = useState<number | null>(null)
  const [last, setLast] = useState<EngineSpin | null>(null)
  const n = (value: number) => value.toLocaleString(i18n.language)
  // A slot being played is a game in progress: the level-up popup waits until the player leaves it.
  useMarkGameInProgress(true)

  const spin = useMutation({
    mutationFn: (body: { bet: number; lines?: number; key: string }) => spinEngineSlot(slug, body),
    onSuccess: (result) => {
      setLast(result)
      // The header balance is the server's: refetch, never patch.
      void queryClient.invalidateQueries({ queryKey: walletKey })
      void queryClient.invalidateQueries({ queryKey: recentSlotsKey })
    },
  })

  const data = slot.data
  const betNow = bet ?? data?.bets[0] ?? 1
  const linesNow = lines ?? data?.lines.default ?? 1
  const inRound = last ? last.next : (data?.free ?? false)
  // A cascade fall (no free games left to count, yet the next drop is free) follows by itself.
  const falling = !!last && last.next && last.free_left === 0
  const stake = data ? betNow * (data.lines.fixed ? data.unit : linesNow) : 0

  const doSpin = () => {
    if (!data || spin.isPending) return
    spin.mutate({ bet: betNow, ...(data.lines.fixed ? {} : { lines: linesNow }), key: randomId() })
  }
  const spinRef = useRef(doSpin)
  useEffect(() => {
    spinRef.current = doSpin
  })
  useEffect(() => {
    if (!falling || spin.isPending) return
    const timer = window.setTimeout(() => spinRef.current(), FALL_DELAY_MS)
    return () => window.clearTimeout(timer)
  }, [falling, last, spin.isPending])
  // Space spins, as on a slot machine (not while typing in a field).
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.code !== 'Space' || event.repeat) return
      const target = event.target as HTMLElement | null
      if (target?.closest('input, textarea, select, button, [role="dialog"]')) return
      event.preventDefault()
      spinRef.current()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  if (slot.isPending) return <Skeleton className="h-[28rem] w-full rounded-panel" />
  if (slot.isError)
    return <QueryErrorState error={slot.error} onRetry={() => void slot.refetch()} />
  if (!data) return null

  const grid = gridOf(last?.screen ?? data.state)
  const wins = last?.wins ?? []
  const lit = winningCells(wins)
  const step = <T,>(list: T[], current: T, by: number) =>
    list[Math.min(list.length - 1, Math.max(0, list.indexOf(current) + by))] ?? current
  const lineOptions = Array.from(
    { length: data.lines.max - data.lines.min + 1 },
    (_, i) => data.lines.min + i,
  )

  return (
    <div className="mx-auto max-w-2xl space-y-4">
      <header className="flex flex-wrap items-end justify-between gap-2">
        <div>
          <p className="text-xs font-bold tracking-[0.16em] text-gold-light/80 uppercase">
            {data.studio ?? t('engineSlots.slot')}
          </p>
          <h1 className="font-display text-2xl font-bold text-fg sm:text-3xl">{data.name}</h1>
        </div>
        <p className="text-xs text-muted">
          {t('engineSlots.size', { reels: data.reels, rows: data.rows })}
          {data.lines.fixed
            ? ` · ${t('engineSlots.fixedLines', { count: data.lines.max })}`
            : ` · ${t('engineSlots.upToLines', { count: data.lines.max })}`}
        </p>
      </header>

      {/* The machine: reels in a brass cabinet. */}
      <section
        aria-label={t('engineSlots.reels')}
        className="relative overflow-hidden rounded-[1.75rem] border border-gold/40 bg-[radial-gradient(ellipse_at_50%_0%,rgb(124_92_255/0.25),transparent_60%),linear-gradient(180deg,#141a33,#0a0d19)] p-3 shadow-[0_24px_60px_-20px_rgb(0_0_0/0.9),inset_0_0_0_1px_rgb(255_255_255/0.05)] sm:p-5"
      >
        {inRound && !falling ? (
          <p
            role="status"
            className="mb-3 rounded-full bg-gold/15 px-4 py-1.5 text-center text-sm font-extrabold tracking-wide text-gold-light motion-safe:animate-rise"
          >
            {t('engineSlots.freeGames', { count: last?.free_left ?? 0 })}
            {last && last.series_win > 0
              ? ` · ${t('engineSlots.seriesWin', { amount: n(last.series_win) })}`
              : ''}
          </p>
        ) : null}
        <div
          className="grid gap-1.5 sm:gap-2"
          style={{ gridTemplateColumns: `repeat(${grid.length || data.reels}, minmax(0, 1fr))` }}
        >
          {grid.map((column, x) => (
            <div
              key={`${x}-${last?.round_id ?? 'start'}`}
              className={`flex flex-col gap-1.5 rounded-xl bg-black/35 p-1 sm:gap-2 sm:p-1.5 ${spin.isPending ? 'motion-safe:animate-pulse' : 'motion-safe:animate-[reel-stop_420ms_cubic-bezier(0.2,0.9,0.3,1.25)_both]'}`}
              style={{ animationDelay: spin.isPending ? undefined : `${x * 90}ms` }}
            >
              {column.map((sym, y) => (
                <SymbolTile
                  key={y}
                  sym={sym}
                  lit={lit.has(`${x + 1}-${y + 1}`)}
                  dim={lit.size > 0 && !lit.has(`${x + 1}-${y + 1}`)}
                  label={t('engineSlots.symbol', { n: sym })}
                />
              ))}
            </div>
          ))}
        </div>

        {/* What this spin paid. */}
        <div aria-live="polite" className="mt-3 min-h-8 text-center">
          {last && last.win > 0 ? (
            <p
              key={last.round_id}
              className="text-gold-gradient font-display text-2xl font-bold motion-safe:animate-[amount-pop_420ms_ease-out]"
            >
              {t('engineSlots.won', { amount: n(last.win) })}
            </p>
          ) : last ? (
            <p className="text-sm text-muted">
              {falling ? t('engineSlots.falling') : t('engineSlots.noWin')}
            </p>
          ) : null}
        </div>
      </section>

      {spin.error ? <Alert tone="danger">{message(spin.error.message)}</Alert> : null}

      {/* Bet, lines, spin. */}
      <div className="club-panel flex flex-wrap items-center gap-3 rounded-3xl p-3 sm:p-4">
        <Stepper
          label={data.lines.fixed ? t('engineSlots.bet') : t('engineSlots.betPerLine')}
          value={n(betNow)}
          disabled={inRound || spin.isPending}
          onDown={() => setBet(step(data.bets, betNow, -1))}
          onUp={() => setBet(step(data.bets, betNow, 1))}
        />
        {!data.lines.fixed ? (
          <Stepper
            label={t('engineSlots.lines')}
            value={String(linesNow)}
            disabled={inRound || spin.isPending}
            onDown={() => setLines(step(lineOptions, linesNow, -1))}
            onUp={() => setLines(step(lineOptions, linesNow, 1))}
          />
        ) : null}
        <p className="min-w-24 text-sm">
          <span className="block text-xs text-muted">{t('engineSlots.stake')}</span>
          <span className="font-bold text-gold-light tabular-nums">
            {inRound ? t('engineSlots.free') : n(stake)}
          </span>
        </p>
        <Button
          onClick={doSpin}
          loading={spin.isPending}
          disabled={falling}
          className="ml-auto min-h-12 min-w-36 text-base"
        >
          {inRound ? t('engineSlots.spinFree') : t('engineSlots.spin')}
        </Button>
      </div>

      {wins.length > 0 ? <WinList wins={wins} /> : null}

      <p className="text-center text-[11px] text-muted">{t('engineSlots.credit')}</p>
    </div>
  )
}

function SymbolTile({
  sym,
  lit,
  dim,
  label,
}: {
  sym: number
  lit: boolean
  dim: boolean
  label: string
}) {
  if (sym <= 0) return <span aria-hidden="true" className="aspect-square rounded-lg bg-black/40" />
  const look = symbolLook(sym)
  return (
    <span
      role="img"
      aria-label={label}
      className={`relative flex aspect-square items-center justify-center rounded-lg border text-[clamp(0.9rem,4.5vw,2.4rem)] font-black transition-[opacity,box-shadow,scale] duration-300 ${
        lit
          ? 'border-gold shadow-[0_0_0_2px_var(--color-gold),0_0_18px_rgb(242_193_78/0.7)] motion-safe:scale-[1.04]'
          : 'border-white/10'
      } ${dim ? 'opacity-45' : ''}`}
      style={{ background: `linear-gradient(160deg, ${look.from}, ${look.to})`, color: look.ink }}
    >
      <span aria-hidden="true" className="drop-shadow-[0_2px_2px_rgb(0_0_0/0.35)]">
        {look.glyph}
      </span>
    </span>
  )
}

function Stepper({
  label,
  value,
  disabled,
  onDown,
  onUp,
}: {
  label: string
  value: string
  disabled: boolean
  onDown: () => void
  onUp: () => void
}) {
  const { t } = useTranslation()
  return (
    <fieldset className="flex items-center gap-1.5" disabled={disabled}>
      <legend className="sr-only">{label}</legend>
      <button
        type="button"
        onClick={onDown}
        aria-label={t('engineSlots.less', { what: label })}
        className="flex size-10 items-center justify-center rounded-full border border-gold/40 bg-black/30 text-lg font-bold text-gold-light hover:border-gold disabled:opacity-40"
      >
        −
      </button>
      <p className="min-w-16 text-center">
        <span className="block text-xs text-muted">{label}</span>
        <span className="font-bold tabular-nums">{value}</span>
      </p>
      <button
        type="button"
        onClick={onUp}
        aria-label={t('engineSlots.more', { what: label })}
        className="flex size-10 items-center justify-center rounded-full border border-gold/40 bg-black/30 text-lg font-bold text-gold-light hover:border-gold disabled:opacity-40"
      >
        +
      </button>
    </fieldset>
  )
}

/** Each win of the spin: the line (or scatter), the symbols, the multiplier, the pay, free games won. */
function WinList({ wins }: { wins: EngineWin[] }) {
  const { t, i18n } = useTranslation()
  return (
    <ul aria-label={t('engineSlots.wins')} className="club-panel space-y-1 rounded-2xl p-3 text-sm">
      {wins.map((win, i) => (
        <li key={i} className="flex flex-wrap items-center gap-x-2 gap-y-1">
          <span className="text-muted">
            {win.li ? t('engineSlots.line', { n: win.li }) : t('engineSlots.scatter')}
          </span>
          {win.sym ? (
            <span
              className="inline-flex size-6 items-center justify-center rounded text-xs font-black"
              style={(() => {
                const look = symbolLook(win.sym)
                return {
                  background: `linear-gradient(160deg, ${look.from}, ${look.to})`,
                  color: look.ink,
                }
              })()}
            >
              {symbolLook(win.sym).glyph}
            </span>
          ) : null}
          {win.num ? <span>× {win.num}</span> : null}
          {win.mp && win.mp > 1 ? <span className="text-gold-light">× {win.mp}</span> : null}
          {win.pay ? (
            <span className="ml-auto font-bold text-gold-light tabular-nums">
              {Math.floor(win.pay * (win.mp || 1)).toLocaleString(i18n.language)}
            </span>
          ) : null}
          {win.fs ? (
            <span className="font-bold text-accent-text">
              {t('engineSlots.freeWon', { count: win.fs })}
            </span>
          ) : null}
        </li>
      ))}
    </ul>
  )
}
