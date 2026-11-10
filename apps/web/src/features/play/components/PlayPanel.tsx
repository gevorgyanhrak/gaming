import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Suspense, useId, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { useMessage } from '@/features/auth/forms'
import type { GameDetail } from '@/features/catalog/queries'
import { isApiError } from '@/shared/api/errors'
import { Alert } from '@/shared/ui/Alert'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { TextField } from '@/shared/ui/Field'
import {
  BoltIcon,
  BotIcon,
  ChevronIcon,
  FriendsIcon,
  PlayIcon,
  TicketIcon,
} from '@/shared/ui/icons'
import { Segmented } from '@/shared/ui/Segmented'
import { Skeleton } from '@/shared/ui/Skeleton'
import {
  createBotRoom,
  createPrivateRoom,
  joinRoom,
  matchmakingKey,
  startMatchmaking,
  type BotDifficulty,
} from '../api'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { useWallet } from '@/features/wallet/hooks'
import { canEnter } from '../groups'
import { GroupPicker } from './GroupPicker'
import { MatchmakingStatus } from './MatchmakingStatus'
import { modePreviews } from '@/games/registry'

/**
 * The game's lobby: the hero (art and stats) with the big "play" button that finds an opponent,
 * and panels for the other ways in: a bot, a friend's private table, an invite code.
 */
