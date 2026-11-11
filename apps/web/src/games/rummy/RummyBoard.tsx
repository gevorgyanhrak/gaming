import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import { useCardLabel } from '../cards/cards'
import { CardBack, PlayingCard } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'

export interface RummyView {
  seat: number | null
  target: number
  scores: number[]
  out: boolean[]
  dealer: number
  round: number
  phase: 'draw' | 'play' | 'over'
  hand: string[]
  hand_sizes: number[]
  stock_size: number
  discard_top: string | null
  discard_size: number
  melds: { kind: 'set' | 'run'; cards: string[]; seat: number }[]
  to_act: number | null
  taken: string | null
  last_round: {
    number: number
    winner: number | null
    points: number
    left: Record<string, number>
    hands: string[][]
  } | null
}

type RummyAction =
  | { type: 'draw'; from: 'stock' | 'discard' }
  | { type: 'meld'; cards: string }
  | { type: 'layoff'; card: string; meld: number }
  | { type: 'discard'; card: string }

// Display order only; the server decides what melds are.
const RANKS = 'A23456789TJQK'
const SUITS = 'SHCD'
const bySuit = (a: string, b: string) =>
  SUITS.indexOf(a[1] ?? '') - SUITS.indexOf(b[1] ?? '') ||
  RANKS.indexOf(a[0] ?? '') - RANKS.indexOf(b[0] ?? '')
const byRank = (a: string, b: string) =>
  RANKS.indexOf(a[0] ?? '') - RANKS.indexOf(b[0] ?? '') ||
  SUITS.indexOf(a[1] ?? '') - SUITS.indexOf(b[1] ?? '')
const sameCards = (a: string[], b: string[]) => [...a].sort().join() === [...b].sort().join()

/**
 * Rummy: the other players across the top, the stock and the discard pile, the melds on the table, and
 * your hand. Select cards, then meld them, lay one off on a meld or discard it — only what the server
 * lists can be played. Sort your hand by suit or by rank.
 */
