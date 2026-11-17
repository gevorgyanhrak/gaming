import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http'

/** How often to ask; the server asks Reverb at most every 10 s. */
const EVERY_MS = 30_000

/**
 * People connected right now (Reverb's "online" presence channel), or null while unknown or when the server
 * can't count — never a made-up number. One query shared by everything that shows it.
 */
export function useOnlineCount(): number | null {
  const online = useQuery({
    queryKey: ['online'],
    queryFn: ({ signal }) =>
      apiRequest<{ data: { online: number | null } }>('/api/v1/stats/online', { signal }).then(
        (r) => r.data,
      ),
    refetchInterval: EVERY_MS,
  })
  return online.data?.online ?? null
}
