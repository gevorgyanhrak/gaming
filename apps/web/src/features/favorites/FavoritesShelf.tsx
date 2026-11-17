import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { SlotPoster } from '@/features/casino/SlotPoster'
import { fetchSlots, slotsKey } from '@/features/casino/slots'
import { GamePoster } from '@/features/catalog/components/GamePoster'
import { useGames } from '@/features/catalog/queries'
import { useFavorites } from './favorites'

/**
 * The player's saved games and slots, newest first, in one row that scrolls sideways (a phone shows two and a
 * bit, so it's plain there is more). Nothing at all until something is saved.
 */
export function FavoritesShelf() {
  const { t } = useTranslation()
  const favorites = useFavorites()
  const games = useGames()
  const slots = useQuery({ queryKey: slotsKey, queryFn: ({ signal }) => fetchSlots(signal) })

  const items = (favorites.data ?? []).flatMap((favorite) => {
    if (favorite.kind === 'game') {
      const game = games.data?.find((g) => g.slug === favorite.slug)
      return game
        ? [{ key: `game-${game.slug}`, node: <GamePoster game={game} morph={false} /> }]
        : []
    }
    const slot = slots.data?.find((s) => s.slug === favorite.slug)
    return slot ? [{ key: `slot-${slot.slug}`, node: <SlotPoster slot={slot} /> }] : []
  })
  if (items.length === 0) return null

  return (
    <section aria-labelledby="favorites-title">
      <h2 id="favorites-title" className="mb-3 text-2xl font-bold text-fg">
        {t('favorites.title')}
      </h2>
      <ul className="-mx-4 flex snap-x scroll-px-4 [scrollbar-width:none] gap-3 overflow-x-auto px-4 pt-1 pb-2 sm:mx-0 sm:gap-4 sm:px-0 [&::-webkit-scrollbar]:hidden">
        {items.map((item) => (
          <li key={item.key} className="flex w-[42%] shrink-0 snap-start sm:w-48 lg:w-52">
            <div className="flex w-full flex-col">{item.node}</div>
          </li>
        ))}
      </ul>
    </section>
  )
}
