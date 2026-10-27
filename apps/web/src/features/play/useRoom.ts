import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useCurrentUser } from '@/features/auth/hooks'
import { isApiError } from '@/shared/api/errors'
import { getEcho } from '@/shared/realtime/echo'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { fetchRoom, fetchRoomState, submitAction, type GameAction, type RoomState } from './api'

export const roomKeys = {
  room: (id: string) => ['rooms', id] as const,
  state: (id: string) => ['rooms', id, 'state'] as const,
}

/**
 * A room and the player's live view of it. Updates arrive on the player's channel; an update is
 * applied only if it is the next version, otherwise the state is refetched (no missed or
 * out-of-order updates). Without a WebSocket connection the state is polled instead.
 */
export function useRoom(id: string) {
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const live = getEcho() !== null

  const room = useQuery({
    queryKey: roomKeys.room(id),
    queryFn: ({ signal }) => fetchRoom(id, signal),
    refetchInterval: (query) => (!live && query.state.data?.status === 'waiting' ? 3000 : false),
  })

  const started = room.data?.status === 'active' || room.data?.status === 'finished'
  const state = useQuery({
    queryKey: roomKeys.state(id),
    queryFn: ({ signal }) => fetchRoomState(id, signal),
    enabled: started,
    refetchInterval: !live && room.data?.status === 'active' ? 3000 : false,
  })

  useUserChannel(user?.id, {
    'room.updated': (payload: RoomState) => {
      if (payload.room !== id) return
      const current = queryClient.getQueryData<RoomState | null>(roomKeys.state(id))

      if (current && payload.version === current.version + 1) {
        queryClient.setQueryData(roomKeys.state(id), payload)
      } else if (!current || payload.version > current.version) {
        void queryClient.invalidateQueries({ queryKey: roomKeys.state(id), exact: true })
      }

      // Players, status and results live on the room itself.
      if (
        payload.status !== queryClient.getQueryData<{ status: string }>(roomKeys.room(id))?.status
      ) {
        void queryClient.invalidateQueries({ queryKey: roomKeys.room(id), exact: true })
      }
    },
  })

  const act = useMutation({
    mutationFn: (action: GameAction) => submitAction(id, action, state.data?.version ?? 0),
    onSuccess: (next) => {
      const current = queryClient.getQueryData<RoomState | null>(roomKeys.state(id))
      if (!current || next.version >= current.version)
        queryClient.setQueryData(roomKeys.state(id), next)
      if (next.status !== room.data?.status)
        void queryClient.invalidateQueries({ queryKey: roomKeys.room(id), exact: true })
    },
    onError: (error) => {
      // 409: the view was outdated (or the game ended) — refetch and let the player act again.
      if (isApiError(error) && error.status === 409) {
        void queryClient.invalidateQueries({ queryKey: ['rooms', id] })
      }
    },
  })

  return { room, state, act }
}
