import { useQuery } from '@tanstack/react-query'
import { useRealtimeConnected } from '@/shared/realtime/useRealtimeConnected'
import { fetchWins, freshWinKey, winsKey } from './api'

/**
 * Big Wins live: the server's latest wins of the week (newest first; people's and AI players', marked), kept up to date by the app's one "wins"
 * listener (BigWinCelebration, in the layout) and polled while the socket is down; and which win just arrived.
 */
export function useWinsFeed() {
  const connected = useRealtimeConnected()
  const wins = useQuery({
    queryKey: winsKey,
    queryFn: ({ signal }) => fetchWins(signal),
    refetchInterval: connected ? false : 60_000,
  })
  const fresh = useQuery<string | null>({
    queryKey: freshWinKey,
    queryFn: () => null,
    enabled: false,
    initialData: null,
  })

  return { wins, fresh: fresh.data }
}
