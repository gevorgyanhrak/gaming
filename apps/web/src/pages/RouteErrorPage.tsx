import { useTranslation } from 'react-i18next'
import { useRouteError } from 'react-router'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

/** Last-resort boundary for errors thrown while rendering a route. */
export function RouteErrorPage() {
  const { t } = useTranslation()
  const error = useRouteError()

  console.error(error)

  return (
    <Container className="py-24 text-center">
      <h1 role="alert" className="text-2xl font-bold">
        {t('errors.generic')}
      </h1>
      <div className="mt-8 flex justify-center">
        <ButtonLink to="/" variant="secondary" reloadDocument>
          {t('errors.goHome')}
        </ButtonLink>
      </div>
    </Container>
  )
}
