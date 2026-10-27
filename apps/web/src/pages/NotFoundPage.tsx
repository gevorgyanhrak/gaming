import { useTranslation } from 'react-i18next'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

export function NotFoundPage() {
  const { t } = useTranslation()

  return (
    <Container className="py-24 text-center">
      <title>{t('errors.notFoundTitle')}</title>
      <h1 className="text-3xl font-bold">{t('errors.notFoundTitle')}</h1>
      <p className="mt-3 text-muted">{t('errors.notFoundBody')}</p>
      <div className="mt-8 flex justify-center">
        <ButtonLink to="/" variant="secondary">
          {t('errors.goHome')}
        </ButtonLink>
      </div>
    </Container>
  )
}
