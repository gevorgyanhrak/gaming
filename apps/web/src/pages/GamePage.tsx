import { useQuery } from '@tanstack/react-query'
import { ViewTransition } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameArtwork } from '@/features/catalog/components/GameArtwork'
import { PlayerCount } from '@/features/catalog/components/PlayerCount'
import { useGame, type GameDetail } from '@/features/catalog/queries'
import { artTransition, titleTransition } from '@/features/catalog/transitions'
import { fetchMyStats, myStatsKey } from '@/features/play/api'
import { PlayPanel } from '@/features/play/components/PlayPanel'
import { isApiError } from '@/shared/api/errors'
import { Badge } from '@/shared/ui/Badge'
import { splitParagraphs } from '@/shared/lib/paragraphs'
import { Container } from '@/shared/ui/Container'
import { BackIcon, HistoryIcon, TargetIcon, TrophyIcon } from '@/shared/ui/icons'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'
import { GameBackdrop } from '@/features/play/components/GameBackdrop'

export function GamePage() {
  const { t } = useTranslation()
  const { slug = '' } = useParams()
  const game = useGame(slug)

  return (
    <Container className="py-4 sm:py-6">
      {/* The game's own room behind its page (known from the address before the game loads). */}
      <GameBackdrop slug={slug} />
      <Link
        to="/games"
        className="inline-flex items-center gap-1.5 text-sm font-medium text-accent-text hover:underline"
      >
        <BackIcon className="size-4" />
        {t('game.back')}
      </Link>

      <div className="mt-3">
        {game.isPending ? (
          <div data-testid="game-loading" className="space-y-4">
            <Skeleton className="h-14 w-1/3" />
            <Skeleton className="aspect-[16/9] w-full max-w-2xl rounded-panel" />
          </div>
        ) : game.isError ? (
          isApiError(game.error) && game.error.isNotFound ? (
            <EmptyState title={t('game.notFound')} />
          ) : (
            <QueryErrorState error={game.error} onRetry={() => void game.refetch()} />
          )
        ) : (
          <article>
            <title>{game.data.name}</title>

            {game.data.is_playable && !game.data.maintenance ? (
              <PlayPanel game={game.data} hero={<GameHero game={game.data} />} />
            ) : (
              <div className="grid gap-6 lg:grid-cols-[1.15fr_1fr] lg:items-start">
                <GameHero game={game.data} />
                <p
                  className="club-panel rounded-panel px-5 py-4 text-muted motion-safe:animate-rise"
                  role={game.data.maintenance ? 'status' : undefined}
                >
                  {/* Game Management: a game in maintenance says why, in the admin's words. */}
                  {game.data.maintenance
                    ? (game.data.maintenance_message ?? t('game.maintenance'))
                    : t('game.notPlayable')}
                </p>
              </div>
            )}

            {/* Below the play area: the rules folded away (opened on demand); the modes only when the game
                can't be played yet (otherwise the mode picker above already shows them). */}
            <div className="mt-5 space-y-3">
              {!game.data.is_playable && game.data.modes && game.data.modes.length > 0 ? (
                <ul className="flex flex-wrap gap-2">
                  {game.data.modes.map((mode) => (
                    <li
                      key={mode.slug}
                      className="club-panel rounded-xl px-3 py-1.5 text-sm"
                      title={mode.description ?? undefined}
                    >
                      <span className="font-semibold">{mode.name}</span>{' '}
                      <span className="text-xs text-muted">
                        <PlayerCount game={mode} />
                      </span>
                    </li>
                  ))}
                </ul>
              ) : null}
              <details className="club-panel group rounded-2xl">
                <summary className="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-4 py-2 font-bold [&::-webkit-details-marker]:hidden">
                  {t('game.rules')}
                  <span
                    aria-hidden="true"
                    className="text-gold transition-transform group-open:rotate-180"
                  >
                    ▾
                  </span>
                </summary>
                <div className="space-y-3 px-4 pb-4 text-sm leading-relaxed text-fg/90">
                  {splitParagraphs(game.data.rules).map((paragraph, index) => (
                    <p key={index}>{paragraph}</p>
                  ))}
                </div>
              </details>
            </div>
          </article>
        )}
      </div>
    </Container>
  )
}

/**
 * The game's name in large gold letters over its artwork, with the player's own record in this
 * game along the bottom. Art and name arrive from the carousel slide that was opened.
 */
function GameHero({ game }: { game: GameDetail }) {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const stats = useQuery({
    queryKey: myStatsKey,
    queryFn: ({ signal }) => fetchMyStats(signal),
    enabled: Boolean(user),
  })
  const mine = stats.data?.find((row) => row.game.slug === game.slug)
  const record = user
    ? [
        { key: 'wins', Icon: TrophyIcon, label: t('dashboard.wins'), value: mine?.wins ?? 0 },
        { key: 'losses', Icon: TargetIcon, label: t('dashboard.losses'), value: mine?.losses ?? 0 },
        {
          key: 'played',
          Icon: HistoryIcon,
          label: t('dashboard.played'),
          value: mine?.played ?? 0,
        },
      ]
    : []

  return (
    <header className="space-y-2.5">
      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
        <ViewTransition name={titleTransition(game.slug)}>
          <h1 className="text-gold-gradient font-display text-3xl font-bold sm:text-4xl">
            {game.name}
          </h1>
        </ViewTransition>
        {game.is_playable ? null : <Badge tone="warning">{t('catalog.comingSoon')}</Badge>}
        {/* Your record in this game: icons and numbers, the words in the tooltip (and for screen readers). */}
        {record.length > 0 ? (
          <dl className="ml-auto flex items-center gap-1.5">
            {record.map(({ key, Icon, label, value }) => (
              <div
                key={key}
                title={label}
                className="flex items-center gap-1 rounded-full bg-black/35 px-2.5 py-1 text-sm font-bold"
              >
                <dt className="sr-only">{label}</dt>
                <Icon className="size-4 text-gold" aria-hidden="true" />
                <dd className={key === 'wins' ? 'text-gold-light' : ''}>{value}</dd>
              </div>
            ))}
          </dl>
        ) : null}
      </div>
      <p
        className="line-clamp-2 max-w-2xl text-sm text-fg/80"
        title={game.description ?? undefined}
      >
        {game.description}
      </p>

      <div className="relative overflow-hidden rounded-2xl border border-gold/30 shadow-raised">
        <ViewTransition name={artTransition(game.slug)}>
          <GameArtwork game={game} className="aspect-[16/9] max-h-44 w-full sm:max-h-52" />
        </ViewTransition>
        <p className="absolute top-2 right-2 flex gap-1.5 text-xs font-medium text-fg">
          <span className="rounded-full bg-emerald-900/85 px-2.5 py-1">
            <PlayerCount game={game} />
          </span>
          <OnlineNow game={game} />
        </p>
      </div>
    </header>
  )
}

/**
 * How many people are at this game's tables now — only for games whose admin setting shows it (off for Poker and
 * Blackjack) and only when someone is playing (a 0 tells nothing). AI players are never counted.
 */
function OnlineNow({ game }: { game: GameDetail }) {
  const { t, i18n } = useTranslation()
  const count = game.playing_now
  if (!game.show_online || !count) return null
  return (
    <span className="flex items-center gap-1 rounded-full bg-emerald-900/85 px-2.5 py-1">
      <span
        aria-hidden="true"
        className="size-2 rounded-full bg-[#3ccf6e] motion-safe:animate-pulse"
      />
      {t('game.playingNow', { count, formatted: count.toLocaleString(i18n.language) })}
    </span>
  )
}
