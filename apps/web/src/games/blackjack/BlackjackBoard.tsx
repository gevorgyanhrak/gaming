import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { walletKey } from '@/features/wallet/api'
import { flyFishkas } from '@/features/wallet/flyFishkas'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import { useCardLabel } from '../cards/cards'
import { CardBack, PlayingCard } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'

type HandResult = 'win' | 'lose' | 'push' | 'blackjack'

interface Hand {
  cards: string[]
  bet: number
  doubled: boolean
  split: boolean
  status: 'playing' | 'stood' | 'bust' | 'blackjack'
  result: HandResult | null
  payout: number
  total: number
  soft: boolean
}

export interface BlackjackView {
  stacks: number[]
  start: number[]
  out: boolean[]
  round: number
  rounds: number
  limits: [number, number]
  h17: boolean
  /** "over": the pause after a round, showing its result until the next one. */
  phase: 'betting' | 'playing' | 'over'
  bets: number[]
  hands: Hand[][]
  /** The hole card is null while it is face down. */
  dealer: (string | null)[]
  dealer_total: number | null
  to_act: number | null
  active: number
  last_round: { number: number; dealer: string[]; dealer_total: number; net: number[] } | null
}

type BlackjackAction =
  { type: 'hit' | 'stand' | 'double' | 'split' } | { type: 'bet'; min: number; max: number }

/**
 * Blackjack: the dealer at the top of a half-moon table, the players' boxes round its edge. The board
 * shows the server's view and offers its legal actions (bet within the range, hit, stand, double,
 * split); totals and results come from the server. After a round the table pauses on the result.
 */
