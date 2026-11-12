import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { Button } from '@/shared/ui/Button'
import { cancelMatchmaking, fetchMatchmaking, matchmakingHeartbeat, matchmakingKey } from '../api'

/**
 * While searching: who's waiting and cancel. Goes to the room once matched (event, or the polled status as a
 * fallback). Sends a heartbeat so abandoned searches are dropped. When people can't be found in time the
 * server fills the seats with AI; nothing here tells the player so.
 */
export function MatchmakingStatus() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()

  const status = useQuery({
    queryKey: matchmakingKey,
    queryFn: ({ signal }) => fetchMatchmaking(signal),
    refetchInterval: 5000,
  })
  const ticket = status.data?.ticket ?? null
  const room = status.data?.room ?? null

  useEffect(() => {
    if (room) void navigate(`/rooms/${room}`)
  }, [room, navigate])

  useEffect(() => {
    if (!ticket) return
    const timer = setInterval(() => void matchmakingHeartbeat().catch(() => undefined), 15_000)
    return () => clearInterval(timer)
  }, [ticket])

  useUserChannel(user?.id, {
    'matchmaking.matched': (payload: { room: string }) => void navigate(`/rooms/${payload.room}`),
  })

  const cancel = useMutation({
    mutationFn: cancelMatchmaking,
    onSuccess: () => queryClient.setQueryData(matchmakingKey, { ticket: null, room: null }),
  })

  if (!ticket) return null

  return (
    <div className="space-y-3 rounded-xl border border-accent/40 bg-accent/10 p-4" role="status">
      <p className="font-medium">{t('play.searching')}</p>
      {(status.data?.waiting ?? 0) > 1 ? (
        <p className="text-sm">{t('play.tableWaiting', { count: status.data?.waiting ?? 0 })}</p>
      ) : null}
      <div className="flex flex-wrap gap-2">
        <Button variant="secondary" onClick={() => cancel.mutate()} loading={cancel.isPending}>
          {t('play.cancelSearch')}
        </Button>
      </div>
    </div>
  )
}
