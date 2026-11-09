import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { LevelCard } from '@/features/levels/components/LevelCard'
import { RankLadder } from '@/features/levels/components/RankLadder'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

/** The club's ranks; signed in, the player's own place and next reward too. */
export function RanksPage() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()

  return (
    <Container className="space-y-6 py-8 sm:py-10">
      <title>{t('ranks.title')}</title>
      <h1 className="text-gold-gradient font-display text-4xl font-bold">{t('ranks.title')}</h1>
      <section className="surface-walnut rounded-panel px-4 py-6 sm:px-6">
        <RankLadder />
      </section>
      {user ? (
        <LevelCard />
      ) : user === null ? (
        <ButtonLink to="/register" variant="gold" size="lg">
          {t('nav.register')}
        </ButtonLink>
      ) : null}
    </Container>
  )
}
