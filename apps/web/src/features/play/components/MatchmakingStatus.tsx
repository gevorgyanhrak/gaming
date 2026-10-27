import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { useMessage } from '@/features/auth/forms'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import {
  acceptBotGame,
  cancelMatchmaking,
  fetchMatchmaking,
  matchmakingHeartbeat,
  matchmakingKey,
} from '../api'
import { useCountdown } from '../useCountdown'

/**
 * While searching: elapsed state, cancel, and the bot offer. Goes to the room once matched (event,
 * or the polled status as a fallback). Sends a heartbeat so abandoned searches are dropped.
 */
export function MatchmakingStatus() {
  const { t } = useTranslation()
  const message = useMessage()
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
  const botIn = useCountdown(ticket?.status === 'queued' ? ticket.bot_offer_at : null, null)

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
    'matchmaking.bot_offered': () =>
      void queryClient.invalidateQueries({ queryKey: matchmakingKey }),
  })

  const cancel = useMutation({
    mutationFn: cancelMatchmaking,
    onSuccess: () => queryClient.setQueryData(matchmakingKey, { ticket: null, room: null }),
  })
  const accept = useMutation({
    mutationFn: () => acceptBotGame(),
    onSuccess: (result) => {
      if (result.room) void navigate(`/rooms/${result.room}`)
    },
  })

  if (!ticket) return null

  return (
    <div className="space-y-3 rounded-xl border border-accent/40 bg-accent/10 p-4" role="status">
      <p className="font-medium">{t('play.searching')}</p>
      {ticket.status === 'bot_offered' ? (
        <p className="text-sm">{t('play.botOffer')}</p>
      ) : botIn !== null && botIn > 0 ? (
        <p className="text-sm text-muted">{t('play.botIn', { count: botIn })}</p>
      ) : null}
      {accept.error ? <Alert tone="danger">{message(accept.error.message)}</Alert> : null}
      <div className="flex flex-wrap gap-2">
        {ticket.status === 'bot_offered' ? (
          <Button onClick={() => accept.mutate()} disabled={accept.isPending}>
            {t('play.acceptBot')}
          </Button>
        ) : null}
        <Button variant="secondary" onClick={() => cancel.mutate()} disabled={cancel.isPending}>
          {t('play.cancelSearch')}
        </Button>
      </div>
    </div>
  )
}