export default function BlackjackBoard({
  state,
  onAction,
  pending,
  players = {},
  bots = {},
  levels = {},
}: BoardProps) {
  const { t, i18n } = useTranslation()
  const labels = useCardLabel()
  const view = state.view as unknown as BlackjackView
  const me = state.seat
  const actions = state.legal_actions as unknown as BlackjackAction[]
  const bet = actions.find((a): a is Extract<BlackjackAction, { type: 'bet' }> => a.type === 'bet')
  const has = (type: BlackjackAction['type']) => actions.some((a) => a.type === type)
  const narrow = useMediaQuery('(max-width: 900px)')
  const format = (n: number) => n.toLocaleString(i18n.language)
  const name = (seat: number) =>
    seat === me ? t('blackjack.you') : players[seat] || t('blackjack.seat', { seat: seat + 1 })

  // The bet being built with chips; it starts at the last bet (or the minimum) each round.
  const myHands = me === null ? [] : (view.hands[me] ?? [])
  const [lastBet, setLastBet] = useState<number | null>(null)
  const [amount, setAmount] = useState<number | null>(null)
  const betAmount = bet ? Math.min(bet.max, Math.max(bet.min, amount ?? lastBet ?? bet.min)) : 0
  const chipValues = bet
    ? [1, 5, 25, 100].map((x) => bet.min * x).filter((v, i) => i === 0 || v <= bet.max)
    : []

  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const turnShare =
    secondsLeft !== null && state.turn_seconds
      ? Math.min(1, secondsLeft / state.turn_seconds)
      : null

  // A round that ends while you watch: your chips at the table changed, so refresh the header
  // balance (it includes them); a win flies to it first. Once per round, never for one already over.
  const [roundOnLoad] = useState(() => view.last_round?.number ?? 0)
  const last = view.last_round
  const showResult = !!last && last.number === view.round && view.phase !== 'betting'
  const fresh = showResult && last.number > roundOnLoad
  const myNet = me !== null && showResult ? (last.net[me] ?? 0) : null
  const queryClient = useQueryClient()
  const endedRound = fresh ? last.number : null
  const iWon = fresh && myNet !== null && myNet > 0
  useEffect(() => {
    if (endedRound === null) return
    const refresh = () => void queryClient.invalidateQueries({ queryKey: walletKey })
    if (iWon) {
      const timer = window.setTimeout(
        () => flyFishkas(document.querySelector('[data-blackjack-me]'), 'in', refresh),
        700,
      )
      return () => window.clearTimeout(timer)
    }
    refresh()
  }, [endedRound, iWon, queryClient])

  const seats = view.stacks.length
  // Round the curved edge of the table, first seat on the left; percentages of the table box. The
  // curve's centre is 40% down (the bottom 60% of the table is the half-ellipse); the tall phone table
  // puts the seats a little lower, clear of the lettering under the dealer. A full phone table spaces
  // the seats evenly across (on the curve), so neighbours never overlap.
  const spot = (seat: number): CSSProperties => {
    const [cy, rx, ry] = narrow ? [47, 37, 42] : [40, 40, 50]
    if (narrow && seats >= 4) {
      const x = 12 + (seat * 76) / (seats - 1)
      const dx = (x - 50) / 44
      return { left: `${x}%`, top: `${cy + ry * Math.sqrt(1 - dx * dx)}%` }
    }
    const [from, to] = narrow ? [158, 22] : [152, 28]
    const angle = seats === 1 ? 90 : from - (seat * (from - to)) / (seats - 1)
    const rad = (angle * Math.PI) / 180
    return { left: `${50 + rx * Math.cos(rad)}%`, top: `${cy + ry * Math.sin(rad)}%` }
  }

  const send = (action: Parameters<BoardProps['onAction']>[0]) => {
    if (action.type === 'bet' && typeof action.amount === 'number') setLastBet(action.amount)
    setAmount(null)
    onAction(action)
  }
  const hand = myHands[view.active]
  const status =
    view.phase === 'over' && !state.result
      ? secondsLeft !== null
        ? t('blackjack.nextRoundIn', { count: secondsLeft })
        : t('blackjack.nextRound')
      : view.to_act === null
        ? state.result
          ? t('blackjack.tableClosed')
          : t('blackjack.dealerPlays')
        : view.phase === 'betting'
          ? t('blackjack.waitingBet', { name: name(view.to_act) })
          : t('blackjack.waitingFor', { name: name(view.to_act) })

  return (
    <div className="space-y-5 text-white">
      <div className="poker-stage relative rounded-3xl px-3 pt-4 pb-12 min-[901px]:px-10 min-[901px]:pt-6">
        <div className="relative mx-auto aspect-[3/4.4] w-full max-w-[max(18rem,min(32rem,calc((100dvh-24rem)*0.68)))] min-[901px]:aspect-[2/1] min-[901px]:max-w-[min(62rem,calc((100dvh-23rem)*2))]">
          <div
            aria-hidden="true"
            className="poker-rail absolute inset-0 rounded-t-[1.5rem] rounded-b-[50%_60%]"
          >
            <div className="blackjack-felt relative h-full w-full overflow-hidden rounded-t-[1.2rem] rounded-b-[50%_60%]">
              {/* The lettering printed on the felt, round the arc under the dealer. */}
              <div className="absolute inset-x-0 top-[24%] text-center min-[901px]:top-[34%]">
                <p className="font-display text-sm font-extrabold tracking-[0.25em] text-gold-light/75 uppercase min-[901px]:text-xl">
                  {t('blackjack.pays')}
                </p>
                <p className="mt-0.5 text-[0.6rem] font-semibold tracking-[0.18em] text-white/45 uppercase min-[901px]:text-xs">
                  {view.h17 ? t('blackjack.hitsSoft17') : t('blackjack.standsSoft17')}
                </p>
              </div>
            </div>
          </div>

          {/* The dealer. */}
          <section
            aria-label={t('blackjack.dealer')}
            className="absolute top-[3%] left-1/2 flex -translate-x-1/2 flex-col items-center gap-1"
          >
            <span className="text-[0.65rem] font-extrabold tracking-[0.2em] text-gold-light/85 uppercase min-[901px]:text-xs">
              {t('blackjack.dealer')}
            </span>
            <div className="flex min-h-[50px] min-[901px]:min-h-24">
              {view.dealer.map((card, i) => (
                <span
                  key={`${i}-${card ?? 'hole'}`}
                  className={`${i > 0 ? '-ml-5 min-[901px]:-ml-8' : ''} motion-safe:animate-card-in`}
                >
                  {card === null ? (
                    <CardBack size={narrow ? 'sm' : 'md'} />
                  ) : (
                    <PlayingCard
                      card={card}
                      label={labels.card(card)}
                      size={narrow ? 'sm' : 'md'}
                    />
                  )}
                </span>
              ))}
            </div>
            {view.dealer_total !== null ? (
              <Total
                value={view.dealer_total}
                bust={view.dealer_total > 21}
                label={t('blackjack.dealerTotal', { total: view.dealer_total })}
              />
            ) : null}
          </section>

          <ul aria-label={t('blackjack.seats')}>
            {view.stacks.map((stack, seat) => {
              const hands = view.hands[seat] ?? []
              const acting = view.to_act === seat
              const net = showResult ? (last.net[seat] ?? 0) : 0
              const pendingBet = view.bets[seat] ?? 0
              return (
                <li
                  key={seat}
                  aria-current={acting ? 'step' : undefined}
                  data-blackjack-me={seat === me ? '' : undefined}
                  aria-label={`${name(seat)}, ${t('blackjack.chips', { count: stack })}${
                    seat !== me && bots[seat] ? `, ${t('room.bot')}` : ''
                  }`}
                  className={`absolute flex w-[18%] -translate-x-1/2 -translate-y-full flex-col items-center gap-1 min-[901px]:w-[17%] ${
                    view.out[seat] ? 'opacity-45' : ''
                  }`}
                  // Outer seats sit higher on the curve: draw them over the inner ones, so their
                  // result shows above a neighbour's cards.
                  style={{
                    ...spot(seat),
                    zIndex: 10 + Math.round(Math.abs(seat - (seats - 1) / 2) * 2),
                  }}
                >
                  {/* Each hand's cards with its total laid over their foot. */}
                  <div className="flex min-h-12 items-end justify-center gap-1.5 pb-2 min-[901px]:min-h-20">
                    {hands.map((h, i) => (
                      <span key={i} className="relative">
                        <HandCards
                          hand={h}
                          active={acting && view.phase === 'playing' && view.active === i}
                          small={narrow || hands.length > 1}
                          label={labels.card}
                        />
                        <span className="absolute inset-x-0 -bottom-2 z-10 flex justify-center">
                          <HandBadge hand={h} />
                        </span>
                      </span>
                    ))}
                  </div>
                  <div className="relative">
                    <SeatPlate
                      name={name(seat)}
                      stack={format(stack)}
                      acting={acting}
                      share={acting ? turnShare : null}
                      level={levels[seat]}
                      bot={seat !== me && !!bots[seat]}
                      botLabel={t('room.bot')}
                      mine={seat === me}
                    />
                    {pendingBet > 0 || hands.length > 0 ? (
                      // The bet, as a chip on the nameplate's corner.
                      <span className="absolute -top-3 -right-4 z-10 flex items-center gap-0.5 rounded-full bg-black/75 py-0.5 pr-1.5 pl-0.5 text-[0.65rem] font-bold tabular-nums motion-safe:animate-card-in min-[901px]:-right-6 min-[901px]:text-xs">
                        <Chip />
                        <span className="sr-only">{t('blackjack.betLabel')} </span>
                        {format(pendingBet + hands.reduce((sum, h) => sum + h.bet, 0))}
                      </span>
                    ) : null}
                    {showResult && hands.length > 0 ? (
                      <span
                        className={`absolute top-full left-1/2 z-10 mt-2.5 -translate-x-1/2 rounded-full px-2 py-0.5 text-xs font-extrabold whitespace-nowrap tabular-nums ${
                          net > 0
                            ? 'bg-gold text-black shadow-[0_0_14px_rgb(214_171_78/0.7)]'
                            : net < 0
                              ? 'bg-black/70 text-white/80'
                              : 'bg-white/85 text-black'
                        } ${fresh ? 'motion-safe:animate-rise' : ''}`}
                      >
                        {net > 0
                          ? `+${format(net)}`
                          : net < 0
                            ? `−${format(-net)}`
                            : t('blackjack.push')}
                      </span>
                    ) : null}
                  </div>
                </li>
              )
            })}
          </ul>
        </div>

        <dl className="absolute bottom-2 left-2 rounded-lg border border-gold/25 bg-black/60 px-2 py-1 text-[0.7rem] min-[901px]:bottom-3 min-[901px]:left-4 min-[901px]:rounded-xl min-[901px]:px-3 min-[901px]:py-2 min-[901px]:text-xs">
          <div className="flex gap-1.5">
            <dt className="text-white/60">{t('blackjack.round')}</dt>
            <dd className="font-bold tabular-nums">
              {view.round} / {view.rounds}
            </dd>
          </div>
          <div className="flex gap-1.5">
            <dt className="text-white/60">{t('blackjack.limits')}</dt>
            <dd className="font-bold text-gold-light tabular-nums">
              {format(view.limits[0])}–{format(view.limits[1])}
            </dd>
          </div>
        </dl>
        <p className="sr-only" role="status">
          {fresh && myNet !== null
            ? myNet > 0
              ? t('blackjack.youWon', { amount: format(myNet) })
              : myNet < 0
                ? t('blackjack.youLost', { amount: format(-myNet) })
                : t('blackjack.push')
            : ''}
        </p>
      </div>

      <div className="club-panel mx-auto flex max-w-4xl flex-col items-center gap-3 rounded-3xl p-4 min-[901px]:p-5">
        {bet ? (
          <div className="flex w-full flex-col items-center gap-3">
            <p className="text-sm font-semibold text-gold-light">{t('blackjack.placeBet')}</p>
            <div
              className="flex flex-wrap justify-center gap-2"
              role="group"
              aria-label={t('blackjack.chipsLabel')}
            >
              {chipValues.map((value, i) => (
                <button
                  key={value}
                  type="button"
                  onClick={() => setAmount(value)}
                  aria-pressed={betAmount === value}
                  className={`flex size-14 items-center justify-center rounded-full border-4 border-dashed text-xs font-extrabold tabular-nums shadow-lg shadow-black/50 transition active:translate-y-px motion-safe:hover:-translate-y-0.5 ${chipColours[i % chipColours.length]} ${
                    betAmount === value
                      ? 'ring-2 ring-gold ring-offset-2 ring-offset-[#1c120d]'
                      : ''
                  }`}
                >
                  {format(value)}
                </button>
              ))}
            </div>
            {bet.max > bet.min ? (
              <input
                type="range"
                min={bet.min}
                max={bet.max}
                step={1}
                value={betAmount}
                onChange={(e) => setAmount(Number(e.target.value))}
                aria-label={t('blackjack.amount')}
                className="h-11 w-full max-w-md accent-[var(--color-gold)]"
              />
            ) : null}
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'bet', amount: betAmount })}
              className={`${actionButton} w-full max-w-md border-white/20 bg-linear-to-b from-accent-light to-accent text-white`}
            >
              {t('blackjack.deal', { amount: format(betAmount) })}
            </button>
          </div>
        ) : actions.length > 0 && hand ? (
          <div className="grid w-full max-w-2xl grid-cols-2 gap-2 min-[560px]:grid-cols-4">
            {has('hit') ? (
              <button
                type="button"
                disabled={pending}
                onClick={() => send({ type: 'hit' })}
                className={`${actionButton} border-white/20 bg-linear-to-b from-accent-light to-accent text-white`}
              >
                {t('blackjack.hit')}
              </button>
            ) : null}
            {has('stand') ? (
              <button
                type="button"
                disabled={pending}
                onClick={() => send({ type: 'stand' })}
                className={`${actionButton} border-[#a8473c]/60 bg-linear-to-b from-[#8e3a31] to-[#5e211b] text-white`}
              >
                {t('blackjack.stand')}
              </button>
            ) : null}
            {has('double') ? (
              <button
                type="button"
                disabled={pending}
                onClick={() => send({ type: 'double' })}
                className={`${actionButton} border-gold/40 bg-linear-to-b from-gold-light to-gold text-black`}
              >
                {t('blackjack.double', { amount: format(hand.bet) })}
              </button>
            ) : null}
            {has('split') ? (
              <button
                type="button"
                disabled={pending}
                onClick={() => send({ type: 'split' })}
                className={`${actionButton} border-gold/40 bg-linear-to-b from-gold-light to-gold text-black`}
              >
                {t('blackjack.split')}
              </button>
            ) : null}
            <p className="col-span-full text-center text-sm text-white/75" aria-live="polite">
              {myHands.length > 1
                ? t('blackjack.playingHand', {
                    hand: view.active + 1,
                    count: myHands.length,
                    total: hand.total,
                  })
                : t('blackjack.yourTotal', { total: hand.total })}
            </p>
          </div>
        ) : (
          <p
            className="w-full rounded-md bg-ivory-100 px-3 py-2 text-center text-sm text-ink"
            aria-live="polite"
          >
            {status}
          </p>
        )}
      </div>
    </div>
  )
}

