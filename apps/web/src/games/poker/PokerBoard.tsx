import { seatTrigger } from '@/features/players/seatTrigger'
import { useQueryClient } from '@tanstack/react-query'
import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCountdown } from '@/features/play/useCountdown'
import { showBanner } from '@/features/play/banners/store'
import { walletKey } from '@/features/wallet/api'
import { flyFishkas } from '@/features/wallet/flyFishkas'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { ThinkingDots } from '@/shared/ui/ThinkingDots'
import { AiFace } from '@/shared/ui/AiFace'
import { frameRing } from '@/shared/ui/frames'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import { useCardLabel } from '../cards/cards'
import { dealCard, flipCard, settleCards } from '../cards/motion'
import { CardBack, PlayingCard } from '../cards/PlayingCard'
import type { BoardProps } from '../registry'
import { BetControls, TurnHeader, type Raise } from './Controls'
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
  levels = {},
  frames = {},
  faces = {},
}: BoardProps) {
  const { t, i18n } = useTranslation()
  const labels = useCardLabel()
  const view = state.view as unknown as PokerView
  const me = state.seat ?? 0
  const actions = state.legal_actions as unknown as PokerAction[]
  const raise = actions.find((a): a is Raise => a.type === 'raise')
  const [handOnLoad] = useState(() => view.last_hand?.number ?? 0)

  // Moments between one view and the next (React's "props changed" pattern, during render): who went all-in,
  // bets swept into the pot at the end of a betting round, players who just sat down. Only what happens while the
  // page is open; never on the first view.
  const [prevView, setPrevView] = useState(view)
  const [prevPlayers, setPrevPlayers] = useState(players)
  const [moments, setMoments] = useState<{
    id: number
    allIn: { seat: number; amount: number }[]
    sweep: { seat: number; amount: number }[]
  } | null>(null)
  const [arrived, setArrived] = useState<number[]>([])
  // Each seat's chips when this hand's first view arrived pre-flop (null: joined mid-hand, so a loss isn't known).
  // Pre-flop every chip a seat has put in this hand is still in its bet, so stack + bet is where it started.
  const startOf = (v: PokerView) =>
    v.street === 'preflop' ? v.stacks.map((stack, seat) => stack + (v.bets[seat] ?? 0)) : null
  const [handStart, setHandStart] = useState<{ hand: number; chips: number[] | null }>(() => ({
    hand: view.hand,
    chips: startOf(view),
  }))
  // The table's hand history: every hand result seen since the page opened, newest first (real results from the
  // server's views; the last ten).
  const [hands, setHands] = useState<LastHand[]>(() => (view.last_hand ? [view.last_hand] : []))
  if (prevView !== view) {
    setPrevView(view)
    if (view.last_hand && view.last_hand.number !== hands[0]?.number)
      setHands([view.last_hand, ...hands].slice(0, 10))
    const sameHand = prevView.hand === view.hand
    if (!sameHand) setHandStart({ hand: view.hand, chips: startOf(view) })
    const allIn =
      sameHand && view.street !== 'over'
        ? view.stacks.flatMap((stack, seat) => {
            const before = prevView.stacks[seat] ?? 0
            return before > 0 && stack === 0 && !view.folded[seat] && !view.out[seat]
              ? [{ seat, amount: before + (prevView.bets[seat] ?? 0) }]
              : []
          })
        : []
    const swept =
      sameHand && view.bets.every((bet) => bet === 0)
        ? prevView.bets.flatMap((amount, seat) => (amount > 0 ? [{ seat, amount }] : []))
        : []
    if (allIn.length > 0 || swept.length > 0)
      setMoments({ id: (moments?.id ?? 0) + 1, allIn, sweep: swept })
  }
  if (prevPlayers !== players) {
    setPrevPlayers(players)
    const fresh = Object.keys(players)
      .map(Number)
      .filter((seat) => players[seat] && !prevPlayers[seat])
    if (fresh.length > 0) setArrived(fresh)
  }

  const owe = Math.max(0, view.current_bet - (view.bets[me] ?? 0))
  const callAmount = Math.min(owe, view.stacks[me] ?? 0)
  const name = (seat: number) =>
    seat === me ? t('poker.you') : players[seat] || t('poker.seat', { seat: seat + 1 })
  const has = (type: PokerAction['type']) => actions.some((a) => a.type === type)
  const presets = raise
    ? [
        { label: t('poker.halfPot'), value: view.current_bet + Math.round(view.pot / 2) },
        { label: t('poker.pot'), value: view.current_bet + view.pot + owe },
      ]
    : []

  // Turn timer: the share of the turn left, for the ring round the acting player's avatar.
  const secondsLeft = useCountdown(state.deadline, state.server_time)
  const turnShare =
    secondsLeft !== null && state.turn_seconds
      ? Math.min(1, secondsLeft / state.turn_seconds)
      : null

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
  useEffect(() => {
    for (const { seat, amount } of moments?.allIn ?? [])
      showBanner({
        kind: 'allIn',
        title: t('banners.allIn'),
        detail: t('banners.allInDetail', {
          name: name(seat),
          amount: amount.toLocaleString(i18n.language),
        }),
      })
    // `name` and `t` change with the language only; one banner per moment.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [moments?.id])

  // The hand's outcome as a banner: a win (yours or another's) with what it took; a loss at the showdown, quietly.
  // Folding is your own call, so it gets none.
  useEffect(() => {
    if (endedHand === null || !result || !last) return
    const [first] = [...result.winners]
    if (result.mine) {
      const won = result.winners.get(me)
      showBanner({
        kind: 'winner',
        title: t('banners.youWin'),
        detail: won != null ? `+${won.toLocaleString(i18n.language)}` : undefined,
      })
    } else if (first) {
      const [seat, chips] = first
      showBanner({
        kind: 'winner',
        title: t('banners.winner'),
        detail:
          chips != null
            ? t('banners.won', { name: name(seat), amount: chips.toLocaleString(i18n.language) })
            : name(seat),
      })
      const lost =
        handStart.hand === last.number && handStart.chips
          ? (handStart.chips[me] ?? 0) - (view.stacks[me] ?? 0)
          : 0
      if (state.seat !== null && !view.folded[me] && !view.out[me] && lost > 0)
        showBanner({
          kind: 'lost',
          title: t('banners.lost'),
          detail: t('banners.lostDetail', { amount: lost.toLocaleString(i18n.language) }),
        })
    }
    // Once per finished hand.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [endedHand])
  const boardCards = result && last ? last.board : view.board

  // Dealing, from the deck in the middle: a new hand's hole cards go round the table one at a time from the seat
  // after the button, twice; the flop, turn and river land face up one by one; shown cards flip at the showdown.
  // Only what changes while the page is open moves (useLayoutEffect: a card is placed before it shows).
  const tableRef = useRef<HTMLDivElement>(null)
  const seenRef = useRef<{ hand: number; board: number } | null>(null)
  const shownKey = result && fresh ? Object.keys(last.shown).join(',') : ''
  useLayoutEffect(() => {
    const root = tableRef.current
    const seen = seenRef.current
    seenRef.current = { hand: view.hand, board: boardCards.length }
    if (!root) return
    if (seen === null) {
      settleCards()
      return
    }
    const deck = root.querySelector('[data-poker-deck]')
    let order = 0
    if (view.hand !== seen.hand && view.street !== 'over') {
      const count = view.stacks.length
      for (let round = 0; round < 2; round++) {
        for (let i = 1; i <= count; i++) {
          const seat = (view.button + i) % count
          const card = root.querySelector<HTMLElement>(`[data-poker-hole="${seat}-${round}"]`)
          if (card) dealCard(card, deck, order++, seat === me)
        }
      }
    }
    const from = view.hand !== seen.hand ? 0 : seen.board
    for (let i = from; i < boardCards.length; i++) {
      dealCard(root.querySelector<HTMLElement>(`[data-poker-board="${i}"]`), deck, order++, true)
    }
  }, [view.hand, view.button, view.street, view.stacks.length, boardCards.length, me])
  useLayoutEffect(() => {
    if (!shownKey) return
    tableRef.current
      ?.querySelectorAll<HTMLElement>('[data-poker-shown]')
      .forEach((card, i) => flipCard(card, i))
  }, [shownKey])

  const seats = view.stacks.length
  // Round the oval from your seat at the bottom; x and y in percent of the table (radii may differ).
  const spot = (seat: number, radius: number, radiusY = radius) => {
    const angle = Math.PI / 2 + ((seat - me) * 2 * Math.PI) / seats
    return { left: `${50 + radius * Math.cos(angle)}%`, top: `${50 + radiusY * Math.sin(angle)}%` }
  }
  // Wide tables: bets sit on the felt between a seat and the middle. The tall phone oval has no
  // room for that next to the board, so there a bet sits at its own seat, on the side facing the
  // middle: under the top seat and seats level with the board, above yours, beside the others.
  const narrow = useMediaQuery('(max-width: 900px)')
  // On a phone the controls sit under the table: when your turn starts and they're off screen, they come into view.
  const controlsRef = useRef<HTMLDivElement>(null)
  const myTurnNow = actions.length > 0
  useEffect(() => {
    const panel = controlsRef.current
    if (!myTurnNow || !narrow || !panel) return
    if (panel.getBoundingClientRect().bottom <= window.innerHeight) return
    const still = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    panel.scrollIntoView?.({ block: 'end', behavior: still ? 'auto' : 'smooth' })
  }, [myTurnNow, narrow])
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
    // Below the "Bot" pill that hangs from the nameplate, not over it.
    if (y < -0.7) return 'top-full mt-3.5 left-1/2 -translate-x-1/2'
    if (Math.abs(y) < 0.3) return `top-full mt-3.5 ${x < 0 ? 'left-0' : 'right-0'}`
    if (y > 0.7) return 'bottom-full mb-1 left-1/2 -translate-x-1/2'
    return `${x < 0 ? 'left-full ml-1' : 'right-full mr-1'} top-1/2 -translate-y-1/2`
  }

  return (
    <div ref={tableRef} className="space-y-5 text-white">
      {/* The room: a dark patterned stage, the table on it, the table's info in the corner. */}
      <div className="poker-stage relative rounded-3xl px-7 pt-14 pb-12 min-[901px]:px-14 min-[901px]:pt-[4.5rem] min-[901px]:pb-12">
        <div className="relative mx-auto aspect-[3/4] w-full max-w-[max(18rem,min(36rem,calc((100dvh-24rem)*0.75)))] min-[901px]:aspect-[2.45/1] min-[901px]:max-w-[min(64rem,calc((100dvh-29rem)*2.45))]">
          <div aria-hidden="true" className="poker-rail absolute inset-0 rounded-[50%]">
            <div className="poker-felt relative h-full w-full overflow-hidden rounded-[50%]">
              <SpadeMark />
              {dealerFree ? <DealerTray label={t('poker.dealer')} /> : null}
            </div>
          </div>

          {/* The deck the cards are dealt from, by the board. */}
          <span
            aria-hidden="true"
            data-poker-deck=""
            className="absolute top-[29%] left-[8%] z-10 grid min-[901px]:top-[30%] min-[901px]:left-[20%]"
          >
            <CardBack
              size="xs"
              className="col-start-1 row-start-1 translate-x-[3px] -translate-y-[3px] opacity-80"
            />
            <CardBack
              size="xs"
              className="col-start-1 row-start-1 translate-x-[1.5px] -translate-y-[1.5px]"
            />
            <CardBack size="xs" className="col-start-1 row-start-1" />
          </span>

          <section
            aria-label={t('poker.board')}
            className="absolute inset-x-[5%] top-[38%] flex flex-col items-center gap-2 text-center min-[901px]:inset-x-[12%] min-[901px]:top-1/2 min-[901px]:-translate-y-1/2 min-[901px]:gap-3"
          >
            <div className="flex justify-center gap-1 min-[901px]:gap-2 max-[900px]:[&>span>span]:h-[50px] max-[900px]:[&>span>span]:w-9 max-[900px]:[&>span>span]:rounded-md">
              {boardCards.map((card, i) => (
                <span key={card} data-poker-board={i}>
                  <PlayingCard card={card} label={labels.card(card)} />
                </span>
              ))}
              {Array.from({ length: result ? 0 : 5 - boardCards.length }, (_, i) => (
                <span key={i} aria-hidden="true">
                  <span className="block h-[50px] w-9 rounded-md border border-dashed border-white/25 bg-black/10 min-[901px]:h-24 min-[901px]:w-16 min-[901px]:rounded-lg" />
                </span>
              ))}
            </div>
            {result ? (
              <ResultPlate summary={result} />
            ) : (
              <p className="flex items-center gap-2 rounded-full bg-black/45 px-4 py-1.5 text-sm font-bold min-[901px]:text-base">
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

          {/* The round's bets slide into the pot; an all-in's stack goes in with a heavier stack. */}
          {moments
            ? moments.sweep.map(({ seat, amount }, i) => (
                <ChipFlight
                  key={`sweep-${moments.id}-${seat}`}
                  from={spot(seat, 38)}
                  delay={i * 60}
                >
                  <ChipStack amount={amount} />
                </ChipFlight>
              ))
            : null}
          {/* A soft light under the player to act; it glides round the table as the turn moves. */}
          <span
            aria-hidden="true"
            className={`pointer-events-none absolute size-36 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(circle,rgb(242_193_78/0.38),rgb(242_193_78/0.12)_45%,transparent_70%)] transition-[left,top,opacity] duration-500 ease-soft motion-reduce:transition-none min-[901px]:size-48 ${
              view.to_act === null || view.street === 'over' ? 'opacity-0' : 'opacity-100'
            }`}
            style={spot(view.to_act ?? me, narrow ? 44 : 50, 50)}
          />
          <ul aria-label={t('poker.seats')}>
            {view.stacks.map((stack, seat) => {
              const acting = view.to_act === seat
              const gone = view.out[seat] || view.folded[seat]
              const won = result?.winners.has(seat) ?? false
              const gain = result?.winners.get(seat)
              const shown = result ? last?.shown[String(seat)] : undefined
              const bet = view.bets[seat] ?? 0
              // Every chip in the pot and still in the hand: the chair glows red until the hand is settled.
              const allIn =
                stack === 0 && !view.folded[seat] && !view.out[seat] && view.street !== 'over'
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
                  aria-label={`${name(seat)}, ${t('poker.chips', { count: stack })}`}
                >
                  <div
                    className={`absolute z-10 flex -translate-x-1/2 -translate-y-1/2 flex-col items-center transition-opacity ${
                      gone ? 'opacity-50' : ''
                    }`}
                    // Phones: the side seats come in a little so their plates stay on screen.
                    style={spot(seat, narrow ? 44 : 50, 50)}
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
                    <div
                      className={`relative isolate ${arrived.includes(seat) ? 'motion-safe:animate-[seat-in_520ms_cubic-bezier(0.2,0.9,0.3,1.2)_both]' : ''}`}
                      data-seat-anchor={seat}
                    >
                      {/* The chair's back behind the player. */}
                      <span
                        aria-hidden="true"
                        className={`poker-chair absolute -inset-x-2.5 -top-2.5 -bottom-1 -z-20 rounded-[1.2rem] transition-shadow min-[901px]:-inset-x-3.5 min-[901px]:-top-3.5 min-[901px]:rounded-[1.4rem] ${
                          // The player to act: their chair lights up in the turn colour (the ring
                          // round the frame is the timer; a hand winner glows gold instead).
                          acting
                            ? 'shadow-[0_0_0_2px_var(--color-turn),0_0_26px_rgb(242_193_78/0.65)] motion-safe:animate-[turn-glow_1.6s_ease-in-out_infinite]'
                            : allIn
                              ? 'shadow-[0_0_0_2px_#ff7a45,0_0_18px_rgb(255_90_50/0.5)] motion-safe:animate-[all-in-pulse_1.4s_ease-in-out_infinite]'
                              : ''
                        }`}
                      />
                      <span {...seatTrigger(seat, t('playerCard.open', { name: name(seat) }))}>
                        <Avatar
                          name={name(seat)}
                          acting={acting}
                          share={acting ? turnShare : null}
                          winner={won}
                          level={levels[seat]}
                          frame={frames[seat]}
                          ai={faces[seat]}
                        />
                      </span>
                      {won && gain != null ? (
                        <span
                          className={`absolute bottom-full left-1/2 z-30 -translate-x-1/2 rounded-full ${shown ? 'mb-9 min-[901px]:mb-12' : 'mb-1'} bg-black/75 px-2 text-sm font-extrabold whitespace-nowrap text-gold-light tabular-nums ${
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
                        <span className="absolute -top-8 left-1/2 z-20 flex -translate-x-1/2 min-[901px]:-top-11">
                          {shown.map((card, i) => (
                            <span
                              key={card}
                              data-poker-shown=""
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
                          <span data-poker-hole={`${seat}-0`} className="-rotate-12">
                            <CardBack size="sm" />
                          </span>
                          <span data-poker-hole={`${seat}-1`} className="-ml-6 rotate-12">
                            <CardBack size="sm" />
                          </span>
                        </span>
                      ) : null}
                    </div>
                    <div
                      className={`relative -mt-2 min-w-20 rounded-lg border bg-[#1c120d]/90 px-2.5 py-1 text-center shadow-lg backdrop-blur min-[901px]:min-w-24 ${
                        won
                          ? 'border-gold shadow-[0_0_0_2px_var(--color-gold),0_0_24px_rgb(214_171_78/0.55)]'
                          : acting
                            ? 'border-[var(--color-turn)] shadow-[0_0_0_1px_var(--color-turn)]'
                            : 'border-gold/35'
                      }`}
                    >
                      <span className="block max-w-28 truncate text-xs font-semibold min-[901px]:text-sm">
                        {name(seat)}
                      </span>
                      <span className="block text-xs font-bold text-gold tabular-nums">
                        {stack.toLocaleString(i18n.language)}
                      </span>
                      {acting && secondsLeft !== null && !status ? (
                        // The acting player's clock on their plate (red in the last seconds).
                        <span
                          aria-hidden="true"
                          className={`absolute -top-3 left-1/2 -translate-x-1/2 rounded-full px-2 py-0.5 text-[0.7rem] font-extrabold whitespace-nowrap tabular-nums shadow ${secondsLeft <= 10 ? 'bg-[#d8412f] text-white' : 'bg-[var(--color-turn)] text-black'}`}
                        >
                          {t('poker.turn.seconds', { count: secondsLeft })}
                        </span>
                      ) : status ? (
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
            the players' bets go. Under 360 px each value goes under its label, so the card stays clear of
            your seat's plate in the middle. */}
        <dl className="absolute bottom-2 left-2 rounded-lg border border-gold/25 bg-black/60 px-2 py-1 text-[0.7rem] min-[901px]:bottom-3 min-[901px]:left-4 min-[901px]:rounded-xl min-[901px]:px-3 min-[901px]:py-2 min-[901px]:text-xs">
          <div className="flex gap-1.5 max-[359px]:flex-col max-[359px]:gap-0">
            <dt className="text-white/60">{t('poker.infoBlinds')}</dt>
            <dd className="font-bold text-gold-light tabular-nums">
              {view.blinds[0].toLocaleString(i18n.language)} /{' '}
              {view.blinds[1].toLocaleString(i18n.language)}
            </dd>
          </div>
          <div className="flex gap-1.5 max-[359px]:flex-col max-[359px]:gap-0">
            <dt className="text-white/60">{t('poker.infoHand')}</dt>
            <dd className="font-bold tabular-nums">{view.hand}</dd>
          </div>
        </dl>
      </div>

      <div
        ref={controlsRef}
        className="club-panel mx-auto flex max-w-4xl scroll-mb-3 flex-col items-center gap-4 rounded-3xl p-4 min-[901px]:flex-row min-[901px]:p-5"
      >
        <div className="flex shrink-0 gap-2" aria-label={t('poker.yourCards')}>
          {view.hand_cards.length > 0
            ? view.hand_cards.map((card, i) => (
                <span
                  key={card}
                  data-poker-hole={`${me}-${i}`}
                  className={i === 0 ? '-rotate-6' : 'rotate-6'}
                >
                  <PlayingCard card={card} label={labels.card(card)} size="lg" />
                </span>
              ))
            : [0, 1].map((i) => <CardBack key={i} size="lg" />)}
        </div>

        <div className="flex w-full min-w-0 flex-col gap-3">
          {/* Whose turn it is (the plain "waiting for…" line is gone, owner 2026-11-17). */}
          {view.street === 'over' && !state.result ? (
            <TurnHeader mode="next" secondsLeft={secondsLeft} share={null} />
          ) : state.result ? null : actions.length > 0 ? (
            <TurnHeader mode="mine" secondsLeft={secondsLeft} share={turnShare} />
          ) : view.to_act === null ? (
            <TurnHeader mode="waiting" secondsLeft={null} share={null} />
          ) : (
            <TurnHeader
              mode="other"
              name={name(view.to_act)}
              face={faces[view.to_act]}
              secondsLeft={secondsLeft}
              share={turnShare}
            />
          )}
          {actions.length > 0 ? (
            <BetControls
              // A new decision starts from the minimum again.
              key={`${view.hand}-${view.street}-${view.current_bet}`}
              raise={raise}
              has={has}
              callAmount={callAmount}
              stack={view.stacks[me] ?? 0}
              opening={view.current_bet === 0}
              presets={presets}
              step={Math.max(1, view.blinds[1])}
              pending={pending}
              onSend={onAction}
            />
          ) : null}
        </div>
      </div>

      {hands.length > 0 ? (
        <section
          aria-label={t('poker.lastHand')}
          className="club-panel mx-auto max-w-4xl rounded-3xl p-4 text-sm"
        >
          <HandRecord hand={hands[0]!} name={name} me={state.seat} />
          {hands.length > 1 ? (
            // Earlier hands at this table, folded away.
            <details className="group mt-3 border-t border-gold/15 pt-3">
              <summary className="flex min-h-10 cursor-pointer list-none items-center justify-between font-semibold text-gold-light [&::-webkit-details-marker]:hidden">
                {t('poker.history.earlier', { count: hands.length - 1 })}
                <span aria-hidden="true" className="transition-transform group-open:rotate-180">
                  ▾
                </span>
              </summary>
              <ol className="mt-2 space-y-3">
                {hands.slice(1).map((hand) => (
                  <li
                    key={hand.number}
                    className="rounded-2xl bg-black/20 p-3 motion-safe:animate-rise"
                  >
                    <HandRecord hand={hand} name={name} me={state.seat} />
                  </li>
                ))}
              </ol>
              <p className="mt-2 text-xs text-muted">{t('poker.history.note')}</p>
            </details>
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
  level,
  frame,
  ai,
}: {
  name: string
  acting: boolean
  share: number | null
  winner?: boolean
  level?: number
  frame?: string | null
  /** An AI player: its drawn face instead of the initial. */
  ai?: string
}) {
  const ring =
    acting && share !== null
      ? `conic-gradient(var(--color-turn) ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? 'var(--color-turn)'
        : winner
          ? 'var(--color-gold)'
          : (frameRing(frame) ?? 'rgb(214 171 78 / 0.45)')
  const face = (
    <span
      className="block rounded-[0.9rem] p-[3px] shadow-lg shadow-black/60 min-[901px]:rounded-2xl"
      style={{ background: ring }}
      aria-hidden="true"
    >
      <span className="flex size-12 items-center justify-center overflow-hidden rounded-[0.7rem] bg-linear-to-b from-[#4a342b] to-[#1c120c] text-xl font-bold uppercase shadow-[inset_0_0_0_1px_rgb(214_171_78/0.35)] min-[901px]:size-16 min-[901px]:rounded-xl min-[901px]:text-2xl">
        {ai ? <AiFace name={ai} className="size-full" /> : name.slice(0, 1)}
      </span>
    </span>
  )
  const thinking =
    acting && ai ? (
      <ThinkingDots name={name} className="absolute -top-4 left-1/2 z-30 -translate-x-1/2" />
    ) : null
  if (level === undefined) {
    return thinking ? (
      <span className="relative block">
        {face}
        {thinking}
      </span>
    ) : (
      face
    )
  }
  return (
    <span className="relative block">
      {face}
      {thinking}
      {/* Top left: the nameplate covers the bottom of the frame, the dealer button its top right. */}
      <LevelBadge level={level} size="md" className="absolute -top-1.5 -left-2 z-10" />
    </span>
  )
}

/** A bet in front of a seat. */
const betChip =
  'flex items-center gap-1.5 rounded-full bg-black/55 py-0.5 pr-2.5 pl-1 text-xs font-bold whitespace-nowrap tabular-nums motion-safe:animate-card-in'

/** The house mark in the middle of the felt. */
function SpadeMark() {
  return (
    <svg
      viewBox="0 0 100 100"
      aria-hidden="true"
      className="absolute top-1/2 left-1/2 size-[38%] max-h-[60%] -translate-x-1/2 -translate-y-1/2 opacity-[0.07] min-[901px]:size-[22%]"
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
      <span className="text-[0.65rem] font-extrabold tracking-[0.2em] text-gold-light/80 uppercase min-[901px]:text-xs">
        {label}
      </span>
      <span className="flex gap-1 rounded-md border border-black/40 bg-black/35 px-1.5 py-1 shadow-inner">
        {colours.map((colour) => (
          <span key={colour} className="flex flex-col-reverse">
            {[0, 1, 2].map((i) => (
              <span
                key={i}
                className="-mt-1 block h-1.5 w-3 rounded-full border border-black/40 min-[901px]:h-2 min-[901px]:w-4"
                style={{ background: colour }}
              />
            ))}
          </span>
        ))}
      </span>
    </div>
  )
}

/** One finished hand: who won each pot, with what, the board and the cards shown down. */
function HandRecord({
  hand,
  name,
  me,
}: {
  hand: LastHand
  name: (seat: number) => string
  me: number | null
}) {
  const { t } = useTranslation()
  const labels = useCardLabel()
  return (
    <>
      <h2 className="mb-2 flex flex-wrap items-center gap-x-3 gap-y-1 font-semibold text-gold-light">
        {t('poker.lastHandTitle', { hand: hand.number })}
        {hand.board.length > 0 ? (
          <span className="flex gap-1" aria-label={t('poker.board')}>
            {hand.board.map((card) => (
              <PlayingCard key={card} card={card} label={labels.card(card)} small />
            ))}
          </span>
        ) : null}
      </h2>
      <ul className="space-y-1">
        {hand.pots.map((pot, i) => (
          <li key={i} className={me !== null && pot.winners.includes(me) ? 'text-gold-light' : ''}>
            {t('poker.won', { names: pot.winners.map(name).join(', '), count: pot.amount })}
            {pot.hand ? ` — ${t(handKeys[pot.hand as keyof typeof handKeys])}` : ''}
          </li>
        ))}
      </ul>
      {Object.keys(hand.shown).length > 0 ? (
        <ul className="mt-3 flex flex-wrap gap-4">
          {Object.entries(hand.shown).map(([seat, cards]) => (
            <li key={seat} className="flex items-center gap-2">
              <span className="text-muted">{name(Number(seat))}</span>
              {cards.map((card) => (
                <PlayingCard key={card} card={card} label={labels.card(card)} small />
              ))}
            </li>
          ))}
        </ul>
      ) : null}
    </>
  )
}
