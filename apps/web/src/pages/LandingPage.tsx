import { useTranslation } from 'react-i18next'
import { GameShowcase } from '@/features/casino/GameShowcase'
import { MAIN_GAMES } from '@/features/casino/mainGames'
import { PromoBanners } from '@/features/casino/PromoBanners'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { RecentlyPlayed } from '@/features/catalog/components/RecentlyPlayed'
import { HomeHero } from '@/features/home/HomeHero'
import { LiveBigWins } from '@/features/wins/LiveBigWins'
import { HomeRankings } from '@/features/rankings/HomeRankings'
import { RankLadder } from '@/features/levels/components/RankLadder'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { Container } from '@/shared/ui/Container'
import { SectionHeading } from '@/shared/ui/SectionHeading'

/**
 * The home page (Midnight Gold, 2026-11-17): the hero, the administrator's promotions, the top games, what you
 * played last, the whole lobby by category, live wins with the rankings, and the club's ranks.
 */
export function LandingPage() {
  const { t } = useTranslation()
  const config = usePublicConfig()

  return (
    <>
      {config.data ? <title>{config.data.name}</title> : null}

      <HomeHero />

      <Container className="pt-10 pb-16">
        <PromoBanners />
        <section id="showcase" aria-labelledby="top-games" className="scroll-mt-24">
          <SectionHeading
            id="top-games"
            eyebrow={t('home.eyebrows.top')}
            title={t('casino.topGames')}
            action={{ to: '/games', label: t('home.seeAll') }}
          />
          <GameShowcase />
        </section>
        <RecentlyPlayed />
        <section id="games" aria-labelledby="all-games" className="mt-14 scroll-mt-24">
          <SectionHeading
            id="all-games"
            eyebrow={t('home.eyebrows.lobby')}
            title={t('casino.allGames')}
          />
          <GameGrid banners exclude={MAIN_GAMES} />
        </section>
        <div className="mt-14 grid gap-5 lg:grid-cols-2 [&>*]:min-w-0">
          <LiveBigWins />
          <HomeRankings />
        </div>
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
