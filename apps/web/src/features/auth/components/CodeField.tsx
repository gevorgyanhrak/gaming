import type { ComponentProps } from 'react'
import { useTranslation } from 'react-i18next'
import { TextField } from '@/shared/ui/Field'

/** Input for a 6-digit one-time code (numeric keypad, OS autofill from SMS). */
export function CodeField(props: Omit<ComponentProps<typeof TextField>, 'label'>) {
  const { t } = useTranslation()

  return (
    <TextField
      label={t('auth.code')}
      inputMode="numeric"
      autoComplete="one-time-code"
      maxLength={6}
      placeholder="123456"
      className="font-mono tracking-[0.4em]"
      {...props}
    />
  )
}
