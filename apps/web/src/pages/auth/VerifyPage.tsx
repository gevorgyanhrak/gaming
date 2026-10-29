import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useNavigate, useSearchParams } from 'react-router'
import { z } from 'zod'
import { sendVerificationCode, verifyCode, type Channel } from '@/features/auth/api'
import { AuthCard } from '@/features/auth/components/AuthCard'
import { CodeField } from '@/features/auth/components/CodeField'
import { ResendCodeButton } from '@/features/auth/components/ResendCodeButton'
import { applyServerErrors, safeRedirect, useMessage } from '@/features/auth/forms'
import { useCurrentUser, useSetCurrentUser } from '@/features/auth/hooks'
import { codeField } from '@/features/auth/schemas'
import { Alert } from '@/shared/ui/Alert'
import { Button, ButtonLink } from '@/shared/ui/Button'

const schema = z.object({ code: codeField })
type Values = z.infer<typeof schema>

export function VerifyPage() {
  const { t } = useTranslation()
  const message = useMessage()
  const navigate = useNavigate()
  const [params] = useSearchParams()
  const { data: user } = useCurrentUser()
  const setCurrentUser = useSetCurrentUser()
  const [formError, setFormError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)

  const requested = params.get('channel')
  const channel: Channel = requested === 'phone' ? 'phone' : 'email'
  const destination = user?.[channel] ?? null
  const verified = channel === 'email' ? user?.email_verified : user?.phone_verified

  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { code: '' } })

  const verify = useMutation({
    mutationFn: (values: Values) => verifyCode(channel, values.code),
    onSuccess: (updated) => {
      setCurrentUser(updated)
      void navigate(safeRedirect(params.get('next')), { replace: true })
    },
    onError: (error) => setFormError(applyServerErrors(error, form.setError, ['code'])),
  })

  const resend = useMutation({
    mutationFn: () => sendVerificationCode(channel),
    onMutate: () => {
      setFormError(null)
      setNotice(null)
    },
    onSuccess: () => setNotice(t('auth.codeSent')),
    onError: (error) => setFormError(applyServerErrors(error, form.setError, [])),
  })

  const title = t('auth.verifyTitle', { channel: t(`channels.${channel}`) })

  if (!user || !destination || verified) {
    return (
      <AuthCard title={title}>
        <div className="space-y-4">
          <Alert tone={verified ? 'success' : 'info'}>
            {verified ? t('auth.alreadyVerified') : t('auth.nothingToVerify')}
          </Alert>
          <ButtonLink to="/dashboard" className="w-full">
            {t('nav.dashboard')}
          </ButtonLink>
        </div>
      </AuthCard>
    )
  }

  return (
    <AuthCard title={title} subtitle={t('auth.verifySubtitle', { destination })}>
      <form
        noValidate
        className="space-y-4"
        onSubmit={(e) =>
          void form.handleSubmit((values) => {
            setFormError(null)
            verify.mutate(values)
          })(e)
        }
      >
        {notice ? <Alert tone="success">{notice}</Alert> : null}
        {formError ? <Alert tone="danger">{message(formError)}</Alert> : null}

        <CodeField error={form.formState.errors.code?.message} {...form.register('code')} />

        <div className="flex flex-col gap-3 sm:flex-row-reverse">
          <Button type="submit" className="flex-1" disabled={verify.isPending}>
            {t('auth.verifySubmit')}
          </Button>
          <ResendCodeButton
            startCoolingDown={params.get('sent') === '1'}
            pending={resend.isPending}
            onResend={() => resend.mutateAsync()}
          />
        </div>
      </form>
    </AuthCard>
  )
}
