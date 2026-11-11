import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import { suitSymbols, useCardLabel, type Suit } from '../cards/cards'
import { CardBack, PlayingCard } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'

interface Contract {
  seat: number
  team: number
  value: number
  suit: Suit
  doubled: 0 | 1 | 2
}

interface Declaration {
  kind: 'terz' | 'fifty' | 'hundred' | 'carre'
  cards: string[]
  points: number
}

export interface BlotView {
  seat: number | null
  dealer: number
  hand_no: number
  phase: 'bidding' | 'playing' | 'over'
  hand: string[]
  hand_sizes: number[]
  bids: {
    seat: number
    type: 'bid' | 'pass' | 'double' | 'redouble'
    value?: number
    suit?: Suit
  }[]
  contract: Contract | null
  trump: Suit | null
  to_act: number | null
  trick: { seat: number; card: string }[]
  last_trick: { cards: { seat: number; card: string }[]; winner: number; points: number } | null
  tricks: [number, number]
  points: [number, number]
  declarations: Declaration[][]
  declarations_shown: boolean
  belote: number | null
  scores: [number, number]
  target: number
  last_hand: {
    number: number
    contract: Contract
    made: boolean
    tricks: [number, number]
    declarations: [number, number]
    belote: [number, number]
    scored: [number, number]
    kaput: boolean
  } | null
}

type BlotAction =
  | { type: 'play'; card: string }
  | { type: 'pass' | 'double' | 'redouble' }
  | { type: 'bid'; min: number; max: number }

const SUITS: Suit[] = ['S', 'H', 'D', 'C']
/** Text presentation, so iOS never draws the suits as colour emoji. */
const suitText = (suit: Suit) => `${suitSymbols[suit]}︎`
const red = (suit: Suit) => suit === 'H' || suit === 'D'

// Display order only (alternating colours, highest first); the server decides what may be played.
const RANKS = 'AKQJT987'
const TRUMP_RANKS = 'J9ATKQ87'
const sortHand = (cards: string[], trump: Suit | null) =>
  [...cards].sort((a, b) => {
    const order = ['S', 'H', 'C', 'D']
    const suit = order.indexOf(a[1] ?? '') - order.indexOf(b[1] ?? '')
    if (suit !== 0) return suit
    const ranks = a[1] === trump ? TRUMP_RANKS : RANKS
    return ranks.indexOf(a[0] ?? '') - ranks.indexOf(b[0] ?? '')
  })

/**
 * Bazar blot for two partnerships: you at the bottom, your partner opposite, the opponents left and
 * right. The bazar (bids with a suit, pass, double, redouble), the trick on the felt, your hand (only the
 * server's playable cards can be played), the contract and the score to the target. After each hand the
 * table pauses on its result.
 */