export default function RummyBoard({
  state,
  onAction,
  pending,
  players = {},
  bots = {},
  levels = {},
}: BoardProps) {
  const { t, i18n } = useTranslation()
  const labels = useCardLabel()
  const view = state.view as unknown as RummyView
  const me = view.seat
  const actions = state.legal_actions as unknown as RummyAction[]
  const narrow = useMediaQuery('(max-width: 640px)')
  const [sort, setSort] = useState<'suit' | 'rank'>('suit')
  // Selected cards by their position in the hand (two decks can hold the same card twice).
  const [selected, setSelected] = useState<number[]>([])
  const format = (n: number) => n.toLocaleString(i18n.language)
  const name = (seat: number) =>
    seat === me ? t('rummy.you') : players[seat] || t('rummy.seat', { seat: seat + 1 })
  const secondsLeft = useCountdown(state.deadline, state.server_time)

  // Reset the selection whenever the hand changes.
  const handKey = view.hand.join()
  const [lastHand, setLastHand] = useState(handKey)
  if (lastHand !== handKey) {
    setLastHand(handKey)
    setSelected([])
  }

  const hand = view.hand
    .map((card, index) => ({ card, index }))
    .sort((a, b) => (sort === 'suit' ? bySuit(a.card, b.card) : byRank(a.card, b.card)))
  const picked = selected.map((i) => view.hand[i] ?? '')
  const drawFrom = (from: 'stock' | 'discard') =>
    actions.find(
      (a): a is Extract<RummyAction, { type: 'draw' }> => a.type === 'draw' && a.from === from,
    )
  const meld = actions.find(
    (a): a is Extract<RummyAction, { type: 'meld' }> =>
      a.type === 'meld' && picked.length >= 3 && sameCards(a.cards.split(','), picked),
  )
  const discard =
    picked.length === 1
      ? actions.find(
          (a): a is Extract<RummyAction, { type: 'discard' }> =>
            a.type === 'discard' && a.card === picked[0],
        )
      : undefined
  const layoffs =
    picked.length === 1
      ? actions.filter(
          (a): a is Extract<RummyAction, { type: 'layoff' }> =>
            a.type === 'layoff' && a.card === picked[0],
        )
      : []
  const playing = view.to_act === me && view.phase === 'play'
  const send = (action: RummyAction) => {
    setSelected([])
    onAction(action)
  }
  const toggle = (index: number) =>
    setSelected((s) => (s.includes(index) ? s.filter((i) => i !== index) : [...s, index]))

  const others = view.scores.map((_, seat) => seat).filter((seat) => seat !== me)
  const result = view.phase === 'over' || state.result ? view.last_round : null
  const status =
    view.phase === 'over' && !state.result
      ? secondsLeft !== null
        ? t('rummy.nextRoundIn', { count: secondsLeft })
        : t('rummy.nextRound')
      : view.to_act === null
        ? ' '
        : view.to_act === me
          ? view.phase === 'draw'
            ? t('rummy.drawPrompt')
            : t('rummy.playPrompt')
          : t('rummy.waitingFor', { name: name(view.to_act) })

  return (
    <div className="space-y-3 text-white">
      <div className="poker-stage space-y-3 rounded-3xl p-2 min-[641px]:p-4">
        <ul
          aria-label={t('rummy.players')}
          className="flex flex-wrap justify-center gap-x-2 gap-y-3"
        >
          {others.map((seat) => (
            <li
              key={seat}
              aria-current={view.to_act === seat ? 'step' : undefined}
              className={`relative flex items-center gap-2 rounded-xl border bg-black/40 px-2.5 py-1.5 ${
                view.to_act === seat ? 'border-[var(--color-turn)]' : 'border-gold/25'
              } ${view.out[seat] ? 'opacity-45' : ''}`}
            >
              {levels[seat] !== undefined ? <LevelBadge level={levels[seat]} size="sm" /> : null}
              <span className="max-w-24 truncate text-xs font-semibold min-[641px]:text-sm">
                {name(seat)}
              </span>
              {bots[seat] ? (
                <span className="rounded-full bg-black/60 px-1.5 py-0.5 text-[0.6rem] uppercase">
                  {t('room.bot')}
                </span>
              ) : null}
              <span
                className="flex"
                aria-label={t('rummy.cards', { count: view.hand_sizes[seat] ?? 0 })}
                role="img"
              >
                {Array.from({ length: Math.min(6, view.hand_sizes[seat] ?? 0) }, (_, i) => (
                  <CardBack key={i} size="xs" className={i > 0 ? '-ml-6' : ''} />
                ))}
              </span>
              <span className="text-xs font-extrabold text-gold-light tabular-nums">
                {format(view.scores[seat] ?? 0)}
              </span>
            </li>
          ))}
        </ul>

        <section
          aria-label={t('rummy.table')}
          className="blackjack-felt relative min-h-52 rounded-[1.6rem] p-3 shadow-[0_0_0_6px_#4a2c17,0_0_0_8px_rgb(214_171_78/0.4)] min-[641px]:p-5"
        >
          {result ? (
            <RoundResult result={result} name={name} format={format} />
          ) : (
            <div className="flex flex-col gap-4 min-[641px]:flex-row min-[641px]:items-start">
              {/* Stock and discard pile. */}
              <div className="flex shrink-0 justify-center gap-4">
                <PileButton
                  label={t('rummy.drawStock', { count: view.stock_size })}
                  enabled={!!drawFrom('stock') && !pending}
                  onClick={() => drawFrom('stock') && send({ type: 'draw', from: 'stock' })}
                >
                  <CardBack size={narrow ? 'sm' : 'md'} />
                  <span className="mt-1 block text-center text-[0.7rem] text-white/75 tabular-nums">
                    {view.stock_size}
                  </span>
                </PileButton>
                <PileButton
                  label={
                    view.discard_top
                      ? t('rummy.drawDiscard', { card: labels.card(view.discard_top) })
                      : t('rummy.emptyDiscard')
                  }
                  enabled={!!drawFrom('discard') && !pending}
                  onClick={() => drawFrom('discard') && send({ type: 'draw', from: 'discard' })}
                >
                  {view.discard_top ? (
                    <PlayingCard
                      card={view.discard_top}
                      label={labels.card(view.discard_top)}
                      size={narrow ? 'sm' : 'md'}
                    />
                  ) : (
                    <span className="block h-16 w-11 rounded-md border border-dashed border-white/30 min-[641px]:h-24 min-[641px]:w-16" />
                  )}
                  <span className="mt-1 block text-center text-[0.7rem] text-white/75 tabular-nums">
                    {view.discard_size}
                  </span>
                </PileButton>
              </div>

              {/* Melds on the table; with one card selected, the ones it extends can take it. */}
              <ul
                aria-label={t('rummy.melds')}
                className="flex min-w-0 flex-1 flex-wrap content-start gap-2"
              >
                {view.melds.length === 0 ? (
                  <li className="text-sm text-white/55">{t('rummy.noMelds')}</li>
                ) : null}
                {view.melds.map((m, i) => {
                  const layoff = layoffs.find((a) => a.meld === i)
                  const cards = (
                    <span className="flex">
                      {m.cards.map((card, j) => (
                        <span
                          key={`${card}-${j}`}
                          className={`${j > 0 ? '-ml-5' : ''} motion-safe:animate-card-in`}
                        >
                          <PlayingCard card={card} label={labels.card(card)} size="xs" />
                        </span>
                      ))}
                    </span>
                  )
                  return (
                    <li key={i}>
                      {layoff ? (
                        <button
                          type="button"
                          onClick={() => send(layoff)}
                          disabled={pending}
                          aria-label={t('rummy.layOffOn', {
                            card: labels.card(layoff.card),
                            owner: name(m.seat),
                          })}
                          className="rounded-md p-1 ring-2 ring-gold motion-safe:animate-pulse"
                        >
                          {cards}
                        </button>
                      ) : (
                        <span
                          className="block rounded-md p-1"
                          aria-label={t('rummy.meldOf', { owner: name(m.seat) })}
                        >
                          {cards}
                        </span>
                      )}
                    </li>
                  )
                })}
              </ul>
            </div>
          )}
          <p className="absolute right-3 bottom-2 text-[0.7rem] text-white/60">
            {t('rummy.round', { round: view.round })} ·{' '}
            {t('rummy.target', { target: format(view.target) })}
          </p>
        </section>

        {/* Your hand. */}
        {me !== null ? (
          <section aria-label={t('rummy.yourCards')} className="space-y-2">
            <div className="flex items-center justify-between gap-2 px-1">
              <span className="text-sm font-semibold">
                {name(me)} ·{' '}
                <span className="text-gold-light tabular-nums">{format(view.scores[me] ?? 0)}</span>
              </span>
              <button
                type="button"
                onClick={() => setSort((s) => (s === 'suit' ? 'rank' : 'suit'))}
                className="min-h-9 rounded-full border border-gold/40 bg-black/30 px-3 text-xs font-semibold"
              >
                {sort === 'suit' ? t('rummy.sortRank') : t('rummy.sortSuit')}
              </button>
            </div>
            <div className="flex flex-wrap justify-center px-1">
              {hand.map(({ card, index }, i) => {
                const on = selected.includes(index)
                return (
                  <button
                    key={index}
                    type="button"
                    disabled={!playing || pending}
                    aria-pressed={on}
                    aria-label={labels.card(card)}
                    onClick={() => toggle(index)}
                    className={`relative rounded-lg transition ${i > 0 ? '-ml-3 min-[641px]:-ml-5' : ''} ${
                      on
                        ? '-translate-y-3 ring-2 ring-gold'
                        : playing
                          ? 'motion-safe:hover:-translate-y-1'
                          : ''
                    } ${view.taken === card ? 'after:absolute after:inset-x-1 after:-bottom-1.5 after:h-1 after:rounded after:bg-gold' : ''}`}
                  >
                    <PlayingCard
                      card={card}
                      label={labels.card(card)}
                      size={narrow ? 'md' : 'lg'}
                    />
                  </button>
                )
              })}
            </div>
          </section>
        ) : null}
      </div>

      <div className="club-panel mx-auto flex max-w-3xl flex-wrap items-center justify-center gap-2 rounded-3xl p-3">
        {playing ? (
          <>
            <button
              type="button"
              disabled={!meld || pending}
              onClick={() => meld && send(meld)}
              className={`${actionButton} border-white/20 bg-linear-to-b from-accent-light to-accent text-white`}
            >
              {t('rummy.meld')}
            </button>
            <button
              type="button"
              disabled={!discard || pending}
              onClick={() => discard && send(discard)}
              className={`${actionButton} border-gold/40 bg-linear-to-b from-gold-light to-gold text-black`}
            >
              {t('rummy.discard')}
            </button>
            <p className="w-full text-center text-xs text-white/75" aria-live="polite">
              {layoffs.length > 0 ? t('rummy.layOffHint') : status}
            </p>
          </>
        ) : (
          <p className="w-full text-center text-sm" aria-live="polite">
            {status}
          </p>
        )}
      </div>
    </div>
  )
}

