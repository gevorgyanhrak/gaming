import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { isApiError } from '@/shared/api/errors'
import { Button } from './Button'

export function EmptyState({ title, children }: { title: string; children?: ReactNode }) {
  return (
    <div className="rounded-2xl border border-dashed border-border px-6 py-12 text-center">
      <p className="font-medium text-fg">{title}</p>
      {children ? <div className="mt-2 text-sm text-muted">{children}</div> : null}
    </div>
  )
}

export function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  const { t } = useTranslation()

  return (
    <div
      role="alert"
      className="flex flex-col items-center gap-4 rounded-2xl border border-danger/30 bg-danger/5 px-6 py-10 text-center"
    >
      <p className="text-fg">{message}</p>
      {onRetry ? (
        <Button variant="secondary" onClick={onRetry}>
          {t('errors.retry')}
        </Button>
      ) : null}
    </div>
  )
}

/** ErrorState for a failed query, with a user-facing message derived from the error. */
export function QueryErrorState({ error, onRetry }: { error: unknown; onRetry: () => void }) {
  const { t } = useTranslation()
  const message =
    isApiError(error) && error.isNetworkError ? t('errors.network') : t('errors.generic')

  return <ErrorState message={message} onRetry={onRetry} />
}