export default function BlotBoard({
  state,
  onAction,
  pending,
  players = {},
  bots = {},
  levels = {},
}: BoardProps) {
  const { t, i18n } = useTranslation()
  const labels = useCardLabel()
  const view = state.view as unknown as BlotView
  const me = view.seat ?? 0
  const myTeam = me % 2
  const actions = state.legal_actions as unknown as BlotAction[]
  const bidRange = actions.find((a): a is Extract<BlotAction, { type: 'bid' }> => a.type === 'bid')
  const playable = new Set(
    actions.flatMap((a) => (a.type === 'play' ? [(a as { card: string }).card] : [])),
  )
  const has = (type: BlotAction['type']) => actions.some((a) => a.type === type)
  const [value, setValue] = useState<number | null>(null)
  const bidValue = bidRange
    ? Math.min(bidRange.max, Math.max(bidRange.min, value ?? bidRange.min))
    : 0
  const narrow = useMediaQuery('(max-width: 640px)')
  const format = (n: number) => n.toLocaleString(i18n.language)
  const name = (seat: number) =>
    seat === me ? t('blot.you') : players[seat] || t('blot.seat', { seat: seat + 1 })
  // Where a seat sits from your side of the table: 0 bottom (you), 1 left, 2 top (partner), 3 right.
  const place = (seat: number) => (seat - me + 4) % 4
  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const turnShare =
    secondsLeft !== null && state.turn_seconds
      ? Math.min(1, secondsLeft / state.turn_seconds)
      : null

  const send = (action: Parameters<BoardProps['onAction']>[0]) => {
    setValue(null)
    onAction(action)
  }
  const lastBid = (seat: number) => [...view.bids].reverse().find((b) => b.seat === seat)
  const bidText = (b: BlotView['bids'][number]) =>
    b.type === 'bid' && b.value && b.suit
      ? `${b.value} ${suitText(b.suit)}`
      : t(`blot.call.${b.type}` as 'blot.call.pass')
  // The trick on the felt; between tricks the last one stays until the next card is played.
  const showingLast =
    view.trick.length === 0 && view.last_trick !== null && view.phase !== 'bidding'
  const trick = showingLast ? (view.last_trick?.cards ?? []) : view.trick
  const contract = view.contract
  const hand = sortHand(view.hand, view.trump)
  const result = view.phase === 'over' || state.result ? view.last_hand : null
  const bidding = view.phase === 'bidding' && (!!bidRange || has('pass'))
  const teamName = (team: number) => (team === myTeam ? t('blot.us') : t('blot.them'))

  const status =
    view.phase === 'over' && !state.result
      ? secondsLeft !== null
        ? t('blot.nextHandIn', { count: secondsLeft })
        : t('blot.nextHand')
      : view.to_act === null
        ? ' '
        : view.phase === 'bidding'
          ? t('blot.waitingBid', { name: name(view.to_act) })
          : t('blot.waitingFor', { name: name(view.to_act) })

  return (
    <div className="space-y-4 text-white">
      <div className="poker-stage relative rounded-3xl p-2 min-[641px]:p-6">
        {/* The score, the contract and the hand number, above the table. */}
        <div className="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs min-[641px]:text-sm">
          <dl className="flex gap-2" aria-label={t('blot.score')}>
            {[myTeam, 1 - myTeam].map((team) => (
              <div
                key={team}
                className={`flex items-center gap-1.5 rounded-full border px-2.5 py-1 ${
                  team === myTeam ? 'border-gold/60 bg-black/50' : 'border-white/20 bg-black/40'
                }`}
              >
                <dt className="text-white/70">{teamName(team)}</dt>
                <dd className="font-extrabold text-gold-light tabular-nums">
                  {format(view.scores[team] ?? 0)}
                </dd>
              </div>
            ))}
            <div className="flex items-center gap-1 px-1 text-white/60">
              <dt>{t('blot.target')}</dt>
              <dd className="font-bold tabular-nums">{format(view.target)}</dd>
            </div>
          </dl>
          {contract ? (
            <p className="flex items-center gap-1.5 rounded-full border border-gold/40 bg-black/50 px-2.5 py-1">
              <span className="text-white/70">{t('blot.contract')}</span>
              <span
                className={`rounded bg-card px-1.5 font-extrabold ${red(contract.suit) ? 'text-suit-red' : 'text-suit-black'}`}
              >
                {contract.value} {suitText(contract.suit)}
              </span>
              <span className="font-semibold">{name(contract.seat)}</span>
              {contract.doubled > 0 ? (
                <span className="rounded-full bg-[#8e3a31] px-1.5 font-bold">
                  ×{contract.doubled === 2 ? 4 : 2}
                </span>
              ) : null}
            </p>
          ) : null}
        </div>

        <div className="relative mx-auto aspect-[1/1.08] w-full max-w-[min(40rem,calc(100dvh-25rem))] min-[641px]:aspect-[1.65/1] min-[641px]:max-w-[min(60rem,calc((100dvh-24rem)*1.65))]">
          <div aria-hidden="true" className="poker-rail absolute inset-0 rounded-[2.2rem]">
            <div className="blackjack-felt relative h-full w-full rounded-[1.9rem]" />
          </div>

          {/* The middle of the felt: the bazar, the trick (each card on its player's side), or the hand's result. */}
          {result ? (
            <div className="absolute inset-x-[18%] top-1/2 z-20 flex -translate-y-1/2 justify-center min-[641px]:inset-x-[26%]">
              <HandSummary result={result} myTeam={myTeam} name={name} format={format} />
            </div>
          ) : bidding ? (
            <div className="absolute inset-x-[24%] top-1/2 z-20 -translate-y-1/2 min-[641px]:inset-x-[25%]">
              {bidControls()}
            </div>
          ) : (
            <section
              aria-label={showingLast ? t('blot.lastTrick') : t('blot.trick')}
              className={`absolute top-1/2 left-1/2 size-[44%] -translate-x-1/2 -translate-y-1/2 ${showingLast ? 'opacity-70' : ''}`}
            >
              {trick.map(({ seat, card }) => {
                const spot = [
                  'bottom-0 left-1/2 -translate-x-1/2',
                  'left-0 top-1/2 -translate-y-1/2 -rotate-6',
                  'top-0 left-1/2 -translate-x-1/2',
                  'right-0 top-1/2 -translate-y-1/2 rotate-6',
                ][place(seat)]
                const won = showingLast && view.last_trick?.winner === seat
                return (
                  <span
                    key={`${view.hand_no}-${card}`}
                    className={`absolute ${spot} rounded-md motion-safe:animate-card-in ${won ? 'ring-2 ring-gold' : ''}`}
                  >
                    <PlayingCard
                      card={card}
                      label={labels.card(card)}
                      size={narrow ? 'sm' : 'md'}
                    />
                  </span>
                )
              })}
              {view.phase === 'bidding' ? (
                <p className="absolute inset-x-0 top-1/3 text-center text-sm font-extrabold tracking-[0.2em] text-gold-light/70 uppercase">
                  {t('blot.bazar')}
                </p>
              ) : null}
              {playable.size === 0 && view.trick.length === 0 ? (
                <p
                  className="absolute inset-x-0 top-1/2 mx-auto w-fit max-w-full -translate-y-1/2 rounded-full bg-black/55 px-3 py-1 text-center text-xs text-white/85"
                  aria-live="polite"
                >
                  {status}
                </p>
              ) : null}
            </section>
          )}

          {/* The four seats. */}
          <ul aria-label={t('blot.seats')}>
            {[0, 1, 2, 3].map((seat) => {
              const where = place(seat)
              const acting = view.to_act === seat
              const bid = view.phase === 'bidding' ? lastBid(seat) : undefined
              const declared = view.declarations[seat] ?? []
              const pos = [
                // Yours sits in the near corner, clear of your cards along the edge (above them on a phone).
                'bottom-[17%] left-[4%] min-[641px]:bottom-[4%]',
                'left-[3%] top-1/2 -translate-y-1/2',
                'top-[3%] left-1/2 -translate-x-1/2',
                'right-[3%] top-1/2 -translate-y-1/2',
              ][where]
              return (
                <li
                  key={seat}
                  aria-current={acting ? 'step' : undefined}
                  aria-label={`${name(seat)}, ${teamName(seat % 2)}${bots[seat] && seat !== me ? `, ${t('room.bot')}` : ''}`}
                  className={`absolute z-10 flex flex-col items-center gap-1 ${pos}`}
                >
                  {seat !== me ? (
                    <span aria-hidden="true" className="flex">
                      {Array.from({ length: Math.min(8, view.hand_sizes[seat] ?? 0) }, (_, i) => (
                        <CardBack
                          key={i}
                          size="xs"
                          className={i > 0 ? '-ml-7 min-[641px]:-ml-6' : ''}
                        />
                      ))}
                    </span>
                  ) : null}
                  <Plate
                    name={name(seat)}
                    ours={seat % 2 === myTeam}
                    acting={acting}
                    share={acting ? turnShare : null}
                    level={levels[seat]}
                    bot={seat !== me && !!bots[seat]}
                    botLabel={t('room.bot')}
                    dealer={view.dealer === seat}
                    dealerLabel={t('blot.dealer')}
                  />
                  {bid ? (
                    <span className="mt-1.5 rounded-full bg-card px-2 py-0.5 text-xs font-extrabold text-ink shadow motion-safe:animate-card-in">
                      {bidText(bid)}
                    </span>
                  ) : null}
                  {view.declarations_shown && declared.length > 0 ? (
                    <span className="flex flex-wrap justify-center gap-1">
                      {declared.map((d, i) => (
                        <span
                          key={i}
                          className="rounded-full bg-gold px-1.5 py-0.5 text-[0.65rem] font-extrabold text-black"
                        >
                          {t(`blot.declaration.${d.kind}`)} {d.points}
                        </span>
                      ))}
                    </span>
                  ) : null}
                  {view.belote === seat && view.declarations_shown ? (
                    <span className="rounded-full bg-white/85 px-1.5 py-0.5 text-[0.65rem] font-extrabold text-black">
                      {t('blot.belote')}
                    </span>
                  ) : null}
                </li>
              )
            })}
          </ul>
        </div>

        {/* Your hand, along the table's near edge. */}
        <section
          aria-label={t('blot.yourCards')}
          className="relative z-20 -mt-8 flex min-h-10 justify-center px-1 min-[641px]:-mt-12 min-[641px]:min-h-14"
        >
          <div className="flex">
            {hand.map((card, i) => {
              const canPlay = playable.has(card)
              return (
                <button
                  key={card}
                  type="button"
                  disabled={!canPlay || pending}
                  onClick={() => send({ type: 'play', card })}
                  aria-label={labels.card(card)}
                  className={`relative rounded-lg transition ${i > 0 ? '-ml-5 min-[641px]:-ml-6' : ''} ${
                    canPlay
                      ? 'z-10 -translate-y-1 focus-visible:-translate-y-3 motion-safe:hover:-translate-y-3'
                      : view.phase === 'playing' && view.to_act === me
                        ? 'brightness-50'
                        : ''
                  }`}
                >
                  <PlayingCard card={card} label={labels.card(card)} size={narrow ? 'md' : 'lg'} />
                </button>
              )
            })}
          </div>
        </section>
        {playable.size > 0 ? (
          <p className="mt-2 text-center text-sm font-semibold text-gold-light" aria-live="polite">
            {t('blot.yourTurn')}
          </p>
        ) : null}
      </div>
    </div>
  )

  /** The bazar on your turn: a value, then the suit button bids it; pass, contra, recontra. */
  function bidControls() {
    return (
      <div className="flex w-full flex-col items-center gap-3">
        {bidRange ? (
          <>
            <div className="flex items-center gap-3">
              <button
                type="button"
                aria-label={t('blot.lower')}
                disabled={bidValue <= bidRange.min}
                onClick={() => setValue(bidValue - 1)}
                className="size-11 rounded-full border border-gold/40 bg-black/30 text-xl font-bold disabled:opacity-40"
              >
                −
              </button>
              <span className="min-w-20 text-center" aria-live="polite">
                <span className="block text-2xl font-extrabold text-gold-light tabular-nums">
                  {bidValue}
                </span>
                <span className="block text-xs text-white/60">
                  {t('blot.points', { count: bidValue * 10 })}
                </span>
              </span>
              <button
                type="button"
                aria-label={t('blot.higher')}
                disabled={bidValue >= bidRange.max}
                onClick={() => setValue(bidValue + 1)}
                className="size-11 rounded-full border border-gold/40 bg-black/30 text-xl font-bold disabled:opacity-40"
              >
                +
              </button>
            </div>
            <div className="grid w-full max-w-md grid-cols-4 gap-1.5 min-[641px]:gap-2">
              {SUITS.map((suit) => (
                <button
                  key={suit}
                  type="button"
                  disabled={pending}
                  onClick={() => send({ type: 'bid', value: bidValue, suit })}
                  aria-label={t('blot.bidIn', { value: bidValue, suit: labels.suit(suit) })}
                  className={`rounded-xl border border-black/20 bg-card py-1.5 text-xl font-bold shadow-lg shadow-black/40 transition hover:brightness-95 active:translate-y-px disabled:opacity-60 min-[641px]:py-2.5 min-[641px]:text-2xl ${
                    red(suit) ? 'text-suit-red' : 'text-suit-black'
                  }`}
                >
                  {suitText(suit)}
                </button>
              ))}
            </div>
          </>
        ) : null}
        <div className="flex w-full max-w-md gap-2">
          {has('pass') ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'pass' })}
              className={`${actionButton} flex-1 border-white/20 bg-black/35 text-white`}
            >
              {t('blot.call.pass')}
            </button>
          ) : null}
          {has('double') ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'double' })}
              className={`${actionButton} flex-1 border-[#a8473c]/60 bg-linear-to-b from-[#8e3a31] to-[#5e211b] text-white`}
            >
              {t('blot.call.double')}
            </button>
          ) : null}
          {has('redouble') ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'redouble' })}
              className={`${actionButton} flex-1 border-gold/40 bg-linear-to-b from-gold-light to-gold text-black`}
            >
              {t('blot.call.redouble')}
            </button>
          ) : null}
        </div>
      </div>
    )
  }
}

