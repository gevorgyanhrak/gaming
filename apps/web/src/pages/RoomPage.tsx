import { Suspense, useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import type { ParseKeys } from 'i18next'
import { Link, useParams } from 'react-router'
import { useMessage } from '@/features/auth/forms'
import { useCurrentUser } from '@/features/auth/hooks'
import { leaveRoom, type ParticipantResult } from '@/features/play/api'
import { resultLabel } from '@/features/play/labels'
import { GameOverPanel } from '@/features/play/components/GameOverPanel'
import { RoomReactions } from '@/features/reactions/components/RoomReactions'
import { GroupBadge } from '@/features/play/components/GroupBadge'
import { PlayerProfilePopup } from '@/features/players/PlayerProfilePopup'
import { GameBackdrop } from '@/features/play/components/GameBackdrop'
import { TableBar } from '@/features/play/components/TableBar'
import { PlayerList } from '@/features/play/components/PlayerList'
import { WaitingTable } from '@/features/play/components/WaitingTable'
import { useMarkGameInProgress } from '@/features/play/gameInProgress'
import { waitingCaption } from '@/features/play/turnCaption'
import { useCountdown } from '@/features/play/useCountdown'
import { useGameSounds } from '@/features/play/useGameSounds'
import { usePresence } from '@/features/play/usePresence'
import { useRoom } from '@/features/play/useRoom'
import { boards, traitsOf, type GameTraits, type View } from '@/games/registry'
import { isApiError } from '@/shared/api/errors'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
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
  const wide = useMediaQuery('(min-width: 768px)')
  const secondsLeft = useCountdown(state.data?.deadline, state.data?.server_time)
  // Report presence while playing: leaving the page for too long loses the game.
  const playing = room.data?.status === 'active' && state.data?.seat != null && !state.data?.result
  usePresence(id, playing)
  useMarkGameInProgress(playing)
  // A player's card (PlayerProfilePopup): any avatar marked with seatTrigger opens it, on click or Enter/Space.
  const [cardSeat, setCardSeat] = useState<number | null>(null)
  useEffect(() => {
    const seatOf = (target: EventTarget | null) =>
      target instanceof Element ? target.closest('[data-player-seat]') : null
    const onClick = (event: MouseEvent) => {
      const el = seatOf(event.target)
      if (el) setCardSeat(Number(el.getAttribute('data-player-seat')))
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key !== 'Enter' && event.key !== ' ') return
      const el = seatOf(event.target)
      if (!el) return
      event.preventDefault()
      setCardSeat(Number(el.getAttribute('data-player-seat')))
    }
    document.addEventListener('click', onClick)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('click', onClick)
      document.removeEventListener('keydown', onKey)
    }
  }, [])
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
      <>
        <TableBar />
        <Container className="space-y-4 py-10">
          <Skeleton className="h-10 w-1/3" />
          <Skeleton className="h-64 w-full" />
        </Container>
      </>
    )
  }

  if (room.isError) {
    return (
      <>
        <TableBar />
        <Container className="py-10">
          {isApiError(room.error) && (room.error.status === 403 || room.error.isNotFound) ? (
            <EmptyState title={t('room.notFound')} />
          ) : (
            <QueryErrorState error={room.error} onRetry={() => void room.refetch()} />
          )}
        </Container>
      </>
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

  // AI players are marked wherever a name shows (ADR 0022): every board gets "Name · AI".
  const playerNames: Partial<Record<number, string>> = Object.fromEntries(
    data.participants.map((p) => [
      p.seat,
      p.is_bot ? t('play.aiName', { name: p.name ?? '' }) : (p.name ?? ''),
    ]),
  )
  // Left a multi-player game that goes on without you.
  const leftTable = data.status === 'active' && myResult != null
  const over = data.status === 'finished' || data.status === 'aborted' || leftTable
  // …with no chips left (a game played for chips), rather than by leaving.
  const outOfChips =
    leftTable &&
    view?.seat != null &&
    (view.view as { stacks?: number[] }).stacks?.[view.seat] === 0
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

  // Your seat: from the game state once it runs, before that from the room's players, so everyone
  // who joins a table sees themselves at the bottom.
  const tableSeat =
    view?.seat ??
    data.participants.find((p) => !p.is_bot && user && p.username === user.username)?.seat ??
    0

  // Whose turn it is: in the bar on wide screens, under it on phones and tablets.
  const turnStatus =
    data.status === 'active' && view && !leftTable ? (
      <div
        // Keyed by whose turn it is: the pill pops once when your turn starts.
        key={myTurn ? 'mine' : 'theirs'}
        className={`ml-auto max-w-[60%] shrink-0 rounded-2xl px-4 py-1.5 text-right ${myTurn ? 'bg-linear-to-b from-gold-light via-gold to-gold-dark text-black shadow-gold/20 shadow-lg motion-safe:animate-turn-pop' : 'border border-border bg-surface'}`}
        role="status"
      >
        <p className="max-w-56 truncate text-sm font-semibold sm:text-base">
          {myTurn
            ? t('room.yourTurn')
            : view.seats_to_act.length === 0
              ? // A pause between rounds (poker hands); the time left is when the next starts.
                t(pauseCaption(traitsOf(data.game.slug).nextRound, view.view))
              : (waitingCaption(view, playerNames, t) ?? t('room.theirTurn'))}
        </p>
        {secondsLeft !== null && !clocks ? (
          <p className={`truncate text-xs sm:text-sm ${myTurn ? 'text-black/70' : 'text-muted'}`}>
            {t('room.secondsLeft', { count: secondsLeft })}
          </p>
        ) : null}
      </div>
    ) : null

  return (
    <>
      <GameBackdrop slug={data.game.slug} />
      {(() => {
        const p = cardSeat === null ? undefined : data.participants.find((x) => x.seat === cardSeat)
        return p ? (
          <PlayerProfilePopup
            player={{
              name: p.name ?? '',
              username: p.username,
              is_bot: p.is_bot,
              level: p.level,
              avatar_url: p.avatar_url,
            }}
            onClose={() => setCardSeat(null)}
          />
        ) : null
      })()}
      <TableBar room={data} status={wide ? turnStatus : null} />
      <Container className="space-y-4 py-4 sm:space-y-5 sm:py-6">
        <title>{tabTitle}</title>
        {/* Phones and tablets: the group and whose turn it is, under the bar (wider screens have both in it). */}
        {!wide && (turnStatus || data.group) ? (
          <div className="flex items-center justify-between gap-3">
            <span className="min-w-0">
              <GroupBadge room={data} />
            </span>
            {turnStatus}
          </div>
        ) : null}

        {/* The table chat: between the header and the table, in view while playing. */}
        <RoomReactions
          room={data}
          mySeat={
            view?.seat ??
            data.participants.find((p) => !p.is_bot && user && p.username === user.username)
              ?.seat ??
            null
          }
        />

        {data.status === 'waiting' ? (
          <WaitingTable room={data} mySeat={tableSeat} onLeave={leave} />
        ) : null}

        {/* The table, with the game-over panel floating over its top on wide screens. */}
        <div className={`relative space-y-4 sm:space-y-5 ${over ? 'min-h-[22rem]' : ''}`}>
          {showPlayers ? (
            <PlayerList
              room={data}
              mySeat={view?.seat ?? null}
              seatsToAct={view?.seats_to_act ?? []}
              clocks={clocks}
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
                  levels={Object.fromEntries(data.participants.map((p) => [p.seat, p.level]))}
                  frames={Object.fromEntries(data.participants.map((p) => [p.seat, p.frame]))}
                />
              </Suspense>
            ) : (
              <EmptyState title={t('room.boardUnavailable')} />
            )
          ) : null}
          {/* After the board: a folded strip leaves its room at the end; a float or sheet doesn't care. */}
          {over ? (
            <GameOverPanel
              room={data}
              result={(myResult ?? null) as ParticipantResult | null}
              leftTable={leftTable}
              outOfChips={outOfChips}
            />
          ) : null}
        </div>

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
    </>
  )
}

/** The header's caption for a pause nobody acts in (a game may name it by its view). */
function pauseCaption(caption: GameTraits['nextRound'], view: View): ParseKeys {
  if (typeof caption === 'function') return caption(view)
  return caption ?? 'room.nextRound'
}
