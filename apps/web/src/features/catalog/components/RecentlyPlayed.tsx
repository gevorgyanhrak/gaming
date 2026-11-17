import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { SectionHeading } from '@/shared/ui/SectionHeading'
import { useCurrentUser } from '@/features/auth/hooks'
import { fetchMyGames, myGamesKey } from '@/features/play/api'
import { useGames } from '../queries'
import { GamePoster } from './GamePoster'

const SHOW = 4

/**
 * The games the signed-in player played last (from their own game history), newest first, each once. Nothing for
 * a guest or someone who hasn't played yet.
 */
export function RecentlyPlayed() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const games = useGames()
  const history = useQuery({
    queryKey: myGamesKey(1),
    queryFn: ({ signal }) => fetchMyGames(1, signal),
    enabled: !!user,
  })

  const slugs = [...new Set((history.data?.data ?? []).map((room) => room.game.slug))]
  const recent = slugs
    .map((slug) => games.data?.find((g) => g.slug === slug))
    .filter((g) => g !== undefined)
    .slice(0, SHOW)
  if (!user || recent.length === 0) return null

  return (
    <section aria-labelledby="recently-played" className="mt-14">
      <SectionHeading
        id="recently-played"
        eyebrow={t('home.eyebrows.recent')}
        title={t('lobby.recent')}
      />
      <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        {recent.map((game) => (
          <li key={game.slug} className="flex">
            <div className="flex w-full flex-col">
              {/* The lobby below shows these games too: only its posters travel to the game page. */}
              <GamePoster game={game} morph={false} />
            </div>
          </li>
        ))}
      </ul>
    </section>
  )
}
