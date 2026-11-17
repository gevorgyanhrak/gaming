import { useState, type CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { AiFace } from '@/shared/ui/AiFace'
import type { BoardProps } from '../registry'

export type Raise = { type: 'raise'; min: number; max: number }

/** The standard chips (owner 2026-11-17): each its own colour, like a casino's. */
const CHIPS = [
  { value: 10, chip: '#2f6fd6', edge: '#f4f1e8', ink: '#ffffff' },
  { value: 20, chip: '#1f8a4c', edge: '#f4f1e8', ink: '#ffffff' },
  { value: 50, chip: '#c9541f', edge: '#fff1d6', ink: '#ffffff' },
  { value: 100, chip: '#1d1a20', edge: '#d6ab4e', ink: '#f2d38a' },
] as const

/** Under this many seconds the turn's bar and number turn red. */
const LOW_SECONDS = 10

/**
 * Whose turn it is, over the action area: "Your turn — action required", "Thinking time" with the acting player's
 * face and name, "Waiting for players", or "Next hand" in the pause after a hand; with the time left as a bar
 * that runs down (red in the last seconds). Enters anew whenever the turn moves.
 */
export function TurnHeader({
  mode,
  name,
  face,
  secondsLeft,
  share,
}: {
  mode: 'mine' | 'other' | 'waiting' | 'next'
  name?: string
  /** The acting AI's face key (an initial for a person). */
  face?: string
  secondsLeft: number | null
  /** The share of the turn left, 0–1, when the turn is timed. */
  share: number | null
}) {
  const { t } = useTranslation()
  const low = secondsLeft !== null && secondsLeft <= LOW_SECONDS && mode !== 'next'
  const label =
    mode === 'mine'
      ? t('poker.turn.yours')
      : mode === 'other'
        ? t('poker.turn.thinking')
        : mode === 'next'
          ? t('poker.turn.nextHand')
          : t('poker.turn.waiting')
  const detail = mode === 'mine' ? t('poker.turn.action') : mode === 'other' ? name : undefined

  return (
    <div
      key={`${mode}-${name ?? ''}`}
      data-turn={mode}
      className={`relative flex w-full items-center gap-3 overflow-hidden rounded-2xl border px-3 py-2 motion-safe:animate-rise ${
        mode === 'mine'
          ? 'border-[var(--color-turn)] bg-linear-to-r from-[rgb(242_193_78/0.22)] to-black/30 shadow-[0_0_24px_rgb(242_193_78/0.25)]'
          : 'border-white/12 bg-black/30'
      }`}
    >
      {mode === 'mine' ? (
        <span aria-hidden="true" className="relative flex size-3 shrink-0">
          <span className="absolute inset-0 rounded-full bg-[var(--color-turn)] motion-safe:animate-ping" />
          <span className="relative size-3 rounded-full bg-[var(--color-turn)]" />
        </span>
      ) : mode === 'other' && name ? (
        <span
          aria-hidden="true"
          className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-linear-to-b from-[#4a342b] to-[#1c120c] font-bold uppercase shadow-[0_0_0_2px_var(--color-turn)]"
        >
          {face ? <AiFace name={face} className="size-full" /> : name.slice(0, 1)}
        </span>
      ) : null}
      <div className="min-w-0 flex-1">
        <p
          className={`text-xs font-extrabold tracking-[0.16em] uppercase ${mode === 'mine' ? 'text-[var(--color-turn)]' : 'text-gold-light/90'}`}
        >
          {label}
        </p>
        {detail ? <p className="truncate text-sm font-semibold text-white/90">{detail}</p> : null}
        {share !== null ? (
          <span
            aria-hidden="true"
            className="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-white/12"
          >
            <span
              className={`block h-full rounded-full transition-[width,background-color] duration-1000 ease-linear ${low ? 'bg-[#ff5a4a]' : 'bg-[var(--color-turn)]'}`}
              style={{ width: `${Math.round(share * 100)}%` }}
            />
          </span>
        ) : null}
      </div>
      {secondsLeft !== null ? (
        <span
          aria-hidden="true"
          className={`shrink-0 text-2xl font-extrabold tabular-nums ${low ? 'text-[#ff7a6a] motion-safe:animate-pulse' : 'text-white'}`}
        >
          {t('poker.turn.seconds', { count: secondsLeft })}
        </span>
      ) : null}
      {/* Read out once per turn (not every second). */}
      <span className="sr-only" aria-live="polite">
        {[label, detail].filter(Boolean).join(', ')}
      </span>
    </div>
  )
}

/**
 * Your actions when it's your turn, as the server allows them (never more): fold, check or call, bet or raise, all-in.
 * The amount is chosen with chips — the first tap takes the bet to that chip (at least the minimum), each next one
 * adds to it — or, under "More", with a large slider between the minimum and maximum, steps of a big blind and the
 * pot presets. The server checks the amount again.
 */
export function BetControls({
  raise,
  has,
  callAmount,
  stack,
  opening,
  presets,
  step,
  pending,
  onSend,
}: {
  raise: Raise | undefined
  has: (type: 'fold' | 'check' | 'call') => boolean
  callAmount: number
  stack: number
  /** Nobody has bet in this round: "Bet", else "Raise to". */
  opening: boolean
  presets: { label: string; value: number }[]
  /** The slider's step: a big blind. */
  step: number
  pending: boolean
  onSend: BoardProps['onAction']
}) {
  const { t, i18n } = useTranslation()
  const [amount, setAmount] = useState<number | null>(null)
  const [tapped, setTapped] = useState<{ value: number; n: number } | null>(null)
  const [more, setMore] = useState(false)
  const n = (value: number) => value.toLocaleString(i18n.language)

  const clamp = (value: number) =>
    raise ? Math.min(raise.max, Math.max(raise.min, Math.round(value))) : 0
  const to = raise ? clamp(amount ?? raise.min) : 0
  // Only an all-in is left to raise with (a stack below a full raise): no amount to choose.
  const adjustable = !!raise && raise.max > raise.min
  const send = (action: Parameters<BoardProps['onAction']>[0]) => {
    setAmount(null)
    setTapped(null)
    setMore(false)
    onSend(action)
  }
  const add = (value: number) => {
    setAmount((current) => clamp(current === null ? value : current + value))
    setTapped((last) => ({ value, n: (last?.n ?? 0) + 1 }))
  }
  const share = raise && adjustable ? (to - raise.min) / (raise.max - raise.min) : 0
  const callAllIn = callAmount > 0 && callAmount >= stack
  const showBet = !!raise && adjustable && to < raise.max
  // How many actions are offered: four sit 2 × 2 on a phone (long words wrapped to three lines in a row of four).
  const offered =
    Number(has('fold')) +
    Number(has('check')) +
    Number(has('call')) +
    Number(showBet) +
    Number(!!raise)
  const betLabel = opening ? t('poker.betTo', { amount: to }) : t('poker.raiseTo', { amount: to })

  return (
    <div className="flex w-full flex-col gap-3">
      {adjustable && raise ? (
        <>
          {/* "More": a larger slider over the chips, opening upwards out of the panel's flow (covers nothing). */}
          <div
            id="poker-more"
            inert={!more}
            className={`grid transition-[grid-template-rows,opacity] duration-300 ease-soft motion-reduce:transition-none ${more ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'}`}
          >
            <div className="min-h-0 overflow-hidden">
              <div className="space-y-3 rounded-2xl border border-gold/30 bg-black/35 p-3">
                <p className="text-xs font-semibold tracking-wider text-white/60 uppercase">
                  {t('poker.bets.custom')}
                </p>
                <div className="flex items-center gap-2">
                  <StepButton
                    label={t('poker.bets.decrease', { amount: step })}
                    disabled={to <= raise.min}
                    onClick={() => setAmount(clamp(to - step))}
                  >
                    −
                  </StepButton>
                  <input
                    type="range"
                    min={raise.min}
                    max={raise.max}
                    step={1}
                    value={to}
                    onChange={(e) => setAmount(Number(e.target.value))}
                    aria-label={t('poker.amount')}
                    aria-valuetext={n(to)}
                    className="poker-range min-w-0 flex-1"
                    style={{ '--fill': `${share * 100}%` } as CSSProperties}
                  />
                  <StepButton
                    label={t('poker.bets.increase', { amount: step })}
                    disabled={to >= raise.max}
                    onClick={() => setAmount(clamp(to + step))}
                  >
                    +
                  </StepButton>
                </div>
                <div className="flex justify-between text-xs font-semibold text-white/60 tabular-nums">
                  <span>
                    {t('poker.bets.min')} <b className="text-white">{n(raise.min)}</b>
                  </span>
                  <span>
                    {t('poker.bets.max')} <b className="text-white">{n(raise.max)}</b>
                  </span>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  {presets.map((preset) => (
                    <button
                      key={preset.label}
                      type="button"
                      onClick={() => setAmount(clamp(preset.value))}
                      className="min-h-9 rounded-full border border-gold/35 bg-black/30 px-3 text-xs font-semibold transition hover:border-gold"
                    >
                      {preset.label}
                    </button>
                  ))}
                  <button
                    type="button"
                    disabled={pending}
                    onClick={() => send({ type: 'raise', amount: to })}
                    className="ml-auto min-h-10 rounded-xl border border-white/20 bg-linear-to-b from-accent-light to-accent px-4 text-sm font-bold text-white shadow-lg shadow-black/40 transition hover:brightness-110 disabled:opacity-60"
                  >
                    {t('poker.bets.confirm')} {n(to)}
                  </button>
                </div>
              </div>
            </div>
          </div>

          {/* The amount, then the chips (one row on wide screens). */}
          <div className="flex flex-col gap-3 min-[901px]:flex-row min-[901px]:items-center">
            {/* The amount chosen so far. */}
            <div className="flex min-w-0 items-center justify-between gap-3 min-[901px]:flex-1">
              <p className="flex min-w-0 items-baseline gap-2">
                <span className="truncate text-xs font-semibold tracking-wider text-white/60 uppercase">
                  {t('poker.bets.yours')}
                </span>
                <output
                  key={to}
                  aria-label={t('poker.amount')}
                  className={`text-2xl font-extrabold tabular-nums motion-safe:animate-[amount-pop_220ms_ease-out] ${amount === null ? 'text-white/70' : 'text-gold-light'}`}
                >
                  {n(to)}
                </output>
                {to === raise.min ? (
                  <span className="rounded-full bg-white/10 px-2 py-0.5 text-[0.65rem] font-bold text-white/70 uppercase">
                    {t('poker.bets.min')}
                  </span>
                ) : to === raise.max ? (
                  <span className="rounded-full bg-[#ff7a45]/20 px-2 py-0.5 text-[0.65rem] font-bold text-[#ffb08a] uppercase">
                    {t('poker.allIn')}
                  </span>
                ) : null}
              </p>
              {amount !== null ? (
                <button
                  type="button"
                  onClick={() => {
                    setAmount(null)
                    setTapped(null)
                  }}
                  className="min-h-9 shrink-0 rounded-full border border-white/15 px-3 text-xs font-semibold text-white/75 transition hover:border-gold/60 hover:text-white"
                >
                  {t('poker.bets.clear')}
                </button>
              ) : null}
            </div>

            {/* The chips, then More. */}
            <div className="flex shrink-0 items-end justify-between gap-1.5 sm:justify-start sm:gap-2.5">
              {CHIPS.map((chip) => {
                const picked = tapped?.value === chip.value
                return (
                  <button
                    key={chip.value}
                    type="button"
                    disabled={pending || (amount !== null && to >= raise.max)}
                    onClick={() => add(chip.value)}
                    aria-label={t('poker.bets.add', { amount: chip.value })}
                    className={`group relative rounded-full transition-[translate,filter] duration-200 ease-soft focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-gold disabled:opacity-40 disabled:grayscale-[0.4] ${
                      picked ? '-translate-y-1.5' : 'motion-safe:hover:-translate-y-0.5'
                    }`}
                  >
                    <span
                      key={picked ? tapped.n : 0}
                      className={`poker-chip size-12 text-sm font-black sm:size-14 sm:text-base ${picked ? 'shadow-[0_0_0_3px_var(--color-turn),0_0_18px_rgb(242_193_78/0.7)] motion-safe:animate-[chip-pop_260ms_ease-out]' : ''}`}
                      style={
                        {
                          '--chip': chip.chip,
                          '--chip-edge': chip.edge,
                          color: chip.ink,
                        } as CSSProperties
                      }
                    >
                      <span aria-hidden="true">{chip.value}</span>
                    </span>
                    {picked ? (
                      <span
                        key={`plus-${tapped.n}`}
                        aria-hidden="true"
                        className="pointer-events-none absolute -top-2 left-1/2 text-sm font-extrabold text-gold-light opacity-0 motion-safe:animate-[chip-plus_700ms_ease-out]"
                      >
                        +{chip.value}
                      </span>
                    ) : null}
                  </button>
                )
              })}
              <button
                type="button"
                aria-expanded={more}
                aria-controls="poker-more"
                onClick={() => setMore((open) => !open)}
                className={`flex h-12 min-w-14 items-center justify-center gap-1 rounded-full border px-3 text-xs font-extrabold tracking-wider uppercase transition sm:h-14 ${
                  more
                    ? 'border-gold bg-gold/20 text-gold-light'
                    : 'border-gold/45 bg-black/35 text-white hover:border-gold'
                }`}
              >
                {more ? t('poker.bets.less') : t('poker.bets.more')}
                <span
                  aria-hidden="true"
                  className={`transition-transform duration-300 ${more ? '' : 'rotate-180'}`}
                >
                  ▾
                </span>
              </button>
            </div>
          </div>
        </>
      ) : null}

      {/* The actions the server offers now. */}
      <div className={`grid gap-2 ${columns[offered] ?? 'grid-cols-2 sm:grid-cols-4'}`}>
        {has('fold') ? (
          <button
            type="button"
            disabled={pending}
            onClick={() => send({ type: 'fold' })}
            className={`${actionButton} border-[#a8473c]/60 bg-linear-to-b from-[#8e3a31] to-[#5e211b] text-white`}
          >
            {t('poker.fold')}
          </button>
        ) : null}
        {has('check') ? (
          <button
            type="button"
            disabled={pending}
            onClick={() => send({ type: 'check' })}
            className={`${actionButton} border-gold/40 bg-linear-to-b from-gold-light to-gold text-black`}
          >
            {t('poker.check')}
          </button>
        ) : null}
        {has('call') ? (
          <button
            type="button"
            disabled={pending}
            onClick={() => send({ type: 'call' })}
            className={`${actionButton} border-gold/40 bg-linear-to-b from-gold-light to-gold text-black`}
          >
            {callAllIn
              ? t('poker.allInAmount', { amount: callAmount })
              : t('poker.call', { amount: callAmount })}
          </button>
        ) : null}
        {raise && showBet ? (
          <button
            type="button"
            disabled={pending}
            onClick={() => send({ type: 'raise', amount: to })}
            className={`${actionButton} border-white/20 bg-linear-to-b from-accent-light to-accent text-white`}
          >
            {betLabel}
          </button>
        ) : null}
        {raise ? (
          <button
            type="button"
            disabled={pending}
            onClick={() => send({ type: 'raise', amount: raise.max })}
            className={`${actionButton} border-[#ff9a5c]/50 bg-linear-to-b from-[#e2632b] to-[#9c2f14] text-white ${to === raise.max && adjustable ? 'shadow-[0_0_0_2px_#ffb08a,0_0_20px_rgb(255_120_60/0.55)]' : ''}`}
          >
            {t('poker.allInAmount', { amount: raise.max })}
          </button>
        ) : null}
      </div>
    </div>
  )
}

function StepButton({
  label,
  disabled,
  onClick,
  children,
}: {
  label: string
  disabled: boolean
  onClick: () => void
  children: string
}) {
  return (
    <button
      type="button"
      aria-label={label}
      disabled={disabled}
      onClick={onClick}
      className="flex size-10 shrink-0 items-center justify-center rounded-full border border-gold/40 bg-black/40 text-xl font-bold text-gold-light transition hover:border-gold disabled:opacity-35"
    >
      <span aria-hidden="true">{children}</span>
    </button>
  )
}

const columns: Partial<Record<number, string>> = {
  1: 'grid-cols-1',
  2: 'grid-cols-2',
  3: 'grid-cols-3',
}

const actionButton =
  'min-w-0 rounded-xl border px-2 py-3 text-sm font-bold shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-60 min-[901px]:text-base'
