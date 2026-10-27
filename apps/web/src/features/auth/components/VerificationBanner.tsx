import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { Container } from '@/shared/ui/Container'
import type { CurrentUser } from '../api'
import { contactToVerify } from '../verification'

export function VerificationBanner({ user }: { user: CurrentUser }) {
  const { t } = useTranslation()
  const location = useLocation()
  const config = usePublicConfig()
  const channel = config.data ? contactToVerify(user, config.data.registration) : null

  if (!channel || location.pathname === '/verify') return null

  return (
    <div className="border-b border-warning/30 bg-warning/10">
      <Container className="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm">
        <span>{t('verifyBanner.message', { channel: t(`channels.${channel}`) })}</span>
        <Link
          to={`/verify?channel=${channel}`}
          className="font-semibold text-warning hover:underline"
        >
          {t('verifyBanner.action')}
        </Link>
      </Container>
    </div>
  )
}
