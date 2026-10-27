import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { useMessage } from '@/features/auth/forms'
import type { GameDetail } from '@/features/catalog/queries'
import { isApiError } from '@/shared/api/errors'
import { Alert } from '@/shared/ui/Alert'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { SelectField, TextField } from '@/shared/ui/Field'
import {
  createBotRoom,
  createPrivateRoom,
  joinRoom,
  matchmakingKey,
  startMatchmaking,
  type BotDifficulty,
} from '../api'
import { MatchmakingStatus } from './MatchmakingStatus'

/**
 * How to start playing a game: find an opponent, play a bot, or play with a friend.
 */
export function PlayPanel({ game }: { game: GameDetail }) {
  const { t } = useTranslation()
  const message = useMessage()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const modes = game.modes ?? []
  const [modeSlug, setModeSlug] = useState(modes[0]?.slug ?? '')
  const [difficulty, setDifficulty] = useState<BotDifficulty>('medium')
  const [code, setCode] = useState('')
  const mode = modes.find((m) => m.slug === modeSlug)

  const toRoom = (room: { id: string }) => void navigate(`/rooms/${room.id}`)
  const search = useMutation({
    mutationFn: (id: number) => startMatchmaking(id),
    onSuccess: (result) => {
      if (result.room) void navigate(`/rooms/${result.room}`)
      else queryClient.setQueryData(matchmakingKey, result)
    },
  })
  const bot = useMutation({
    mutationFn: (id: number) => createBotRoom(id, difficulty),
    onSuccess: toRoom,
  })
  const friend = useMutation({
    mutationFn: (id: number) => createPrivateRoom(id),
    onSuccess: toRoom,
  })
  const join = useMutation({ mutationFn: () => joinRoom(code.trim()), onSuccess: toRoom })

  if (!game.is_playable || modes.length === 0) return null

  if (!user) {
    return (
      <Card title={t('play.title')}>
        <ButtonLink to={`/login?next=${encodeURIComponent(`/games/${game.slug}`)}`}>
          {t('play.loginToPlay')}
        </ButtonLink>
      </Card>
    )
  }

  const error = search.error ?? bot.error ?? friend.error ?? join.error
  // 403 here means a contact must be verified first (platform setting).
  const needsVerification = isApiError(error) && error.status === 403
  const busy = search.isPending || bot.isPending || friend.isPending
  const modeId = mode?.id

  return (
    <Card title={t('play.title')}>
      <div className="space-y-5">
        {modes.length > 1 ? (
          <SelectField
            label={t('play.mode')}
            value={modeSlug}
            onChange={(e) => setModeSlug(e.target.value)}
          >
            {modes.map((m) => (
              <option key={m.slug} value={m.slug}>
                {m.name}
              </option>
            ))}
          </SelectField>
        ) : null}

        {error ? (
          <Alert tone="danger">
            {message(error.message)}{' '}
            {needsVerification ? (
              <Link to="/verify" className="font-semibold underline">
                {t('verifyBanner.action')}
              </Link>
            ) : null}
          </Alert>
        ) : null}

        <MatchmakingStatus />

        <div className="flex flex-wrap items-end gap-3">
          <Button
            disabled={busy || modeId === undefined}
            onClick={() => modeId !== undefined && search.mutate(modeId)}
          >
            {t('play.findOpponent')}
          </Button>
          <Button
            variant="secondary"
            disabled={busy || modeId === undefined}
            onClick={() => modeId !== undefined && friend.mutate(modeId)}
          >
            {t('play.playWithFriend')}
          </Button>
        </div>

        <div className="flex flex-wrap items-end gap-3">
          <div className="w-44">
            <SelectField
              label={t('play.botDifficulty')}
              value={difficulty}
              onChange={(e) => setDifficulty(e.target.value as BotDifficulty)}
            >
              {(['easy', 'medium', 'hard'] as const).map((d) => (
                <option key={d} value={d}>
                  {t(`play.difficulty.${d}`)}
                </option>
              ))}
            </SelectField>
          </div>
          <Button
            variant="secondary"
            disabled={busy || modeId === undefined}
            onClick={() => modeId !== undefined && bot.mutate(modeId)}
          >
            {t('play.playBot')}
          </Button>
        </div>

        <form
          className="flex flex-wrap items-end gap-3"
          onSubmit={(e) => {
            e.preventDefault()
            if (code.trim()) join.mutate()
          }}
        >
          <div className="w-44">
            <TextField
              label={t('play.inviteCode')}
              value={code}
              maxLength={12}
              onChange={(e) => setCode(e.target.value.toUpperCase())}
            />
          </div>
          <Button type="submit" variant="secondary" disabled={join.isPending || !code.trim()}>
            {t('play.join')}
          </Button>
        </form>
      </div>
    </Card>
  )
}
