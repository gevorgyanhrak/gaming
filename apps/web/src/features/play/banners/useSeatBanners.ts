import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { showBanner } from './store'

type Seat = {
  seat: number
  name: string | null
  username: string | null
  result: string | null
  return_until?: string | null
  replaced?: boolean
}

/**
 * "Anna joined" / "Anna left" at any table, from the room's participants: a seat filled or emptied, or a player who
 * left a game that plays on (their result turns `abandoned`). Not for your own seat, not for the room as it was when
 * the page opened.
 */
export function useSeatBanners(participants: Seat[] | undefined, mySeat: number | null) {
  const { t } = useTranslation()
  const seenRef = useRef<Map<number, Seat> | null>(null)

  useEffect(() => {
    if (!participants) return
    const now = new Map(participants.map((p) => [p.seat, p]))
    const seen = seenRef.current
    seenRef.current = now
    if (seen === null) return
    const label = (p: Seat) => p.name ?? p.username ?? t('banners.someone')
    for (const [seat, p] of now) {
      if (seat === mySeat) continue
      const before = seen.get(seat)
      if (!before) showBanner({ kind: 'joined', title: t('banners.joined', { name: label(p) }) })
      // ADR 0028: disconnected (a few seconds to return), back in time, or the seat went to AI.
      else if (!before.replaced && p.replaced)
        showBanner({ kind: 'left', title: t('banners.replaced', { name: label(p) }) })
      else if (!before.return_until && p.return_until)
        showBanner({ kind: 'left', title: t('banners.disconnected', { name: label(p) }) })
      else if (before.return_until && !p.return_until && !p.replaced)
        showBanner({ kind: 'joined', title: t('banners.back', { name: label(p) }) })
      else if (before.result === null && p.result === 'abandoned')
        showBanner({ kind: 'left', title: t('banners.left', { name: label(p) }) })
    }
    for (const [seat, p] of seen) {
      if (seat !== mySeat && !now.has(seat))
        showBanner({ kind: 'left', title: t('banners.left', { name: label(p) }) })
    }
  }, [participants, mySeat, t])
}
