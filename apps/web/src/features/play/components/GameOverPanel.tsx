import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router'
import { useMessage } from '@/features/auth/forms'
import { useMediaQuery } from '@/shared/lib/useMediaQuery'
import { Alert } from '@/shared/ui/Alert'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { CloseIcon, StarIcon } from '@/shared/ui/icons'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { ResultTrophy } from './ResultTrophy'
import { Modal } from '@/shared/ui/Modal'
import {
  createBotRoom,
  matchmakingKey,
  startMatchmaking,
  type ParticipantResult,
  type Room,
} from '../api'
import { endReasonLabel, outcomeLabel } from '../labels'
import { GameResult } from '@/features/wallet/components/GameResult'
import { MatchmakingStatus } from './MatchmakingStatus'

/**
 * How the game ended, with one tap into the same kind of game: a new bot table with the same mode, size
 * and level, or a new search for an online game. Private tables go back to the lobby (a rematch with the
 * same friend is a separate feature). It never pushes the table down (TASK-048): from 1024 px it floats
 * over the top of the board, on phones and tablets it is a bottom sheet. Either one folds into a small
 * strip at the bottom, to look at the final position, and opens again from there.
 */
export function GameOverPanel({
  room,
  result,
  leftTable,
  outOfChips = false,
}: {
  room: Room
  /** The player's own result, if they had a seat. */
  result: ParticipantResult | null
  /** The player left a table that plays on without them. */
  leftTable: boolean
  /** …because they lost all their chips (poker), not because they walked away. */
  outOfChips?: boolean
}) {
  const { t, i18n } = useTranslation()
  const message = useMessage()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const heading = useRef<HTMLHeadingElement>(null)
  const [searching, setSearching] = useState(false)
  const [folded, setFolded] = useState(false)
  const wide = useMediaQuery('(min-width: 1024px)')

  const aborted = room.status === 'aborted'
  const rewards = room.my_rewards ?? null
  const trophy: 'win' | 'loss' | 'neutral' =
    aborted || (!result && !leftTable)
      ? 'neutral'
      : result === 'win' && !leftTable
        ? 'win'
        : result === 'draw'
          ? 'neutral'
          : 'loss'
  const again = useMutation({
    mutationFn: async () => {
      // Same table terms: group and bet (the server checks them again).
      const terms = { group: room.group?.slug, ...(room.stake > 0 ? { bet: room.stake } : {}) }
      if (room.type === 'bot')
        return {
          room: (await createBotRoom(room.mode.id, room.seats, terms)).id,
        }
      return startMatchmaking(room.mode.id, room.group?.slug)
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
  // Another room (Play again) starts unfolded.
  const [shownFor, setShownFor] = useState(room.id)
  if (shownFor !== room.id) {
    setShownFor(room.id)
    setFolded(false)
  }

  const title = aborted
    ? t('room.aborted')
    : leftTable
      ? t(outOfChips ? 'room.outOfChips' : 'room.leftTable')
      : result === 'abandoned' && room.end_reason === 'abandon'
        ? // Recorded as abandoned for leaving the page too, not only for a missed turn timer.
          t('room.outcome.left')
        : result
          ? t(outcomeLabel[result])
          : t('room.finished')
  const reason = !aborted && room.end_reason ? endReasonLabel[room.end_reason] : undefined
  // A result worth two (Nardi's mars: the loser bore off none).
  const doubleWin =
    room.status === 'finished' &&
    room.participants.some((p) => p.result === 'win' && (p.score ?? 0) >= 2)
  const canPlayAgain = !aborted && room.type !== 'private'
  const lobby = `/games/${room.game.slug}`
  const settings = [
    room.mode.name,
    room.type === 'bot' ? t('play.botHint') : t('play.findOpponent'),
    room.seats > 2 ? t('play.playersCount', { count: room.seats }) : null,
  ]
    .filter(Boolean)
    .join(' · ')

  const playAgain = canPlayAgain ? (
    <Button
      onClick={() => again.mutate()}
      loading={again.isPending}
      className="min-h-11 w-full sm:w-auto"
    >
      {t('room.playAgain')}
    </Button>
  ) : null

  if (folded) {
    return (
      <>
        {/* Room under the fixed strip, so the end of the page can still be reached. */}
        <div aria-hidden="true" className="h-28 sm:h-20" />
        <section
          aria-label={t('room.over')}
          className="club-frame fixed inset-x-3 bottom-3 z-30 mx-auto flex max-w-xl flex-wrap items-center gap-2 rounded-2xl p-2 motion-safe:animate-[sheet-up_260ms_cubic-bezier(0.2,0.8,0.2,1)] sm:flex-nowrap sm:gap-3 sm:pl-4"
        >
          <p className="w-full truncate px-2 pt-1 text-center font-bold sm:w-auto sm:flex-1 sm:p-0 sm:text-left">
            {title}
          </p>
          <Button
            variant="secondary"
            className="min-h-11 flex-1 sm:flex-none"
            onClick={() => setFolded(false)}
          >
            {t('room.showResult')}
          </Button>
          {playAgain ? (
            <span className="flex flex-1 sm:flex-none [&>*]:w-full">{playAgain}</span>
          ) : null}
        </section>
      </>
    )
  }

  const content = (
    <div className="relative p-5 text-center sm:p-6">
      <button
        type="button"
        onClick={() => setFolded(true)}
        aria-label={t('room.showTable')}
        title={t('room.showTable')}
        className="absolute top-2 right-2 flex size-11 items-center justify-center rounded-full text-muted transition-colors hover:bg-emerald-800 hover:text-fg"
      >
        <CloseIcon className="size-5" />
      </button>
      <ResultTrophy outcome={trophy} />
      {aborted ? null : (
        <p className="text-xs font-semibold tracking-wider text-muted uppercase">
          {t('room.over')}
        </p>
      )}
      <h2
        id="game-over-title"
        ref={heading}
        tabIndex={-1}
        className={`mt-2 text-2xl font-extrabold tracking-wide uppercase outline-none sm:text-4xl ${
          trophy === 'win' ? 'text-gold-gradient' : trophy === 'loss' ? 'text-[#e8867a]' : ''
        }`}
      >
        {title}
      </h2>
      {doubleWin ? (
        <p className="mt-1 text-sm font-bold text-gold-light">{t('room.doubleWin')}</p>
      ) : reason ? (
        <p className="mt-1 text-sm text-muted">{t(reason)}</p>
      ) : null}
      {leftTable ? <p className="mt-1 text-sm text-muted">{t('room.playsOn')}</p> : null}
      {rewards ? (
        // What the game brought: Fishkas (net) and XP, side by side.
        <div className="mt-4 grid grid-cols-2 gap-3">
          <div className="flex items-center justify-center gap-3 rounded-2xl border border-gold/25 bg-black/25 px-3 py-3">
            <FishkaIcon className="size-9 shrink-0" />
            <span className="text-left">
              <span className="block text-2xl font-extrabold tabular-nums">
                {rewards.fishkas > 0 ? '+' : ''}
                {rewards.fishkas.toLocaleString(i18n.language)}
              </span>
              <span className="text-sm text-muted">{t('room.rewardFishkas')}</span>
            </span>
          </div>
          <div className="flex items-center justify-center gap-3 rounded-2xl border border-gold/25 bg-black/25 px-3 py-3">
            <StarIcon className="size-9 shrink-0 text-gold" />
            <span className="text-left">
              <span className="block text-2xl font-extrabold tabular-nums">+{rewards.xp}</span>
              <span className="text-sm text-muted">{t('room.rewardXp')}</span>
            </span>
          </div>
        </div>
      ) : result || leftTable ? (
        <GameResult roomId={room.id} />
      ) : null}

      {searching ? (
        <div className="mt-5 text-left">
          <MatchmakingStatus />
        </div>
      ) : (
        <>
          <div className="mt-5 flex flex-col gap-2 sm:flex-row sm:justify-center">
            {playAgain}
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
    </div>
  )

  if (!wide) {
    return (
      <Modal onClose={() => setFolded(true)} labelledBy="game-over-title" sheetOnPhone>
        {content}
      </Modal>
    )
  }

  // Over the top of the board: the room page puts the board in a relative box.
  return (
    <div className="pointer-events-none absolute inset-x-0 top-2 z-20 flex justify-center px-2">
      <section
        aria-labelledby="game-over-title"
        className="club-frame pointer-events-auto w-full max-w-xl rounded-3xl shadow-modal motion-safe:animate-rise"
      >
        {content}
      </section>
    </div>
  )
}
