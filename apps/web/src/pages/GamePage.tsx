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

export function GamePage() {
  const { t } = useTranslation()
  const { slug = '' } = useParams()
  const game = useGame(slug)

  return (
    <Container className="py-6 sm:py-8">
      <Link
        to="/games"
        className="inline-flex items-center gap-1.5 text-sm font-medium text-accent-text hover:underline"
      >
        <BackIcon className="size-4" />
        {t('game.back')}
      </Link>

      <div className="mt-4">
        {game.isPending ? (
          <div data-testid="game-loading" className="space-y-4">
            <Skeleton className="h-14 w-1/3" />
            <Skeleton className="aspect-[16/9] w-full max-w-2xl rounded-3xl" />
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

            {game.data.is_playable ? (
              <PlayPanel game={game.data} hero={<GameHero game={game.data} />} />
            ) : (
              <div className="grid gap-6 lg:grid-cols-[1.15fr_1fr] lg:items-start">
                <GameHero game={game.data} />
                <p className="club-panel rounded-3xl px-5 py-4 text-muted motion-safe:animate-rise">
                  {t('game.notPlayable')}
                </p>
              </div>
            )}

            <div className="mt-10 grid gap-6 lg:grid-cols-[1fr_1.4fr]">
              {game.data.modes && game.data.modes.length > 0 ? (
                <section className="club-panel rounded-3xl p-5 sm:p-6">
                  <h2 className="text-xl font-bold">{t('game.modes')}</h2>
                  <ul className="mt-4 space-y-3">
                    {game.data.modes.map((mode) => (
                      <li key={mode.slug} className="rounded-2xl bg-black/20 p-4">
                        <p className="font-semibold">{mode.name}</p>
                        {mode.description ? (
                          <p className="mt-1 text-sm text-muted">{mode.description}</p>
                        ) : null}
                        <p className="mt-2 text-xs font-medium tracking-wide text-muted uppercase">
                          <PlayerCount game={mode} />
                        </p>
                      </li>
                    ))}
                  </ul>
                </section>
              ) : null}

              <section className="club-panel rounded-3xl p-5 sm:p-6">
                <h2 className="text-xl font-bold">{t('game.rules')}</h2>
                <div className="mt-4 space-y-4 leading-relaxed text-fg/90">
                  {splitParagraphs(game.data.rules).map((paragraph, index) => (
                    <p key={index}>{paragraph}</p>
                  ))}
                </div>
              </section>
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
    <header className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <ViewTransition name={titleTransition(game.slug)}>
          <h1 className="text-gold-gradient text-4xl font-extrabold tracking-tight uppercase sm:text-6xl">
            {game.name}
          </h1>
        </ViewTransition>
        {game.is_playable ? null : <Badge tone="warning">{t('catalog.comingSoon')}</Badge>}
      </div>
      <p className="max-w-2xl text-fg/80 motion-safe:animate-rise">{game.description}</p>

      <div className="relative overflow-hidden rounded-[1.75rem] border border-gold/30 shadow-2xl shadow-black/50">
        <ViewTransition name={artTransition(game.slug)}>
          <GameArtwork game={game} className="aspect-[16/9] w-full" />
        </ViewTransition>
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-linear-to-t from-black/85 via-black/10 to-transparent"
        />
        <p className="absolute top-4 right-4 rounded-full border border-white/15 bg-black/45 px-3 py-1 text-sm font-medium backdrop-blur">
          <PlayerCount game={game} />
        </p>
        {record.length > 0 ? (
          <dl className="absolute inset-x-0 bottom-0 grid grid-cols-3 gap-2 p-4 sm:p-6">
            {record.map(({ key, Icon, label, value }, index) => (
              <div
                key={key}
                className="flex flex-col items-center motion-safe:animate-rise"
                style={{ animationDelay: `${250 + index * 90}ms` }}
              >
                <dt className="text-xs font-medium tracking-wide text-fg/80 uppercase sm:text-sm">
                  {label}
                </dt>
                <dd className="mt-1 flex items-center gap-2 text-2xl font-extrabold sm:text-4xl">
                  <Icon className="size-6 text-gold sm:size-8" />
                  <span className={key === 'wins' ? 'text-gold' : ''}>{value}</span>
                </dd>
              </div>
            ))}
          </dl>
        ) : null}
      </div>
    </header>
  )
}
