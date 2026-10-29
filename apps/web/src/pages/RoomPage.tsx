import { Suspense, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { useMessage } from '@/features/auth/forms'
import { useCurrentUser } from '@/features/auth/hooks'
import { leaveRoom, type ParticipantResult } from '@/features/play/api'
import { resultLabel } from '@/features/play/labels'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { GameOverPanel } from '@/features/play/components/GameOverPanel'
import { PlayerList } from '@/features/play/components/PlayerList'
import { WaitingTable } from '@/features/play/components/WaitingTable'
import { waitingCaption } from '@/features/play/turnCaption'
import { useCountdown } from '@/features/play/useCountdown'
import { useGameSounds } from '@/features/play/useGameSounds'
import { usePresence } from '@/features/play/usePresence'
import { useRoom } from '@/features/play/useRoom'
import { boards, traitsOf } from '@/games/registry'
import { isApiError } from '@/shared/api/errors'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

export function RoomPage() {
  const { t } = useTranslation()
  const message = useMessage()
  const { id = '' } = useParams()
  const { room, state, act } = useRoom(id)
  const { data: user } = useCurrentUser()
  const [confirmResign, setConfirmResign] = useState(false)
  const secondsLeft = useCountdown(state.data?.deadline, state.data?.server_time)
  // Report presence while playing: leaving the page for too long loses the game.
  usePresence(id, room.data?.status === 'active' && state.data?.seat != null && !state.data?.result)
  const mySeat = state.data?.seat
  const stillPlaying =
    mySeat != null && room.data?.participants.find((p) => p.seat === mySeat)?.result == null
  useGameSounds({
    roomId: id,
    state: state.data,
    waiting: room.data?.status === 'waiting',
    seats: room.data?.participants.length ?? 2,
    traits: traitsOf(room.data?.game.slug ?? ''),
    // Players who left a table that plays on don't hear it; the game's end still sounds.
    enabled:
      Boolean(user?.profile.preferences.sound) && (stillPlaying || Boolean(state.data?.result)),
    secondsLeft,
  })

  if (room.isPending) {
    return (
      <Container className="space-y-4 py-10">
        <Skeleton className="h-10 w-1/3" />
        <Skeleton className="h-64 w-full" />
      </Container>
    )
  }

  if (room.isError) {
    return (
      <Container className="py-10">
        {isApiError(room.error) && (room.error.status === 403 || room.error.isNotFound) ? (
          <EmptyState title={t('room.notFound')} />
        ) : (
          <QueryErrorState error={room.error} onRetry={() => void room.refetch()} />
        )}
      </Container>
    )
  }

  const data = room.data
  const view = state.data
  const myTurn = view ? view.seat !== null && view.seats_to_act.includes(view.seat) : false
  const Board = boards[data.game.slug]
  const leave = () =>
    void leaveRoom(id)
      .then(() => room.refetch())
      .then(() => state.refetch())
  // Chess clocks: the running clock counts down with the deadline, the others stand still.
  const clocks =
    view?.clocks && data.status === 'active'
      ? (view.clocks as number[]).map((ms, seat) =>
          view.seats_to_act.includes(seat) ? secondsLeft : Math.ceil(ms / 1000),
        )
      : null
  const myResult =
    view?.seat != null ? data.participants.find((p) => p.seat === view.seat)?.result : null

  const playerNames: Partial<Record<number, string>> = Object.fromEntries(
    data.participants.map((p) => [p.seat, p.name ?? '']),
  )
  // Left a multi-player game that goes on without you.
  const leftTable = data.status === 'active' && myResult != null
  // Boards that seat every player show them already while the game is on.
  const showPlayers =
    data.status !== 'waiting' &&
    !(traitsOf(data.game.slug).seatsOnBoard && data.status === 'active')
  const tabTitle =
    data.status === 'active' && myTurn && !leftTable
      ? t('room.tab.yourTurn', { game: data.game.name })
      : data.status === 'finished' && myResult
        ? t('room.tab.finished', {
            game: data.game.name,
            result: t(resultLabel[myResult as ParticipantResult]),
          })
        : data.game.name

  return (
    <Container className="space-y-6 py-8">
      <title>{tabTitle}</title>
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="flex size-11 items-center justify-center rounded-xl bg-gold/15">
            <GameIcon slug={data.game.slug} name={data.game.name} className="size-6 text-gold" />
          </span>
          <div>
            <h1 className="text-2xl font-bold">{data.game.name}</h1>
            <p className="text-sm text-muted">{data.mode.name}</p>
          </div>
        </div>
        {data.status === 'active' && view ? (
          <div
            className={`rounded-2xl px-4 py-2 text-right ${myTurn ? 'bg-linear-to-b from-gold-light via-gold to-gold-dark text-black shadow-lg shadow-gold/20' : 'border border-border bg-surface'}`}
            role="status"
          >
            <p className="max-w-48 truncate font-semibold">
              {myTurn
                ? t('room.yourTurn')
                : (waitingCaption(view, playerNames, t) ?? t('room.theirTurn'))}
            </p>
            {secondsLeft !== null && !clocks ? (
              <p className={`text-sm ${myTurn ? 'text-black/70' : 'text-muted'}`}>
                {t('room.secondsLeft', { count: secondsLeft })}
              </p>
            ) : null}
          </div>
        ) : null}
      </header>

      {data.status === 'waiting' ? (
        <WaitingTable room={data} mySeat={view?.seat ?? 0} onLeave={leave} />
      ) : null}

      {showPlayers ? (
        <PlayerList
          room={data}
          mySeat={view?.seat ?? null}
          seatsToAct={view?.seats_to_act ?? []}
          clocks={clocks}
        />
      ) : null}

      {data.status === 'finished' || data.status === 'aborted' || leftTable ? (
        <GameOverPanel
          room={data}
          result={(myResult ?? null) as ParticipantResult | null}
          leftTable={leftTable}
        />
      ) : null}

      {act.error ? <Alert tone="danger">{message(act.error.message)}</Alert> : null}

      {view && data.status !== 'waiting' ? (
        Board ? (
          <Suspense fallback={<Skeleton className="h-96 w-full" />}>
            <Board
              state={view}
              onAction={(action) => act.mutate(action)}
              pending={act.isPending}
              players={playerNames}
              bots={Object.fromEntries(data.participants.map((p) => [p.seat, p.is_bot]))}
            />
          </Suspense>
        ) : (
          <EmptyState title={t('room.boardUnavailable')} />
        )
      ) : null}

      <footer className="flex flex-wrap gap-3">
        {data.status === 'active' && !leftTable ? (
          confirmResign ? (
            <>
              <Button
                variant="primary"
                onClick={() => {
                  setConfirmResign(false)
                  leave()
                }}
              >
                {t('room.confirmResign')}
              </Button>
              <Button variant="secondary" onClick={() => setConfirmResign(false)}>
                {t('room.keepPlaying')}
              </Button>
            </>
          ) : (
            <Button variant="secondary" onClick={() => setConfirmResign(true)}>
              {t('room.resign')}
            </Button>
          )
        ) : null}
        <Link
          to="/dashboard"
          className="self-center text-sm font-medium text-accent-text hover:underline"
        >
          {t('nav.dashboard')}
        </Link>
      </footer>
    </Container>
  )
}