function PileButton({
  label,
  enabled,
  onClick,
  children,
}: {
  label: string
  enabled: boolean
  onClick: () => void
  children: ReactNode
}) {
  return (
    <button
      type="button"
      aria-label={label}
      disabled={!enabled}
      onClick={onClick}
      className={`rounded-lg p-1 transition ${enabled ? 'ring-2 ring-gold motion-safe:hover:-translate-y-1' : ''}`}
    >
      {children}
    </button>
  )
}

function RoundResult({
  result,
  name,
  format,
}: {
  result: NonNullable<RummyView['last_round']>
  name: (seat: number) => string
  format: (n: number) => string
}) {
  const { t } = useTranslation()
  const labels = useCardLabel()
  return (
    <section
      aria-label={t('rummy.roundResult')}
      className="mx-auto max-w-lg rounded-2xl border border-gold/40 bg-black/45 p-3 text-sm motion-safe:animate-rise"
    >
      <p className="mb-2 text-center font-bold" role="status">
        {result.winner === null
          ? t('rummy.stockOut')
          : t('rummy.wentOut', { name: name(result.winner), points: format(result.points) })}
      </p>
      <ul className="space-y-1.5">
        {Object.entries(result.left).map(([seat, points]) => (
          <li key={seat} className="flex items-center gap-2">
            <span className="w-20 truncate text-white/80">{name(Number(seat))}</span>
            <span className="flex flex-1 flex-wrap">
              {(result.hands[Number(seat)] ?? []).map((card, i) => (
                <span key={`${card}-${i}`} className={i > 0 ? '-ml-5' : ''}>
                  <PlayingCard card={card} label={labels.card(card)} size="xs" />
                </span>
              ))}
            </span>
            <span className="text-white/70 tabular-nums">
              {t('rummy.points', { count: points })}
            </span>
          </li>
        ))}
      </ul>
    </section>
  )
}

const actionButton =
  'min-w-32 rounded-xl border px-4 py-3 text-sm font-bold shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-50 min-[641px]:text-base'
