import { useTranslation } from 'react-i18next'
import { useGames } from '@/features/catalog/queries'
import { Leaderboard } from '@/features/leaderboards/Leaderboard'
import { Container } from '@/shared/ui/Container'

/** Leaderboards: today's, this week's and this month's biggest winners, overall or per game. */
export function LeaderboardsPage() {
  const { t } = useTranslation()
  const games = useGames()

  return (
    <Container className="py-6">
      <title>{t('leaders.title')}</title>
      <h1 className="casino-gold font-display text-3xl font-bold sm:text-4xl">
        {t('leaders.title')}
      </h1>
      <p className="mt-1 text-sm text-muted">{t('leaders.subtitle')}</p>
      <div className="club-panel mt-4 rounded-3xl p-4 sm:p-5">
        <Leaderboard games={(games.data ?? []).map((g) => ({ slug: g.slug, name: g.name }))} />
      </div>
    </Container>
  )
}
