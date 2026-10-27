import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { z } from 'zod'
import { forgotPassword } from '@/features/auth/api'
import { AuthCard } from '@/features/auth/components/AuthCard'
import { applyServerErrors, useMessage } from '@/features/auth/forms'
import { loginField } from '@/features/auth/schemas'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { TextField } from '@/shared/ui/Field'

const schema = z.object({ login: loginField })
type Values = z.infer<typeof schema>

export function ForgotPasswordPage() {
  const { t } = useTranslation()
  const message = useMessage()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { login: '' } })

  const mutation = useMutation({
    mutationFn: (values: Values) => forgotPassword(values.login),
    // The email or phone travels in router state, never in the URL.
    onSuccess: (_, values) => void navigate('/reset-password', { state: { login: values.login } }),
    onError: (error) => setFormError(applyServerErrors(error, form.setError, ['login'])),
  })

  return (
    <AuthCard
      title={t('auth.forgotTitle')}
      subtitle={t('auth.forgotSubtitle')}
      footer={
        <Link to="/login" className="font-semibold text-accent-text hover:underline">
          {t('auth.backToLogin')}
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
          hint={t('auth.loginHint')}
          autoComplete="username"
          error={form.formState.errors.login?.message}
          {...form.register('login')}
        />
        <Button type="submit" className="w-full" disabled={mutation.isPending}>
          {t('auth.sendCode')}
        </Button>
      </form>
    </AuthCard>
  )
}
