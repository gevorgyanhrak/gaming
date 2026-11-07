import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { walletKey } from '@/features/wallet/api'
import { flyFishkas } from '@/features/wallet/flyFishkas'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { useCardLabel } from '../cards/cards'
import { CardBack, PlayingCard } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'
import { ChipFlight, ResultPlate } from './HandResult'
import { handKeys, summarize, type LastHand } from './result'

interface PokerView {
  stacks: number[]
  out: boolean[]
  button: number
  hand: number
  blinds: [number, number]
  board: string[]
  /** "over": the pause after a hand, showing its result until the next deal. */
  street: 'preflop' | 'flop' | 'turn' | 'river' | 'over'
  bets: number[]
  folded: boolean[]
  current_bet: number
  to_act: number | null
  hand_cards: string[]
  pot: number
  last_hand: LastHand | null
}

type PokerAction = { type: 'fold' | 'check' | 'call' } | { type: 'raise'; min: number; max: number }

/**
 * Hold'em table: seats with stacks and bets, the board and pot, your hole cards, and the server's
 * legal actions (fold / check / call, and raises anywhere in the allowed range). Play chips only.
 *
 * When a hand ends the server pauses before the next deal; the table then shows the result: who won
 * how much and with what, the shown cards, chips sliding to the winners. Only a hand that ends while
 * the page is open animates (not one already over on load).
 */