/** One hand's cards, fanned; the hand being played is lifted and outlined. */
function HandCards({
  hand,
  active,
  small,
  label,
}: {
  hand: Hand
  active: boolean
  small: boolean
  label: (card: string) => string
}) {
  // Each card shows a strip of the one under it; the strips narrow as the hand grows, so a long hand
  // stays about two cards wide (card widths: xs 32 px, sm 44 px).
  const [width, strip, span] = small ? [32, 16, 36] : [44, 22, 60]
  const overlap = -(width - Math.min(strip, span / Math.max(1, hand.cards.length - 1)))
  return (
    <span
      className={`flex rounded-md transition-transform ${
        active
          ? 'ring-2 ring-[var(--color-turn)] ring-offset-2 ring-offset-transparent motion-safe:-translate-y-1'
          : ''
      } ${hand.status === 'bust' ? 'opacity-60' : ''}`}
    >
      {hand.cards.map((card, i) => (
        <span
          key={`${i}-${card}`}
          className={`${hand.doubled && i === 2 ? 'rotate-90' : ''} motion-safe:animate-card-in`}
          style={i > 0 ? { marginLeft: overlap } : undefined}
        >
          <PlayingCard card={card} label={label(card)} size={small ? 'xs' : 'sm'} />
        </span>
      ))}
    </span>
  )
}

