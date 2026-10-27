import { useTranslation } from 'react-i18next'
import { useParams } from 'react-router'
import { usePublicProfile } from '@/features/account/api'
import { ProfileView } from '@/features/account/components/ProfileView'
import { isApiError } from '@/shared/api/errors'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

export function PublicProfilePage() {
  const { t } = useTranslation()
  const { username = '' } = useParams()
  const profile = usePublicProfile(username)

  return (
    <Container className="py-10">
      {profile.isPending ? (
        <Skeleton className="h-48 w-full rounded-2xl" />
      ) : profile.isError ? (
        isApiError(profile.error) && profile.error.isNotFound ? (
          <EmptyState title={t('profile.notFound')} />
        ) : (
          <QueryErrorState error={profile.error} onRetry={() => void profile.refetch()} />
        )
      ) : (
        <ProfileView user={profile.data} />
      )}
    </Container>
  )
}
