import { useEffect, useState, type CSSProperties } from 'react'
import { useCurrentUser } from '@/features/auth/hooks'
import { playSound } from '@/features/play/sounds'
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
  /** 25 for kaput. */
  value: number
  /** null: no-trump. */
  suit: Suit | null
  kaput: boolean
  doubled: 0 | 1 | 2
}

type Kind = 'terz' | 'fifty' | 'hundred' | 'carre'

export interface BlotView {
  seat: number | null
  dealer: number
  hand_no: number
  phase: 'bidding' | 'answer' | 'playing' | 'over'
  hand: string[]
  hand_sizes: number[]
  bids: {
    seat: number
    type: 'bid' | 'kaput' | 'pass' | 'contra' | 'recontra'
    value?: number
    suit?: Suit | 'N'
  }[]
  contract: Contract | null
  trump: Suit | null
  to_act: number | null
  trick: { seat: number; card: string }[]
  last_trick: { cards: { seat: number; card: string }[]; winner: number; points: number } | null
  /** Tricks taken this deal, per team. */
  tricks: [number, number]
  points: [number, number]
  my_declarations: {
    index: number
    kind: Kind
    points: number
    cards: string[]
    declared: boolean
  }[]
  /** Announced declarations per seat (cards once the first trick is over). */
  declared: { kind: Kind; points: number; cards: string[] }[][]
  declarations_shown: boolean
  belote: number | null
  /** Who opened the bazar this deal. */
  opener: number | null
  /** This deal's public events, oldest first. */
  log: LogEntry[]
  /** Match scores in sai, per team. */
  scores: [number, number]
  target: number
  last_hand: {
    number: number
    contract: Contract
    made: boolean
    tricks: [number, number]
    card_points: [number, number]
    card_sai: [number, number]
    declarations: { seat: number; kind: Kind; points: number; cards: string[]; counted: boolean }[]
    declaration_sai: [number, number]
    belote_sai: [number, number]
    bid_sai: number
    scored: [number, number]
    totals: [number, number]
    kaput: boolean
  } | null
}

export type LogEntry =
  | { type: 'pass' | 'contra' | 'recontra'; seat: number }
  | { type: 'bid'; seat: number; value: number; suit: Suit | 'N' }
  | { type: 'kaput'; seat: number; suit: Suit | 'N' }
  | ({ type: 'contract' } & Contract)
  | { type: 'declare'; seat: number; kind: Kind; points: number; cards: string[] }
  | { type: 'blot' | 'reblot' | 'chakhel'; seat: number; card: string }
  | { type: 'trick'; seat: number; points: number; last: boolean }
  | { type: 'kaput_failed'; seat: number }
  | {
      type: 'hand_over'
      made: boolean
      scored: [number, number]
      kaput: boolean
      reverse_kaput: boolean
    }

type BlotAction =
  | { type: 'play'; card: string }
  | { type: 'pass' | 'contra' | 'recontra' }
  | { type: 'bid'; min: number; max: number }
  | { type: 'kaput'; suit: Suit | 'N' }
  | { type: 'declare'; index: number }

