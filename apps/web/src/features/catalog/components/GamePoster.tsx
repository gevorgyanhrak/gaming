import { useState, ViewTransition } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { useCurrentUser } from '@/features/auth/hooks'
import { PlayPanel } from '@/features/play/components/PlayPanel'
import { Button, ButtonLink } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Skeleton } from '@/shared/ui/Skeleton'
import { useGame, type GameSummary } from '../queries'
import { artTransition, titleTransition } from '../transitions'
import { GamePosterScene } from './HomeArt'
import { PlayerCount } from './PlayerCount'

const quickPlace =
  'absolute right-2.5 bottom-[3.75rem] opacity-100 transition-opacity duration-150 [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-focus-within:opacity-100 [@media(hover:hover)]:group-hover:opacity-100'

/** A game's poster: art, an ivory name plate, and Quick seat, which opens the play panel on the spot. */
export function GamePoster({ game }: { game: GameSummary }) {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const [seating, setSeating] = useState(false)

  return (
    <div className="group relative flex flex-col overflow-hidden rounded-card border border-gold/30 bg-emerald-800 shadow-raised transition-[transform,border-color,box-shadow] duration-150 ease-soft hover:border-gold hover:shadow-[var(--shadow-raised),0_0_0_1px_var(--color-gold)] motion-safe:hover:-translate-y-1.5">
      <Link to={`/games/${game.slug}`} data-game-card="" className="flex flex-1 flex-col">
        <ViewTransition name={artTransition(game.slug)}>
          <div className="relative aspect-[3/3.4] overflow-hidden">
            {game.thumbnail_url ? (
              <img
                src={game.thumbnail_url}
                alt=""
                loading="lazy"
                className="size-full object-cover"
              />
            ) : (
              <GamePosterScene slug={game.slug} name={game.name} className="block size-full" />
            )}
            {game.is_playable ? null : (
              <span className="absolute top-2.5 left-2.5 rounded-full bg-ivory-50 px-2.5 py-1 text-xs font-bold text-ink shadow-sm">
                {t('catalog.comingSoon')}
              </span>
            )}
          </div>
        </ViewTransition>
        <div className="surface-ivory flex-1 rounded-none px-3 pt-2.5 pb-3 shadow-none">
          <ViewTransition name={titleTransition(game.slug)}>
            <h3 className="text-base leading-[1.375rem] font-bold text-ink">{game.name}</h3>
          </ViewTransition>
          <p className="text-xs text-ink-soft">
            <PlayerCount game={game} />
          </p>
        </div>
      </Link>

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
      {game.data ? (
        <PlayPanel game={game.data} hero={null} />
      ) : (
        <Skeleton className="h-64 w-full rounded-card" />
      )}
    </Modal>
  )
}
