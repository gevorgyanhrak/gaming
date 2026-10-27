import { useTranslation } from 'react-i18next'
import { Container } from '@/shared/ui/Container'

/** Shown to players while maintenance mode is on. Staff keep using the app normally. */
export function MaintenanceScreen({ message }: { message: string | null }) {
  const { t } = useTranslation()

  return (
    <Container className="py-24 text-center">
      <title>{t('maintenance.title')}</title>
      <h1 className="text-3xl font-bold">{t('maintenance.title')}</h1>
      <p className="mx-auto mt-4 max-w-lg text-muted">{message || t('maintenance.body')}</p>
    </Container>
  )
}