const BID_SUITS: (Suit | 'N')[] = ['S', 'H', 'D', 'C', 'N']
/** Text presentation, so iOS never draws the suits as colour emoji. */
const suitText = (suit: Suit) => `${suitSymbols[suit]}︎`
const red = (suit: Suit | 'N' | null) => suit === 'H' || suit === 'D'

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
 * Armenian Bazar Blot for two partnerships: you at the bottom, your partner opposite, the opponents left
 * and right. The bazar (a number with a suit or no-trump, kaput, contra, recontra), your declarations
 * before your first card, the trick, your hand (only the server's playable cards can be played), the
 * contract, the tricks taken and the match score in sai. After each deal the table shows how it was
 * scored. Every number comes from the server.
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
  const kaputs = actions.filter(
    (a): a is Extract<BlotAction, { type: 'kaput' }> => a.type === 'kaput',
  )
  const declares = actions.filter(
    (a): a is Extract<BlotAction, { type: 'declare' }> => a.type === 'declare',
  )
  const playable = new Set(
    actions.flatMap((a) => (a.type === 'play' ? [(a as { card: string }).card] : [])),
  )
  const has = (type: BlotAction['type']) => actions.some((a) => a.type === type)
  const [value, setValue] = useState<number | null>(null)
  const [kaputMode, setKaputMode] = useState(false)
  const [suit, setSuit] = useState<Suit | 'N' | null>(null)
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
    setKaputMode(false)
    setSuit(null)
    onAction(action)
  }
  const suitLabel = (suit: Suit | 'N' | null) =>
    suit === null || suit === 'N' ? t('blot.noTrumpShort') : suitText(suit)
  const contractText = (c: Contract) =>
    `${c.kaput ? t('blot.kaputShort') : c.value} ${suitLabel(c.suit)}`
  const lastBid = (seat: number) => [...view.bids].reverse().find((b) => b.seat === seat)
  const bidText = (b: BlotView['bids'][number]) =>
    b.type === 'bid' && b.value && b.suit
      ? `${b.value} ${suitLabel(b.suit)}`
      : b.type === 'kaput' && b.suit
        ? `${t('blot.kaputShort')} ${suitLabel(b.suit)}`
        : t(`blot.call.${b.type}` as 'blot.call.pass')
  // The trick on the felt; between tricks the last one stays until the next card is played.
  const showingLast =
    view.trick.length === 0 && view.last_trick !== null && view.phase === 'playing'
  const trick = showingLast ? (view.last_trick?.cards ?? []) : view.trick
  const contract = view.contract
  const hand = sortHand(view.hand, view.trump)
  const result = view.phase === 'over' || state.result ? view.last_hand : null
  const bidding = (view.phase === 'bidding' || view.phase === 'answer') && actions.length > 0
  const teamName = (team: number) => (team === myTeam ? t('blot.us') : t('blot.them'))
  const multiplier = (c: Contract) => (c.suit === null ? [2, 3, 5] : [1, 2, 4])[c.doubled] ?? 1
  const tricksLeft = 8 - (view.tricks[0] ?? 0) - (view.tricks[1] ?? 0)

  /** Each logged event in words: a title (the term) and a detail line. */
  const describe = (entry: LogEntry): { title: string; detail: string } => {
    switch (entry.type) {
      case 'pass':
        return { title: t('blot.call.pass'), detail: '' }
      case 'bid':
        return { title: `${entry.value} ${suitLabel(entry.suit)}`, detail: t('blot.log.bid') }
      case 'kaput':
        return {
          title: `${t('blot.kaput')} ${suitLabel(entry.suit)}`,
          detail: t('blot.log.kaputBid'),
        }
      case 'contra':
        return { title: t('blot.call.contra'), detail: t('blot.log.contra') }
      case 'recontra':
        return { title: t('blot.call.recontra'), detail: t('blot.log.recontra') }
      case 'contract':
        return {
          title: t('blot.log.contract', { name: name(entry.seat), contract: contractText(entry) }),
          detail: `${t('blot.trump')}: ${suitLabel(entry.suit)} · ×${multiplier(entry)}`,
        }
      case 'declare':
        return {
          title: `${t(`blot.declaration.${entry.kind}`)} +${entry.points}`,
          detail: t('blot.log.declare'),
        }
      case 'blot':
        return {
          title: t('blot.belote'),
          detail: t('blot.log.blot', { card: labels.card(entry.card) }),
        }
      case 'reblot':
        return {
          title: t('blot.reblot'),
          detail: t('blot.log.reblot', { card: labels.card(entry.card) }),
        }
      case 'chakhel':
        return {
          title: t('blot.chakhel'),
          detail: t('blot.log.chakhel', { name: name(entry.seat), card: labels.card(entry.card) }),
        }
      case 'trick':
        return entry.last
          ? {
              title: t('blot.lastTrick'),
              detail: t('blot.log.lastTrick', { name: name(entry.seat), points: entry.points }),
            }
          : {
              title: t('blot.trick'),
              detail: t('blot.log.trick', { name: name(entry.seat), points: entry.points }),
            }
      case 'kaput_failed':
        return {
          title: t('blot.kaputFailed'),
          detail: t('blot.log.kaputFailed', { name: name(entry.seat) }),
        }
      case 'hand_over':
        return {
          title: entry.reverse_kaput
            ? t('blot.reverseKaput')
            : entry.kaput
              ? t('blot.kaput')
              : t(entry.made ? 'blot.log.made' : 'blot.log.failed'),
          detail: t('blot.log.scored', {
            us: entry.scored[myTeam] ?? 0,
            them: entry.scored[1 - myTeam] ?? 0,
          }),
        }
    }
  }
  // In the ticker and the history, who did it comes first.
  const describeWithName = (entry: LogEntry) => {
    const text = describe(entry)
    return 'seat' in entry &&
      entry.type !== 'contract' &&
      entry.type !== 'chakhel' &&
      entry.type !== 'trick'
      ? { ...text, title: `${name(entry.seat)}: ${text.title}` }
      : text
  }

  // ── Dealing (visual only): the server has already dealt; the table shows it card by card. ──────────
  // Three, two, three to each player, starting left of the dealer — a round of 12 small packets.
  const reduced =
    typeof window !== 'undefined' &&
    !!window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  const freshDeal = view.phase === 'bidding' && view.log.length === 0
  const [shownDeal, setShownDeal] = useState(() => (freshDeal && !reduced ? 0 : view.hand_no))
  const dealing = freshDeal && shownDeal !== view.hand_no
  // How far the deal on screen has got, for this deal number (a new deal starts from nothing).
  const [progress, setProgress] = useState({ hand: view.hand_no, k: 0 })
  const step = progress.hand === view.hand_no ? progress.k : 0
  const { data: user } = useCurrentUser()
  const soundOn = Boolean(user?.profile.preferences.sound)
  useEffect(() => {
    if (!dealing) return
    const hand = view.hand_no
    let k = 0
    const timer = window.setInterval(() => {
      k += 1
      if (soundOn) playSound('move', 'cards')
      if (k >= PACKETS.length) {
        window.clearInterval(timer)
        setShownDeal(hand)
      }
      setProgress({ hand, k })
    }, 190)
    return () => window.clearInterval(timer)
  }, [dealing, view.hand_no, soundOn])
  // How many cards each seat holds so far in the deal on screen.
  const dealtTo = (seat: number) => {
    if (!dealing) return view.hand_sizes[seat] ?? 0
    let n = 0
    for (let k = 0; k < step; k++) {
      if ((view.dealer + 1 + k) % 4 === seat) n += PACKETS[k] ?? 0
    }
    return n
  }
  const packet =
    dealing && step < PACKETS.length
      ? { seat: (view.dealer + 1 + step) % 4, cards: PACKETS[step] ?? 0 }
      : null
  const shownHand = dealing ? sortHand(view.hand.slice(0, dealtTo(me)), view.trump) : hand

  // The call each player made last in the bazar, for their speech bubble.
  const bubble = (seat: number) => {
    if (dealing || (view.phase !== 'bidding' && view.phase !== 'answer')) return null
    const b = lastBid(seat)
    if (!b) return null
    return b.type === 'bid' && b.value && b.suit ? (
      <span className="inline-flex items-center gap-1">
        <span
          className={b.suit === 'N' ? 'text-sm' : red(b.suit) ? 'text-suit-red' : 'text-suit-black'}
        >
          {suitLabel(b.suit)}
        </span>
        <span>{b.value}</span>
      </span>
    ) : (
      bidText(b)
    )
  }
  const thinking = (seat: number) =>
    !dealing && view.to_act === seat && seat !== me && view.phase !== 'over' && !state.result

  // Where each seat sits on the table, from your side: 0 you (bottom left), 1 left, 2 partner (top), 3 right.
  const SEAT_POS = [
    'left-[1%] bottom-[24%] min-[641px]:bottom-[3%]',
    'left-[1%] top-[36%]',
    'left-1/2 top-[1%] -translate-x-1/2',
    'right-[1%] top-[36%]',
  ]
  // Where a played card comes from (towards the trick in the middle).
  const FROM = [
    { x: '0px', y: '170px' },
    { x: '-240px', y: '0px' },
    { x: '0px', y: '-170px' },
    { x: '240px', y: '0px' },
  ]

  return (
    <div className="space-y-3 text-white">
      <div className="relative overflow-hidden rounded-3xl border border-gold/25 bg-[radial-gradient(ellipse_at_50%_35%,#3a2418_0%,#24150d_55%,#140b06_100%)] shadow-[inset_0_0_80px_rgb(0_0_0/0.55)]">
        <TableAnnouncement view={view} name={name} levels={levels} describe={describe} />

        <div className="relative mx-auto aspect-[10/16] w-full max-w-[min(30rem,calc((100dvh-9rem)*0.625))] min-[641px]:aspect-[16/10] min-[641px]:max-w-[min(72rem,calc((100dvh-10rem)*1.6))]">
          {/* The oval table: a wood rim round green felt. */}
          <div
            aria-hidden="true"
            className="absolute inset-x-[12%] top-[15%] bottom-[30%] rounded-[50%] bg-[linear-gradient(160deg,#8a5a2b,#5a3416_45%,#3d220d)] p-[1.6%] shadow-[0_24px_60px_rgb(0_0_0/0.65),inset_0_2px_0_rgb(255_220_160/0.35)] min-[641px]:inset-x-[11%] min-[641px]:top-[19%] min-[641px]:bottom-[17%] min-[641px]:p-[1.1%]"
          >
            <div className="relative h-full w-full rounded-[50%] bg-[radial-gradient(ellipse_at_50%_40%,#2f7a4c_0%,#1f5e39_55%,#15452a_100%)] shadow-[inset_0_0_0_2px_rgb(0_0_0/0.35),inset_0_14px_50px_rgb(0_0_0/0.45)]">
              <span className="absolute inset-0 flex items-center justify-center text-center font-display text-xl font-extrabold tracking-[0.25em] text-white/[0.07] uppercase min-[641px]:text-4xl">
                {t('blot.bazar')}
              </span>
            </div>
          </div>

          {/* The middle of the felt: the deck while dealing, the bazar, the trick, or the deal's result. */}
          <div className="absolute inset-x-[16%] top-[19%] bottom-[34%] flex items-center justify-center min-[641px]:inset-x-[22%] min-[641px]:top-[24%] min-[641px]:bottom-[22%]">
            {dealing ? (
              <div className="relative" aria-label={t('blot.dealing')} role="status">
                {[0, 1, 2].map((i) => (
                  <CardBack key={i} size="sm" className="absolute -top-1 left-0" />
                ))}
                <CardBack size="sm" className="relative" />
              </div>
            ) : result ? (
              <HandSummary
                result={result}
                myTeam={myTeam}
                name={name}
                format={format}
                contractText={contractText}
                target={view.target}
                matchOver={!!state.result}
              />
            ) : bidding ? (
              bidControls()
            ) : (
              <section
                aria-label={showingLast ? t('blot.lastTrick') : t('blot.trick')}
                className={`relative h-full w-full ${showingLast ? 'opacity-70' : ''}`}
              >
                {trick.map(({ seat, card }) => {
                  const where = place(seat)
                  const spot = [
                    'bottom-[2%] left-1/2 -translate-x-1/2',
                    'left-[8%] top-1/2 -translate-y-1/2 -rotate-6',
                    'top-[2%] left-1/2 -translate-x-1/2',
                    'right-[8%] top-1/2 -translate-y-1/2 rotate-6',
                  ][where]
                  const won = showingLast && view.last_trick?.winner === seat
                  const from = FROM[where] ?? FROM[0]
                  return (
                    <span key={`${view.hand_no}-${card}`} className={`absolute ${spot}`}>
                      <span
                        className={`block rounded-md motion-safe:animate-card-from ${won ? 'ring-2 ring-gold' : ''}`}
                        style={{ '--fx': from?.x, '--fy': from?.y } as CSSProperties}
                      >
                        <PlayingCard
                          card={card}
                          label={labels.card(card)}
                          size={narrow ? 'sm' : 'md'}
                        />
                      </span>
                    </span>
                  )
                })}
              </section>
            )}
          </div>

          {/* A packet of cards on its way from the deck to a player. */}
          {packet ? (
            <span
              key={step}
              aria-hidden="true"
              className="pointer-events-none absolute top-1/2 left-1/2 z-30 -translate-x-1/2 -translate-y-1/2"
            >
              {Array.from({ length: packet.cards }, (_, i) => (
                <span
                  key={i}
                  className="absolute animate-deal-fly"
                  style={
                    {
                      '--tx': ['0px', '-42vw', '0px', '42vw'][place(packet.seat)],
                      '--ty': narrow
                        ? ['44vh', '-6vh', '-40vh', '-6vh'][place(packet.seat)]
                        : ['36vh', '-4vh', '-36vh', '-4vh'][place(packet.seat)],
                      animationDelay: `${i * 40}ms`,
                      left: `${i * 4}px`,
                    } as CSSProperties
                  }
                >
                  <CardBack size="sm" />
                </span>
              ))}
            </span>
          ) : null}

          {/* The four players. */}
          <ul aria-label={t('blot.seats')}>
            {[0, 1, 2, 3].map((seat) => {
              const where = place(seat)
              const acting = view.to_act === seat && !dealing
              const declared = view.declared[seat] ?? []
              const said = bubble(seat)
              return (
                <li
                  key={seat}
                  aria-current={acting ? 'step' : undefined}
                  aria-label={`${name(seat)}, ${teamName(seat % 2)}${bots[seat] && seat !== me ? `, ${t('room.bot')}` : ''}`}
                  className={`absolute z-20 flex flex-col items-center ${SEAT_POS[where]}`}
                >
                  <span className="relative">
                    <Avatar
                      name={name(seat)}
                      acting={acting}
                      share={acting ? turnShare : null}
                      level={levels[seat]}
                      ours={seat % 2 === myTeam}
                    />
                    {view.dealer === seat ? (
                      <span
                        title={t('blot.dealer')}
                        className="absolute -top-1 -left-1 flex size-6 items-center justify-center rounded-full border-2 border-[#c9c2b0] bg-white text-[0.65rem] font-extrabold text-black shadow"
                      >
                        D
                      </span>
                    ) : null}
                    {/* The speech bubble: their last bazar call, or "thinking" while it's their turn. */}
                    {thinking(seat) || said ? (
                      <span
                        className={`absolute z-30 flex min-h-9 min-w-12 items-center justify-center rounded-2xl border-2 border-[#e8c66a] bg-[#fdf3d0] px-3 py-1 text-base font-extrabold whitespace-nowrap text-[#2a1b0c] shadow-[0_6px_14px_rgb(0_0_0/0.45)] motion-safe:animate-card-in ${
                          where === 3
                            ? 'top-0 right-full mr-3'
                            : where === 2
                              ? 'top-2 right-full mr-3'
                              : 'top-0 left-full ml-3'
                        }`}
                      >
                        {thinking(seat) ? (
                          <span
                            className="flex gap-1"
                            aria-label={t('blot.thinking', { name: name(seat) })}
                            role="img"
                          >
                            {[0, 1, 2].map((i) => (
                              <span
                                key={i}
                                className="size-1.5 rounded-full bg-[#2a1b0c] motion-safe:animate-thinking"
                                style={{ animationDelay: `${i * 160}ms` }}
                              />
                            ))}
                          </span>
                        ) : (
                          said
                        )}
                      </span>
                    ) : null}
                  </span>
                  <span
                    className={`relative -mt-1 block max-w-28 min-w-20 truncate rounded-lg border px-3 py-0.5 text-center text-xs font-bold shadow-lg min-[641px]:min-w-28 min-[641px]:text-sm ${
                      acting
                        ? 'border-[#e8c66a] bg-linear-to-b from-[#fbe3a0] to-[#e3b955] text-[#2a1b0c]'
                        : 'border-white/15 bg-[#2a1a12]/95 text-white'
                    }`}
                  >
                    {name(seat)}
                    {acting && turnShare !== null ? (
                      <span
                        aria-hidden="true"
                        className="absolute inset-x-1 -bottom-1 h-1 overflow-hidden rounded-full bg-black/30"
                      >
                        <span
                          className="block h-full rounded-full bg-[#3ccf6e] transition-[width] duration-1000 ease-linear"
                          style={{ width: `${turnShare * 100}%` }}
                        />
                      </span>
                    ) : null}
                  </span>
                  {seat !== me && bots[seat] ? (
                    <span className="mt-1 rounded-full bg-black/60 px-1.5 py-0.5 text-[0.6rem] leading-none font-semibold tracking-wide text-white/80 uppercase">
                      {t('room.bot')}
                    </span>
                  ) : null}
                  {/* Card backs (not for the partner on a phone: the facts column needs that room). */}
                  {seat !== me &&
                  (view.phase === 'playing' || dealing) &&
                  !(narrow && where === 2) ? (
                    <span aria-hidden="true" className="mt-1 flex">
                      {Array.from({ length: Math.min(8, dealtTo(seat)) }, (_, i) => (
                        <CardBack key={i} size="xs" className={i > 0 ? '-ml-6' : ''} />
                      ))}
                    </span>
                  ) : null}
                  {view.phase === 'playing' && declared.length > 0 ? (
                    <span
                      className={`flex flex-col items-center gap-1 ${
                        where === 2
                          ? 'absolute top-2 left-full ml-24'
                          : where === 0
                            ? 'absolute bottom-full left-0 mb-2 min-[641px]:top-2 min-[641px]:bottom-auto min-[641px]:left-full min-[641px]:mb-0 min-[641px]:ml-24'
                            : 'mt-1'
                      }`}
                    >
                      {declared.map((d, i) => (
                        <span
                          key={i}
                          className="flex items-center gap-1 rounded-lg bg-black/60 p-1"
                          aria-label={t('blot.declaredWith', {
                            what: `${t(`blot.declaration.${d.kind}`)} +${d.points}`,
                            cards: d.cards.map(labels.card).join(', '),
                          })}
                        >
                          <span className="rounded-full bg-gold px-1.5 py-0.5 text-[0.65rem] font-extrabold whitespace-nowrap text-black">
                            {t(`blot.declaration.${d.kind}`)} +{d.points}
                          </span>
                          <span className="flex" aria-hidden="true">
                            {d.cards.map((card, j) => (
                              <span key={card} className={j > 0 ? '-ml-5' : ''}>
                                <PlayingCard card={card} label={labels.card(card)} size="xs" />
                              </span>
                            ))}
                          </span>
                        </span>
                      ))}
                    </span>
                  ) : null}
                  {view.belote === seat && view.phase === 'playing' ? (
                    <span className="mt-1 rounded-full bg-white/85 px-1.5 py-0.5 text-[0.65rem] font-extrabold text-black">
                      {t('blot.belote')}
                    </span>
                  ) : null}
                </li>
              )
            })}
          </ul>

          {/* The scoreboard (Us | You) and the deal's facts, top right. */}
          <div className="absolute top-[1.5%] right-[1.5%] z-20 flex flex-col items-end gap-1.5">
            <dl
              aria-label={t('blot.score')}
              className="grid grid-cols-2 overflow-hidden rounded-xl border border-gold/35 bg-[#1c120d]/90 text-center shadow-lg"
            >
              {[myTeam, 1 - myTeam].map((team, i) => (
                <div key={team} className={`px-3 py-1 ${i === 0 ? 'border-r border-gold/25' : ''}`}>
                  <dt className="text-[0.7rem] font-bold text-gold-light min-[641px]:text-xs">
                    {teamName(team)}
                  </dt>
                  <dd className="text-lg font-extrabold tabular-nums min-[641px]:text-xl">
                    {format(view.scores[team] ?? 0)}
                  </dd>
                </div>
              ))}
            </dl>
            <p className="rounded-full bg-black/50 px-2 py-0.5 text-[0.65rem] text-white/70 min-[641px]:text-xs">
              {t('blot.target')} {format(view.target)} · {t('blot.deal')} {view.hand_no}
            </p>
            {contract ? (
              <p className="flex items-center gap-1.5 rounded-full border border-gold/40 bg-black/60 px-2 py-0.5 text-[0.7rem] min-[641px]:text-xs">
                {/* During the bazar it's only the highest bid so far; trump once the bazar is over. */}
                <span className="text-white/70">
                  {view.phase === 'bidding' ? t('blot.highestBid') : t('blot.trump')}
                </span>
                <span
                  className={`rounded bg-card px-1 font-extrabold ${red(contract.suit) ? 'text-suit-red' : 'text-suit-black'}`}
                >
                  {contractText(contract)}
                </span>
                <span className="font-bold text-gold-light">×{multiplier(contract)}</span>
              </p>
            ) : null}
            {view.phase === 'playing' ? (
              <p className="rounded-full bg-black/50 px-2 py-0.5 text-[0.65rem] text-white/80 min-[641px]:text-xs">
                {t('blot.tricksWon', {
                  us: view.tricks[myTeam] ?? 0,
                  them: view.tricks[1 - myTeam] ?? 0,
                })}{' '}
                · {t('blot.tricksLeft', { count: tricksLeft })}
              </p>
            ) : null}
            {(view.phase === 'bidding' || view.phase === 'answer') &&
            view.opener !== null &&
            !dealing ? (
              <p className="rounded-full bg-black/50 px-2 py-0.5 text-[0.65rem] text-white/80 min-[641px]:text-xs">
                {t('blot.openedBy', { name: name(view.opener) })}
              </p>
            ) : null}
          </div>

          {/* Quansh: said against the contract, from your seat. */}
          {has('contra') && !dealing ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'contra' })}
              className="absolute bottom-[25%] left-1/2 z-30 -translate-x-1/2 rounded-2xl border-2 border-[#f0a090]/60 bg-linear-to-b from-[#a8473c] to-[#6e2620] px-8 py-2.5 text-lg font-extrabold text-white shadow-[0_10px_24px_rgb(0_0_0/0.5)] transition hover:brightness-110 active:translate-y-px min-[641px]:bottom-[19%]"
            >
              {t('blot.call.contra')}
            </button>
          ) : null}

          {/* Your combinations, to announce before your first card. */}
          {declares.length > 0 ? (
            <section
              aria-label={t('blot.yourDeclarations')}
              className="absolute bottom-[25%] left-1/2 z-30 flex w-max max-w-[92%] -translate-x-1/2 flex-wrap items-center justify-center gap-2 rounded-2xl border border-gold/40 bg-black/70 px-3 py-2 min-[641px]:bottom-[19%]"
            >
              <span className="text-xs font-semibold text-gold-light">
                {t('blot.yourDeclarations')}
              </span>
              {view.my_declarations.map((d) => {
                const action = declares.find((a) => a.index === d.index)
                const text = `${t(`blot.declaration.${d.kind}`)} +${d.points}`
                return action ? (
                  <button
                    key={d.index}
                    type="button"
                    disabled={pending}
                    onClick={() => send(action)}
                    aria-label={t('blot.declareIt', {
                      what: text,
                      cards: d.cards.map(labels.card).join(', '),
                    })}
                    className="min-h-9 rounded-full border border-gold bg-gold/20 px-3 text-xs font-bold text-gold-light hover:bg-gold/30"
                  >
                    {t('blot.declare')} · {text}
                  </button>
                ) : null
              })}
            </section>
          ) : null}

          {/* Your hand, fanned along the bottom; the cards you may play stand up. */}
          <section
            aria-label={t('blot.yourCards')}
            className="absolute inset-x-0 bottom-0 z-20 flex justify-center pb-1"
          >
            <div className="flex items-end">
              {shownHand.map((card, i) => {
                const canPlay = playable.has(card) && !dealing
                const mid = (shownHand.length - 1) / 2
                const offset = i - mid
                return (
                  <button
                    key={card}
                    type="button"
                    disabled={!canPlay || pending}
                    onClick={() => send({ type: 'play', card })}
                    aria-label={labels.card(card)}
                    className={`relative origin-bottom rounded-lg transition-transform duration-200 ${i > 0 ? '-ml-9 min-[641px]:-ml-7' : ''} ${
                      canPlay
                        ? 'z-10 hover:z-20 focus-visible:z-20'
                        : view.phase === 'playing' && view.to_act === me
                          ? 'brightness-50'
                          : ''
                    } ${dealing ? 'motion-safe:animate-card-in' : ''}`}
                    style={{
                      transform: `translateY(${offset * offset * (narrow ? 2.5 : 3) - (canPlay ? 10 : 0)}px) rotate(${offset * (narrow ? 4 : 3.5)}deg)`,
                    }}
                  >
                    <span
                      className={`block ${canPlay ? 'motion-safe:transition-transform motion-safe:hover:-translate-y-4' : ''}`}
                    >
                      <PlayingCard
                        card={card}
                        label={labels.card(card)}
                        size={narrow ? 'md' : 'lg'}
                      />
                    </span>
                  </button>
                )
              })}
            </div>
          </section>
        </div>
      </div>
      {playable.size > 0 && !dealing ? (
        <p className="text-center text-sm font-semibold text-gold-light" aria-live="polite">
          {declares.length > 0 ? t('blot.declareFirst') : t('blot.yourTurn')}
        </p>
      ) : null}
      <EventTicker log={view.log} describe={describeWithName} />
    </div>
  )

  /**
   * The bazar on your turn, in the middle of the table: pick a suit or Boy, set the number with − and +
   * (or KP for kaput), then Bid — or Pass. After a Quansh: Sur or Pass.
   */
  function bidControls() {
    if (has('recontra')) {
      return (
        <div className="flex w-full max-w-sm flex-col gap-2 rounded-2xl border border-gold/40 bg-[#2a1a12]/95 p-3 shadow-[0_16px_40px_rgb(0_0_0/0.55)]">
          <p className="text-center text-sm font-semibold text-gold-light">{t('blot.surPrompt')}</p>
          <div className="grid grid-cols-2 gap-2">
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'pass' })}
              className={panelButton}
            >
              {t('blot.call.pass')}
            </button>
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'recontra' })}
              className={`${panelButton} border-[#e8c66a] bg-linear-to-b from-[#fbe3a0] to-[#e3b955] text-[#2a1b0c]`}
            >
              {t('blot.call.recontra')}
            </button>
          </div>
        </div>
      )
    }
    const kaputOnly = !bidRange && kaputs.length > 0
    const isKaput = kaputMode || kaputOnly
    const chosen = suit
    const canBid = chosen !== null && (isKaput ? kaputs.length > 0 : !!bidRange)
    return (
      <div className="flex w-full max-w-sm flex-col gap-2 rounded-2xl border border-gold/40 bg-[#2a1a12]/95 p-2.5 shadow-[0_16px_40px_rgb(0_0_0/0.55)] min-[641px]:gap-3 min-[641px]:p-3">
        {bidRange || kaputs.length > 0 ? (
          <>
            <div
              className="grid grid-cols-5 gap-1.5"
              role="radiogroup"
              aria-label={t('blot.trump')}
            >
              {BID_SUITS.map((s) => (
                <button
                  key={s}
                  type="button"
                  role="radio"
                  aria-checked={chosen === s}
                  aria-label={s === 'N' ? t('blot.noTrump') : labels.suit(s)}
                  onClick={() => setSuit(s)}
                  className={`flex h-11 items-center justify-center rounded-xl border font-bold shadow transition min-[641px]:h-12 ${
                    chosen === s
                      ? 'border-[#e8c66a] bg-linear-to-b from-[#fbe3a0] to-[#e3b955] ring-2 ring-[#e8c66a]/50'
                      : 'border-black/20 bg-card hover:brightness-95'
                  } ${s === 'N' ? 'text-sm text-ink' : `text-2xl ${red(s) ? 'text-suit-red' : 'text-suit-black'}`}`}
                >
                  {suitLabel(s)}
                </button>
              ))}
            </div>
            <div className="flex items-center gap-1.5 rounded-xl bg-black/30 p-1.5">
              <button
                type="button"
                aria-label={t('blot.lower')}
                disabled={isKaput || !bidRange || bidValue <= bidRange.min}
                onClick={() => setValue(bidValue - 1)}
                className={stepButton}
              >
                −
              </button>
              <span
                className="flex-1 text-center text-2xl font-extrabold text-gold-light tabular-nums"
                aria-live="polite"
              >
                {isKaput ? t('blot.kaputShort') : bidValue}
              </span>
              <button
                type="button"
                aria-label={t('blot.higher')}
                disabled={isKaput || !bidRange || bidValue >= bidRange.max}
                onClick={() => setValue(bidValue + 1)}
                className={stepButton}
              >
                +
              </button>
              {kaputs.length > 0 && !kaputOnly ? (
                <button
                  type="button"
                  aria-pressed={kaputMode}
                  aria-label={t('blot.bidKaput')}
                  onClick={() => setKaputMode((k) => !k)}
                  className={`${stepButton} w-auto px-3 text-sm ${kaputMode ? 'border-[#e8c66a] text-gold-light' : ''}`}
                >
                  {t('blot.kaputButton')}
                </button>
              ) : null}
            </div>
          </>
        ) : null}
        <div className="grid grid-cols-2 gap-2">
          <button
            type="button"
            disabled={pending || !has('pass')}
            onClick={() => send({ type: 'pass' })}
            className={panelButton}
          >
            {t('blot.call.pass')}
          </button>
          <button
            type="button"
            disabled={pending || !canBid}
            aria-label={
              chosen === null
                ? t('blot.pickSuit')
                : isKaput
                  ? t('blot.kaputIn', {
                      suit: chosen === 'N' ? t('blot.noTrump') : labels.suit(chosen),
                    })
                  : t('blot.bidIn', {
                      value: bidValue,
                      suit: chosen === 'N' ? t('blot.noTrump') : labels.suit(chosen),
                    })
            }
            onClick={() =>
              chosen !== null &&
              send(
                isKaput
                  ? { type: 'kaput', suit: chosen }
                  : { type: 'bid', value: bidValue, suit: chosen },
              )
            }
            className={`${panelButton} border-[#e8c66a] bg-linear-to-b from-[#fbe3a0] to-[#e3b955] text-[#2a1b0c] disabled:from-[#6b5a3a] disabled:to-[#4d3f28] disabled:text-white/60`}
          >
            {t('blot.bidAction')}
          </button>
        </div>
      </div>
    )
  }
}

