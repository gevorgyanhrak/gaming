import { rankText, suitOf, suitSymbols } from './cards'

export function PlayingCard({
  card,
  label,
  small = false,
}: {
  card: string
  label: string
  small?: boolean
}) {
  const red = suitOf(card) === 'H' || suitOf(card) === 'D'
  return (
    <span
      role="img"
      aria-label={label}
      className={`flex flex-col items-center justify-center rounded-lg border border-black/20 bg-white font-bold shadow ${
        small ? 'h-16 w-11 text-base' : 'h-24 w-16 text-xl'
      } ${red ? 'text-red-600' : 'text-neutral-900'}`}
    >
      <span>{rankText(card)}</span>
      <span aria-hidden="true">{suitSymbols[suitOf(card)]}</span>
    </span>
  )
}

/** A face-down card. */
export function CardBack({ small = false }: { small?: boolean }) {
  return (
    <span
      aria-hidden="true"
      className={`block rounded-lg border border-white/20 bg-accent/60 ${small ? 'h-16 w-11' : 'h-24 w-16'}`}
    />
  )
}
