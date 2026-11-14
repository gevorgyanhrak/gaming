import { useQuery } from '@tanstack/react-query'
import { apiRequest } from '@/shared/api/http'
import type { Schemas } from '@/shared/api/types'

export type PlayerCard = Schemas['PlayerCardResource']

/** A person's card for the popup (GET /users/{username}/card); off for AI (no account). */
export function usePlayerCard(username: string | null) {
  return useQuery({
    queryKey: ['users', username, 'card'],
    queryFn: ({ signal }) =>
      apiRequest<{ data: PlayerCard }>(`/api/v1/users/${encodeURIComponent(username ?? '')}/card`, {
        signal,
      }).then((r) => r.data),
    enabled: username !== null,
  })
}