/** Packets of the deal: three, two, three cards to each of the four players in turn. */
const PACKETS = [3, 3, 3, 3, 2, 2, 2, 2, 3, 3, 3, 3]

const panelButton =
  'min-h-11 rounded-xl border border-white/15 bg-linear-to-b from-[#5a3a28] to-[#3a2418] px-3 text-base font-bold text-white shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-50'

const stepButton =
  'flex size-10 items-center justify-center rounded-lg border border-white/15 bg-linear-to-b from-[#5a3a28] to-[#3a2418] text-xl font-bold text-white disabled:opacity-40'

/** A round avatar with the player's initial; the turn ring shows the time left; level at the bottom right. */
function Avatar({
  name,
  acting,
  share,
  level,
  ours,
}: {
  name: string
  acting: boolean
  share: number | null
  level?: number
  ours: boolean
}) {
  const ring =
    acting && share !== null
      ? `conic-gradient(#3ccf6e ${share * 360}deg, rgb(255 255 255 / 0.15) 0)`
      : acting
        ? '#3ccf6e'
        : ours
          ? 'linear-gradient(160deg,#f3d27a,#b98a35)'
          : 'linear-gradient(160deg,#c9c2b0,#7d766a)'
  return (
    <span
      className={`relative block rounded-full p-[3px] shadow-[0_8px_20px_rgb(0_0_0/0.55)] ${acting ? 'motion-safe:animate-turn-glow' : ''}`}
      style={{ background: ring }}
    >
      <span className="flex size-12 items-center justify-center rounded-full bg-[radial-gradient(circle_at_50%_35%,#5b4334,#2a1b12)] text-xl font-extrabold text-white uppercase shadow-[inset_0_0_0_2px_rgb(0_0_0/0.35)] min-[641px]:size-16 min-[641px]:text-2xl">
        {name.slice(0, 1)}
      </span>
      {level !== undefined ? (
        <LevelBadge level={level} size="sm" className="absolute -right-2 bottom-0" />
      ) : null}
    </span>
  )
}

