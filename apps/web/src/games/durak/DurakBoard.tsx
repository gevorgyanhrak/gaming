import { useState } from 'react'
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

type Place = 'left' | 'top' | 'right'

interface Role {
  text: string
  tone: string
}

const RANKS = '6789TJQKA'
const SUITS = 'SHDC'

/** Where the other players sit, in table order from the player after you (on your left). */
const PLACES: Record<number, Place[]> = {
  1: ['top'],
  2: ['left', 'right'],
  3: ['left', 'top', 'right'],
  4: ['left', 'top', 'top', 'right'],
  5: ['left', 'top', 'top', 'top', 'right'],
}

/** Your hand in a readable order: suits together, ranks ascending, trumps last. */
function sortHand(hand: string[], trump: Suit) {
  const key = (card: string) =>
    (card[1] === trump ? 100 : SUITS.indexOf(card[1] ?? 'S') * 10) + RANKS.indexOf(card[0] ?? '6')
  return [...hand].sort((a, b) => key(a) - key(b))
}

/**
 * Durak table: the other players in panels round the felt (left, across and right), the deck with
 * the trump under it, the bout in the middle and your hand in a row at the bottom with the action
 * button under it. Offers exactly the server's legal actions: cards to lead, throw in or beat with,
 * "take" and "done".
 */
