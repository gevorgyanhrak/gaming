import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import type { CurrentUser } from '@/features/auth/api'
import { applyServerErrors, useMessage } from '@/features/auth/forms'
import { countryCodes, countryName, isCountryCode } from '@/shared/lib/countries'
import { Alert } from '@/shared/ui/Alert'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { SelectField, TextAreaField, TextField } from '@/shared/ui/Field'
import { updateProfile, useAccountMutation } from '../api'

const schema = z.object({
  display_name: z.string().trim().max(40, 'validation.max40'),
  bio: z.string().trim().max(500, 'validation.max500'),
  country: z.string(),
})
type Values = z.infer<typeof schema>

export function ProfileForm({ user }: { user: CurrentUser }) {
  const { t, i18n } = useTranslation()
  const message = useMessage()
  const [status, setStatus] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null)

  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      display_name: user.profile.display_name ?? '',
      bio: user.profile.bio ?? '',
      country: user.profile.country ?? '',
    },
  })

  const mutation = useAccountMutation(updateProfile)
  const countries = countryCodes
    .map((code) => ({ code, name: countryName(code, i18n.language) }))
    .sort((a, b) => a.name.localeCompare(b.name, i18n.language))

  const onSubmit = form.handleSubmit((values) => {
    setStatus(null)
    mutation.mutate(
      {
        display_name: values.display_name || null,
        bio: values.bio || null,
        country: isCountryCode(values.country) ? values.country : null,
      },
      {
        onSuccess: () => setStatus({ tone: 'success', text: t('settings.saved') }),
        onError: (error) => {
          const text = applyServerErrors(error, form.setError, ['display_name', 'bio', 'country'])
          if (text) setStatus({ tone: 'danger', text: message(text) })
        },
      },
    )
  })

  const { errors } = form.formState

  return (
    <Card title={t('settings.profileSection')}>
      <form noValidate className="space-y-4" onSubmit={(e) => void onSubmit(e)}>
        {status ? <Alert tone={status.tone}>{status.text}</Alert> : null}
        <TextField
          label={t('settings.displayName')}
          hint={t('settings.displayNameHint')}
          error={errors.display_name?.message}
          {...form.register('display_name')}
        />
        <TextAreaField
          label={t('settings.bio')}
          error={errors.bio?.message}
          {...form.register('bio')}
        />
        <SelectField
          label={t('settings.country')}
          error={errors.country?.message}
          {...form.register('country')}
        >
          <option value="">{t('settings.noCountry')}</option>
          {countries.map(({ code, name }) => (
            <option key={code} value={code}>
              {name}
            </option>
          ))}
        </SelectField>
        <Button type="submit" disabled={mutation.isPending}>
          {t('settings.save')}
        </Button>
      </form>
    </Card>
  )
}
