import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { useMessage } from '@/features/auth/forms'
import { Alert } from '@/shared/ui/Alert'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { TrophyIcon } from '@/shared/ui/icons'
import {
  createBotRoom,
  matchmakingKey,
  startMatchmaking,
  type BotDifficulty,
  type ParticipantResult,
  type Room,
} from '../api'
import { endReasonLabel, outcomeLabel } from '../labels'
import { MatchmakingStatus } from './MatchmakingStatus'

/**
 * How the game ended, above the final position, with one tap into the same kind of game: a new bot
 * table with the same mode, size and level, or a new search for an online game. Private tables go
 * back to the lobby (a rematch with the same friend is a separate feature).
 */
export function GameOverPanel({
  room,
  result,
  leftTable,
}: {
  room: Room
  /** The player's own result, if they had a seat. */
  result: ParticipantResult | null
  /** The player left a table that plays on without them. */
  leftTable: boolean
}) {
  const { t } = useTranslation()
  const message = useMessage()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const heading = useRef<HTMLHeadingElement>(null)
  const [searching, setSearching] = useState(false)

  const aborted = room.status === 'aborted'
  const level = room.participants.find((p) => p.is_bot)?.bot_difficulty as BotDifficulty | undefined
  const again = useMutation({
    mutationFn: async () => {
      if (room.type === 'bot')
        return { room: (await createBotRoom(room.mode.id, level ?? 'medium', room.seats)).id }
      return startMatchmaking(room.mode.id)
    },
    onSuccess: (next) => {
      if (next.room) void navigate(`/rooms/${next.room}`)
      else {
        queryClient.setQueryData(matchmakingKey, next)
        setSearching(true)
      }
    },
  })

  // Announce the end once per room: focus moves to the outcome.
  useEffect(() => {
    heading.current?.focus()
  }, [room.id])

  const title = aborted
    ? t('room.aborted')
    : leftTable
      ? t('room.leftTable')
      : result
        ? t(outcomeLabel[result])
        : t('room.finished')
  const reason = !aborted && room.end_reason ? endReasonLabel[room.end_reason] : undefined
  const canPlayAgain = !aborted && room.type !== 'private'
  const lobby = `/games/${room.game.slug}`
  const settings = [
    room.mode.name,
    room.type === 'bot'
      ? t('play.botHint', { level: t(`play.difficulty.${level ?? 'medium'}`) })
      : t('play.findOpponent'),
    room.seats > 2 ? t('play.playersCount', { count: room.seats }) : null,
  ]
    .filter(Boolean)
    .join(' · ')

  return (
    <section
      aria-labelledby="game-over-title"
      className="club-frame mx-auto w-full max-w-xl rounded-3xl p-5 text-center motion-safe:animate-rise sm:p-6"
    >
      {result === 'win' && !leftTable ? (
        <span className="club-medal mx-auto mb-3 flex size-12 items-center justify-center rounded-full text-black">
          <TrophyIcon className="size-6" />
        </span>
      ) : null}
      {aborted ? null : (
        <p className="text-xs font-semibold tracking-wider text-muted uppercase">
          {t('room.over')}
        </p>
      )}
      <h2
        id="game-over-title"
        ref={heading}
        tabIndex={-1}
        className={`mt-1 text-2xl font-extrabold outline-none sm:text-3xl ${result === 'win' && !leftTable ? 'text-gold-gradient' : ''}`}
      >
        {title}
      </h2>
      {reason ? <p className="mt-1 text-sm text-muted">{t(reason)}</p> : null}

      {searching ? (
        <div className="mt-5 text-left">
          <MatchmakingStatus />
        </div>
      ) : (
        <>
          <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
            {canPlayAgain ? (
              <Button
                onClick={() => again.mutate()}
                disabled={again.isPending}
                aria-busy={again.isPending}
                className="min-h-11 w-full sm:w-auto"
              >
                {t('room.playAgain')}
              </Button>
            ) : null}
            <ButtonLink
              to={lobby}
              variant={canPlayAgain ? 'secondary' : 'primary'}
              className="min-h-11 w-full sm:w-auto"
            >
              {t('room.backToLobby')}
            </ButtonLink>
          </div>
          {canPlayAgain ? (
            <p className="mt-3 text-xs text-muted">
              {t('room.sameSettings')} {settings}
            </p>
          ) : null}
          {again.error ? (
            <div className="mt-3 text-left">
              <Alert tone="danger">{message(again.error.message)}</Alert>
            </div>
          ) : null}
        </>
      )}
    </section>
  )
}
