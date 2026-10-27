import { useTranslation } from 'react-i18next'
import { ProfileView } from '@/features/account/components/ProfileView'
import { useCurrentUser } from '@/features/auth/hooks'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'

/** The signed-in user's profile, as other players see it. */
export function ProfilePage() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()

  if (!user) return null

  return (
    <Container className="py-10">
      <ProfileView
        user={{
          username: user.username,
          display_name: user.profile.display_name,
          avatar_url: user.profile.avatar_url,
          bio: user.profile.bio,
          country: user.profile.country,
          created_at: user.created_at,
          last_active_at: user.last_active_at,
        }}
        actions={
          <ButtonLink to="/settings" variant="secondary">
            {t('profile.edit')}
          </ButtonLink>
        }
      />
    </Container>
  )
}
