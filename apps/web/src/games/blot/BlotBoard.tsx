import { useEffect, useState } from 'react'
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

  const status =
    view.phase === 'over' && !state.result
      ? secondsLeft !== null
        ? t('blot.nextHandIn', { count: secondsLeft })
        : t('blot.nextHand')
      : view.to_act === null
        ? ' '
        : view.phase === 'bidding' || view.phase === 'answer'
          ? t('blot.waitingBid', { name: name(view.to_act) })
          : t('blot.waitingFor', { name: name(view.to_act) })

  return (
    <div className="space-y-4 text-white">
      <div className="poker-stage relative rounded-3xl p-2 min-[641px]:p-6">
        <TableAnnouncement view={view} name={name} levels={levels} describe={describe} />
        {/* Match score, deal, contract and tricks, above the table. */}
        <div className="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs min-[641px]:text-sm">
          <dl className="flex flex-wrap items-center gap-2" aria-label={t('blot.score')}>
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
            <div className="flex items-center gap-1 px-1 text-white/60">
              <dt>{t('blot.deal')}</dt>
              <dd className="font-bold tabular-nums">{view.hand_no}</dd>
            </div>
          </dl>
          <div className="flex flex-wrap items-center gap-2">
            {contract ? (
              <p className="flex items-center gap-1.5 rounded-full border border-gold/40 bg-black/50 px-2.5 py-1">
                <span className="text-white/70">{t('blot.contract')}</span>
                <span
                  className={`rounded bg-card px-1.5 font-extrabold ${red(contract.suit) ? 'text-suit-red' : 'text-suit-black'}`}
                >
                  {contractText(contract)}
                </span>
                <span className="font-semibold">{name(contract.seat)}</span>
                {contract.doubled > 0 ? (
                  <span className="rounded-full bg-[#8e3a31] px-1.5 font-bold">
                    {t(contract.doubled === 2 ? 'blot.call.recontra' : 'blot.call.contra')}
                  </span>
                ) : null}
              </p>
            ) : null}
            {contract && view.phase !== 'bidding' ? (
              <p className="flex items-center gap-1.5 rounded-full border border-gold/40 bg-black/50 px-2.5 py-1">
                <span className="text-white/70">{t('blot.trump')}</span>
                <span
                  className={`rounded bg-card px-1.5 font-extrabold ${red(contract.suit) ? 'text-suit-red' : 'text-suit-black'}`}
                >
                  {suitLabel(contract.suit)}
                </span>
                <span className="font-bold text-gold-light">×{multiplier(contract)}</span>
              </p>
            ) : null}
            {view.phase === 'playing' ? (
              <p className="rounded-full border border-white/20 bg-black/40 px-2.5 py-1 text-white/80">
                {t('blot.tricksWon', {
                  us: view.tricks[myTeam] ?? 0,
                  them: view.tricks[1 - myTeam] ?? 0,
                })}{' '}
                · {t('blot.tricksLeft', { count: tricksLeft })}
              </p>
            ) : null}
            {(view.phase === 'bidding' || view.phase === 'answer') && view.opener !== null ? (
              <p className="rounded-full border border-white/20 bg-black/40 px-2.5 py-1 text-white/80">
                {t('blot.openedBy', { name: name(view.opener) })}
              </p>
            ) : null}
          </div>
        </div>

        <div className="relative mx-auto aspect-[1/1.08] w-full max-w-[min(40rem,calc(100dvh-25rem))] min-[641px]:aspect-[1.65/1] min-[641px]:max-w-[min(60rem,calc((100dvh-24rem)*1.65))]">
          <div aria-hidden="true" className="poker-rail absolute inset-0 rounded-[2.2rem]">
            <div className="blackjack-felt relative h-full w-full rounded-[1.9rem]" />
          </div>

          {/* The middle of the felt: the bazar, the trick (each card on its player's side), or the deal's result. */}
          {result ? (
            <div className="absolute inset-x-[16%] top-1/2 z-20 flex -translate-y-1/2 justify-center min-[641px]:inset-x-[24%]">
              <HandSummary
                result={result}
                myTeam={myTeam}
                name={name}
                format={format}
                contractText={contractText}
                target={view.target}
                matchOver={!!state.result}
              />
            </div>
          ) : bidding ? (
            <div className="absolute inset-x-[22%] top-1/2 z-20 -translate-y-1/2 min-[641px]:inset-x-[24%]">
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
              {view.phase === 'bidding' || view.phase === 'answer' ? (
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
              const bid =
                view.phase === 'bidding' || view.phase === 'answer' ? lastBid(seat) : undefined
              const declared = view.declared[seat] ?? []
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
                  {view.phase === 'playing' && declared.length > 0 ? (
                    // Beside the nameplate for the seats at the top and bottom edge, so the cards never
                    // reach into the trick in the middle; under it at the side seats.
                    <span
                      className={`flex flex-col items-center gap-1 ${
                        where === 0 || where === 2 ? 'absolute top-0 left-full ml-2' : ''
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
                          <span className="rounded-full bg-gold px-1.5 py-0.5 text-[0.65rem] font-extrabold text-black">
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
                    <span className="rounded-full bg-white/85 px-1.5 py-0.5 text-[0.65rem] font-extrabold text-black">
                      {t('blot.belote')}
                    </span>
                  ) : null}
                </li>
              )
            })}
          </ul>
        </div>

        {/* Your combinations, to announce before your first card. */}
        {declares.length > 0 ? (
          <section
            aria-label={t('blot.yourDeclarations')}
            className="relative z-30 mx-auto mt-2 mb-10 flex max-w-xl flex-wrap items-center justify-center gap-2 rounded-2xl border border-gold/40 bg-black/55 px-3 py-2 min-[641px]:mb-14"
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
              ) : d.declared ? (
                <span
                  key={d.index}
                  className="rounded-full bg-gold px-3 py-1 text-xs font-bold text-black"
                >
                  ✓ {text}
                </span>
              ) : null
            })}
          </section>
        ) : null}

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
            {declares.length > 0 ? t('blot.declareFirst') : t('blot.yourTurn')}
          </p>
        ) : null}
        <EventTicker log={view.log} describe={describeWithName} />
      </div>
    </div>
  )

  /** The bazar on your turn: a number (or kaput), then the suit or no-trump button bids it; pass, contra, recontra. */
  function bidControls() {
    const canNumber = !!bidRange && !kaputMode
    return (
      <div className="flex w-full flex-col items-center gap-2.5">
        {bidRange || kaputs.length > 0 ? (
          <>
            {canNumber ? (
              <div className="flex items-center gap-3">
                <button
                  type="button"
                  aria-label={t('blot.lower')}
                  disabled={bidValue <= bidRange.min}
                  onClick={() => setValue(bidValue - 1)}
                  className="size-10 rounded-full border border-gold/40 bg-black/30 text-xl font-bold disabled:opacity-40"
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
                  className="size-10 rounded-full border border-gold/40 bg-black/30 text-xl font-bold disabled:opacity-40"
                >
                  +
                </button>
              </div>
            ) : (
              <p className="text-center text-sm font-extrabold text-gold-light">
                {t('blot.kaputHint')}
              </p>
            )}
            <div className="grid w-full max-w-md grid-cols-5 gap-1.5 min-[641px]:gap-2">
              {BID_SUITS.map((suit) => (
                <button
                  key={suit}
                  type="button"
                  disabled={pending}
                  onClick={() =>
                    send(
                      canNumber ? { type: 'bid', value: bidValue, suit } : { type: 'kaput', suit },
                    )
                  }
                  aria-label={
                    canNumber
                      ? t('blot.bidIn', {
                          value: bidValue,
                          suit: suit === 'N' ? t('blot.noTrump') : labels.suit(suit),
                        })
                      : t('blot.kaputIn', {
                          suit: suit === 'N' ? t('blot.noTrump') : labels.suit(suit),
                        })
                  }
                  className={`rounded-xl border border-black/20 bg-card py-1.5 font-bold shadow-lg shadow-black/40 transition hover:brightness-95 active:translate-y-px disabled:opacity-60 min-[641px]:py-2.5 ${
                    suit === 'N' ? 'text-sm text-ink' : 'text-xl min-[641px]:text-2xl'
                  } ${red(suit) ? 'text-suit-red' : suit === 'N' ? '' : 'text-suit-black'}`}
                >
                  {suitLabel(suit)}
                </button>
              ))}
            </div>
            {kaputs.length > 0 ? (
              <button
                type="button"
                aria-pressed={kaputMode}
                onClick={() => setKaputMode((k) => !k)}
                className="min-h-9 rounded-full border border-gold/40 bg-black/30 px-3 text-xs font-semibold aria-pressed:border-gold aria-pressed:text-gold-light"
              >
                {kaputMode ? t('blot.backToNumber') : t('blot.bidKaput')}
              </button>
            ) : null}
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
          {has('contra') ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'contra' })}
              className={`${actionButton} flex-1 border-[#a8473c]/60 bg-linear-to-b from-[#8e3a31] to-[#5e211b] text-white`}
            >
              {t('blot.call.contra')}
            </button>
          ) : null}
          {has('recontra') ? (
            <button
              type="button"
              disabled={pending}
              onClick={() => send({ type: 'recontra' })}
              className={`${actionButton} flex-1 border-gold/40 bg-linear-to-b from-gold-light to-gold text-black`}
            >
              {t('blot.call.recontra')}
            </button>
          ) : null}
        </div>
      </div>
    )
  }
}

/** Log entries worth a banner: the moments everyone at the table should notice. */
const ANNOUNCED = new Set([
  'declare',
  'blot',
  'reblot',
  'contra',
  'recontra',
  'kaput',
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
      className="absolute inset-x-2 top-1 z-40 mx-auto flex max-w-xl items-center gap-2 rounded-2xl border border-gold bg-[#1c120d]/95 px-2 py-1.5 shadow-[0_10px_30px_rgb(0_0_0/0.6),0_0_0_3px_rgb(214_171_78/0.25)] motion-safe:animate-rise min-[641px]:top-2 min-[641px]:gap-3 min-[641px]:px-3"
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
  'rounded-xl border px-3 py-2.5 text-sm font-bold shadow-lg shadow-black/40 transition hover:brightness-110 active:translate-y-px disabled:opacity-60 min-[641px]:text-base'
