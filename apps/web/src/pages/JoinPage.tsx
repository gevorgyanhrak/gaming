import { useMutation } from '@tanstack/react-query'
import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router'
import { useMessage, withNext } from '@/features/auth/forms'
import { joinRoom } from '@/features/play/api'
import { isApiError } from '@/shared/api/errors'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

/**
 * An invite link (/join/CODE): takes the player straight to their friend's table. Guests come here
 * after logging in or signing up, which carry this page along as the place to continue to.
 */
export function JoinPage() {
  const { t } = useTranslation()
  const message = useMessage()
  const navigate = useNavigate()
  const { code = '' } = useParams()
  const inviteCode = code.trim().toUpperCase()
  const started = useRef(false)

  const join = useMutation({
    mutationFn: () => joinRoom(inviteCode),
    // Replace: going Back shouldn't join again.
    onSuccess: (room) => void navigate(`/rooms/${room.id}`, { replace: true }),
  })

  useEffect(() => {
    if (started.current) return
    started.current = true
    join.mutate()
  }, [join])

  const error = join.error

  return (
    <Container className="py-12">
      <title>{t('join.title')}</title>
      {error ? (
        isApiError(error) && error.status === 403 ? (
          <EmptyState title={message(error.message)}>
            <ButtonLink to={withNext('/verify', `/join/${inviteCode}`)} className="mt-2">
              {t('verifyBanner.action')}
            </ButtonLink>
          </EmptyState>
        ) : isApiError(error) && error.status === 422 ? (
          <EmptyState title={message(error.message)}>
            <ButtonLink to="/games" className="mt-2">
              {t('dashboard.play')}
            </ButtonLink>
          </EmptyState>
        ) : (
          <QueryErrorState error={error} onRetry={() => join.mutate()} />
        )
      ) : (
        <div className="space-y-4">
          <Skeleton className="h-40 w-full" />
          <p role="status" className="text-center text-muted">
            {t('join.joining')}
          </p>
        </div>
      )}
    </Container>
  )
}
