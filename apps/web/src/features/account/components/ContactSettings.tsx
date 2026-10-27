import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { z } from 'zod'
import type { Channel, CurrentUser } from '@/features/auth/api'
import { CodeField } from '@/features/auth/components/CodeField'
import { applyServerErrors, useMessage } from '@/features/auth/forms'
import { codeField, emailField, phoneField } from '@/features/auth/schemas'
import { Alert } from '@/shared/ui/Alert'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { TextField } from '@/shared/ui/Field'
import { confirmContactChange, requestContactChange, useAccountMutation } from '../api'

export function ContactSettings({ user }: { user: CurrentUser }) {
  const { t } = useTranslation()

  return (
    <Card title={t('settings.contactSection')} description={t('settings.contactHint')}>
      <div className="divide-y divide-border">
        <ContactRow user={user} channel="email" />
        <ContactRow user={user} channel="phone" />
      </div>
    </Card>
  )
}

/** One contact: its current value and status, plus a two-step change flow (value → code). */
function ContactRow({ user, channel }: { user: CurrentUser; channel: Channel }) {
  const { t } = useTranslation()
  const current = user[channel]
  const verified = channel === 'email' ? user.email_verified : user.phone_verified
  const [step, setStep] = useState<'idle' | 'enter' | 'confirm'>('idle')
  const [pending, setPending] = useState('')

  return (
    <div className="py-4 first:pt-0 last:pb-0">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium">
            {t(channel === 'email' ? 'auth.email' : 'auth.phone')}
          </p>
          <p className="truncate text-muted">{current ?? t('settings.notSet')}</p>
        </div>
        <div className="flex items-center gap-2">
          {current ? (
            verified ? (
              <Badge>{t('settings.verified')}</Badge>
            ) : (
              <Link
                to={`/verify?channel=${channel}`}
                className="text-sm font-semibold text-warning hover:underline"
              >
                {t('settings.verify')}
              </Link>
            )
          ) : null}
          {step === 'idle' ? (
            <Button variant="secondary" onClick={() => setStep('enter')}>
              {current ? t('settings.change') : t('settings.add')}
            </Button>
          ) : null}
        </div>
      </div>

      {step === 'enter' ? (
        <NewContactForm
          channel={channel}
          onCancel={() => setStep('idle')}
          onSent={(value) => {
            setPending(value)
            setStep('confirm')
          }}
        />
      ) : null}
      {step === 'confirm' ? (
        <ConfirmContactForm
          channel={channel}
          destination={pending}
          onDone={() => setStep('idle')}
        />
      ) : null}
    </div>
  )
}

function NewContactForm({
  channel,
  onSent,
  onCancel,
}: {
  channel: Channel
  onSent: (value: string) => void
  onCancel: () => void
}) {
  const { t } = useTranslation()
  const message = useMessage()
  const [formError, setFormError] = useState<string | null>(null)
  const schema = z.object({ value: channel === 'email' ? emailField : phoneField })
  const form = useForm<{ value: string }>({
    resolver: zodResolver(schema),
    defaultValues: { value: '' },
  })
  const mutation = useMutation({
    mutationFn: (value: string) => requestContactChange(channel, value),
  })

  const onSubmit = form.handleSubmit(({ value }) => {
    setFormError(null)
    mutation.mutate(value, {
      onSuccess: () => onSent(value),
      onError: (error) => {
        // The API reports problems on the channel's field (email/phone); show them on the input.
        const text = applyServerErrors(error, (_, err) => form.setError('value', err), [channel])
        if (text) setFormError(text)
      },
    })
  })

  return (
    <form noValidate className="mt-4 space-y-3" onSubmit={(e) => void onSubmit(e)}>
      {formError ? <Alert tone="danger">{message(formError)}</Alert> : null}
      <TextField
        label={t(channel === 'email' ? 'settings.newEmail' : 'settings.newPhone')}
        type={channel === 'email' ? 'email' : 'tel'}
        autoComplete={channel === 'email' ? 'email' : 'tel'}
        hint={channel === 'phone' ? t('auth.phoneHint') : undefined}
        error={form.formState.errors.value?.message}
        {...form.register('value')}
      />
      <div className="flex gap-2">
        <Button type="submit" disabled={mutation.isPending}>
          {t('auth.sendCode')}
        </Button>
        <Button variant="secondary" onClick={onCancel}>
          {t('settings.cancel')}
        </Button>
      </div>
    </form>
  )
}

function ConfirmContactForm({
  channel,
  destination,
  onDone,
}: {
  channel: Channel
  destination: string
  onDone: () => void
}) {
  const { t } = useTranslation()
  const message = useMessage()
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<{ code: string }>({
    resolver: zodResolver(z.object({ code: codeField })),
    defaultValues: { code: '' },
  })
  const mutation = useAccountMutation((code: string) => confirmContactChange(channel, code))

  const onSubmit = form.handleSubmit(({ code }) => {
    setFormError(null)
    mutation.mutate(code, {
      onSuccess: onDone,
      onError: (error) => {
        const text = applyServerErrors(error, form.setError, ['code'])
        if (text) setFormError(text)
      },
    })
  })

  return (
    <form noValidate className="mt-4 space-y-3" onSubmit={(e) => void onSubmit(e)}>
      <Alert tone="info">{t('settings.codeSentTo', { destination })}</Alert>
      {formError ? <Alert tone="danger">{message(formError)}</Alert> : null}
      <CodeField error={form.formState.errors.code?.message} {...form.register('code')} />
      <div className="flex gap-2">
        <Button type="submit" disabled={mutation.isPending}>
          {t('settings.confirm')}
        </Button>
        <Button variant="secondary" onClick={onDone}>
          {t('settings.cancel')}
        </Button>
      </div>
    </form>
  )
}
