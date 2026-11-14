import type { CSSProperties } from 'react'
import { CardFace } from './CardFace'

export type CardSize = 'xs' | 'sm' | 'md' | 'lg' | 'fluid'

/** Card boxes (1 : 1.43); the face scales inside. Radii follow the face's corner (7 % of the width). */
const sizes: Record<CardSize, string> = {
  xs: 'h-[46px] w-8 rounded-[2.5px]',
  sm: 'h-16 w-11 rounded-[3px]',
  md: 'h-24 w-16 rounded-[5px]',
  lg: 'h-28 w-20 rounded-[6px] sm:h-32 sm:w-[5.5rem]',
  // Sized by the parent through --card-w (tables that fit the screen and hands sized to the screen).
  fluid: 'h-[calc(var(--card-w)*1.43)] w-(--card-w) rounded-[calc(var(--card-w)*0.072)]',
}

/** A face-up card (CardFace: large corner index, pips, court portraits). */
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
  return (
    <span
      role="img"
      aria-label={label}
      className={`relative block shrink-0 shadow-[0_2px_6px_rgb(0_0_0/0.35)] ${sizes[size ?? (small ? 'sm' : 'md')]}`}
    >
      <CardFace
        card={card}
        compact={
          (size ?? (small ? 'sm' : 'md')) === 'xs' || (size ?? (small ? 'sm' : 'md')) === 'sm'
        }
      />
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
      className={`card-back block shrink-0 ${sizes[size ?? (small ? 'sm' : 'md')]} ${className}`}
    />
  )
}
