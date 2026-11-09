import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { fetchRoomFishkas, walletKey } from '../api'
import { flyFishkas } from '../flyFishkas'
import { FishkaIcon } from './FishkaIcon'

/**
 * What a finished game did to the player's Fishkas, from the ledger (bet or buy-in, winnings or chips
 * back): "+N" or "−N". Once per game (not again after a reload), chips fly into the header balance for a
 * gain or out of it for a loss, and the balance is refreshed from the server.
 */
export function GameResult({ roomId }: { roomId: string }) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const box = useRef<HTMLParagraphElement>(null)
  const entries = useQuery({
    queryKey: ['me', 'wallet', 'room', roomId],
    queryFn: ({ signal }) => fetchRoomFishkas(roomId, signal),
  })
  const net = entries.data?.reduce((sum, entry) => sum + entry.amount, 0)

  useEffect(() => {
    if (net === undefined || entries.data?.length === 0) return
    const key = `fishka-result:${roomId}`
    try {
      if (sessionStorage.getItem(key)) return
      sessionStorage.setItem(key, '1')
    } catch {
      // No storage (private window): the flight may repeat on reload; the balance never does.
    }
    if (net === 0) return
    flyFishkas(
      box.current,
      net > 0 ? 'in' : 'out',
      () => void queryClient.invalidateQueries({ queryKey: walletKey }),
    )
  }, [net, roomId, entries.data, queryClient])

  if (net === undefined || entries.data?.length === 0) return null
  const amount = Math.abs(net).toLocaleString(i18n.language)

  return (
    <p
      ref={box}
      className={`mt-3 inline-flex items-center gap-2 rounded-full bg-black/30 px-4 py-1.5 text-lg font-extrabold tabular-nums ${
        net > 0 ? 'text-success' : net < 0 ? 'text-danger' : 'text-muted'
      }`}
    >
      <FishkaIcon className="size-6" />
      {net > 0
        ? t('fishka.resultWon', { amount })
        : net < 0
          ? t('fishka.resultLost', { amount })
          : t('fishka.resultEven')}
    </p>
  )
}
