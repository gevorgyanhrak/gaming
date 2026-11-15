import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { seatTrigger } from './seatTrigger'

/**
 * Makes a board's player plate tappable like an avatar (seatTrigger: emojis, quick messages, their card) and the
 * place reactions fly to (`data-seat-anchor`), for boards that show names on plates rather than avatars.
 */
export function SeatTap({
  seat,
  name,
  className = '',
  children,
}: {
  seat: number
  name: string
  className?: string
  children: ReactNode
}) {
  const { t } = useTranslation()
  const trigger = seatTrigger(seat, t('playerCard.open', { name }))

  return (
    <span
      {...trigger}
      data-seat-anchor={seat}
      className={`cursor-pointer rounded-xl focus-visible:outline-2 focus-visible:outline-gold ${className}`}
    >
      {children}
    </span>
  )
}
