import { useQuery } from '@tanstack/react-query'
import { useRealtimeConnected } from '@/shared/realtime/useRealtimeConnected'
import { fetchWins, freshWinKey, winsKey } from './api'

/**
 * Big Wins live: the server's biggest wins of the week (biggest first), kept up to date by the app's one "wins"
 * listener (BigWinCelebration, in the layout) and polled while the socket is down; and which win just arrived.
 */
export function useWinsFeed() {
  const connected = useRealtimeConnected()
  const wins = useQuery({
    queryKey: winsKey,
    queryFn: ({ signal }) => fetchWins(signal),
    refetchInterval: connected ? false : 60_000,
  })
  const fresh = useQuery<number | null>({
    queryKey: freshWinKey,
    queryFn: () => null,
    enabled: false,
    initialData: null,
  })

  return { wins, fresh: fresh.data }
}