/** A hand's total, or what became of it. */
function HandBadge({ hand }: { hand: Hand }) {
  const { t } = useTranslation()
  const text =
    hand.result === 'blackjack' || hand.status === 'blackjack'
      ? t('blackjack.blackjack')
      : hand.status === 'bust'
        ? t('blackjack.bust')
        : hand.soft
          ? `${hand.total - 10}/${hand.total}`
          : String(hand.total)
  const tone =
    hand.status === 'blackjack' || hand.result === 'blackjack' || hand.result === 'win'
      ? 'bg-gold text-black'
      : hand.status === 'bust' || hand.result === 'lose'
        ? 'bg-[#8e3a31] text-white'
        : 'bg-black/70 text-white'
  return (
    <span
      className={`rounded-full px-1.5 py-0.5 text-[0.65rem] leading-none font-extrabold whitespace-nowrap tabular-nums min-[901px]:text-xs ${tone}`}
    >
      {text}
    </span>
  )
}

function Total({ value, bust, label }: { value: number; bust: boolean; label: string }) {
  return (
    <span
      aria-label={label}
      className={`rounded-full px-2 py-0.5 text-xs font-extrabold tabular-nums ${bust ? 'bg-[#8e3a31] text-white' : 'bg-black/70 text-white'}`}
    >
      {value}
    </span>
  )
}

