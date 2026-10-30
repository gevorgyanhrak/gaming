import { useEffect, useRef, useState, type CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { useFitHeight } from '@/features/play/useFitHeight'
import { waitingCaption } from '@/features/play/turnCaption'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { Button } from '@/shared/ui/Button'
import { useCardLabel, type Suit } from '../cards/cards'
import { PlayingCard } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'
import { DurakHand } from './DurakHand'
import { Avatar, Bubble, CompactSeat, PanelSeat, type Role } from './DurakSeat'
import { Deck, Discard } from './TablePiles'
import { useBoutMotion, type Pair } from './useBoutMotion'

interface DurakView {
  hand: string[]
  hand_counts: number[]
  deck_count: number
  trump: Suit
  trump_card: string | null
  table: Pair[]
  attacker: number
  defender: number
  taking: boolean
  passed?: number[]
  left?: boolean[]
  discarded?: number
}

type DurakAction = { type: 'attack' | 'defend'; card: string } | { type: 'take' | 'done' }

type Place = 'left' | 'top' | 'right'

const RANKS = '6789TJQKA'
const SUITS = 'SHDC'

/** Where the other players sit on a wide screen, in table order from the player after you. */
const PLACES: Record<number, Place[]> = {
  1: ['top'],
  2: ['left', 'right'],
  3: ['left', 'top', 'right'],
  4: ['left', 'top', 'top', 'right'],
  5: ['left', 'top', 'top', 'top', 'right'],
}

/**
 * Card width on the table (CSS), from the table area's size in container units: the larger of
 * "all pairs in one row" and "two rows", within the size limits. The bout area is the table area
 * less the piles (wide: deck on the left, discard on the right; phone: piles above).
 */
const boutCard = (wide: boolean, pairs: number) => {
  const n = Math.max(1, pairs)
  const [side, top] = wide ? [272, 24] : [16, 64]
  const fit = (perRow: number, rows: number) =>
    `min((100cqw - ${side + 12 * (perRow - 1)}px) / ${1.2 * perRow}, (100cqh - ${top + 8 * (rows - 1)}px) / ${1.83 * rows})`
  const best = `max(${fit(n, 1)}, ${fit(Math.ceil(n / 2), 2)})`
  return wide ? `clamp(56px, ${best}, 96px)` : `clamp(40px, ${best}, 64px)`
}

/** Your hand in a readable order: suits together, ranks ascending, trumps last. */
function sortHand(hand: string[], trump: Suit) {
  const key = (card: string) =>
    (card[1] === trump ? 100 : SUITS.indexOf(card[1] ?? 'S') * 10) + RANKS.indexOf(card[0] ?? '6')
  return [...hand].sort((a, b) => key(a) - key(b))
}

/**
 * Durak table, sized to fit the screen: the other players across the top (and at the sides on a
 * wide screen), the deck with the trump at one side and the discard pile at the other, the bout in
 * the middle, and at the bottom your status, your hand and one action button. Offers exactly the
 * server's legal actions: cards to lead, throw in or beat with, "take" and "done".
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
  const finished = state.result != null
  const wide = useMediaQuery('(min-width: 1024px)')
  const reduced = useMediaQuery('(prefers-reduced-motion: reduce)')

  const stage = useRef<HTMLDivElement>(null)
  const height = useFitHeight(stage, { min: 420 })
  const handList = useRef<HTMLUListElement>(null)
  const actionButton = useRef<HTMLButtonElement>(null)
  const tapped = useRef<{ card: string; rect: DOMRect } | null>(null)
  const justPlayed = useRef(false)

  const seats = view.hand_counts.length
  // The others in table order, starting with the player after you.
  const others = Array.from({ length: seats - 1 }, (_, i) => (me + 1 + i) % seats)
  const places = PLACES[others.length] ?? others.map((): Place => 'top')
  const nameOf = (seat: number) =>
    seat === me
      ? players[me] || t('poker.you')
      : players[seat] || t('durak.seat', { seat: seat + 1 })
  const loser = Object.entries(state.result?.seats ?? {}).find(([, r]) => r === 'loss')?.[0]

  const roleOf = (seat: number): Role | null => {
    if (finished)
      return String(seat) === loser
        ? { text: t('durak.role.durak'), bg: 'bg-take', fg: 'text-black' }
        : null
    if (view.left?.[seat])
      return { text: t('durak.role.left'), bg: 'bg-black/60', fg: 'text-white/80' }
    if (view.deck_count === 0 && (view.hand_counts[seat] ?? 0) === 0)
      return { text: t('durak.role.out'), bg: 'bg-beaten', fg: 'text-black' }
    if (seat === view.attacker) {
      const allBeaten = view.table.length > 0 && view.table.every((pair) => pair.defense)
      return view.passed?.includes(seat) && allBeaten
        ? { text: t('durak.done'), bg: 'bg-black/70', fg: 'text-white' }
        : { text: t('durak.role.attacker'), bg: 'bg-turn', fg: 'text-black' }
    }
    if (seat === view.defender)
      return view.taking
        ? { text: t('durak.role.taking'), bg: 'bg-take', fg: 'text-black' }
        : { text: t('durak.role.defender'), bg: 'bg-white', fg: 'text-black' }
    if (view.passed?.includes(seat))
      return { text: t('durak.role.passed'), bg: 'bg-black/70', fg: 'text-white' }
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

  const { ghost, ended } = useBoutMotion({
    version: state.version,
    bout: {
      table: view.table,
      handCounts: view.hand_counts,
      discarded: view.discarded ?? 0,
      defender: view.defender,
    },
    stageRef: stage,
    tappedRef: tapped,
    reduced,
  })

  const actionFor = (card: string) => actions.find((a) => 'card' in a && a.card === card)
  const action = actions.find((a) => a.type === 'take') ?? actions.find((a) => a.type === 'done')
  const actionLabel =
    action?.type === 'take'
      ? t('durak.take')
      : view.taking
        ? t('durak.letTake')
        : me === view.attacker
          ? t('durak.done')
          : t('durak.pass')

  const status = !myTurn
    ? view.taking && view.defender === me
      ? t('durak.youTake')
      : (waitingCaption(state, players, t) ?? '')
    : view.defender === me
      ? t('durak.defend')
      : view.table.length === 0
        ? t('durak.lead')
        : view.taking
          ? t('durak.throwInBeforeTake')
          : t('durak.throwIn')

  const play = (card: string, from: DOMRect) => {
    const chosen = actionFor(card)
    if (!chosen) return
    tapped.current = { card, rect: from }
    justPlayed.current = true
    onAction(chosen)
  }

  // After playing, keep the focus in the hand (the card's button is gone): the next playable card,
  // else the action button, else the hand itself.
  useEffect(() => {
    if (!justPlayed.current) return
    const active = document.activeElement
    if (active && active !== document.body && document.contains(active)) return
    justPlayed.current = false
    const next = handList.current?.querySelector<HTMLButtonElement>('button:not(:disabled)')
    ;(next ?? actionButton.current ?? handList.current)?.focus()
  }, [state.version])

  const seatProps = (other: number) => {
    const acting = !finished && state.seats_to_act.includes(other)
    const count = view.hand_counts[other] ?? 0
    const role = roleOf(other)
    const bot = bots[other] ? t('room.bot') : null
    return {
      key: other,
      seat: other,
      name: nameOf(other),
      count,
      role,
      acting,
      share: acting ? turnShare : null,
      bot,
      label: [t('durak.opponentCards', { name: nameOf(other), count }), bot, role?.text]
        .filter(Boolean)
        .join(', '),
    }
  }
  const placed = (place: Place) =>
    others
      .filter((_, i) => places[i] === place)
      .map((other) => {
        const { key, ...props } = seatProps(other)
        return <PanelSeat key={key} {...props} />
      })
  const hasLeft = wide && places.includes('left')
  const hasRight = wide && places.includes('right')
  const row = (middle: string) =>
    [hasLeft ? 'left' : null, middle, hasRight ? 'right' : null].filter(Boolean).join(' ')
  const grid: CSSProperties = wide
    ? {
        gridTemplateColumns: [
          hasLeft ? 'minmax(0,14rem)' : null,
          'minmax(0,1fr)',
          hasRight ? 'minmax(0,14rem)' : null,
        ]
          .filter(Boolean)
          .join(' '),
        gridTemplateRows: 'auto minmax(0,1fr) auto',
        gridTemplateAreas: `"${row('top')}" "${row('table')}" "${[hasLeft ? 'me' : null, 'me', hasRight ? 'me' : null].filter(Boolean).join(' ')}"`,
      }
    : {
        gridTemplateColumns: 'minmax(0,1fr)',
        gridTemplateRows: 'auto minmax(0,1fr) auto',
        gridTemplateAreas: '"top" "table" "me"',
      }

  const myRole = roleOf(me)
  const handCard = wide ? Math.max(60, Math.min(88, Math.round((height ?? 640) * 0.14))) : 64

  const pairs = (list: Pair[], ghostly: boolean) =>
    list.map((pair, i) => (
      <li
        key={`${pair.attack}-${i}`}
        aria-label={
          ghostly
            ? undefined
            : pair.defense
              ? `${cardLabel(pair.attack)}, ${t('durak.beatenBy', { card: cardLabel(pair.defense) })}`
              : t('durak.notBeaten', { card: cardLabel(pair.attack) })
        }
        className="relative"
        style={{ width: 'calc(var(--card-w) * 1.2)', height: 'calc(var(--card-w) * 1.83)' }}
      >
        <span
          className="absolute top-0 left-0"
          {...(ghostly ? { 'data-ghost-card': '' } : { 'data-card': pair.attack })}
        >
          <PlayingCard card={pair.attack} label={cardLabel(pair.attack)} size="fluid" />
        </span>
        {pair.defense ? (
          // The beating card lies across the attack, turned a little, so the attack's rank shows.
          <span
            className="absolute"
            style={{ left: 'calc(var(--card-w) * 0.2)', top: 'calc(var(--card-w) * 0.4)' }}
            {...(ghostly ? { 'data-ghost-card': '' } : { 'data-card': pair.defense })}
          >
            <span className="block rotate-6">
              <PlayingCard
                card={pair.defense}
                label={t('durak.beatenBy', { card: cardLabel(pair.defense) })}
                size="fluid"
              />
            </span>
          </span>
        ) : null}
      </li>
    ))

  return (
    <div
      ref={stage}
      className="table-rim rounded-[1.75rem] p-1.5 text-white sm:p-2.5"
      style={height ? { height } : undefined}
    >
      <div
        className="table-felt grid h-full gap-2 overflow-hidden rounded-[1.4rem] p-2 sm:rounded-[1.6rem] sm:p-3 lg:gap-4 lg:p-5"
        style={grid}
      >
        {wide ? (
          <>
            {hasLeft ? (
              <div style={{ gridArea: 'left' }} className="flex flex-col justify-center gap-8 pt-8">
                {placed('left')}
              </div>
            ) : null}
            <div
              style={{ gridArea: 'top' }}
              className="flex justify-center gap-6 pt-8 empty:hidden"
            >
              {placed('top')}
            </div>
            {hasRight ? (
              <div
                style={{ gridArea: 'right' }}
                className="flex flex-col justify-center gap-8 pt-8"
              >
                {placed('right')}
              </div>
            ) : null}
          </>
        ) : (
          <ul
            aria-label={t('durak.opponents')}
            // Bottom-aligned: a role bubble on two lines doesn't push its player down.
            className="grid items-end gap-1"
            style={{
              gridArea: 'top',
              gridTemplateColumns: `repeat(${Math.max(1, others.length)}, minmax(0, 1fr))`,
            }}
          >
            {others.map((other) => {
              const { key, ...props } = seatProps(other)
              return <CompactSeat key={key} {...props} />
            })}
          </ul>
        )}

        <div style={{ gridArea: 'table' }} className="[container-type:size] relative min-h-0">
          <div
            className={
              wide ? 'absolute top-1/2 left-2 z-10 -translate-y-1/2' : 'absolute top-1 left-1 z-10'
            }
          >
            <Deck
              count={view.deck_count}
              trump={view.trump}
              trumpCard={view.trump_card}
              wide={wide}
            />
          </div>
          <div
            data-discard=""
            className={
              wide
                ? 'absolute top-1/2 right-4 z-10 -translate-y-1/2'
                : 'absolute top-2 right-2 z-10'
            }
          >
            <Discard count={view.discarded ?? 0} wide={wide} />
          </div>
          <section
            aria-label={t('durak.table')}
            className={`absolute ${wide ? 'inset-y-3 right-24 left-44' : 'inset-x-2 top-14 bottom-2'}`}
            style={{
              ['--card-w' as string]: boutCard(
                wide,
                Math.max(view.table.length, ghost?.pairs.length ?? 0),
              ),
            }}
          >
            {view.table.length === 0 ? (
              <p className="sr-only">{t('durak.emptyTable')}</p>
            ) : (
              <ul className="flex h-full flex-wrap content-center justify-center gap-x-3 gap-y-2">
                {pairs(view.table, false)}
              </ul>
            )}
            {ghost ? (
              <ul
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 flex flex-wrap content-center justify-center gap-x-3 gap-y-2"
              >
                {pairs(ghost.pairs, true)}
              </ul>
            ) : null}
          </section>
        </div>

        <div style={{ gridArea: 'me' }} data-seat={me} className="flex min-w-0 flex-col gap-2">
          {finished ? null : (
            <div className="flex items-center gap-2">
              <Avatar
                name={nameOf(me)}
                acting={myTurn}
                share={myTurn ? turnShare : null}
                size="sm"
              />
              {myRole ? <Bubble role={myRole} /> : null}
              <p
                className="line-clamp-3 min-w-0 flex-1 rounded-lg bg-[#fdebc8] px-3 py-1.5 text-sm text-black shadow"
                aria-live="polite"
              >
                {status}
              </p>
            </div>
          )}
          <div className="flex flex-col gap-2 lg:flex-row lg:items-end lg:gap-6">
            <DurakHand
              cards={sortHand(view.hand, view.trump)}
              playable={(card) => !!actionFor(card)}
              onPlay={play}
              myTurn={myTurn}
              pending={pending}
              finished={finished}
              card={handCard}
              strip={wide ? 44 : 40}
              label={t('durak.yourHand')}
              cardLabel={cardLabel}
              listRef={handList}
            />
            {finished ? null : (
              // The row keeps its height while playing so the hand never jumps.
              <div className="min-h-12 lg:min-h-14 lg:w-56 lg:shrink-0">
                {action ? (
                  <Button
                    ref={actionButton}
                    variant="gold"
                    disabled={pending}
                    onClick={() => {
                      justPlayed.current = true
                      onAction({ type: action.type })
                    }}
                    className="min-h-12 w-full text-base lg:min-h-14"
                  >
                    {actionLabel}
                  </Button>
                ) : null}
              </div>
            )}
          </div>
        </div>
      </div>
      <p className="sr-only" role="status">
        {ended
          ? ended.to === 'discard'
            ? t('durak.bout.beaten')
            : ended.to === me
              ? t('durak.bout.youTook')
              : t('durak.bout.took', { name: nameOf(ended.to) })
          : ''}
      </p>
    </div>
  )
}