/** Log entries worth a banner: the moments everyone at the table should notice. */
const ANNOUNCED = new Set([
  'declare',
  'blot',
  'reblot',
  'contra',
  'recontra',
  'kaput',
  'kaput_failed',
  'contract',
  'hand_over',
])

/**
 * Announces the important moments of a deal to the whole table as they happen — a declaration with its
 * cards turning over, Blot and Reblot, Quansh and Sur, a kaput bid, the bazar won, a kaput or reverse kaput
 * — as a one-line banner across the top of the table frame (it covers neither the trick nor any cards).
 * Each stays 3.5 s (or until closed) and they queue; what happened before the page opened isn't announced
 * again (the ticker below has it).
 */
function TableAnnouncement({
  view,
  name,
  levels,
  describe,
}: {
  view: BlotView
  name: (seat: number) => string
  levels: Partial<Record<number, number>>
  describe: (entry: LogEntry) => { title: string; detail: string }
}) {
  const { t } = useTranslation()
  const labels = useCardLabel()
  const entries = view.log
    .map((entry, i) => ({ key: `${view.hand_no}:${i}`, entry }))
    .filter(({ entry }) => ANNOUNCED.has(entry.type))
  const [seen, setSeen] = useState(() => new Set(entries.map((e) => e.key)))
  const current = entries.find((e) => !seen.has(e.key))
  const currentKey = current?.key
  useEffect(() => {
    if (!currentKey) return
    const timer = window.setTimeout(() => setSeen((s) => new Set(s).add(currentKey)), 3500)
    return () => window.clearTimeout(timer)
  }, [currentKey])
  if (!current) return null

  const { entry } = current
  const seat = 'seat' in entry ? entry.seat : null
  const cards = entry.type === 'declare' ? entry.cards : 'card' in entry ? [entry.card] : []
  const { title, detail } = describe(entry)
  return (
    <div
      role="status"
      aria-label={[
        seat !== null ? name(seat) : '',
        title,
        detail,
        cards.map(labels.card).join(', '),
      ]
        .filter(Boolean)
        .join(' · ')}
      className="absolute inset-x-2 top-1 z-40 mx-auto flex max-w-xl items-center gap-2 rounded-2xl border border-gold bg-[#1c120d]/95 px-2 py-1.5 shadow-[0_10px_30px_rgb(0_0_0/0.6),0_0_0_3px_rgb(214_171_78/0.25)] motion-safe:animate-rise min-[641px]:top-2 min-[641px]:right-auto min-[641px]:left-3 min-[641px]:w-[min(26rem,40%)] min-[641px]:gap-3 min-[641px]:px-3"
    >
      {seat !== null ? (
        <span className="relative flex size-9 shrink-0 items-center justify-center rounded-xl bg-linear-to-b from-[#4a342b] to-[#1c120c] font-bold uppercase shadow-[inset_0_0_0_2px_rgb(214_171_78/0.6)]">
          {name(seat).slice(0, 1)}
          {levels[seat] !== undefined ? (
            <LevelBadge level={levels[seat] ?? 1} size="sm" className="absolute -top-2 -left-2" />
          ) : null}
        </span>
      ) : null}
      <span className="min-w-0 shrink">
        {seat !== null ? (
          <span className="block truncate text-xs font-semibold text-white/80">{name(seat)}</span>
        ) : null}
        <span className="text-gold-gradient block text-base leading-tight font-extrabold tracking-wide whitespace-nowrap uppercase min-[641px]:text-lg">
          {title}
        </span>
        {detail ? <span className="block truncate text-xs text-gold-light">{detail}</span> : null}
      </span>
      {cards.length > 0 ? (
        <span className="ml-auto flex shrink-0" aria-hidden="true">
          {cards.map((card, i) => (
            <span
              key={card}
              className={`${i > 0 ? '-ml-3 min-[641px]:ml-1' : ''} motion-safe:animate-card-flip`}
              style={{ animationDelay: `${150 + i * 140}ms` }}
            >
              <PlayingCard card={card} label={labels.card(card)} size="xs" />
            </span>
          ))}
        </span>
      ) : null}
      <button
        type="button"
        onClick={() => setSeen((s) => new Set(s).add(current.key))}
        aria-label={t('blot.closeAnnouncement')}
        className={`size-8 shrink-0 rounded-full border border-white/20 text-white/70 hover:text-white ${cards.length > 0 ? '' : 'ml-auto'}`}
      >
        ×
      </button>
    </div>
  )
}

