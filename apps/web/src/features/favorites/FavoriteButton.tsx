import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { isFavorite, useFavorites, useToggleFavorite, type FavoriteKind } from './favorites'

/**
 * The ♥ on a catalogue card: saves the game or slot to the player's favourites, or takes it off. Signed-in
 * players only (favourites live on the server). Pressed state, not only colour: the heart fills.
 */
export function FavoriteButton({
  kind,
  slug,
  name,
  className = '',
}: {
  kind: FavoriteKind
  slug: string
  name: string
  className?: string
}) {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const favorites = useFavorites()
  const toggle = useToggleFavorite()
  if (!user) return null

  const on = isFavorite(favorites.data, kind, slug)

  return (
    <button
      type="button"
      aria-pressed={on}
      aria-label={t(on ? 'favorites.remove' : 'favorites.add', { name })}
      title={t(on ? 'favorites.remove' : 'favorites.add', { name })}
      onClick={(event) => {
        // The card around it is a link.
        event.preventDefault()
        event.stopPropagation()
        toggle.mutate({ kind, slug, on: !on })
      }}
      className={`grid size-10 place-items-center rounded-full bg-black/55 text-white shadow-sm backdrop-blur-[2px] transition-[transform,background-color] duration-150 hover:bg-black/75 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-text motion-safe:active:scale-90 ${className}`}
    >
      <svg
        viewBox="0 0 24 24"
        aria-hidden="true"
        className={`size-5 transition-colors ${on ? 'fill-[#ff5a6e] stroke-[#ff5a6e] motion-safe:animate-[heart-pop_260ms_ease-out]' : 'fill-none stroke-current'}`}
        strokeWidth="2"
        strokeLinejoin="round"
      >
        <path d="M12 20.5s-7.5-4.6-9.3-9.4C1.4 7.7 3.6 4.5 7 4.5c2 0 3.6 1.1 5 3 1.4-1.9 3-3 5-3 3.4 0 5.6 3.2 4.3 6.6-1.8 4.8-9.3 9.4-9.3 9.4z" />
      </svg>
    </button>
  )
}
