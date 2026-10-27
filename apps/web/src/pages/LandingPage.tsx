import { useTranslation } from 'react-i18next'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

export function LandingPage() {
  const { t } = useTranslation()
  const config = usePublicConfig()

  return (
    <>
      {config.data ? <title>{config.data.name}</title> : null}

      <section className="relative overflow-hidden border-b border-border">
        <div
          aria-hidden="true"
          className="pointer-events-none absolute inset-x-0 -top-40 mx-auto h-80 max-w-3xl rounded-full bg-accent/25 blur-3xl"
        />
        <Container className="relative py-20 text-center sm:py-28">
          <h1 className="mx-auto max-w-3xl text-4xl font-extrabold tracking-tight text-balance sm:text-5xl">
            {t('landing.title')}
          </h1>
          <p className="mx-auto mt-5 max-w-xl text-lg text-muted">{t('landing.subtitle')}</p>
          <div className="mt-8 flex justify-center">
            <ButtonLink to="/games">{t('landing.cta')}</ButtonLink>
          </div>
        </Container>
      </section>

      <Container className="py-14">
        <h2 className="mb-6 text-2xl font-bold">{t('landing.gamesHeading')}</h2>
        <GameGrid />
      </Container>
    </>
  )
}