/**
 * The deal so far in one line — the latest event — with the whole list a tap away. Every player sees the
 * same events (they come from the server), and a player who reconnects finds them here.
 */
function EventTicker({
  log,
  describe,
}: {
  log: LogEntry[]
  describe: (entry: LogEntry) => { title: string; detail: string }
}) {
  const { t } = useTranslation()
  const line = (entry: LogEntry) => {
    const { title, detail } = describe(entry)
    return detail ? `${title} — ${detail}` : title
  }
  const latest = log[log.length - 1]
  if (!latest) return null
  return (
    <details className="group mx-auto mt-2 max-w-3xl rounded-xl border border-white/10 bg-black/35 px-3 py-1.5 text-xs text-white/85">
      <summary className="flex min-h-7 cursor-pointer list-none items-center gap-2">
        <span className="shrink-0 font-semibold text-gold-light">{t('blot.lastEvent')}</span>
        <span className="min-w-0 flex-1 truncate" aria-live="polite">
          {line(latest)}
        </span>
        <span className="shrink-0 text-white/50 group-open:hidden">
          {t('blot.showEvents', { count: log.length })}
        </span>
      </summary>
      <ol className="mt-1.5 max-h-40 space-y-0.5 overflow-y-auto border-t border-white/10 pt-1.5">
        {log.map((entry, i) => (
          <li key={i} className="text-white/75">
            {line(entry)}
          </li>
        ))}
      </ol>
    </details>
  )
}

