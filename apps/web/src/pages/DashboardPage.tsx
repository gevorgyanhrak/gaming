import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameIcon } from '@/features/catalog/components/GameArt'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { useGames, type GameSummary } from '@/features/catalog/queries'
import { fetchMyStats, myStatsKey } from '@/features/play/api'
import { DailyBonusCard } from '@/features/wallet/components/DailyBonusCard'
import { DashboardGames } from '@/features/play/components/DashboardGames'
import { LevelCard } from '@/features/levels/components/LevelCard'
import { Avatar } from '@/shared/ui/Avatar'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

/**
 * Signed-in home: games to resume, statistics, and the catalog.
 */
export function DashboardPage() {
  const { t, i18n } = useTranslation()
  const { data: user } = useCurrentUser()

  if (!user) return null

  const name = user.profile.display_name ?? user.username
  const memberSince = new Intl.DateTimeFormat(i18n.language, { dateStyle: 'long' }).format(
    new Date(user.created_at),
  )

  return (
    <>
      <title>{t('dashboard.title')}</title>
      <section className="home-hero">
        <Container className="py-8 lg:py-12">
          <div className="grid max-w-5xl items-center gap-6 lg:grid-cols-[1fr_1.1fr]">
            <div className="flex min-w-0 items-center gap-4">
              <Avatar
                url={user.profile.avatar_url}
                name={name}
                size="lg"
                level={user.level}
                frame={user.looks.frame}
              />
              <div className="min-w-0">
                <h1 className="text-gold-gradient font-display text-3xl font-bold break-words lg:text-4xl">
                  {t('dashboard.welcome', { name })}
                </h1>
                <p className="mt-1 text-xs text-muted">
                  {t('dashboard.memberSince', { date: memberSince })}
                </p>
                <ButtonLink to="/games" variant="gold" className="mt-3">
                  {t('dashboard.play')}
                </ButtonLink>
              </div>
            </div>
            <LevelCard className="relative bg-emerald-900/80" />
          </div>
        </Container>
      </section>

      <Container className="space-y-5 pb-12">
        <div className="grid gap-5 lg:grid-cols-2">
          <DailyBonusCard className="club-panel-glass" />
          <TryNext />
        </div>
        <section className="club-panel-glass rounded-panel p-5 sm:p-6">
          <h2 className="mb-5 text-2xl font-bold text-fg">{t('dashboard.gamesHeading')}</h2>
          <GameGrid />
        </section>
        <DashboardGames />
      </Container>
    </>
  )
}

/** A game the player hasn't tried, or has played least. Only with more than one game to choose from. */
function TryNext() {
  const { t } = useTranslation()
  const games = useGames()
  const stats = useQuery({ queryKey: myStatsKey, queryFn: ({ signal }) => fetchMyStats(signal) })
  if (!games.data || !stats.data || games.data.length < 2) return null

  const played = (slug: string) => stats.data.find((row) => row.game.slug === slug)?.played ?? 0
  const game = games.data
    .filter((g) => g.is_playable)
    .reduce<GameSummary | null>(
      (least, g) => (!least || played(g.slug) < played(least.slug) ? g : least),
      null,
    )
  if (!game) return null

  return (
    <section className="club-panel-glass flex items-center gap-4 rounded-panel p-5 sm:p-6">
      <GameIcon slug={game.slug} name={game.name} className="size-10 shrink-0 text-gold" />
      <div className="min-w-0 flex-1">
        <h2 className="text-sm text-muted">{t('home.tryNext')}</h2>
        <p className="truncate text-lg font-bold">{game.name}</p>
      </div>
      <ButtonLink to={`/games/${game.slug}`} variant="secondary">
        {t('catalog.play')}
      </ButtonLink>
    </section>
  )
}
