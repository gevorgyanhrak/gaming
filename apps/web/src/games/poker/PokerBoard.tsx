import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
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
export default function PokerBoard({
  state,
  onAction,
  pending,
  players = {},
  bots = {},
}: BoardProps) {
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

  // Turn timer: the share of the turn left, for the ring round the acting player's avatar.
  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const [timer, setTimer] = useState({ deadline: state.deadline, total: secondsLeft })
  if (timer.deadline !== state.deadline || (timer.total === null && secondsLeft !== null)) {
    setTimer({ deadline: state.deadline, total: secondsLeft })
  }
  const turnShare =
    secondsLeft !== null && timer.total ? Math.min(1, secondsLeft / timer.total) : null

  const seats = view.stacks.length
  // Round the oval from your seat at the bottom; x and y in percent of the table.
  const spot = (seat: number, radius: number) => {
    const angle = Math.PI / 2 + ((seat - me) * 2 * Math.PI) / seats
    return { left: `${50 + radius * Math.cos(angle)}%`, top: `${50 + radius * Math.sin(angle)}%` }
  }

  return (
    <div className="space-y-5 text-white">
      <div className="px-7 pt-10 pb-12 sm:px-14 sm:pt-12 sm:pb-14">
        <div className="relative mx-auto aspect-[3/4] w-full max-w-5xl sm:aspect-[2.1/1]">
          <div aria-hidden="true" className="table-rim absolute inset-0 rounded-[50%]">
            <div className="table-felt h-full w-full rounded-[50%]" />
          </div>

          <section
            aria-label={t('poker.board')}
            className="absolute inset-x-[5%] top-1/2 flex -translate-y-1/2 flex-col items-center gap-2 text-center sm:inset-x-[12%] sm:gap-3"
          >
            <div className="flex justify-center gap-1 sm:gap-2 max-sm:[&>span>span]:h-16 max-sm:[&>span>span]:w-11 max-sm:[&>span>span]:rounded-md">
              {view.board.map((card) => (
                <span key={card} className="motion-safe:animate-card-in">
                  <PlayingCard card={card} label={labels.card(card)} />
                </span>
              ))}
              {Array.from({ length: 5 - view.board.length }, (_, i) => (
                <span key={i} aria-hidden="true">
                  <span className="block h-16 w-11 rounded-md border border-dashed border-white/25 bg-black/10 sm:h-24 sm:w-16 sm:rounded-lg" />
                </span>
              ))}
            </div>
            <p className="flex items-center gap-2 rounded-full bg-black/45 px-4 py-1.5 text-sm font-bold sm:text-base">
              <ChipStack amount={view.pot} />
              {t('poker.potAmount', { count: view.pot })}
            </p>
            <p className="text-xs text-white/65">
              {t('poker.handInfo', { hand: view.hand, small: view.blinds[0], big: view.blinds[1] })}
            </p>
          </section>

          <ul aria-label={t('poker.seats')}>
            {view.stacks.map((stack, seat) => {
              const acting = view.to_act === seat
              const gone = view.out[seat] || view.folded[seat]
              const bet = view.bets[seat] ?? 0
              const status = view.out[seat]
                ? t('poker.out')
                : view.folded[seat]
                  ? t('poker.folded')
                  : stack === 0
                    ? t('poker.allInStatus')
                    : null
              return (
                <li
                  key={seat}
                  aria-current={acting ? 'step' : undefined}
                  aria-label={`${name(seat)}, ${t('poker.chips', { count: stack })}${
                    seat !== me && bots[seat] ? `, ${t('room.bot')}` : ''
                  }`}
                >
                  <div
                    className={`absolute z-10 flex -translate-x-1/2 -translate-y-1/2 flex-col items-center transition-opacity ${
                      gone ? 'opacity-50' : ''
                    }`}
                    style={spot(seat, 50)}
                  >
                    <div className="relative isolate">
                      <Avatar name={name(seat)} acting={acting} share={acting ? turnShare : null} />
                      {view.button === seat ? (
                        <span
                          title={t('poker.dealer')}
                          className="absolute -top-1 -right-2 flex size-6 items-center justify-center rounded-full border-2 border-[#c9c2b0] bg-white text-xs font-extrabold text-black shadow"
                        >
                          D
                        </span>
                      ) : null}
                      {seat !== me && !gone ? (
                        // Their two cards, face down, peeking out behind the avatar.
                        <span
                          aria-hidden="true"
                          className="absolute -top-4 left-1/2 -z-10 flex -translate-x-1/2"
                        >
                          <CardBack size="sm" className="-rotate-12" />
                          <CardBack size="sm" className="-ml-6 rotate-12" />
                        </span>
                      ) : null}
                    </div>
                    <div className="relative -mt-2 min-w-24 rounded-lg border border-gold/35 bg-[#1c120d]/90 px-2.5 py-1 text-center shadow-lg backdrop-blur">
                      <span className="block max-w-28 truncate text-xs font-semibold sm:text-sm">
                        {name(seat)}
                      </span>
                      <span className="block text-xs font-bold text-gold tabular-nums">
                        {stack.toLocaleString()}
                      </span>
                      {seat !== me && bots[seat] ? (
                        <span className="absolute -bottom-2.5 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-1.5 py-px text-[0.65rem] font-semibold tracking-wide text-white/85 uppercase">
                          {t('room.bot')}
                        </span>
                      ) : null}
                      {status ? (
                        <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-black/80 px-2 py-0.5 text-[0.65rem] font-semibold whitespace-nowrap uppercase">
                          {status}
                        </span>
                      ) : null}
                    </div>
                  </div>

                  {bet > 0 ? (
                    <span
                      className="absolute z-10 flex -translate-x-1/2 -translate-y-1/2 items-center gap-1.5 rounded-full bg-black/55 py-0.5 pr-2.5 pl-1 text-xs font-bold whitespace-nowrap tabular-nums transition-[left,top] duration-500 motion-safe:animate-card-in"
                      style={spot(seat, 30)}
                    >
                      <ChipStack amount={bet} />
                      {t('poker.bet', { amount: bet })}
                    </span>
                  ) : null}
                </li>
              )
            })}
          </ul>
        </div>
      </div>

      <div className="club-panel mx-auto flex max-w-4xl flex-col items-center gap-4 rounded-3xl p-4 sm:flex-row sm:p-5">
        <div className="flex shrink-0 gap-2" aria-label={t('poker.yourCards')}>
          {view.hand_cards.length > 0
            ? view.hand_cards.map((card, i) => (
                <span key={card} className={i === 0 ? '-rotate-6' : 'rotate-6'}>
                  <PlayingCard card={card} label={labels.card(card)} size="lg" />
                </span>
              ))
            : [0, 1].map((i) => <CardBack key={i} size="lg" />)}
        </div>

        {actions.length > 0 ? (
          <div className="flex w-full flex-col gap-3">
            <div className="grid grid-cols-3 gap-2">
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
                  {t('poker.call', { amount: callAmount })}
                </button>
              ) : null}
              {raise ? (
                <button
                  type="button"
                  disabled={pending}
                  onClick={() => send({ type: 'raise', amount: raiseTo })}
                  className={`${actionButton} border-white/20 bg-linear-to-b from-accent-light to-accent text-white`}
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
                  className="min-w-32 flex-1 accent-[var(--color-gold)]"
                />
                {presets.map((preset) => (
                  <button
                    key={preset.label}
                    type="button"
                    onClick={() => setAmount(preset.value)}
                    className="rounded-full border border-gold/35 bg-black/30 px-3 py-1 text-xs font-semibold hover:border-gold"
                  >
                    {preset.label}
                  </button>
                ))}
              </div>
            ) : null}
          </div>
        ) : (
          <p
            className="w-full rounded-md bg-[#fdebc8] px-3 py-2 text-center text-sm text-black"
            aria-live="polite"
          >
            {view.to_act === null ? ' ' : t('poker.waitingFor', { name: name(view.to_act) })}
          </p>
        )}
      </div>

      {view.last_hand ? (
        <section
          aria-label={t('poker.lastHand')}
          className="club-panel mx-auto max-w-4xl rounded-3xl p-4 text-sm"
        >
          <h2 className="mb-2 font-semibold text-gold">
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

/** A few play chips, more of them for bigger amounts. Decorative. */
function ChipStack({ amount }: { amount: number }) {
  const count = amount >= 500 ? 3 : amount >= 50 ? 2 : 1
  return (
    <span aria-hidden="true" className="relative inline-block h-5 w-5">
      {Array.from({ length: count }, (_, i) => (
        <span
          key={i}
          className="absolute left-0 size-5 rounded-full border-2 border-dashed border-white/85 shadow"
          style={{
            bottom: `${i * 3}px`,
            background: ['#c62f3a', '#2f6fd6', '#d6ab4e'][i],
          }}
        />
      ))}
    </span>
  )
}

/** A round avatar with the player's initial; the acting player's ring shows the time left. */
function Avatar({ name, acting, share }: { name: string; acting: boolean; share: number | null }) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : 'rgb(214 171 78 / 0.45)'
  return (
    <span
      className="block rounded-full p-[3px] shadow-lg shadow-black/50"
      style={{ background: ring }}
      aria-hidden="true"
    >
      <span className="flex size-12 items-center justify-center rounded-full bg-linear-to-b from-[#4a342b] to-[#24170f] text-xl font-bold uppercase sm:size-16 sm:text-2xl">
        {name.slice(0, 1)}
      </span>
    </span>
  )
}

const actionButton =
  'rounded-xl border px-3 py-3 text-sm font-bold shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-60 sm:text-base'
