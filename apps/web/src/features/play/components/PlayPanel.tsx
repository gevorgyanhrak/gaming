import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useId, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { useMessage } from '@/features/auth/forms'
import type { GameDetail } from '@/features/catalog/queries'
import { isApiError } from '@/shared/api/errors'
import { Alert } from '@/shared/ui/Alert'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { TextField } from '@/shared/ui/Field'
import { BoltIcon, BotIcon, FriendsIcon, TicketIcon } from '@/shared/ui/icons'
import { Segmented } from '@/shared/ui/Segmented'
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
  const id = useId()
  const mode = modes.find((m) => m.slug === modeSlug)
  // Tables for more than two (poker): how many sit down, you included.
  const tableSizes = mode
    ? Array.from(
        { length: mode.max_players - mode.min_players + 1 },
        (_, i) => mode.min_players + i,
      )
    : []
  const [players, setPlayers] = useState<number | null>(null)
  const tableSize =
    tableSizes.length > 1 ? (players ?? Math.min(4, mode?.max_players ?? 2)) : undefined

  const toRoom = (room: { id: string }) => void navigate(`/rooms/${room.id}`)
  const search = useMutation({
    mutationFn: (id: number) => startMatchmaking(id),
    onSuccess: (result) => {
      if (result.room) void navigate(`/rooms/${result.room}`)
      else queryClient.setQueryData(matchmakingKey, result)
    },
  })
  const bot = useMutation({
    mutationFn: (id: number) => createBotRoom(id, difficulty, tableSize),
    onSuccess: toRoom,
  })
  const friend = useMutation({
    mutationFn: (id: number) => createPrivateRoom(id, tableSize),
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

  const tiles = [
    {
      key: 'search',
      Icon: BoltIcon,
      title: t('play.findOpponent'),
      hint: t('play.findOpponentHint'),
      run: () => modeId !== undefined && search.mutate(modeId),
      featured: true,
    },
    {
      key: 'bot',
      Icon: BotIcon,
      title: t('play.playBot'),
      hint: t('play.botHint', { level: t(`play.difficulty.${difficulty}`) }),
      run: () => modeId !== undefined && bot.mutate(modeId),
    },
    {
      key: 'friend',
      Icon: FriendsIcon,
      title: t('play.playWithFriend'),
      hint: t('play.friendHint'),
      run: () => modeId !== undefined && friend.mutate(modeId),
    },
  ]

  return (
    <section
      aria-labelledby={`${id}-title`}
      className="rounded-3xl border border-border bg-surface/80 p-5 shadow-xl shadow-black/30 backdrop-blur sm:p-6"
    >
      <h2 id={`${id}-title`} className="text-lg font-semibold">
        {t('play.title')}
      </h2>

      <div className="mt-4 flex flex-wrap gap-x-6 gap-y-4">
        {modes.length > 1 ? (
          <Segmented
            label={t('play.mode')}
            value={modeSlug}
            options={modes.map((m) => ({ value: m.slug, label: m.name }))}
            onChange={setModeSlug}
          />
        ) : null}
        {tableSize !== undefined ? (
          <Segmented
            label={t('play.players')}
            value={tableSize}
            options={tableSizes.map((n) => ({
              value: n,
              label: t('play.playersCount', { count: n }),
            }))}
            onChange={setPlayers}
          />
        ) : null}
        <Segmented
          label={t('play.botDifficulty')}
          value={difficulty}
          options={(['easy', 'medium', 'hard'] as const).map((d) => ({
            value: d,
            label: t(`play.difficulty.${d}`),
          }))}
          onChange={setDifficulty}
        />
      </div>

      <div className="mt-5 space-y-4">
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
      </div>

      <div className="mt-5 grid gap-3 sm:grid-cols-2">
        {tiles.map(({ key, Icon, title, hint, run, featured }) => (
          <button
            key={key}
            type="button"
            aria-label={title}
            aria-describedby={`${id}-${key}`}
            disabled={busy || modeId === undefined}
            onClick={run}
            className={`group flex items-center gap-4 rounded-2xl border p-4 text-left transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0 ${
              featured
                ? 'border-gold/40 bg-linear-to-br from-gold/20 to-surface-raised hover:border-gold'
                : 'border-border bg-surface-raised hover:border-accent/60'
            }`}
          >
            <span
              className={`flex size-12 shrink-0 items-center justify-center rounded-xl ${
                featured ? 'bg-gold text-black' : 'bg-accent/15 text-accent-text'
              }`}
            >
              <Icon className="size-6" />
            </span>
            <span className="min-w-0">
              <span className="block font-semibold text-fg">{title}</span>
              <span id={`${id}-${key}`} className="mt-0.5 block text-sm text-muted">
                {hint}
              </span>
            </span>
          </button>
        ))}

        <form
          className="flex flex-col justify-center gap-3 rounded-2xl border border-dashed border-border p-4"
          onSubmit={(e) => {
            e.preventDefault()
            if (code.trim()) join.mutate()
          }}
        >
          <span className="flex items-center gap-2 text-sm font-semibold">
            <TicketIcon className="size-5 text-gold" />
            {t('play.joinTitle')}
          </span>
          <div className="flex items-end gap-2">
            <div className="min-w-0 flex-1">
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
          </div>
        </form>
      </div>
    </section>
  )
}
