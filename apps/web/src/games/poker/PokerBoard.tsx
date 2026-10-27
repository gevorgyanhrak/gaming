import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCardLabel } from '../cards/cards'
import { CardBack, PlayingCard } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'

interface Pot {
  amount: number
  winners: number[]
  hand: string | null
}

interface PokerView {
  stacks: number[]
  out: boolean[]
  button: number
  hand: number
  blinds: [number, number]
  board: string[]
  street: 'preflop' | 'flop' | 'turn' | 'river'
  bets: number[]
  folded: boolean[]
  current_bet: number
  to_act: number | null
  hand_cards: string[]
  pot: number
  last_hand: {
    number: number
    board: string[]
    shown: Record<string, string[]>
    pots: Pot[]
  } | null
}

type PokerAction = { type: 'fold' | 'check' | 'call' } | { type: 'raise'; min: number; max: number }

const handKeys = {
  high_card: 'poker.hand.highCard',
  pair: 'poker.hand.pair',
  two_pair: 'poker.hand.twoPair',
  three_of_a_kind: 'poker.hand.threeOfAKind',
  straight: 'poker.hand.straight',
  flush: 'poker.hand.flush',
  full_house: 'poker.hand.fullHouse',
  four_of_a_kind: 'poker.hand.fourOfAKind',
  straight_flush: 'poker.hand.straightFlush',
} as const

/**
 * Hold'em table: seats with stacks and bets, the board and pot, your hole cards, and the server's
 * legal actions (fold / check / call, and raises anywhere in the allowed range). Play chips only.
 */
