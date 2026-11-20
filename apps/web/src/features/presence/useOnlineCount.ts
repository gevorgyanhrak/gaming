import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'

/** How often to ask; the server asks Reverb at most every 10 s and moves the demo figure once a minute. */
const EVERY_MS = 30_000

type OnlineStats = ApiResponse<'v1.stats.online'>['data']

function useOnlineStats() {
  return useQuery({
    queryKey: ['online'],
    queryFn: ({ signal }) =>
      apiRequest<ApiResponse<'v1.stats.online'>>('/api/v1/stats/online', { signal }).then(
        (r): OnlineStats => r.data,
      ),
    refetchInterval: EVERY_MS,
  })
}

/**
 * People connected right now (Reverb's "online" presence channel), or null while unknown or when the server
 * can't count — never a made-up number. One query shared by everything that shows it.
 */
export function useOnlineCount(): number | null {
  return useOnlineStats().data?.online ?? null
}

/**
 * The simulated activity figure the admin can show (DemoActivity on the server): never part of the real count, and
 * every place that shows it must label it as a demo. Null when it's off or unknown.
 */
export function useDemoActivity(): number | null {
  return useOnlineStats().data?.demo ?? null
}
