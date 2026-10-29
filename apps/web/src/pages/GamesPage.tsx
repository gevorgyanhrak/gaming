import { useTranslation } from 'react-i18next'
import { GameGrid } from '@/features/catalog/components/GameGrid'
import { Container } from '@/shared/ui/Container'

export function GamesPage() {
  const { t } = useTranslation()

  return (
    <Container className="py-12">
      <title>{t('catalog.title')}</title>
      <header className="mb-8">
        <h1 className="text-gold-gradient text-4xl font-extrabold tracking-tight">
          {t('catalog.title')}
        </h1>
        <p className="mt-2 text-muted">{t('catalog.subtitle')}</p>
      </header>
      <GameGrid />
    </Container>
  )
}