/**
 * The deal's result in sai: the contract made or not, card points, declarations, blot, the contract's
 * value, what each pair scored, and the match total — and whether the match goes on.
 */
function HandSummary({
  result,
  myTeam,
  name,
  format,
  contractText,
  target,
  matchOver,
}: {
  result: NonNullable<BlotView['last_hand']>
  myTeam: number
  name: (seat: number) => string
  format: (n: number) => string
  contractText: (c: Contract) => string
  target: number
  matchOver: boolean
}) {
  const { t } = useTranslation()
  const bidders = result.contract.team
  // The contract's value goes to the bidders when made, to the defenders when not.
  const bidRow: [number, number] = [0, 0]
  bidRow[result.made ? bidders : 1 - bidders] = result.bid_sai
  // Declarations stay with their pair only in a made contract without Quansh; otherwise the pair that wins
  // the deal takes them all — show them where they went.
  const declRow: [number, number] = [...result.declaration_sai]
  if (!result.made || result.contract.doubled > 0) {
    const taker = result.made ? bidders : 1 - bidders
    declRow[taker] = result.declaration_sai[0] + result.declaration_sai[1]
    declRow[1 - taker] = 0
  }
  const rows: { label: string; values: [number, number]; strong?: boolean; note?: string }[] = [
    { label: t('blot.cardPoints'), values: result.card_points, note: t('blot.raw') },
    { label: t('blot.tricksTaken'), values: result.tricks },
    { label: t('blot.contractValue'), values: bidRow },
    { label: t('blot.declarations'), values: declRow },
    { label: t('blot.belote'), values: result.belote_sai },
    { label: t('blot.scored'), values: result.scored, strong: true },
    { label: t('blot.matchTotal'), values: result.totals, strong: true },
  ]
  const won = (result.scored[myTeam] ?? 0) > (result.scored[1 - myTeam] ?? 0)
  return (
    <section
      aria-label={t('blot.handResult')}
      className={`max-h-full w-full max-w-md overflow-y-auto rounded-2xl border p-3 text-xs motion-safe:animate-rise min-[641px]:text-sm ${won ? 'border-gold bg-black/60 shadow-[0_0_24px_rgb(214_171_78/0.35)]' : 'border-white/15 bg-black/55'}`}
    >
      <p className="mb-1 text-center font-bold" role="status">
        {t('blot.dealNumber', { n: result.number })} ·{' '}
        {t(result.made ? 'blot.made' : 'blot.failed', {
          name: name(result.contract.seat),
          contract: contractText(result.contract),
        })}
        {result.contract.doubled > 0
          ? ` · ${t(result.contract.doubled === 2 ? 'blot.call.recontra' : 'blot.call.contra')}`
          : ''}
        {result.kaput ? ` · ${t('blot.kaput')}` : ''}
      </p>
      <table className="w-full text-center tabular-nums">
        <thead>
          <tr className="text-white/60">
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
                {row.note ? <span className="text-white/45"> ({row.note})</span> : null}
              </th>
              <td>{format(row.values[myTeam] ?? 0)}</td>
              <td>{format(row.values[1 - myTeam] ?? 0)}</td>
            </tr>
          ))}
        </tbody>
      </table>
      {result.declarations.length > 0 ? (
        <ul className="mt-2 space-y-0.5 text-white/80">
          {result.declarations.map((d, i) => (
            <li key={i} className={d.counted ? '' : 'text-white/40 line-through'}>
              {name(d.seat)}: {t(`blot.declaration.${d.kind}`)} +{d.points}
            </li>
          ))}
        </ul>
      ) : null}
      <p className="mt-2 text-center text-white/70">
        {matchOver ? t('blot.matchOver') : t('blot.matchGoesOn', { target: format(target) })}
      </p>
    </section>
  )
}
