import type { CSSProperties } from 'react'
import { rankText, suitOf, suitSymbols } from './cards'

export type CardSize = 'sm' | 'md' | 'lg'

const sizes: Record<CardSize, { box: string; corner: string; pip: string }> = {
  sm: { box: 'h-16 w-11 rounded-md', corner: 'text-[0.7rem]', pip: 'text-xl' },
  md: { box: 'h-24 w-16 rounded-lg', corner: 'text-sm', pip: 'text-3xl' },
  lg: {
    box: 'h-28 w-20 rounded-lg sm:h-32 sm:w-[5.5rem]',
    corner: 'text-base',
    pip: 'text-4xl sm:text-5xl',
  },
}

/** A face-up card: rank and suit in two corners, a large suit in the middle. */
export function PlayingCard({
  card,
  label,
  small = false,
  size,
}: {
  card: string
  label: string
  /** Shorthand for size="sm". */
  small?: boolean
  size?: CardSize
}) {
  const s = sizes[size ?? (small ? 'sm' : 'md')]
  const suit = suitSymbols[suitOf(card)]
  const red = suitOf(card) === 'H' || suitOf(card) === 'D'
  const corner = (
    <span className={`flex flex-col items-center leading-none ${s.corner}`}>
      <span className="font-bold">{rankText(card)}</span>
      <span>{suit}</span>
    </span>
  )

  return (
    <span
      role="img"
      aria-label={label}
      className={`relative block shrink-0 border border-black/15 bg-card font-card shadow-[0_2px_5px_rgb(0_0_0/0.35)] ${s.box} ${
        red ? 'text-suit-red' : 'text-suit-black'
      }`}
    >
      <span aria-hidden="true" className="absolute top-1 left-1">
        {corner}
      </span>
      <span
        aria-hidden="true"
        className={`absolute inset-0 flex items-center justify-center ${s.pip}`}
      >
        {suit}
      </span>
      <span aria-hidden="true" className="absolute right-1 bottom-1 rotate-180">
        {corner}
      </span>
    </span>
  )
}

/** A face-down card. */
export function CardBack({
  small = false,
  size,
  className = '',
  style,
}: {
  small?: boolean
  size?: CardSize
  className?: string
  style?: CSSProperties
}) {
  return (
    <span
      aria-hidden="true"
      style={style}
      className={`card-back block shrink-0 ${sizes[size ?? (small ? 'sm' : 'md')].box} ${className}`}
    />
  )
}
