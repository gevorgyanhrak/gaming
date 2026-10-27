import { useTranslation } from 'react-i18next'

export type Suit = 'S' | 'H' | 'D' | 'C'

export const suitSymbols: Record<Suit, string> = { S: '♠', H: '♥', D: '♦', C: '♣' }

const suitKeys = {
  S: 'cards.suit.spades',
  H: 'cards.suit.hearts',
  D: 'cards.suit.diamonds',
  C: 'cards.suit.clubs',
} as const

const rankKeys = {
  J: 'cards.rank.jack',
  Q: 'cards.rank.queen',
  K: 'cards.rank.king',
  A: 'cards.rank.ace',
} as const

/** Cards are rank + suit, e.g. "TH" (ten of hearts) or "AS". */
export const suitOf = (card: string) => (card[1] ?? 'S') as Suit
export const rankText = (card: string) => (card[0] === 'T' ? '10' : (card[0] ?? ''))

/** "Queen of clubs" (localized). */
export function useCardLabel() {
  const { t } = useTranslation()

  return {
    card: (card: string) => {
      const rank = card[0] ?? ''
      const rankName =
        rank in rankKeys ? t(rankKeys[rank as keyof typeof rankKeys]) : rankText(card)
      return t('cards.card', { rank: rankName, suit: t(suitKeys[suitOf(card)]) })
    },
    suit: (suit: Suit) => t(suitKeys[suit]),
  }
}
