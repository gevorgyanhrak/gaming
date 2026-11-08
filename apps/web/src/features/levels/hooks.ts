import { useQuery, useQueryClient } from '@tanstack/react-query'
import { authKeys, useCurrentUser } from '@/features/auth/hooks'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { fetchLevel, levelKey } from './api'
import { offerLevelUp } from './popup'

/** The signed-in player's level and progress (disabled while signed out). */
export function useLevel() {
  const { data: user } = useCurrentUser()
  return useQuery({
    queryKey: levelKey,
    queryFn: ({ signal }) => fetchLevel(signal),
    enabled: !!user,
    refetchOnWindowFocus: true,
  })
}

/**
 * Keeps the level current in every open tab: the server announces new XP ("level.updated") on the
 * player's channel. A level-up opens the popup again, even if it was put off earlier in this visit.
 */
export function useLevelSync() {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()

  useUserChannel(user?.id, {
    'level.updated': (payload: { level: number; pending: number }) => {
      const refreshed = queryClient.invalidateQueries({ queryKey: levelKey })
      if (payload.level === user?.level) return
      void queryClient.invalidateQueries({ queryKey: authKeys.me, exact: true })
      // After the refresh: until the new level-up is in the cache, the popup has nothing to show.
      if (user && payload.pending > 0) void refreshed.then(() => offerLevelUp(user.id, true))
    },
  })
}
