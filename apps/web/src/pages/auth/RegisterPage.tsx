import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { z } from 'zod'
import { isUsernameAvailable, register as registerAccount, type Channel } from '@/features/auth/api'
import { AuthCard } from '@/features/auth/components/AuthCard'
import { applyServerErrors, useMessage } from '@/features/auth/forms'
import { useSetCurrentUser } from '@/features/auth/hooks'
import {
  confirmed,
  emailField,
  passwordField,
  phoneField,
  usernameField,
} from '@/features/auth/schemas'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { TextField } from '@/shared/ui/Field'
import { Skeleton } from '@/shared/ui/Skeleton'

const schema = z
  .object({
    method: z.enum(['email', 'phone']),
    email: z.string(),
    phone: z.string(),
    username: usernameField,
    password: passwordField,
    password_confirmation: z.string(),
  })
  .superRefine((value, ctx) => {
    // Only the identifier for the chosen method is validated (and sent).
    const field = value.method === 'email' ? emailField : phoneField
    const result = field.safeParse(value[value.method])
    if (!result.success) {
      ctx.addIssue({
        code: 'custom',
        path: [value.method],
        message: result.error.issues[0]?.message,
      })
    }
    confirmed(value, ctx)
  })

type Values = z.infer<typeof schema>
const fields = ['email', 'phone', 'username', 'password', 'password_confirmation'] as const

export function RegisterPage() {
  const { t } = useTranslation()
  const message = useMessage()
  const config = usePublicConfig()
  const setCurrentUser = useSetCurrentUser()
  const [formError, setFormError] = useState<string | null>(null)
  const [usernameHint, setUsernameHint] = useState<'available' | 'taken' | null>(null)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      method: 'email',
      email: '',
      phone: '',
      username: '',
      password: '',
      password_confirmation: '',
    },
  })
  const method = useWatch({ control: form.control, name: 'method' })

  const mutation = useMutation({
    // The API only reads the identifier for the chosen method and ignores the other one.
    mutationFn: (values: Values) => registerAccount(values),
    // RequireGuest then continues to verification of the contact we just sent a code to.
    onSuccess: (user, values) => setCurrentUser(user, `/verify?channel=${values.method}&sent=1`),
    onError: (error) => setFormError(applyServerErrors(error, form.setError, fields)),
  })

  const checkUsername = async () => {
    setUsernameHint(null)
    if (!(await form.trigger('username'))) return
    const available = await isUsernameAvailable(form.getValues('username')).catch(() => null)
    if (available !== null) setUsernameHint(available ? 'available' : 'taken')
  }

  const onSubmit = form.handleSubmit((values) => {
    setFormError(null)
    mutation.mutate(values)
  })

  const { errors } = form.formState
  const footer = (
    <>
      {t('auth.haveAccount')}{' '}
      <Link to="/login" className="font-semibold text-accent-text hover:underline">
        {t('auth.login')}
      </Link>
    </>
  )

  if (config.isPending) {
    return (
      <AuthCard title={t('auth.registerTitle')} footer={footer}>
        <Skeleton className="h-80 w-full" />
      </AuthCard>
    )
  }

  if (config.data && !config.data.registration.enabled) {
    return (
      <AuthCard title={t('auth.registerTitle')} footer={footer}>
        <Alert tone="info">{t('auth.registrationClosed')}</Alert>
      </AuthCard>
    )
  }

  return (
    <AuthCard title={t('auth.registerTitle')} subtitle={t('auth.registerSubtitle')} footer={footer}>
      <form onSubmit={(e) => void onSubmit(e)} noValidate className="space-y-4">
        {formError ? <Alert tone="danger">{message(formError)}</Alert> : null}

        <fieldset>
          <legend className="mb-1.5 text-sm font-medium">{t('auth.method')}</legend>
          <div className="grid grid-cols-2 gap-1 rounded-xl border border-border bg-bg p-1">
            {(['email', 'phone'] as Channel[]).map((option) => (
              <label
                key={option}
                className={`cursor-pointer rounded-lg px-3 py-2 text-center text-sm font-medium transition-colors has-focus-visible:outline-2 has-focus-visible:outline-accent-text ${
                  method === option ? 'bg-surface-raised text-fg' : 'text-muted hover:text-fg'
                }`}
              >
                <input
                  type="radio"
                  value={option}
                  className="sr-only"
                  {...form.register('method')}
                />
                {t(option === 'email' ? 'auth.methodEmail' : 'auth.methodPhone')}
              </label>
            ))}
          </div>
        </fieldset>

        {method === 'email' ? (
          <TextField
            label={t('auth.email')}
            type="email"
            autoComplete="email"
            error={errors.email?.message}
            {...form.register('email')}
          />
        ) : (
          <TextField
            label={t('auth.phone')}
            type="tel"
            autoComplete="tel"
            placeholder="+374 77 123456"
            hint={t('auth.phoneHint')}
            error={errors.phone?.message}
            {...form.register('phone')}
          />
        )}

        <TextField
          label={t('auth.username')}
          autoComplete="username"
          hint={
            usernameHint === 'available'
              ? t('auth.usernameAvailable')
              : usernameHint === 'taken'
                ? t('auth.usernameTaken')
                : t('auth.usernameHint')
          }
          error={errors.username?.message}
          {...form.register('username', { onBlur: () => void checkUsername() })}
        />
        <TextField
          label={t('auth.password')}
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
          {t('auth.register')}
        </Button>
      </form>
    </AuthCard>
  )
}
