import { useTranslation } from 'react-i18next'
import { suitSymbols, useCardLabel, type Suit } from '../cards/cards'
import { CardBack, PlayingCard } from '../cards/PlayingCard'

const red = (suit: Suit) => suit === 'H' || suit === 'D'

/** The rest of the deck with the trump card lying sideways under it, and a round trump badge. */
export function Deck({
  count,
  trump,
  trumpCard,
  wide,
}: {
  count: number
  trump: Suit
  trumpCard: string | null
  wide: boolean
}) {
  const { t } = useTranslation()
  const labels = useCardLabel()
  const size = wide ? 'md' : 'xs'
  // Card box per size (px): the trump sticks out to the right of the deck by about half its length.
  const [w, h] = wide ? [64, 96] : [32, 46]
  const trumpName = `${suitSymbols[trump]} ${labels.suit(trump)}`

  return (
    <div
      role="group"
      aria-label={`${t('durak.deck', { count })}. ${t('durak.trump')}: ${trumpName}${
        trumpCard ? ` (${labels.card(trumpCard)})` : ''
      }`}
      className="flex items-center gap-2"
    >
      {count > 0 ? (
        <span className="relative block" style={{ width: w + h * 0.55, height: h }}>
          {trumpCard ? (
            <span
              className="absolute top-1/2 rotate-90"
              style={{ left: w * 0.62, marginTop: -h / 2 }}
            >
              <PlayingCard
                card={trumpCard}
                label={t('durak.trumpCard', { card: labels.card(trumpCard) })}
                size={size}
              />
            </span>
          ) : null}
          {count > 1 ? (
            <>
              <CardBack size={size} className="absolute top-0 left-0" />
              <CardBack size={size} className="absolute top-0.5 left-0.5" />
              <span
                className="absolute -top-2 -translate-x-1/2 rounded-full bg-black/75 px-1.5 text-[0.7rem] font-bold tabular-nums"
                style={{ left: w - 2 }}
              >
                {count}
              </span>
            </>
          ) : null}
        </span>
      ) : null}
      <span
        aria-hidden="true"
        className={`flex size-7 shrink-0 items-center justify-center rounded-full bg-card text-lg shadow ${
          red(trump) ? 'text-suit-red' : 'text-suit-black'
        } ${count === 0 ? 'opacity-60' : ''}`}
      >
        {suitSymbols[trump]}
      </span>
      {count === 0 ? (
        <span className={`text-xs text-white/75 ${wide ? '' : 'sr-only'}`}>
          {t('durak.deckEmpty')}
        </span>
      ) : null}
    </div>
  )
}

/** Beaten cards, face down in a loose pile, with their number. Hidden until the first bout ends. */
export function Discard({ count, wide }: { count: number; wide: boolean }) {
  const { t } = useTranslation()
  if (count === 0) return null
  return (
    <div
      role="group"
      aria-label={t('durak.discarded', { count })}
      className={`relative ${wide ? 'h-20 w-16' : 'h-12 w-10'}`}
    >
      {[-14, 6, -4].map((angle, i) => (
        <CardBack
          key={i}
          size={wide ? 'sm' : 'xs'}
          className="absolute top-0 left-1"
          style={{ transform: `rotate(${angle}deg)` }}
        />
      ))}
      <span className="absolute -top-2 -right-2 rounded-full bg-black/75 px-1.5 text-[0.7rem] font-bold tabular-nums">
        {count}
      </span>
    </div>
  )
}