export default function DurakBoard({
  state,
  onAction,
  pending,
  players = {},
  bots = {},
}: BoardProps) {
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
  const places = PLACES[others.length] ?? others.map((): Place => 'top')
  const nameOf = (seat: number) => players[seat] || t('durak.seat', { seat: seat + 1 })
  const roleOf = (seat: number): Role | null => {
    if (view.left?.[seat]) return { text: t('durak.role.left'), tone: 'bg-black/60 text-white/80' }
    if (view.deck_count === 0 && (view.hand_counts[seat] ?? 0) === 0)
      return { text: t('durak.role.out'), tone: 'bg-beaten text-white' }
    if (seat === view.attacker)
      return { text: t('durak.role.attacker'), tone: 'bg-turn text-black' }
    if (seat === view.defender)
      return view.taking
        ? { text: t('durak.role.taking'), tone: 'bg-take text-white' }
        : { text: t('durak.role.defender'), tone: 'bg-white text-black' }
    if (view.passed?.includes(seat))
      return { text: t('durak.role.passed'), tone: 'bg-black/60 text-white/90' }
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
  const myRole = roleOf(me)

  const seat = (other: number) => {
    const acting = state.seats_to_act.includes(other)
    return (
      <Seat
        key={other}
        name={nameOf(other)}
        count={view.hand_counts[other] ?? 0}
        role={roleOf(other)}
        acting={acting}
        share={acting ? turnShare : null}
        bot={bots[other] ? t('room.bot') : null}
        label={`${t('durak.opponentCards', {
          name: nameOf(other),
          count: view.hand_counts[other] ?? 0,
        })}${bots[other] ? `, ${t('room.bot')}` : ''}`}
      />
    )
  }
  const placed = (place: Place) => others.filter((_, i) => places[i] === place).map(seat)

  return (
    <div className="relative px-1 pt-8 pb-4 text-white sm:px-4 sm:pt-10">
      <div className="grid grid-cols-2 items-start gap-x-3 gap-y-8 sm:grid-cols-[minmax(0,1fr)_minmax(0,2.4fr)_minmax(0,1fr)] sm:items-center sm:gap-x-6">
        {/* The oval table behind the bout; the other players sit on its rim. */}
        <div
          aria-hidden="true"
          className="table-rim col-span-2 col-start-1 row-start-3 self-stretch rounded-[2.5rem] sm:col-span-3 sm:col-start-1 sm:row-start-2 sm:mx-[3%] sm:-my-10 sm:rounded-[50%]"
        >
          <div className="table-felt h-full w-full rounded-[inherit]" />
        </div>
        <div className="relative z-10 col-start-1 row-start-2 sm:col-start-1 sm:row-start-2">
          {placed('left')}
        </div>
        <div className="relative z-10 col-span-2 col-start-1 row-start-1 flex flex-wrap justify-center gap-x-6 gap-y-8 sm:col-span-3 sm:col-start-1 sm:row-start-1">
          {placed('top')}
        </div>
        <div className="relative z-10 col-start-2 row-start-2 sm:col-start-3 sm:row-start-2">
          {placed('right')}
        </div>

        {/* On a phone the deck and the discard pile share a row above the bout. */}
        <div className="relative z-10 col-span-2 col-start-1 row-start-3 flex flex-wrap items-start justify-between gap-3 px-5 py-6 sm:col-span-1 sm:col-start-2 sm:row-start-2 sm:flex-nowrap sm:justify-center sm:gap-6 sm:px-0 sm:py-4">
          <div className="flex shrink-0 flex-col items-center gap-2">
            {/* The trump card lies face up under the deck, its top end showing. */}
            <div className="relative h-36 w-16 sm:h-40">
              {view.trump_card ? (
                <span className="absolute top-0 left-0">
                  <Card
                    card={view.trump_card}
                    label={t('durak.trumpCard', { card: cardLabel(view.trump_card) })}
                  />
                </span>
              ) : null}
              {view.deck_count > 1 ? (
                <>
                  <CardBack className="absolute top-11 left-0 sm:top-14" />
                  <CardBack className="absolute top-12 left-0 sm:top-[3.75rem]" />
                </>
              ) : null}
              {view.deck_count === 0 ? (
                <span
                  aria-hidden="true"
                  className={`absolute inset-x-0 top-6 text-center text-6xl opacity-40 ${
                    view.trump === 'H' || view.trump === 'D' ? 'text-red-300' : 'text-white'
                  }`}
                >
                  {suitSymbols[view.trump]}
                </span>
              ) : null}
            </div>
            <p className="text-center text-xs text-white/75">
              <span className="block">{t('durak.deck', { count: view.deck_count })}</span>
              <span className="block">
                {t('durak.trump')}: {suitSymbols[view.trump]} {labels.suit(view.trump)}
              </span>
            </p>
          </div>

          <section
            aria-label={t('durak.table')}
            className="order-last flex min-h-44 min-w-0 basis-full flex-wrap content-center items-start justify-center gap-x-3 gap-y-4 sm:order-none sm:min-h-48 sm:flex-1 sm:basis-auto sm:gap-x-4"
          >
            {view.table.length === 0 ? (
              <p className="self-center text-sm text-white/55">{t('durak.emptyTable')}</p>
            ) : (
              view.table.map((pair, i) => (
                <div
                  key={i}
                  className="relative h-40 w-20 motion-safe:animate-card-in sm:h-44 sm:w-[5.5rem]"
                >
                  <Card card={pair.attack} label={cardLabel(pair.attack)} size="lg" />
                  {pair.defense ? (
                    // The beating card lies straight over the lower part of the attack.
                    <span className="absolute top-9 left-0 motion-safe:animate-card-in sm:top-10">
                      <Card
                        card={pair.defense}
                        label={t('durak.beatenBy', { card: cardLabel(pair.defense) })}
                        size="lg"
                      />
                    </span>
                  ) : null}
                </div>
              ))
            )}
          </section>

          <div
            role="group"
            aria-label={t('durak.discardPile')}
            className="flex shrink-0 flex-col items-center gap-2"
          >
            <div className="relative h-16 w-14">
              {(view.discarded ?? 0) > 0
                ? [-14, 6, -4].map((angle, i) => (
                    <CardBack
                      key={i}
                      size="sm"
                      className="absolute top-0 left-1.5"
                      style={{ transform: `rotate(${angle}deg)` }}
                    />
                  ))
                : null}
            </div>
            <p className="text-xs text-white/75">
              {t('durak.discarded', { count: view.discarded ?? 0 })}
            </p>
          </div>
        </div>
      </div>

      <div className="mx-auto mt-8 w-full max-w-xl">
        <div className="flex items-center gap-3">
          <Avatar
            name={players[me] || t('poker.you')}
            acting={myTurn}
            share={myTurn ? turnShare : null}
            size="lg"
          />
          <div className="min-w-0 flex-1">
            <p className="flex items-center gap-2 text-sm text-white/75">
              <span className="truncate">{players[me] || t('poker.you')}</span>
              {myRole ? (
                <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${myRole.tone}`}>
                  {myRole.text}
                </span>
              ) : null}
            </p>
            <p
              className="mt-1 rounded-md bg-[#fdebc8] px-3 py-1.5 text-sm text-black shadow"
              aria-live="polite"
            >
              {status}
            </p>
          </div>
        </div>

        <ul
          aria-label={t('durak.yourHand')}
          className="mt-4 flex justify-center pt-4 [--card-w:5rem] sm:[--card-w:5.5rem]"
        >
          {hand.map((card, i) => {
            const action = actionFor(card)
            return (
              <li
                key={card}
                // A straight row that overlaps more as the hand grows, so it always fits.
                style={
                  i === 0
                    ? undefined
                    : {
                        marginLeft: `calc(min(3.25rem, (100% - var(--card-w)) / ${hand.length - 1}) - var(--card-w))`,
                      }
                }
              >
                <button
                  type="button"
                  disabled={pending || !action}
                  onClick={() => action && onAction(action)}
                  aria-label={cardLabel(card)}
                  className={`block rounded-lg transition-transform duration-150 disabled:cursor-default ${
                    action
                      ? '-translate-y-3 hover:-translate-y-6 focus-visible:-translate-y-6 [&>span]:shadow-[0_0_0_2px_var(--color-turn),0_6px_14px_rgb(0_0_0/0.45)]'
                      : myTurn
                        ? 'brightness-[.8]'
                        : ''
                  }`}
                >
                  <Card card={card} label={cardLabel(card)} size="lg" />
                </button>
              </li>
            )
          })}
        </ul>

        {/* The action row keeps its height so the hand doesn't jump when the buttons come and go. */}
        <div className="mt-4 flex min-h-12 gap-3">
          {canTake ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => onAction({ type: 'take' })}
              className="flex-1 rounded-md bg-turn px-6 py-3 text-base font-semibold text-black shadow-lg hover:brightness-110 disabled:opacity-60"
            >
              {t('durak.take')}
            </button>
          ) : null}
          {canFinish ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => onAction({ type: 'done' })}
              className="flex-1 rounded-md bg-turn px-6 py-3 text-base font-semibold text-black shadow-lg hover:brightness-110 disabled:opacity-60"
            >
              {finishLabel}
            </button>
          ) : null}
        </div>
      </div>
    </div>
  )
}

/**
 * Another player: a panel with the avatar on its corner, the name, the cards held face down in a
 * row with their number, and the player's part in the bout as a speech bubble.
 */
function Seat({
  name,
  count,
  role,
  acting,
  share,
  bot,
  label,
}: {
  name: string
  count: number
  role: Role | null
  acting: boolean
  share: number | null
  /** "Bot" when a bot plays this seat. */
  bot: string | null
  label: string
}) {
  const shown = Math.min(count, 8)
  return (
    <div
      role="group"
      aria-label={label}
      aria-current={acting ? 'step' : undefined}
      className={`relative mx-auto w-full max-w-60 rounded-2xl border px-3 pt-7 pb-3 backdrop-blur-sm transition-colors ${
        acting
          ? 'border-gold bg-black/55 shadow-[0_0_0_3px_rgb(214_171_78/0.25)]'
          : 'border-gold/20 bg-black/40'
      }`}
    >
      <span className="absolute -top-5 -left-2 sm:-left-4">
        <Avatar name={name} acting={acting} share={share} />
      </span>
      <span className="absolute top-1.5 right-2 left-12 flex items-center gap-1.5 text-sm text-white/75 sm:left-10">
        <span className="truncate">{name}</span>
        {bot ? (
          <span className="shrink-0 rounded-full bg-black/60 px-1.5 py-px text-[0.65rem] font-semibold tracking-wide text-white/85 uppercase">
            {bot}
          </span>
        ) : null}
      </span>
      {role ? (
        <span
          className={`absolute -top-7 left-10 rounded-xl px-2.5 py-0.5 text-xs font-semibold shadow sm:left-8 ${role.tone}`}
        >
          {role.text}
        </span>
      ) : null}
      <span className="mt-2 flex items-center gap-2">
        <span className="flex min-h-16 sm:min-h-24" aria-hidden="true">
          {Array.from({ length: shown }, (_, i) => (
            <CardBack
              key={i}
              className={`max-sm:h-16 max-sm:w-11 max-sm:rounded-md ${i ? '-ml-11 max-sm:-ml-8' : ''}`}
            />
          ))}
        </span>
        <span className="ml-auto rounded-full bg-black/45 px-2 py-0.5 text-xs tabular-nums">
          {count}
        </span>
      </span>
    </div>
  )
}

/** A round avatar with the player's initial; the acting player's ring shows the time left. */
function Avatar({
  name,
  acting,
  share,
  size = 'md',
}: {
  name: string
  acting: boolean
  share: number | null
  size?: 'md' | 'lg'
}) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : 'rgb(255 255 255 / 0.25)'
  return (
    <span
      className="block rounded-full p-[3px] shadow-md"
      style={{ background: ring }}
      aria-hidden="true"
    >
      <span
        className={`flex items-center justify-center rounded-full bg-[#1d2a24] font-bold uppercase ${
          size === 'lg' ? 'size-12 text-xl sm:size-14' : 'size-10 text-lg'
        }`}
      >
        {name.slice(0, 1)}
      </span>
    </span>
  )
}
