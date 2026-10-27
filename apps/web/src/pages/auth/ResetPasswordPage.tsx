import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useLocation, useNavigate } from 'react-router'
import { z } from 'zod'
import { resetPassword } from '@/features/auth/api'
import { AuthCard } from '@/features/auth/components/AuthCard'
import { CodeField } from '@/features/auth/components/CodeField'
import { applyServerErrors, useMessage } from '@/features/auth/forms'
import { codeField, confirmed, loginField, passwordField } from '@/features/auth/schemas'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { TextField } from '@/shared/ui/Field'

const schema = z
  .object({
    login: loginField,
    code: codeField,
    password: passwordField,
    password_confirmation: z.string(),
  })
  .superRefine(confirmed)

type Values = z.infer<typeof schema>

export function ResetPasswordPage() {
  const { t } = useTranslation()
  const message = useMessage()
  const navigate = useNavigate()
  const location = useLocation()
  const [formError, setFormError] = useState<string | null>(null)
  const login = (location.state as { login?: string } | null)?.login ?? ''

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { login, code: '', password: '', password_confirmation: '' },
  })

  const mutation = useMutation({
    mutationFn: resetPassword,
    onSuccess: () =>
      void navigate('/login', { replace: true, state: { notice: 'password-reset' } }),
    onError: (error) =>
      setFormError(
        applyServerErrors(error, form.setError, [
          'login',
          'code',
          'password',
          'password_confirmation',
        ]),
      ),
  })

  const { errors } = form.formState

  return (
    <AuthCard
      title={t('auth.resetTitle')}
      subtitle={login ? t('auth.resetSubtitle', { login }) : t('auth.resetSubtitleGeneric')}
      footer={
        <Link to="/forgot-password" className="font-semibold text-accent-text hover:underline">
          {t('auth.requestNewCode')}
        </Link>
      }
    >
      <form
        noValidate
        className="space-y-4"
        onSubmit={(e) =>
          void form.handleSubmit((values) => {
            setFormError(null)
            mutation.mutate(values)
          })(e)
        }
      >
        {formError ? <Alert tone="danger">{message(formError)}</Alert> : null}
        <TextField
          label={t('auth.loginField')}
          autoComplete="username"
          readOnly={Boolean(login)}
          error={errors.login?.message}
          {...form.register('login')}
        />
        <CodeField error={errors.code?.message} {...form.register('code')} />
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
        <Button type="submit" className="w-full" disabled={mutation.isPending}>
          {t('auth.resetSubmit')}
        </Button>
      </form>
    </AuthCard>
  )
}
