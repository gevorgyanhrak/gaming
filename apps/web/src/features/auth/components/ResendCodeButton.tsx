import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/shared/ui/Button'

const COOLDOWN_SECONDS = 60

/**
 * "Send a new code" with the same 60-second cooldown the API enforces, so users are
 * not invited to make requests that will be throttled.
 */
export function ResendCodeButton({
  onResend,
  startCoolingDown = false,
  pending = false,
}: {
  onResend: () => Promise<unknown>
  startCoolingDown?: boolean
  pending?: boolean
}) {
  const { t } = useTranslation()
  const [remaining, setRemaining] = useState(startCoolingDown ? COOLDOWN_SECONDS : 0)

  useEffect(() => {
    if (remaining <= 0) return
    const timer = setTimeout(() => setRemaining((s) => s - 1), 1000)
    return () => clearTimeout(timer)
  }, [remaining])

  return (
    <Button
      variant="secondary"
      disabled={remaining > 0 || pending}
      onClick={() =>
        void onResend().then(
          () => setRemaining(COOLDOWN_SECONDS),
          () => undefined,
        )
      }
    >
      {remaining > 0 ? t('auth.resendIn', { seconds: remaining }) : t('auth.resend')}
    </Button>
  )
}
