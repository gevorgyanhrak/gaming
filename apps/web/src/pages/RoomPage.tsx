import { Suspense, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { useMessage } from '@/features/auth/forms'
import { leaveRoom, type ParticipantResult } from '@/features/play/api'
import { endReasonLabel, outcomeLabel } from '@/features/play/labels'
import { PlayerList } from '@/features/play/components/PlayerList'
import { useCountdown } from '@/features/play/useCountdown'
import { useRoom } from '@/features/play/useRoom'
import { boards } from '@/games/registry'
import { isApiError } from '@/shared/api/errors'
import { Alert } from '@/shared/ui/Alert'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

export function RoomPage() {
  const { t } = useTranslation()
  const message = useMessage()
  const { id = '' } = useParams()
  const { room, state, act } = useRoom(id)
  const [confirmResign, setConfirmResign] = useState(false)
  const secondsLeft = useCountdown(state.data?.deadline, state.data?.server_time)

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
  const reasonKey = data.end_reason ? endReasonLabel[data.end_reason] : undefined
  const myResult =
    view?.seat != null ? data.participants.find((p) => p.seat === view.seat)?.result : null

  return (
    <Container className="space-y-6 py-8">
      <title>{data.game.name}</title>
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{data.game.name}</h1>
          <p className="text-sm text-muted">{data.mode.name}</p>
        </div>
        {data.status === 'active' && view ? (
          <div className="text-right" role="status">
            <p className="font-semibold">{myTurn ? t('room.yourTurn') : t('room.theirTurn')}</p>
            {secondsLeft !== null ? (
              <p className="text-sm text-muted">{t('room.secondsLeft', { count: secondsLeft })}</p>
            ) : null}
          </div>
        ) : null}
      </header>

      {data.status === 'waiting' ? (
        <section className="space-y-4 rounded-2xl border border-border bg-surface p-6 text-center">
          <p className="text-muted">{t('room.waiting')}</p>
          {data.invite_code ? (
            <div>
              <p className="text-sm text-muted">{t('room.inviteCode')}</p>
              <p className="mt-1 font-mono text-3xl font-bold tracking-[0.3em]">
                {data.invite_code}
              </p>
              <Button
                variant="secondary"
                className="mt-3"
                onClick={() => void navigator.clipboard?.writeText(data.invite_code ?? '')}
              >
                {t('room.copyCode')}
              </Button>
            </div>
          ) : null}
          <Button variant="secondary" onClick={leave}>
            {t('room.cancel')}
          </Button>
        </section>
      ) : null}

      {data.status !== 'waiting' ? (
        <PlayerList room={data} mySeat={view?.seat ?? null} seatsToAct={view?.seats_to_act ?? []} />
      ) : null}

      {data.status === 'finished' ? (
        <Alert tone={myResult === 'win' ? 'success' : 'info'}>
          <p className="font-semibold">
            {myResult ? t(outcomeLabel[myResult as ParticipantResult]) : t('room.finished')}
          </p>
          {reasonKey ? <p className="mt-1">{t(reasonKey)}</p> : null}
        </Alert>
      ) : null}
      {data.status === 'aborted' ? <Alert tone="info">{t('room.aborted')}</Alert> : null}

      {act.error ? <Alert tone="danger">{message(act.error.message)}</Alert> : null}

      {view && data.status !== 'waiting' ? (
        Board ? (
          <Suspense fallback={<Skeleton className="h-96 w-full" />}>
            <Board state={view} onAction={(action) => act.mutate(action)} pending={act.isPending} />
          </Suspense>
        ) : (
          <EmptyState title={t('room.boardUnavailable')} />
        )
      ) : null}

      <footer className="flex flex-wrap gap-3">
        {data.status === 'active' ? (
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
        {data.status === 'finished' || data.status === 'aborted' ? (
          <ButtonLink to={`/games/${data.game.slug}`}>{t('room.playAgain')}</ButtonLink>
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