/** The last hand: the contract made or not, and what each team scored. */
function HandSummary({
  result,
  myTeam,
  name,
  format,
}: {
  result: NonNullable<BlotView['last_hand']>
  myTeam: number
  name: (seat: number) => string
  format: (n: number) => string
}) {
  const { t } = useTranslation()
  const rows = [
    { label: t('blot.tricks'), values: result.tricks },
    { label: t('blot.declarations'), values: result.declarations },
    { label: t('blot.belote'), values: result.belote },
    { label: t('blot.scored'), values: result.scored, strong: true },
  ]
  const won = (result.scored[myTeam] ?? 0) > (result.scored[1 - myTeam] ?? 0)
  return (
    <section
      aria-label={t('blot.handResult')}
      className={`w-full max-w-md rounded-2xl border p-3 text-sm motion-safe:animate-rise ${won ? 'border-gold bg-black/40 shadow-[0_0_24px_rgb(214_171_78/0.35)]' : 'border-white/15 bg-black/30'}`}
    >
      <p className="mb-2 text-center font-bold" role="status">
        {result.made
          ? t('blot.made', { name: name(result.contract.seat), value: result.contract.value })
          : t('blot.failed', { name: name(result.contract.seat), value: result.contract.value })}
        {result.kaput ? ` · ${t('blot.kaput')}` : ''}
      </p>
      <table className="w-full text-center tabular-nums">
        <thead>
          <tr className="text-xs text-white/60">
            <th scope="col" className="text-left font-normal">
              <span className="sr-only">{t('blot.score')}</span>
            </th>
            <th scope="col" className="font-semibold text-gold-light">
              {t('blot.us')}
            </th>
            <th scope="col" className="font-semibold">
              {t('blot.them')}
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr
              key={row.label}
              className={row.strong ? 'border-t border-white/15 font-extrabold' : ''}
            >
              <th scope="row" className="py-0.5 text-left font-normal text-white/75">
                {row.label}
              </th>
              <td>{format(row.values[myTeam] ?? 0)}</td>
              <td>{format(row.values[1 - myTeam] ?? 0)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  )
}

/** A player's nameplate: their team's colour, the turn ring, level, dealer chip and bot pill. */
function Plate({
  name,
  ours,
  acting,
  share,
  level,
  bot,
  botLabel,
  dealer,
  dealerLabel,
}: {
  name: string
  ours: boolean
  acting: boolean
  share: number | null
  level?: number
  bot: boolean
  botLabel: string
  dealer: boolean
  dealerLabel: string
}) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : ours
          ? 'var(--color-gold)'
          : 'rgb(255 255 255 / 0.3)'
  return (
    <span
      className="relative block rounded-lg p-[2px] shadow-lg shadow-black/50"
      style={{ background: ring }}
    >
      <span className="block max-w-28 min-w-16 truncate rounded-[0.4rem] bg-[#1c120d]/95 px-2 py-1 text-center text-xs font-semibold min-[641px]:min-w-24 min-[641px]:text-sm">
        {name}
      </span>
      {level !== undefined ? (
        <LevelBadge level={level} size="sm" className="absolute -top-2 -left-2" />
      ) : null}
      {dealer ? (
        <span
          title={dealerLabel}
          className="absolute -top-2 -right-2 flex size-5 items-center justify-center rounded-full border-2 border-[#c9c2b0] bg-white text-[0.6rem] font-extrabold text-black"
        >
          D
        </span>
      ) : null}
      {bot ? (
        <span className="absolute -bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-black/70 px-1.5 py-0.5 text-[0.6rem] leading-none font-semibold tracking-wide text-white/85 uppercase">
          {botLabel}
        </span>
      ) : null}
    </span>
  )
}

const actionButton =
  'rounded-xl border px-3 py-3 text-sm font-bold shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-60 min-[641px]:text-base'