export function PlayPanel({ game, hero }: { game: GameDetail; hero: ReactNode }) {
  const { t, i18n } = useTranslation()
  const wallet = useWallet()
  const message = useMessage()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const modes = game.modes ?? []
  const Preview = modePreviews[game.slug]
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

  // The group (default: the lowest the balance can enter) and, for stake games, the bet in its range.
  const groups = game.groups ?? []
  const balance = wallet.data?.balance
  const [groupSlug, setGroupSlug] = useState<string | null>(null)
  const group =
    groups.find((g) => g.slug === groupSlug) ??
    groups.find((g) => canEnter(g, balance)) ??
    groups[0]
  const chips = group?.kind === 'chips'
  const [betChoice, setBetChoice] = useState<number | null>(null)
  const betMax = group
    ? Math.max(group.min_bet, Math.min(group.max_bet ?? group.min_bet, balance ?? group.min_bet))
    : 0
  const bet =
    group && !chips
      ? Math.min(betMax, Math.max(group.min_bet, betChoice ?? group.min_bet))
      : undefined
  const terms = group ? { group: group.slug, ...(bet ? { bet } : {}) } : {}
  const format = (n: number) => n.toLocaleString(i18n.language)
  const chooseGroup = (slug: string) => {
    setGroupSlug(slug)
    setBetChoice(null)
  }

  const toRoom = (room: { id: string }) => void navigate(`/rooms/${room.id}`)
  const search = useMutation({
    mutationFn: (id: number) => startMatchmaking(id, group?.slug),
    onSuccess: (result) => {
      if (result.room) void navigate(`/rooms/${result.room}`)
      else queryClient.setQueryData(matchmakingKey, result)
    },
  })
  const bot = useMutation({
    mutationFn: (id: number) => createBotRoom(id, difficulty, tableSize, terms),
    onSuccess: toRoom,
  })
  const friend = useMutation({
    mutationFn: (id: number) => createPrivateRoom(id, tableSize, terms),
    onSuccess: toRoom,
  })
  const join = useMutation({ mutationFn: () => joinRoom(code.trim()), onSuccess: toRoom })

  if (!game.is_playable || modes.length === 0) return null

  // Not known yet (loading, or the server asked us to slow down and the request is being retried):
  // don't tell a signed-in player to log in.
  if (user === undefined) {
    return (
      <div className="grid gap-6 lg:grid-cols-[1.15fr_1fr] lg:items-start">
        {hero}
        <Skeleton className="h-40 w-full rounded-2xl" />
      </div>
    )
  }

  if (!user) {
    return (
      <div className="grid gap-6 lg:grid-cols-[1.15fr_1fr] lg:items-start">
        {hero}
        <ModeCard index={0} Icon={BoltIcon} title={t('play.title')}>
          <ButtonLink
            to={`/login?next=${encodeURIComponent(`/games/${game.slug}`)}`}
            className="mt-3 w-full py-3 text-base"
          >
            {t('play.loginToPlay')}
          </ButtonLink>
        </ModeCard>
      </div>
    )
  }

  const error = search.error ?? bot.error ?? friend.error ?? join.error
  // 403 here means a contact must be verified first (platform setting).
  const needsVerification = isApiError(error) && error.status === 403
  const busy = search.isPending || bot.isPending || friend.isPending
  const modeId = mode?.id

  return (
    <div className="grid gap-6 lg:grid-cols-[1.15fr_1fr] lg:items-start">
      <div className="space-y-5">
        {hero}

        {modes.length > 1 || tableSize !== undefined ? (
          <div className="flex flex-wrap justify-center gap-x-6 gap-y-4 [animation-delay:120ms] motion-safe:animate-rise">
            {modes.length > 1 && Preview ? (
              // Modes that look different (Nardi: Long and Short) are chosen by their picture.
              <fieldset className="w-full">
                <legend className="mb-2 text-center text-sm font-semibold text-gold-light">
                  {t('play.whatToPlay')}
                </legend>
                <div className="grid grid-cols-2 gap-3">
                  {modes.map((m) => (
                    <label
                      key={m.slug}
                      className="club-panel flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-transparent p-2.5 transition hover:border-gold/40 has-checked:border-gold has-checked:shadow-[0_0_0_3px_rgb(214_171_78/0.25)] has-focus-visible:outline-2 has-focus-visible:outline-accent-text sm:p-3"
                    >
                      <input
                        type="radio"
                        name="mode"
                        value={m.slug}
                        checked={modeSlug === m.slug}
                        onChange={() => setModeSlug(m.slug)}
                        className="sr-only"
                      />
                      <Suspense fallback={<span className="block aspect-[196/120] w-full" />}>
                        <Preview mode={m.slug} />
                      </Suspense>
                      <span className="text-sm font-bold sm:text-base">{m.name}</span>
                    </label>
                  ))}
                </div>
              </fieldset>
            ) : modes.length > 1 ? (
              <Segmented
                label={t('play.mode')}
                value={modeSlug}
                options={modes.map((m) => ({ value: m.slug, label: m.name }))}
                onChange={setModeSlug}
              />
            ) : null}
            {tableSize !== undefined ? (
              <Segmented
                fill
                label={t('play.players')}
                value={tableSize}
                // Just the number: the legend says what it counts, and 2–6 fits on one line.
                options={tableSizes.map((n) => ({ value: n, label: String(n) }))}
                onChange={setPlayers}
              />
            ) : null}
          </div>
        ) : null}

        {group ? (
          <div className="club-panel space-y-4 rounded-3xl p-4 [animation-delay:150ms] motion-safe:animate-rise sm:p-5">
            <GroupPicker
              groups={groups}
              value={group.slug}
              balance={balance}
              onChange={chooseGroup}
            />
            {chips ? (
              <p className="flex items-start gap-2 text-sm text-gold-light">
                <FishkaIcon className="size-5 shrink-0" />
                {t('play.pokerBuyIn', {
                  amount: format(balance ?? 0),
                  small: format(Math.max(1, Math.floor(group.min_bet / 2))),
                  big: format(group.min_bet),
                })}
              </p>
            ) : bet !== undefined ? (
              <div className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                  <label htmlFor={`${id}-bet`} className="text-sm font-semibold">
                    {t('play.yourBet')}
                  </label>
                  <span className="flex items-center gap-1.5 text-lg font-extrabold text-gold-light tabular-nums">
                    <FishkaIcon className="size-5" />
                    {format(bet)}
                  </span>
                  <span className="ml-auto flex gap-1">
                    <Button
                      variant="secondary"
                      className="min-h-11 px-3"
                      onClick={() => setBetChoice(group.min_bet)}
                    >
                      {t('play.betMin')}
                    </Button>
                    <Button
                      variant="secondary"
                      className="min-h-11 px-3"
                      onClick={() => setBetChoice(betMax)}
                    >
                      {t('play.betMax')}
                    </Button>
                  </span>
                </div>
                {betMax > group.min_bet ? (
                  <input
                    id={`${id}-bet`}
                    type="range"
                    min={group.min_bet}
                    max={betMax}
                    step={Math.max(1, Math.round(group.min_bet / 10))}
                    value={bet}
                    onChange={(e) => setBetChoice(Number(e.target.value))}
                    className="h-11 w-full accent-[var(--color-gold)]"
                  />
                ) : null}
                <p className="text-xs text-muted">
                  {t('play.potHint', { amount: format(bet * (tableSize ?? 2)) })}{' '}
                  {t('play.matchBet', { amount: format(group.min_bet) })}
                </p>
              </div>
            ) : null}
          </div>
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

        {/* The big green button: a game against another person. */}
        <button
          type="button"
          aria-label={t('play.findOpponent')}
          aria-describedby={`${id}-search`}
          disabled={busy || modeId === undefined}
          onClick={() => modeId !== undefined && search.mutate(modeId)}
          className="group mx-auto flex w-full max-w-lg flex-col items-center rounded-2xl border border-white/20 bg-linear-to-b from-[#3f9d57] via-accent-light to-accent px-6 py-4 text-white shadow-[0_10px_30px_rgb(0_0_0/0.45),inset_0_1px_0_rgb(255_255_255/0.25)] transition [animation-delay:180ms] hover:brightness-110 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60 motion-safe:animate-rise"
        >
          <span className="flex items-center gap-3 text-2xl font-extrabold tracking-wide uppercase sm:text-3xl">
            <PlayIcon className="size-7 transition-transform motion-safe:group-hover:scale-110" />
            {t('catalog.play')}
          </span>
          <span id={`${id}-search`} className="mt-0.5 text-sm text-white/85">
            {t('play.findOpponent')} · {t('play.findOpponentHint')}
          </span>
        </button>
      </div>

      <div className="space-y-4">
        <ModeCard
          index={0}
          Icon={BotIcon}
          title={t('play.playBot')}
          action={{
            label: t('play.startBot'),
            disabled: busy || modeId === undefined,
            run: () => modeId !== undefined && bot.mutate(modeId),
          }}
        >
          <div>
            <Segmented
              fill
              label={t('play.botDifficulty')}
              value={difficulty}
              options={(['easy', 'medium', 'hard'] as const).map((d) => ({
                value: d,
                label: t(`play.difficulty.${d}`),
              }))}
              onChange={setDifficulty}
            />
          </div>
        </ModeCard>

        <ModeCard
          index={1}
          Icon={FriendsIcon}
          title={t('play.playWithFriend')}
          action={{
            label: t('play.createTable'),
            disabled: busy || modeId === undefined,
            run: () => modeId !== undefined && friend.mutate(modeId),
          }}
        >
          <p className="text-sm text-muted">{t('play.friendHint')}</p>
        </ModeCard>

        <ModeCard index={2} Icon={TicketIcon} title={t('play.joinTitle')}>
          <form
            className="flex items-end gap-2"
            onSubmit={(e) => {
              e.preventDefault()
              if (code.trim()) join.mutate()
            }}
          >
            <div className="min-w-0 flex-1">
              <TextField
                label={t('play.inviteCode')}
                value={code}
                maxLength={12}
                onChange={(e) => setCode(e.target.value.toUpperCase())}
              />
            </div>
            <Button
              type="submit"
              variant="outline"
              className="min-h-11"
              disabled={!code.trim()}
              loading={join.isPending}
            >
              {t('play.join')}
            </Button>
          </form>
        </ModeCard>
      </div>
    </div>
  )
}

/**
 * One way to play, as a panel: a brass medallion and the title, its options, and a button that
 * starts it. On phones the options and button take the card's full width. Panels rise in one after
 * another.
 */
function ModeCard({
  index,
  Icon,
  title,
  action,
  children,
}: {
  index: number
  Icon: (props: { className?: string }) => ReactNode
  title: string
  action?: { label: string; disabled: boolean; run: () => void }
  children: ReactNode
}) {
  const titleId = useId()
  return (
    <section
      aria-labelledby={titleId}
      className="club-panel grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-3 rounded-3xl p-4 transition hover:border-gold/40 motion-safe:animate-rise sm:items-start sm:gap-x-5 sm:p-5"
      style={{ animationDelay: `${150 + index * 110}ms` }}
    >
      <span className="club-medal flex size-12 shrink-0 items-center justify-center rounded-full text-[#3a2410] sm:row-span-3 sm:size-20">
        <Icon className="size-6 sm:size-10" />
      </span>
      <h3 id={titleId} className="text-gold-gradient text-xl font-extrabold sm:text-2xl">
        {title}
      </h3>
      <div className="col-span-2 min-w-0 sm:col-span-1 sm:col-start-2">{children}</div>
      {action ? (
        <div className="col-span-2 sm:col-span-1 sm:col-start-2 sm:justify-self-end">
          <Button
            variant="outline"
            disabled={action.disabled}
            onClick={action.run}
            aria-describedby={titleId}
            className="min-h-11 w-full sm:w-auto"
          >
            {action.label}
            <ChevronIcon className="size-5" />
          </Button>
        </div>
      ) : null}
    </section>
  )
}
