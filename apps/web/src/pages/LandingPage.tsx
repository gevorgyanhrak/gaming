import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { GameShowcase } from '@/features/casino/GameShowcase'
import { MAIN_GAMES } from '@/features/casino/mainGames'
import { PromoBanners } from '@/features/casino/PromoBanners'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { RecentlyPlayed } from '@/features/catalog/components/RecentlyPlayed'
import { MissionsPanel } from '@/features/missions/MissionsPanel'
import { FavoritesShelf } from '@/features/favorites/FavoritesShelf'
import { HomeHero } from '@/features/home/HomeHero'
import { TryNext, WelcomeStrip } from '@/features/home/WelcomeStrip'
import { RankLadder } from '@/features/levels/components/RankLadder'
import { DashboardGames } from '@/features/play/components/DashboardGames'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { HomeRankings } from '@/features/rankings/HomeRankings'
import { LiveBigWins } from '@/features/wins/LiveBigWins'
import { Container } from '@/shared/ui/Container'
import { SectionHeading } from '@/shared/ui/SectionHeading'

/**
 * The one home page, for guests and players (HayBet brief item 2: `/` is home, `/dashboard` is the games section).
 * Guests get the hero; a signed-in player gets their welcome strip instead, plus their own shelves (favourites, games
 * to try, their games in progress and statistics). Everything else — promotions, top games, what you played last,
 * the lobby by category, live wins with the rankings, the club's ranks — is the same for both.
 */
export function LandingPage() {
  const { t } = useTranslation()
  const config = usePublicConfig()
  const { data: user } = useCurrentUser()

  return (
    <>
      {config.data ? <title>{config.data.name}</title> : null}

      {user ? <WelcomeStrip user={user} /> : <HomeHero />}

      <Container className="space-y-14 pt-8 pb-16 sm:pt-10">
        <PromoBanners />
        {user ? <MissionsPanel /> : null}
        {user ? <FavoritesShelf /> : null}
        <section id="showcase" aria-labelledby="top-games" className="scroll-mt-24">
          <SectionHeading
            id="top-games"
            eyebrow={t('home.eyebrows.top')}
            title={t('casino.topGames')}
            action={{ to: '/dashboard', label: t('home.seeAll') }}
          />
          <GameShowcase />
        </section>
        <RecentlyPlayed />
        <section id="games" aria-labelledby="all-games" className="scroll-mt-24">
          <SectionHeading
            id="all-games"
            eyebrow={t('home.eyebrows.lobby')}
            title={t('casino.allGames')}
          />
          <GameGrid banners exclude={MAIN_GAMES} />
        </section>
        {user ? <TryNext /> : null}
        <div className="grid gap-5 lg:grid-cols-2 [&>*]:min-w-0">
          <LiveBigWins />
          <HomeRankings />
        </div>
        {user ? <DashboardGames /> : null}
      </Container>

      <section aria-labelledby="club-ranks" className="surface-walnut">
        <Container className="py-12">
          <SectionHeading
            id="club-ranks"
            eyebrow={t('home.eyebrows.ranks')}
            title={t('ranks.title')}
          />
          <RankLadder />
        </Container>
      </section>
    </>
  )
}
