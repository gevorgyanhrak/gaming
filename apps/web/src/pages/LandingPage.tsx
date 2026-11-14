import { useTranslation } from 'react-i18next'
import { CasinoHero } from '@/features/casino/CasinoHero'
import { GameShowcase } from '@/features/casino/GameShowcase'
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
          <section id="showcase" aria-label={t('casino.mainGames')} className="scroll-mt-24">
            <GameShowcase />
          </section>
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
