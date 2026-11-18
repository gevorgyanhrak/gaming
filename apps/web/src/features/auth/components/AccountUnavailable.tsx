import { useTranslation } from 'react-i18next'
import { useMatches } from 'react-router'
import { Button } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { useCurrentUser } from '../hooks'

/**
 * Shown under the header when the account couldn't be loaded after every retry (a 429 that outlasted them, the
 * server down). The account stays unknown (`data` undefined), so nothing claims the visitor is a guest; this says
 * so and offers to try again.
 */
export function AccountUnavailable() {
  const { t } = useTranslation()
  const user = useCurrentUser()
  // Pages for signed-in players say it in their place (RequireAuth).
  const guarded = useMatches().some((match) => isGuarded(match.handle))
  if (!user.isError || user.data !== undefined || guarded) return null

  return (
    <div role="alert" className="border-b border-danger/40 bg-danger/12">
      <Container className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-2.5 text-sm">
        <p className="min-w-0 text-fg">{t('errors.account')}</p>
        <Button
          size="sm"
          variant="secondary"
          loading={user.isFetching}
          onClick={() => void user.refetch()}
        >
          {t('errors.retry')}
        </Button>
      </Container>
    </div>
  )
}

function isGuarded(handle: unknown): boolean {
  return typeof handle === 'object' && handle !== null && 'requiresAccount' in handle
}
