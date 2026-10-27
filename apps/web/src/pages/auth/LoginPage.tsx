import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useLocation } from 'react-router'
import { z } from 'zod'
import { login } from '@/features/auth/api'
import { AuthCard } from '@/features/auth/components/AuthCard'
import { applyServerErrors, useMessage } from '@/features/auth/forms'
import { useSetCurrentUser } from '@/features/auth/hooks'
import { loginField } from '@/features/auth/schemas'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { TextField } from '@/shared/ui/Field'

const schema = z.object({
  login: loginField,
  password: z.string().min(1, 'validation.required'),
  remember: z.boolean(),
})

type Values = z.infer<typeof schema>

export function LoginPage() {
  const { t } = useTranslation()
  const message = useMessage()
  const location = useLocation()
  const setCurrentUser = useSetCurrentUser()
  const [formError, setFormError] = useState<string | null>(null)
  const notice = (location.state as { notice?: string } | null)?.notice

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { login: '', password: '', remember: true },
  })

  const mutation = useMutation({
    mutationFn: login,
    // RequireGuest then continues to the `next` page.
    onSuccess: (user) => setCurrentUser(user),
    onError: (error) =>
      setFormError(applyServerErrors(error, form.setError, ['login', 'password'])),
  })

  const onSubmit = form.handleSubmit((values) => {
    setFormError(null)
    mutation.mutate(values)
  })

  const { errors } = form.formState

  return (
    <AuthCard
      title={t('auth.loginTitle')}
      subtitle={t('auth.loginSubtitle')}
      footer={
        <>
          {t('auth.noAccount')}{' '}
          <Link to="/register" className="font-semibold text-accent-text hover:underline">
            {t('auth.signUp')}
          </Link>
        </>
      }
    >
      <form onSubmit={(e) => void onSubmit(e)} noValidate className="space-y-4">
        {notice === 'password-reset' ? <Alert tone="success">{t('auth.resetDone')}</Alert> : null}
        {formError ? <Alert tone="danger">{message(formError)}</Alert> : null}

        <TextField
          label={t('auth.loginField')}
          hint={t('auth.loginHint')}
          autoComplete="username"
          error={errors.login?.message}
          {...form.register('login')}
        />
        <TextField
          label={t('auth.password')}
          type="password"
          autoComplete="current-password"
          error={errors.password?.message}
          {...form.register('password')}
        />

        <div className="flex items-center justify-between gap-4 text-sm">
          <label className="flex items-center gap-2 text-muted">
            <input
              type="checkbox"
              className="size-4 accent-accent"
              {...form.register('remember')}
            />
            {t('auth.remember')}
          </label>
          <Link to="/forgot-password" className="font-medium text-accent-text hover:underline">
            {t('auth.forgot')}
          </Link>
        </div>

        <Button type="submit" className="w-full" disabled={mutation.isPending}>
          {t('auth.login')}
        </Button>
      </form>
    </AuthCard>
  )
}
