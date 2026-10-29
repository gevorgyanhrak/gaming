import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { ProfileView } from '@/features/account/components/ProfileView'
import { useCurrentUser } from '@/features/auth/hooks'
import { fetchMyGames, fetchMyStats, myGamesKey, myStatsKey } from '@/features/play/api'
import { GameRecord } from '@/features/play/components/GameRecord'
import { RoomSummary } from '@/features/play/components/RoomSummary'
import { ButtonLink } from '@/shared/ui/Button'
import { Container } from '@/shared/ui/Container'
import { Skeleton } from '@/shared/ui/Skeleton'
import { EmptyState, QueryErrorState } from '@/shared/ui/States'

const RECENT = 5

/** The signed-in user's profile, as other players see it, with their records and latest games. */
export function ProfilePage() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  // The same queries as the dashboard and history, so the numbers match and come from the cache.
  const stats = useQuery({ queryKey: myStatsKey, queryFn: ({ signal }) => fetchMyStats(signal) })
  const games = useQuery({
    queryKey: myGamesKey(1),
    queryFn: ({ signal }) => fetchMyGames(1, signal),
  })

  if (!user) return null

  const noGames = stats.data?.length === 0

  return (
    <Container className="space-y-8 py-10">
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

      {noGames ? (
        <EmptyState title={t('profile.noGames')}>
          <ButtonLink to="/games" className="mt-2">
            {t('dashboard.play')}
          </ButtonLink>
        </EmptyState>
      ) : (
        <>
          <section aria-labelledby="profile-records">
            <h2 id="profile-records" className="text-xl font-bold">
              {t('profile.records')}
            </h2>
            {stats.isPending ? (
              <Skeleton className="mt-4 h-40 w-full" />
            ) : stats.isError ? (
              <div className="mt-4">
                <QueryErrorState error={stats.error} onRetry={() => void stats.refetch()} />
              </div>
            ) : (
              <>
                <ul className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                  {stats.data.map((row) => (
                    <li
                      key={row.game.slug}
                      className="rounded-2xl border border-border bg-surface p-4"
                    >
                      <GameRecord row={row} size="lg" />
                    </li>
                  ))}
                </ul>
                <p className="mt-3 text-xs text-muted">{t('dashboard.statsNote')}</p>
              </>
            )}
          </section>

          <section aria-labelledby="profile-recent">
            <h2 id="profile-recent" className="text-xl font-bold">
              {t('profile.recent')}
            </h2>
            {games.isPending ? (
              <Skeleton className="mt-4 h-48 w-full" />
            ) : games.isError ? (
              <div className="mt-4">
                <QueryErrorState error={games.error} onRetry={() => void games.refetch()} />
              </div>
            ) : (
              <>
                <ul className="mt-4 space-y-2">
                  {games.data.data.slice(0, RECENT).map((room) => (
                    <li key={room.id}>
                      <RoomSummary room={room} />
                    </li>
                  ))}
                </ul>
                {games.data.meta.total > RECENT ? (
                  <Link
                    to="/history"
                    className="mt-4 inline-block text-sm font-medium text-accent-text hover:underline"
                  >
                    {t('dashboard.history')}
                  </Link>
                ) : null}
              </>
            )}
          </section>
        </>
      )}
    </Container>
  )
}