/** The player's name and chips, with a ring showing the turn's time left. */
function SeatPlate({
  name,
  stack,
  acting,
  share,
  level,
  bot,
  botLabel,
  mine,
}: {
  name: string
  stack: string
  acting: boolean
  share: number | null
  level?: number
  bot: boolean
  botLabel: string
  mine: boolean
}) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : mine
          ? 'var(--color-gold)'
          : 'rgb(214 171 78 / 0.35)'
  return (
    <span
      className="relative block rounded-lg p-[2px] shadow-lg shadow-black/50"
      style={{ background: ring }}
    >
      <span className="block min-w-16 rounded-[0.4rem] bg-[#1c120d]/95 px-2 py-0.5 text-center min-[901px]:min-w-24">
        <span className="block max-w-24 truncate text-[0.7rem] font-semibold min-[901px]:text-sm">
          {name}
        </span>
        <span className="block text-[0.7rem] font-bold text-gold tabular-nums min-[901px]:text-xs">
          {stack}
        </span>
      </span>
      {level !== undefined ? (
        <LevelBadge level={level} size="sm" className="absolute -top-2 -left-2" />
      ) : null}
      {bot ? (
        <span className="absolute -bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-black/70 px-1.5 py-0.5 text-[0.6rem] leading-none font-semibold tracking-wide text-white/85 uppercase">
          {botLabel}
        </span>
      ) : null}
    </span>
  )
}

function Chip() {
  return (
    <span
      aria-hidden="true"
      className="block size-4 rounded-full border-2 border-dashed border-white/85 bg-[#c62f3a] shadow"
    />
  )
}

const chipColours = [
  'border-white/80 bg-[#e6e1d3] text-black',
  'border-white/80 bg-[#c62f3a] text-white',
  'border-white/80 bg-[#1f8a4c] text-white',
  'border-white/80 bg-[#22232a] text-gold-light',
]

const actionButton =
  'rounded-xl border px-3 py-3 text-sm font-bold shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-60 min-[901px]:text-base'
