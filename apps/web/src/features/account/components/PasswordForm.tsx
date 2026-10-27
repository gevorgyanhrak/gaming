import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import type { CurrentUser } from '@/features/auth/api'
import { applyServerErrors, useMessage } from '@/features/auth/forms'
import { confirmed, passwordField } from '@/features/auth/schemas'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { TextField } from '@/shared/ui/Field'
import { changePassword } from '../api'

const schema = z
  .object({
    current_password: z.string(),
    password: passwordField,
    password_confirmation: z.string(),
  })
  .superRefine(confirmed)
type Values = z.infer<typeof schema>

export function PasswordForm({ user }: { user: CurrentUser }) {
  const { t } = useTranslation()
  const message = useMessage()
  const [status, setStatus] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { current_password: '', password: '', password_confirmation: '' },
  })
  const mutation = useMutation({ mutationFn: changePassword })

  const onSubmit = form.handleSubmit(({ current_password, ...values }) => {
    setStatus(null)
    mutation.mutate(user.has_password ? { current_password, ...values } : values, {
      onSuccess: () => {
        form.reset()
        setStatus({ tone: 'success', text: t('settings.passwordChanged') })
      },
      onError: (error) => {
        const text = applyServerErrors(error, form.setError, [
          'current_password',
          'password',
          'password_confirmation',
        ])
        if (text) setStatus({ tone: 'danger', text: message(text) })
      },
    })
  })

  const { errors } = form.formState

  return (
    <Card title={t('settings.securitySection')}>
      <form noValidate className="space-y-4" onSubmit={(e) => void onSubmit(e)}>
        {status ? <Alert tone={status.tone}>{status.text}</Alert> : null}
        {user.has_password ? (
          <TextField
            label={t('settings.currentPassword')}
            type="password"
            autoComplete="current-password"
            error={errors.current_password?.message}
            {...form.register('current_password')}
          />
        ) : null}
        <TextField
          label={t('auth.newPassword')}
          type="password"
          autoComplete="new-password"
          hint={t('auth.passwordHint')}
          error={errors.password?.message}
          {...form.register('password')}
        />
        <TextField
          label={t('auth.passwordConfirmation')}
          type="password"
          autoComplete="new-password"
          error={errors.password_confirmation?.message}
          {...form.register('password_confirmation')}
        />
        <Button type="submit" disabled={mutation.isPending}>
          {t('settings.changePassword')}
        </Button>
      </form>
    </Card>
  )
}
