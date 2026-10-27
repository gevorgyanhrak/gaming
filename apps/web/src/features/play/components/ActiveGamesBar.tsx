import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { Container } from '@/shared/ui/Container'
import { fetchMyRooms, leaveRoom, matchmakingKey, myRoomsKey, type Room } from '../api'

/**
 * Below the header, on every page except the game itself: the games you are still in, with a link
 * back and a close button that leaves the game (a loss for a running game, closing for a waiting room),
 * so you can start a new one.
 */
export function ActiveGamesBar() {
  const { data: user } = useCurrentUser()
  const { pathname } = useLocation()
  const rooms = useQuery({
    queryKey: myRoomsKey,
    queryFn: ({ signal }) => fetchMyRooms(signal),
    enabled: Boolean(user),
    refetchInterval: 15_000,
  })
  const { refetch } = rooms

  // Games end while you are elsewhere (and you leave pages that end them): look again on every page.
  useEffect(() => {
    if (user) void refetch()
  }, [pathname, user, refetch])

  const others = (rooms.data ?? []).filter(
    (room) =>
      (room.status === 'active' || room.status === 'waiting') && pathname !== `/rooms/${room.id}`,
  )
  if (!user || others.length === 0) return null

  return (
    <div className="border-b border-accent/30 bg-accent/10">
      <Container className="space-y-1 py-2">
        {others.map((room) => (
          <ActiveGame key={room.id} room={room} />
        ))}
      </Container>
    </div>
  )
}

function ActiveGame({ room }: { room: Room }) {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const awayLimit = config.data?.play.abandon_after_seconds ?? 0
  const queryClient = useQueryClient()
  const [confirming, setConfirming] = useState(false)
  const leave = useMutation({
    mutationFn: () => leaveRoom(room.id),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: myRoomsKey })
      void queryClient.invalidateQueries({ queryKey: ['rooms', room.id] })
      void queryClient.invalidateQueries({ queryKey: matchmakingKey })
    },
  })

  return (
    <div className="flex flex-wrap items-center justify-between gap-2 text-sm" role="status">
      <p>
        {room.status === 'waiting'
          ? t('activeGame.waiting', { game: room.game.name })
          : t('activeGame.playing', { game: room.game.name })}{' '}
        <Link to={`/rooms/${room.id}`} className="font-semibold text-accent-text hover:underline">
          {t('activeGame.return')}
        </Link>
        {room.status === 'active' && awayLimit > 0 ? (
          <span className="block text-xs text-muted sm:inline sm:pl-2">
            {t('activeGame.awayLimit', { count: awayLimit })}
          </span>
        ) : null}
      </p>
      {confirming ? (
        <div className="flex items-center gap-2">
          <span className="text-muted">
            {room.status === 'waiting'
              ? t('activeGame.confirmClose')
              : t('activeGame.confirmLeave')}
          </span>
          <button
            type="button"
            disabled={leave.isPending}
            onClick={() => leave.mutate()}
            className="rounded-md bg-danger px-2.5 py-1 font-semibold text-white"
          >
            {t('activeGame.leave')}
          </button>
          <button
            type="button"
            onClick={() => setConfirming(false)}
            className="rounded-md px-2.5 py-1 text-muted hover:text-fg"
          >
            {t('activeGame.keep')}
          </button>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => setConfirming(true)}
          aria-label={t('activeGame.close', { game: room.game.name })}
          className="rounded-md p-1 text-lg leading-none text-muted hover:bg-surface-raised hover:text-fg"
        >
          <span aria-hidden="true">×</span>
        </button>
      )}
    </div>
  )
}