export default function PokerBoard({ state, onAction, pending, players = {} }: BoardProps) {
  const { t } = useTranslation()
  const labels = useCardLabel()
  const view = state.view as unknown as PokerView
  const me = state.seat ?? 0
  const actions = state.legal_actions as unknown as PokerAction[]
  const raise = actions.find(
    (a): a is Extract<PokerAction, { type: 'raise' }> => a.type === 'raise',
  )
  const [amount, setAmount] = useState<number | null>(null)

  const owe = Math.max(0, view.current_bet - (view.bets[me] ?? 0))
  const callAmount = Math.min(owe, view.stacks[me] ?? 0)
  const raiseTo = raise ? Math.min(raise.max, Math.max(raise.min, amount ?? raise.min)) : 0
  const name = (seat: number) =>
    seat === me ? t('poker.you') : players[seat] || t('poker.seat', { seat: seat + 1 })
  const has = (type: PokerAction['type']) => actions.some((a) => a.type === type)
  const send = (action: Parameters<BoardProps['onAction']>[0]) => {
    setAmount(null)
    onAction(action)
  }
  const presets = raise
    ? [
        { label: t('poker.halfPot'), value: view.current_bet + Math.round(view.pot / 2) },
        { label: t('poker.pot'), value: view.current_bet + view.pot + owe },
        { label: t('poker.allIn'), value: raise.max },
      ]
    : []

  return (
    <div className="space-y-5">
      <ul aria-label={t('poker.seats')} className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {view.stacks.map((stack, seat) => (
          <li
            key={seat}
            aria-current={view.to_act === seat ? 'step' : undefined}
            className={`rounded-xl border p-3 text-sm ${view.to_act === seat ? 'border-accent-text' : 'border-border'} ${
              view.out[seat] || view.folded[seat] ? 'opacity-50' : ''
            } bg-surface`}
          >
            <p className="flex items-center justify-between gap-2 font-medium">
              <span className="truncate">{name(seat)}</span>
              {view.button === seat ? (
                <span
                  title={t('poker.dealer')}
                  className="rounded-full bg-fg px-1.5 text-xs font-bold text-bg"
                >
                  D
                </span>
              ) : null}
            </p>
            <p className="tabular-nums">{t('poker.chips', { count: stack })}</p>
            <p className="text-xs text-muted">
              {view.out[seat]
                ? t('poker.out')
                : view.folded[seat]
                  ? t('poker.folded')
                  : (view.bets[seat] ?? 0) > 0
                    ? t('poker.bet', { amount: view.bets[seat] })
                    : stack === 0
                      ? t('poker.allInStatus')
                      : ' '}
            </p>
          </li>
        ))}
      </ul>

      <section
        aria-label={t('poker.board')}
        className="space-y-3 rounded-[2rem] border border-border bg-[#1f5c3d]/60 p-5 text-center text-white"
      >
        <div className="flex min-h-24 flex-wrap justify-center gap-2">
          {view.board.map((card) => (
            <PlayingCard key={card} card={card} label={labels.card(card)} />
          ))}
          {Array.from({ length: 5 - view.board.length }, (_, i) => (
            <span
              key={i}
              aria-hidden="true"
              className="h-24 w-16 rounded-lg border border-dashed border-white/30"
            />
          ))}
        </div>
        <p className="font-semibold">{t('poker.potAmount', { count: view.pot })}</p>
        <p className="text-xs text-white/70">
          {t('poker.handInfo', { hand: view.hand, small: view.blinds[0], big: view.blinds[1] })}
        </p>
      </section>

      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex gap-2" aria-label={t('poker.yourCards')}>
          {view.hand_cards.length > 0
            ? view.hand_cards.map((card) => (
                <PlayingCard key={card} card={card} label={labels.card(card)} />
              ))
            : [0, 1].map((i) => <CardBack key={i} />)}
        </div>

        {actions.length > 0 ? (
          <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-2">
              {has('fold') ? (
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => send({ type: 'fold' })}
                  className={secondary}
                >
                  {t('poker.fold')}
                </button>
              ) : null}
              {has('check') ? (
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => send({ type: 'check' })}
                  className={secondary}
                >
                  {t('poker.check')}
                </button>
              ) : null}
              {has('call') ? (
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => send({ type: 'call' })}
                  className={secondary}
                >
                  {t('poker.call', { amount: callAmount })}
                </button>
              ) : null}
              {raise ? (
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => send({ type: 'raise', amount: raiseTo })}
                  className="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-white hover:bg-accent-hover"
                >
                  {raiseTo === raise.max
                    ? t('poker.allInAmount', { amount: raiseTo })
                    : view.current_bet === 0
                      ? t('poker.betTo', { amount: raiseTo })
                      : t('poker.raiseTo', { amount: raiseTo })}
                </button>
              ) : null}
            </div>
            {raise && raise.max > raise.min ? (
              <div className="flex flex-wrap items-center gap-2">
                <input
                  type="range"
                  min={raise.min}
                  max={raise.max}
                  step={1}
                  value={raiseTo}
                  onChange={(e) => setAmount(Number(e.target.value))}
                  aria-label={t('poker.amount')}
                  className="w-40 accent-[var(--color-accent)]"
                />
                {presets.map((preset) => (
                  <button
                    key={preset.label}
                    type="button"
                    onClick={() => setAmount(preset.value)}
                    className="rounded-md border border-border px-2 py-1 text-xs hover:border-accent-text"
                  >
                    {preset.label}
                  </button>
                ))}
              </div>
            ) : null}
          </div>
        ) : (
          <p className="text-sm text-muted" aria-live="polite">
            {view.to_act === null ? '' : t('poker.waitingFor', { name: name(view.to_act) })}
          </p>
        )}
      </div>

      {view.last_hand ? (
        <section
          aria-label={t('poker.lastHand')}
          className="rounded-xl border border-border bg-surface p-4 text-sm"
        >
          <h2 className="mb-2 font-semibold">
            {t('poker.lastHandTitle', { hand: view.last_hand.number })}
          </h2>
          <ul className="space-y-1">
            {view.last_hand.pots.map((pot, i) => (
              <li key={i}>
                {t('poker.won', {
                  names: pot.winners.map(name).join(', '),
                  count: pot.amount,
                })}
                {pot.hand ? ` — ${t(handKeys[pot.hand as keyof typeof handKeys])}` : ''}
              </li>
            ))}
          </ul>
          {Object.keys(view.last_hand.shown).length > 0 ? (
            <ul className="mt-3 flex flex-wrap gap-4">
              {Object.entries(view.last_hand.shown).map(([seat, cards]) => (
                <li key={seat} className="flex items-center gap-2">
                  <span className="text-muted">{name(Number(seat))}</span>
                  {cards.map((card) => (
                    <PlayingCard key={card} card={card} label={labels.card(card)} small />
                  ))}
                </li>
              ))}
            </ul>
          ) : null}
        </section>
      ) : null}
    </div>
  )
}

const secondary =
  'rounded-lg border border-border bg-surface-raised px-4 py-2 text-sm font-semibold hover:border-accent-text'