export default function PokerBoard({
  state,
  onAction,
  pending,
  players = {},
  bots = {},
}: BoardProps) {
  const { t, i18n } = useTranslation()
  const labels = useCardLabel()
  const view = state.view as unknown as PokerView
  const me = state.seat ?? 0
  const actions = state.legal_actions as unknown as PokerAction[]
  const raise = actions.find(
    (a): a is Extract<PokerAction, { type: 'raise' }> => a.type === 'raise',
  )
  const [amount, setAmount] = useState<number | null>(null)
  const [handOnLoad] = useState(() => view.last_hand?.number ?? 0)

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

  const last = view.last_hand
  const result =
    last && (view.street === 'over' || state.result) ? summarize(last, state.seat, name, t) : null
  const fresh = !!result && !!last && last.number > handOnLoad

  // A hand that ends while you watch changes your chips at the table: refresh the header balance
  // (it includes them). If you won, your winnings fly from your seat to it first. Once per hand.
  const queryClient = useQueryClient()
  const endedHand = fresh ? last.number : null
  const iWon = fresh && result.mine
  useEffect(() => {
    if (endedHand === null) return
    const refresh = () => void queryClient.invalidateQueries({ queryKey: walletKey })
    if (iWon) {
      const timer = window.setTimeout(
        () => flyFishkas(document.querySelector('[data-poker-me]'), 'in', refresh),
        1100,
      )
      return () => window.clearTimeout(timer)
    }
    refresh()
  }, [endedHand, iWon, queryClient])
  const boardCards = result && last ? last.board : view.board

  const seats = view.stacks.length
  // Round the oval from your seat at the bottom; x and y in percent of the table (radii may differ).
  const spot = (seat: number, radius: number, radiusY = radius) => {
    const angle = Math.PI / 2 + ((seat - me) * 2 * Math.PI) / seats
    return { left: `${50 + radius * Math.cos(angle)}%`, top: `${50 + radiusY * Math.sin(angle)}%` }
  }
  // Wide tables: bets sit on the felt between a seat and the middle. The tall phone oval has no
  // room for that next to the board, so there a bet sits at its own seat, on the side facing the
  // middle: under the top seat and seats level with the board, above yours, beside the others.
  const narrow = useMediaQuery('(max-width: 639px)')
  // The dealer's tray sits at the top of the felt, unless a player's seat is there.
  const dealerFree = view.stacks.every((_, seat) => {
    const angle = (Math.PI / 2 + ((seat - me) * 2 * Math.PI) / seats) % (2 * Math.PI)
    return Math.abs(angle - (3 * Math.PI) / 2) > Math.PI / 7
  })
  const betBeside = (seat: number) => {
    const angle = Math.PI / 2 + ((seat - me) * 2 * Math.PI) / seats
    const [x, y] = [Math.cos(angle), Math.sin(angle)]
    // Seats across the top: under the nameplate. Seats level with the board (4 seats) are at the
    // screen's edge: under the nameplate, lined up towards the middle so the bet stays on screen.
    if (y < -0.7) return 'top-full mt-1 left-1/2 -translate-x-1/2'
    if (Math.abs(y) < 0.3) return `top-full mt-1 ${x < 0 ? 'left-0' : 'right-0'}`
    if (y > 0.7) return 'bottom-full mb-1 left-1/2 -translate-x-1/2'
    return `${x < 0 ? 'left-full ml-1' : 'right-full mr-1'} top-1/2 -translate-y-1/2`
  }

  return (
    <div className="space-y-5 text-white">
      {/* The room: a dark patterned stage, the table on it, the table's info in the corner. */}
      <div className="poker-stage relative rounded-3xl px-7 pt-14 pb-12 sm:px-14 sm:pt-[4.5rem] sm:pb-12">
        <div className="relative mx-auto aspect-[3/4] w-full max-w-5xl sm:aspect-[2.45/1] sm:max-w-[min(64rem,calc((100dvh-25.5rem)*2.45))]">
          <div aria-hidden="true" className="poker-rail absolute inset-0 rounded-[50%]">
            <div className="poker-felt relative h-full w-full overflow-hidden rounded-[50%]">
              <SpadeMark />
              {dealerFree ? <DealerTray label={t('poker.dealer')} /> : null}
            </div>
          </div>

          <section
            aria-label={t('poker.board')}
            className="absolute inset-x-[5%] top-[38%] flex flex-col items-center gap-2 text-center sm:inset-x-[12%] sm:top-1/2 sm:-translate-y-1/2 sm:gap-3"
          >
            <div className="flex justify-center gap-1 sm:gap-2 max-sm:[&>span>span]:h-[50px] max-sm:[&>span>span]:w-9 max-sm:[&>span>span]:rounded-md">
              {boardCards.map((card) => (
                <span key={card} className="motion-safe:animate-card-in">
                  <PlayingCard card={card} label={labels.card(card)} />
                </span>
              ))}
              {Array.from({ length: result ? 0 : 5 - boardCards.length }, (_, i) => (
                <span key={i} aria-hidden="true">
                  <span className="block h-[50px] w-9 rounded-md border border-dashed border-white/25 bg-black/10 sm:h-24 sm:w-16 sm:rounded-lg" />
                </span>
              ))}
            </div>
            {result ? (
              <ResultPlate summary={result} />
            ) : (
              <p className="flex items-center gap-2 rounded-full bg-black/45 px-4 py-1.5 text-sm font-bold sm:text-base">
                <ChipStack amount={view.pot} />
                {t('poker.potAmount', { count: view.pot })}
              </p>
            )}
          </section>
          <p className="sr-only" role="status">
            {fresh ? result.announcement : ''}
          </p>

          {fresh && last
            ? [...result.winners].map(([seat, chips], i) => (
                <ChipFlight
                  key={`${last.number}-${seat}`}
                  to={spot(seat, 42)}
                  delay={250 + i * 150}
                >
                  <ChipStack amount={chips ?? last.pots[0]?.amount ?? 0} />
                </ChipFlight>
              ))
            : null}

          <ul aria-label={t('poker.seats')}>
            {view.stacks.map((stack, seat) => {
              const acting = view.to_act === seat
              const gone = view.out[seat] || view.folded[seat]
              const won = result?.winners.has(seat) ?? false
              const gain = result?.winners.get(seat)
              const shown = result ? last?.shown[String(seat)] : undefined
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
                  data-poker-me={seat === me ? '' : undefined}
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
                    {bet > 0 && narrow ? (
                      // On a phone the chip says "bet": only the amount is written (long words
                      // in ru/hy ran off the screen); screen readers still hear "Bet N".
                      <span className={`${betChip} absolute z-20 ${betBeside(seat)}`}>
                        <ChipStack amount={bet} />
                        <span aria-hidden="true">{bet.toLocaleString(i18n.language)}</span>
                        <span className="sr-only">{t('poker.bet', { amount: bet })}</span>
                      </span>
                    ) : null}
                    <div className="relative isolate" data-seat-anchor={seat}>
                      {/* The chair's back behind the player. */}
                      <span
                        aria-hidden="true"
                        className={`poker-chair absolute -inset-x-2.5 -top-2.5 -bottom-1 -z-20 rounded-[1.2rem] transition-shadow sm:-inset-x-3.5 sm:-top-3.5 sm:rounded-[1.4rem] ${
                          // The player to act: their chair lights up in the turn colour (the ring
                          // round the frame is the timer; a hand winner glows gold instead).
                          acting
                            ? 'shadow-[0_0_0_2px_var(--color-turn),0_0_26px_rgb(242_193_78/0.65)]'
                            : ''
                        }`}
                      />
                      <Avatar
                        name={name(seat)}
                        acting={acting}
                        share={acting ? turnShare : null}
                        winner={won}
                      />
                      {won && gain != null ? (
                        <span
                          className={`absolute bottom-full left-1/2 z-30 -translate-x-1/2 rounded-full ${shown ? 'mb-9 sm:mb-12' : 'mb-1'} bg-black/75 px-2 text-sm font-extrabold whitespace-nowrap text-gold-light tabular-nums ${
                            fresh
                              ? 'motion-safe:animate-rise motion-safe:[animation-delay:850ms]'
                              : ''
                          }`}
                        >
                          +{gain.toLocaleString(i18n.language)}
                        </span>
                      ) : null}
                      {shown ? (
                        // Cards shown at the showdown, in front of the avatar while the result is up.
                        <span className="absolute -top-8 left-1/2 z-20 flex -translate-x-1/2 sm:-top-11">
                          {shown.map((card, i) => (
                            <span
                              key={card}
                              className={`${i === 0 ? '-rotate-6' : '-ml-3 rotate-6'} ${won ? 'rounded-[5px] ring-2 ring-gold' : ''}`}
                            >
                              <PlayingCard
                                card={card}
                                label={labels.card(card)}
                                size={narrow ? 'xs' : 'sm'}
                              />
                            </span>
                          ))}
                        </span>
                      ) : null}
                      {view.button === seat ? (
                        <span
                          title={t('poker.dealer')}
                          className="absolute -top-1 -right-2 flex size-6 items-center justify-center rounded-full border-2 border-[#c9c2b0] bg-white text-xs font-extrabold text-black shadow"
                        >
                          D
                        </span>
                      ) : null}
                      {seat !== me && !gone && !shown && !won ? (
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
                    <div
                      className={`relative -mt-2 min-w-20 rounded-lg border bg-[#1c120d]/90 px-2.5 py-1 text-center shadow-lg backdrop-blur sm:min-w-24 ${
                        won
                          ? 'border-gold shadow-[0_0_0_2px_var(--color-gold),0_0_24px_rgb(214_171_78/0.55)]'
                          : acting
                            ? 'border-[var(--color-turn)] shadow-[0_0_0_1px_var(--color-turn)]'
                            : 'border-gold/35'
                      }`}
                    >
                      <span className="block max-w-28 truncate text-xs font-semibold sm:text-sm">
                        {name(seat)}
                      </span>
                      <span className="block text-xs font-bold text-gold tabular-nums">
                        {stack.toLocaleString(i18n.language)}
                      </span>
                      {seat !== me && bots[seat] ? (
                        <span className="absolute -bottom-2.5 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-1.5 py-0.5 text-[0.65rem] leading-none font-semibold tracking-wide text-white/85 uppercase">
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

                  {bet > 0 && !narrow ? (
                    <span
                      className={`${betChip} absolute z-10 -translate-x-1/2 -translate-y-1/2 transition-[left,top] duration-500`}
                      style={spot(seat, 28)}
                    >
                      <ChipStack amount={bet} />
                      {/* The chip says "bet": only the amount is written (long labels reached the cards). */}
                      <span aria-hidden="true">{bet.toLocaleString(i18n.language)}</span>
                      <span className="sr-only">{t('poker.bet', { amount: bet })}</span>
                    </span>
                  ) : null}
                </li>
              )
            })}
          </ul>
        </div>

        {/* The table's info, like a card on the table's edge, in the stage's corner: off the felt, where
            the players' bets go. */}
        <dl className="absolute bottom-2 left-2 rounded-lg border border-gold/25 bg-black/60 px-2 py-1 text-[0.7rem] sm:bottom-3 sm:left-4 sm:rounded-xl sm:px-3 sm:py-2 sm:text-xs">
          <div className="flex gap-1.5">
            <dt className="text-white/60">{t('poker.infoBlinds')}</dt>
            <dd className="font-bold text-gold-light tabular-nums">
              {view.blinds[0].toLocaleString(i18n.language)} /{' '}
              {view.blinds[1].toLocaleString(i18n.language)}
            </dd>
          </div>
          <div className="flex gap-1.5">
            <dt className="text-white/60">{t('poker.infoHand')}</dt>
            <dd className="font-bold tabular-nums">{view.hand}</dd>
          </div>
        </dl>
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
            {view.street === 'over' && !state.result
              ? secondsLeft !== null
                ? t('poker.nextHandIn', { count: secondsLeft })
                : t('poker.nextHand')
              : view.to_act === null
                ? ' '
                : t('poker.waitingFor', { name: name(view.to_act) })}
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
              <li
                key={i}
                className={
                  state.seat !== null && pot.winners.includes(state.seat) ? 'text-gold' : ''
                }
              >
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

/**
 * A framed square avatar with the player's initial; the acting player's frame shows the time left, a
 * hand winner's frame is gold.
 */
function Avatar({
  name,
  acting,
  share,
  winner = false,
}: {
  name: string
  acting: boolean
  share: number | null
  winner?: boolean
}) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : winner
          ? 'var(--color-gold)'
          : 'rgb(214 171 78 / 0.45)'
  return (
    <span
      className="block rounded-[0.9rem] p-[3px] shadow-lg shadow-black/60 sm:rounded-2xl"
      style={{ background: ring }}
      aria-hidden="true"
    >
      <span className="flex size-12 items-center justify-center rounded-[0.7rem] bg-linear-to-b from-[#4a342b] to-[#1c120c] text-xl font-bold uppercase shadow-[inset_0_0_0_1px_rgb(214_171_78/0.35)] sm:size-16 sm:rounded-xl sm:text-2xl">
        {name.slice(0, 1)}
      </span>
    </span>
  )
}

/** A bet in front of a seat. */
const betChip =
  'flex items-center gap-1.5 rounded-full bg-black/55 py-0.5 pr-2.5 pl-1 text-xs font-bold whitespace-nowrap tabular-nums motion-safe:animate-card-in'

const actionButton =
  'rounded-xl border px-3 py-3 text-sm font-bold shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-60 sm:text-base'

/** The house mark in the middle of the felt. */
function SpadeMark() {
  return (
    <svg
      viewBox="0 0 100 100"
      aria-hidden="true"
      className="absolute top-1/2 left-1/2 size-[38%] max-h-[60%] -translate-x-1/2 -translate-y-1/2 opacity-[0.07] sm:size-[22%]"
    >
      <circle cx="50" cy="50" r="46" fill="none" stroke="#fff" strokeWidth="3" />
      <path
        d="M50 18 C 38 34, 22 42, 22 57 C 22 68, 32 74, 41 70 C 39 76, 36 80, 32 83 H 68 C 64 80, 61 76, 59 70 C 68 74, 78 68, 78 57 C 78 42, 62 34, 50 18 Z"
        fill="#fff"
      />
    </svg>
  )
}

/** The dealer's place at the top of the table: a tray of chips. */
function DealerTray({ label }: { label: string }) {
  const colours = ['#c62f3a', '#e6e1d3', '#2f6fd6', '#d6ab4e', '#1f8a4c']
  return (
    <div className="absolute top-[7%] left-1/2 flex -translate-x-1/2 flex-col items-center gap-1">
      <span className="text-[0.65rem] font-extrabold tracking-[0.2em] text-gold-light/80 uppercase sm:text-xs">
        {label}
      </span>
      <span className="flex gap-1 rounded-md border border-black/40 bg-black/35 px-1.5 py-1 shadow-inner">
        {colours.map((colour) => (
          <span key={colour} className="flex flex-col-reverse">
            {[0, 1, 2].map((i) => (
              <span
                key={i}
                className="-mt-1 block h-1.5 w-3 rounded-full border border-black/40 sm:h-2 sm:w-4"
                style={{ background: colour }}
              />
            ))}
          </span>
        ))}
      </span>
    </div>
  )
}
