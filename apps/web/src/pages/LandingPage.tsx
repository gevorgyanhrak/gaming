import { useTranslation } from 'react-i18next'
import { CasinoHero } from '@/features/casino/CasinoHero'
import { GameShowcase } from '@/features/casino/GameShowcase'
import { MAIN_GAMES } from '@/features/casino/mainGames'
import { PromoBanners } from '@/features/casino/PromoBanners'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { RecentlyPlayed } from '@/features/catalog/components/RecentlyPlayed'
import { LiveBigWins } from '@/features/wins/LiveBigWins'
import { LeaderboardCard } from '@/features/leaderboards/LeaderboardCard'
import { RankLadder } from '@/features/levels/components/RankLadder'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { Container } from '@/shared/ui/Container'

export function LandingPage() {
  const { t } = useTranslation()
  const config = usePublicConfig()

  return (
    <>
      {config.data ? <title>{config.data.name}</title> : null}

      <CasinoHero />

      <div className="bg-[radial-gradient(ellipse_at_50%_0%,#13241b_0%,#07110c_70%)]">
        <Container className="py-8">
          <PromoBanners />
          <section id="showcase" aria-labelledby="top-games" className="scroll-mt-24">
            <h2
              id="top-games"
              className="casino-gold mb-4 font-display text-2xl font-bold sm:text-3xl"
            >
              {t('casino.topGames')}
            </h2>
            <GameShowcase />
          </section>
          <RecentlyPlayed />
          <section id="games" aria-labelledby="all-games" className="mt-10 scroll-mt-24">
            <h2 id="all-games" className="mb-4 font-display text-2xl font-bold text-fg sm:text-3xl">
              {t('casino.allGames')}
            </h2>
            <GameGrid banners exclude={MAIN_GAMES} />
          </section>
          <div className="mt-10">
            <div className="grid gap-5 lg:grid-cols-2">
              <LiveBigWins />
              <LeaderboardCard />
            </div>
          </div>
        </Container>
      </div>

      <section className="surface-walnut">
        <Container className="py-8">
          <h2 className="mb-5 text-2xl font-bold text-fg">{t('ranks.title')}</h2>
          <RankLadder />
        </Container>
      </section>
    </>
  )
}
