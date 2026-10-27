import { useState, type CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { suitSymbols, useCardLabel, type Suit } from '../cards/cards'
import { CardBack, PlayingCard as Card } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'

interface DurakView {
  hand: string[]
  hand_counts: number[]
  deck_count: number
  trump: Suit
  trump_card: string | null
  table: { attack: string; defense: string | null }[]
  attacker: number
  defender: number
  taking: boolean
  passed?: number[]
  left?: boolean[]
  discarded?: number
}

type DurakAction = { type: 'attack' | 'defend'; card: string } | { type: 'take' | 'done' }

const RANKS = '6789TJQKA'
const SUITS = 'SHDC'

/** Your hand in a readable order: suits together, ranks ascending, trumps last. */
function sortHand(hand: string[], trump: Suit) {
  const key = (card: string) =>
    (card[1] === trump ? 100 : SUITS.indexOf(card[1] ?? 'S') * 10) + RANKS.indexOf(card[0] ?? '6')
  return [...hand].sort((a, b) => key(a) - key(b))
}

/**
 * Durak table: the other players round the top of the felt, deck and trump on the left, the
 * discard pile on the right, the bout in the middle and your hand fanned at the bottom.
 * Offers exactly the server's legal actions: cards to lead, throw in or beat with, "take" and "done".
 */
export default function DurakBoard({ state, onAction, pending, players = {} }: BoardProps) {
  const { t } = useTranslation()
  const view = state.view as unknown as DurakView
  const me = state.seat ?? 0
  const actions = state.legal_actions as unknown as DurakAction[]
  const myTurn = state.seats_to_act.includes(me)
  const labels = useCardLabel()
  const cardLabel = labels.card

  const seats = view.hand_counts.length
  // The others in table order, starting with the player after you.
  const others = Array.from({ length: seats - 1 }, (_, i) => (me + 1 + i) % seats)
  const nameOf = (seat: number) => players[seat] || t('durak.seat', { seat: seat + 1 })
  const roleOf = (seat: number): { text: string; tone: string } | null => {
    if (view.left?.[seat]) return { text: t('durak.role.left'), tone: 'bg-black/40 text-white/70' }
    if (view.deck_count === 0 && (view.hand_counts[seat] ?? 0) === 0)
      return { text: t('durak.role.out'), tone: 'bg-beaten text-white' }
    if (seat === view.attacker)
      return { text: t('durak.role.attacker'), tone: 'bg-turn text-black' }
    if (seat === view.defender)
      return view.taking
        ? { text: t('durak.role.taking'), tone: 'bg-take text-white' }
        : { text: t('durak.role.defender'), tone: 'bg-sky-300 text-black' }
    if (view.passed?.includes(seat))
      return { text: t('durak.role.passed'), tone: 'bg-black/40 text-white/80' }
    return null
  }

  // Turn timer: the share of the turn left, for the ring round the acting player's avatar.
  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const [timer, setTimer] = useState({ deadline: state.deadline, total: secondsLeft })
  if (timer.deadline !== state.deadline || (timer.total === null && secondsLeft !== null)) {
    setTimer({ deadline: state.deadline, total: secondsLeft })
  }
  const turnShare =
    secondsLeft !== null && timer.total ? Math.min(1, secondsLeft / timer.total) : null

  const actionFor = (card: string) => actions.find((a) => 'card' in a && a.card === card)
  const canTake = actions.some((a) => a.type === 'take')
  const canFinish = actions.some((a) => a.type === 'done')
  const finishLabel = view.taking
    ? t('durak.letTake')
    : me === view.attacker
      ? t('durak.done')
      : t('durak.pass')

  const status = !myTurn
    ? view.taking && view.defender === me
      ? t('durak.youTake')
      : t('durak.waiting')
    : view.defender === me
      ? t('durak.defend')
      : view.table.length === 0
        ? t('durak.lead')
        : view.taking
          ? t('durak.throwInBeforeTake')
          : t('durak.throwIn')

  const hand = sortHand(view.hand, view.trump)
  const spread = Math.min(7, 48 / Math.max(hand.length, 1))

  return (
    <div className="felt-table relative overflow-hidden rounded-[2rem] px-3 pt-5 pb-10 text-white sm:rounded-[2.5rem] sm:px-10 sm:pt-6">
      <ul
        className="flex flex-wrap justify-around gap-x-4 gap-y-3"
        aria-label={t('durak.opponents')}
      >
        {others.map((seat, index) => {
          const count = view.hand_counts[seat] ?? 0
          const role = roleOf(seat)
          const acting = state.seats_to_act.includes(seat)
          // Seats further from the middle sit a little lower, along the table's curve.
          const fromMiddle = Math.abs(index - (others.length - 1) / 2)
          return (
            <li
              key={seat}
              aria-current={acting ? 'step' : undefined}
              aria-label={t('durak.opponentCards', { name: nameOf(seat), count })}
              className="flex flex-col items-center gap-1 sm:mt-(--arc)"
              style={{ '--arc': `${fromMiddle * 0.9}rem` } as CSSProperties}
            >
              <Avatar name={nameOf(seat)} acting={acting} share={acting ? turnShare : null} />
              <span className="max-w-24 truncate text-xs font-medium">{nameOf(seat)}</span>
              <span className="relative block h-14 w-20" aria-hidden="true">
                {Array.from({ length: Math.min(count, 7) }, (_, i) => (
                  <CardBack
                    key={i}
                    size="sm"
                    className="absolute top-0 left-1/2 -ml-[1.375rem] origin-bottom"
                    // A small fan: the cards turn out from the middle.
                    style={{
                      transform: `rotate(${(i - (Math.min(count, 7) - 1) / 2) * 9}deg) scale(0.75)`,
                    }}
                  />
                ))}
              </span>
              <span className="relative z-10 flex items-center gap-1.5">
                <span className="rounded-full bg-black/45 px-2 py-0.5 text-xs tabular-nums">
                  {count}
                </span>
                {role ? (
                  <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${role.tone}`}>
                    {role.text}
                  </span>
                ) : null}
              </span>
            </li>
          )
        })}
      </ul>

      <div className="mt-6 grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-1 sm:gap-6">
        <div className="flex flex-col items-center gap-2">
          <div className="relative h-24 w-24 origin-top scale-75 sm:scale-100">
            {view.trump_card ? (
              <span className="absolute top-3 left-10 rotate-90">
                <Card
                  card={view.trump_card}
                  label={t('durak.trumpCard', { card: cardLabel(view.trump_card) })}
                />
              </span>
            ) : null}
            {view.deck_count > 1 ? (
              <>
                <CardBack className="absolute top-0 left-0" />
                <CardBack className="absolute top-0.5 left-0.5" />
              </>
            ) : null}
            {view.deck_count === 0 ? (
              <span
                aria-hidden="true"
                className={`absolute inset-0 flex items-center justify-center text-6xl opacity-40 ${
                  view.trump === 'H' || view.trump === 'D' ? 'text-red-300' : 'text-white'
                }`}
              >
                {suitSymbols[view.trump]}
              </span>
            ) : null}
          </div>
          <p className="text-center text-xs text-white/80">
            <span className="block">{t('durak.deck', { count: view.deck_count })}</span>
            <span className="block">
              {t('durak.trump')}: {suitSymbols[view.trump]} {labels.suit(view.trump)}
            </span>
          </p>
        </div>

        <section
          aria-label={t('durak.table')}
          className="flex min-h-40 flex-wrap items-center justify-center gap-x-8 gap-y-4"
        >
          {view.table.length === 0 ? (
            <p className="text-sm text-white/60">{t('durak.emptyTable')}</p>
          ) : (
            view.table.map((pair, i) => (
              <div key={i} className="relative h-32 w-20 motion-safe:animate-card-in">
                <Card card={pair.attack} label={cardLabel(pair.attack)} />
                {pair.defense ? (
                  <span className="absolute top-6 left-5 rotate-12 motion-safe:animate-card-in">
                    <Card
                      card={pair.defense}
                      label={t('durak.beatenBy', { card: cardLabel(pair.defense) })}
                    />
                  </span>
                ) : null}
              </div>
            ))
          )}
        </section>

        <div className="flex flex-col items-center gap-2" aria-label={t('durak.discardPile')}>
          <div className="relative h-24 w-20 origin-top scale-75 sm:scale-100">
            {(view.discarded ?? 0) > 0
              ? [-14, 6, -4].map((angle, i) => (
                  <CardBack
                    key={i}
                    className="absolute top-0 left-2"
                    style={{ transform: `rotate(${angle}deg)` }}
                  />
                ))
              : null}
          </div>
          <p className="text-xs text-white/80">
            {t('durak.discarded', { count: view.discarded ?? 0 })}
          </p>
        </div>
      </div>

      <p
        className="mx-auto mt-4 w-fit max-w-full rounded-full bg-black/40 px-4 py-1.5 text-center text-sm"
        aria-live="polite"
      >
        {status}
      </p>

      <div className="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div className="order-2 flex items-center gap-2 sm:order-1">
          <Avatar
            name={players[me] || t('poker.you')}
            acting={myTurn}
            share={myTurn ? turnShare : null}
          />
          {roleOf(me) ? (
            <span
              className={`rounded-full px-2 py-0.5 text-xs font-semibold ${roleOf(me)?.tone ?? ''}`}
            >
              {roleOf(me)?.text}
            </span>
          ) : null}
        </div>

        <ul
          aria-label={t('durak.yourHand')}
          className="order-1 flex w-full justify-center pt-4 sm:order-2 sm:w-auto sm:flex-1"
        >
          {hand.map((card, i) => {
            const action = actionFor(card)
            const angle = (i - (hand.length - 1) / 2) * spread
            return (
              <li
                key={card}
                className="-ml-12 origin-bottom first:ml-0 sm:-ml-8"
                style={{ transform: `rotate(${angle}deg) translateY(${Math.abs(angle) * 0.25}px)` }}
              >
                <button
                  type="button"
                  disabled={pending || !action}
                  onClick={() => action && onAction(action)}
                  aria-label={cardLabel(card)}
                  className={`block rounded-lg transition-transform duration-150 disabled:cursor-default ${
                    action
                      ? 'hover:-translate-y-5 focus-visible:-translate-y-5 [&>span]:shadow-[0_0_0_2px_var(--color-turn),0_6px_14px_rgb(0_0_0/0.45)]'
                      : myTurn
                        ? 'brightness-[.82]'
                        : ''
                  }`}
                >
                  <Card card={card} label={cardLabel(card)} size="lg" />
                </button>
              </li>
            )
          })}
        </ul>

        <div className="order-3 flex gap-2">
          {canTake ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => onAction({ type: 'take' })}
              className="rounded-full bg-take px-6 py-3 text-base font-bold shadow-lg hover:brightness-110 disabled:opacity-60"
            >
              {t('durak.take')}
            </button>
          ) : null}
          {canFinish ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => onAction({ type: 'done' })}
              className={`rounded-full px-6 py-3 text-base font-bold shadow-lg hover:brightness-110 disabled:opacity-60 ${
                view.taking || me !== view.attacker ? 'bg-turn text-black' : 'bg-beaten'
              }`}
            >
              {finishLabel}
            </button>
          ) : null}
        </div>
      </div>
    </div>
  )
}

/** A round avatar with the player's initial; the acting player's ring shows the time left. */
function Avatar({ name, acting, share }: { name: string; acting: boolean; share: number | null }) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : 'rgb(255 255 255 / 0.15)'
  return (
    <span className="rounded-full p-[3px]" style={{ background: ring }} aria-hidden="true">
      <span className="flex size-9 items-center justify-center rounded-full bg-[#1d2a24] text-base font-bold uppercase sm:size-11 sm:text-lg">
        {name.slice(0, 1)}
      </span>
    </span>
  )
}
