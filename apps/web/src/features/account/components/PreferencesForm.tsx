import { useTranslation } from 'react-i18next'
import type { CurrentUser } from '@/features/auth/api'
import { playSound, unlockSounds } from '@/features/play/sounds'
import { bundledLocales, isLocale, languageName } from '@/shared/i18n'
import { Card } from '@/shared/ui/Card'
import { SelectField } from '@/shared/ui/Field'
import { updateSettings, useAccountMutation } from '../api'

/** Saved immediately on change; the account's language also becomes the UI language. */
export function PreferencesForm({
  user,
  serverLocales,
}: {
  user: CurrentUser
  serverLocales: readonly string[]
}) {
  const { t, i18n } = useTranslation()
  const mutation = useAccountMutation(updateSettings)
  const locales = bundledLocales.filter((locale) => serverLocales.includes(locale))

  return (
    <Card title={t('settings.preferencesSection')}>
      <div className="space-y-5">
        <SelectField
          label={t('footer.language')}
          value={user.locale ?? i18n.language}
          disabled={mutation.isPending}
          onChange={(event) => {
            const locale = event.target.value
            if (isLocale(locale)) mutation.mutate({ locale })
          }}
        >
          {locales.map((locale) => (
            <option key={locale} value={locale}>
              {languageName(locale)}
            </option>
          ))}
        </SelectField>

        <label className="flex items-center justify-between gap-4">
          <span>
            <span className="block text-sm font-medium">{t('settings.sound')}</span>
            <span className="block text-sm text-muted">{t('settings.soundHint')}</span>
          </span>
          <input
            type="checkbox"
            role="switch"
            className="size-5 accent-accent"
            checked={user.profile.preferences.sound}
            disabled={mutation.isPending}
            onChange={(event) => {
              const sound = event.target.checked
              mutation.mutate({ preferences: { sound } })
              // A preview; the click is the gesture that lets the browser play audio.
              if (sound) {
                unlockSounds()
                window.setTimeout(() => playSound('yourTurn'), 60)
              }
            }}
          />
        </label>
      </div>
    </Card>
  )
}
