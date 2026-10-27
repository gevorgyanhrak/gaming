import { useTranslation } from 'react-i18next'
import { AvatarForm } from '@/features/account/components/AvatarForm'
import { ContactSettings } from '@/features/account/components/ContactSettings'
import { PasswordForm } from '@/features/account/components/PasswordForm'
import { PreferencesForm } from '@/features/account/components/PreferencesForm'
import { ProfileForm } from '@/features/account/components/ProfileForm'
import { useCurrentUser } from '@/features/auth/hooks'
import { usePublicConfig } from '@/features/platform/usePublicConfig'
import { Container } from '@/shared/ui/Container'

export function SettingsPage() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const config = usePublicConfig()

  if (!user) return null

  return (
    <Container className="py-10">
      <div className="mx-auto max-w-3xl space-y-6">
        <title>{t('settings.title')}</title>
        <h1 className="text-3xl font-bold">{t('settings.title')}</h1>
        <ProfileForm user={user} />
        <AvatarForm user={user} />
        <PreferencesForm
          user={user}
          serverLocales={config.data?.supported_locales ?? [user.locale ?? 'en']}
        />
        <ContactSettings user={user} />
        <PasswordForm user={user} />
      </div>
    </Container>
  )
}
