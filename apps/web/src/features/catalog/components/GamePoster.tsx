import { useState, ViewTransition } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { FavoriteButton } from '@/features/favorites/FavoriteButton'
import { PlayPanel } from '@/features/play/components/PlayPanel'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Skeleton } from '@/shared/ui/Skeleton'
import { useGame, type GameSummary } from '../queries'
import { artTransition, titleTransition } from '../transitions'
import { GamePosterScene } from './HomeArt'
import { PlayerCount } from './PlayerCount'
import { posterArt, posterCard, posterPlate } from './posterStyles'

const quickPlace =
  'absolute right-2.5 bottom-[3.75rem] opacity-100 transition-opacity duration-150 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-focus-within:opacity-100 [@media(hover:hover)]:group-hover:opacity-100'

/**
 * A game's poster: art, an ivory name plate, and Quick seat, which opens the play panel on the spot.
 * The featured one (first in the lobby) is double width with the art's wide crop.
 */
export function GamePoster({
  game,
  featured = false,
  morph = true,
}: {
  game: GameSummary
  /** A double-width tile: wide art, a larger name. */
  featured?: boolean
  /**
   * Whether its art and name travel to the game's page. Only one poster per game on a page may (React refuses
   * two transitions of one name), so second lists (Try next, Favorites) pass false.
   */
  morph?: boolean
}) {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const [seating, setSeating] = useState(false)

  return (
    <div className={posterCard}>
      <Link to={`/games/${game.slug}`} data-game-card="" className="flex flex-1 flex-col">
        <ViewTransition name={morph ? artTransition(game.slug) : undefined}>
          <div
            className={`relative overflow-hidden ${featured ? 'aspect-[16/9] sm:aspect-[2.4/1] lg:aspect-[16/9]' : 'aspect-[3/3.4]'}`}
          >
            {game.thumbnail_url ? (
              <img
                src={game.thumbnail_url}
                alt=""
                loading="lazy"
                className={`size-full object-cover ${posterArt}`}
              />
            ) : (
              <GamePosterScene
                slug={game.slug}
                name={game.name}
                className={`block size-full ${posterArt}`}
              />
            )}
            {game.is_playable ? null : (
              <span className="absolute top-2.5 left-2.5 rounded-full border border-white/15 bg-black/65 px-2.5 py-1 text-xs font-bold text-fg shadow-sm backdrop-blur">
                {t('catalog.comingSoon')}
              </span>
            )}
          </div>
        </ViewTransition>
        <div className={posterPlate}>
          <ViewTransition name={morph ? titleTransition(game.slug) : undefined}>
            <h3
              className={`font-bold text-fg ${featured ? 'text-lg leading-6 sm:text-xl' : 'text-base leading-[1.375rem]'}`}
            >
              {game.name}
            </h3>
          </ViewTransition>
          <p className="text-xs text-muted">
            <PlayerCount game={game} />
          </p>
        </div>
      </Link>
      {/* Beside the link, not in it: a button can't sit inside a link. */}
      <FavoriteButton
        kind="game"
        slug={game.slug}
        name={game.name}
        className="absolute top-2 right-2"
      />

      {game.is_playable ? (
        user ? (
          <Button
            variant="gold"
            size="sm"
            className={quickPlace}
            aria-label={`${t('lobby.quickSeat')}: ${game.name}`}
            onClick={() => setSeating(true)}
          >
            {t('lobby.quickSeat')}
          </Button>
        ) : (
          <ButtonLink
            to={`/games/${game.slug}`}
            variant="gold"
            size="sm"
            className={quickPlace}
            aria-label={`${t('lobby.quickSeat')}: ${game.name}`}
          >
            {t('lobby.quickSeat')}
          </ButtonLink>
        )
      ) : null}

      {seating ? (
        <QuickSeat slug={game.slug} name={game.name} onClose={() => setSeating(false)} />
      ) : null}
    </div>
  )
}

function QuickSeat({ slug, name, onClose }: { slug: string; name: string; onClose: () => void }) {
  const game = useGame(slug)
  return (
    <Modal onClose={onClose} label={name} size="xl" sheetOnPhone className="p-4 sm:p-5">
      {game.data?.maintenance ? (
        <p className="text-muted" role="status">
          {game.data.maintenance_message}
        </p>
      ) : game.data ? (
        <PlayPanel game={game.data} hero={null} />
      ) : (
        <Skeleton className="h-64 w-full rounded-card" />
      )}
    </Modal>
  )
}
