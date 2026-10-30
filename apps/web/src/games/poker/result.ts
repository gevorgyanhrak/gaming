import type { TFunction } from 'i18next'

export interface Pot {
  amount: number
  winners: number[]
  hand: string | null
}

export interface LastHand {
  number: number
  board: string[]
  shown: Record<string, string[]>
  pots: Pot[]
  /** Chips won per seat (newer servers). */
  payouts?: number[]
}

export const handKeys = {
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

const handName = (hand: string | null, t: TFunction) =>
  hand ? t(handKeys[hand as keyof typeof handKeys] ?? 'poker.hand.highCard') : null

export interface ResultSummary {
  /** Chips each winning seat took, or null where the exact share isn't known. */
  winners: Map<number, number | null>
  mine: boolean
  headline: string
  /** The winning hand, "everyone else folded", or one line per pot. */
  details: string[]
  /** One sentence for screen readers. */
  announcement: string
}

/** What the table says about a finished hand, all from the server's `last_hand`. */
export function summarize(
  last: LastHand,
  me: number | null,
  name: (seat: number) => string,
  t: TFunction,
): ResultSummary {
  const winners = new Map<number, number | null>()
  for (const pot of last.pots) {
    for (const seat of pot.winners) {
      const exact = last.payouts?.[seat]
      if (exact !== undefined) winners.set(seat, exact)
      else {
        // Without payouts: exact only when every pot this seat won splits evenly.
        const share = pot.amount % pot.winners.length === 0 ? pot.amount / pot.winners.length : null
        const sofar = winners.has(seat) ? winners.get(seat)! : 0
        winners.set(seat, sofar === null || share === null ? null : sofar + share)
      }
    }
  }

  const mine = me !== null && winners.has(me)
  const myPot = mine ? last.pots.find((pot) => pot.winners.includes(me)) : undefined
  const first = last.pots[0]
  const why = (pot: Pot | undefined) => {
    const hand = handName(pot?.hand ?? null, t)
    return hand ? t('poker.result.withHand', { hand }) : t('poker.result.othersFolded')
  }
  const names = (pot: Pot) => pot.winners.map(name).join(', ')

  let headline: string
  let details: string[]
  if (mine) {
    const total = winners.get(me)
    headline =
      total != null
        ? t('poker.result.youWin', { count: total })
        : t('poker.won', { names: name(me), count: myPot?.amount ?? 0 })
    details = [why(myPot)]
  } else if (last.pots.length === 1 && first) {
    // A split names the same amounts as the "+N" at the seats: the server's payouts when it sends
    // them (an odd chip goes to one winner), else the even share.
    const shares = first.winners.map(
      (seat) => last.payouts?.[seat] ?? Math.floor(first.amount / first.winners.length),
    )
    headline =
      first.winners.length === 1
        ? t('poker.won', { names: names(first), count: first.amount })
        : shares.every((share) => share === shares[0])
          ? t('poker.result.split', { names: names(first), count: shares[0] ?? 0 })
          : t('poker.result.splitShares', {
              shares: first.winners.map((seat, i) => `${name(seat)} ${shares[i]}`).join(', '),
            })
    details = [why(first)]
  } else {
    // Side pots: the main pot leads, one short line per side pot (at most two, then "and N more").
    const main = first!
    headline = t('poker.won', { names: names(main), count: main.amount })
    const side = last.pots.slice(1)
    details = [
      why(main),
      ...side
        .slice(0, 2)
        .map(
          (pot) =>
            `${t('poker.result.sidePot')} · ${names(pot)} · ${t('poker.chips', { count: pot.amount })}`,
        ),
    ]
    if (side.length > 2) details.push(t('poker.result.more', { count: side.length - 2 }))
  }

  return {
    winners,
    mine,
    headline,
    details,
    announcement: t('poker.result.announce', {
      hand: last.number,
      text: [headline, ...details].join(' — '),
    }),
  }
}
