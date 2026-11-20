import { useQuery } from '@tanstack/react-query'
import { useCurrentUser } from '@/features/auth/hooks'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'

export type MissionsResponse = ApiResponse<'v1.me.missions.index'>
export type Mission = MissionsResponse['data'][number]

export const missionsKey = ['me', 'missions'] as const

/** Today's missions and this week's challenges (progress from the player's real games, computed on the server). */
export function useMissions() {
  const { data: user } = useCurrentUser()
  return useQuery({
    queryKey: missionsKey,
    queryFn: ({ signal }) =>
      apiRequest<MissionsResponse>('/api/v1/me/missions', { signal }).then((r) => r.data),
    enabled: !!user,
    refetchOnWindowFocus: true,
  })
}

export const claimMission = (key: string) =>
  apiRequest<MissionsResponse>(`/api/v1/me/missions/${encodeURIComponent(key)}/claim`, {
    method: 'POST',
  }).then((r) => r.data)
