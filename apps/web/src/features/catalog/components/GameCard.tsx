import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Badge } from '@/shared/ui/Badge'
import type { GameSummary } from '../queries'
import { GameArtwork } from './GameArtwork'
import { PlayerCount } from './PlayerCount'

/** A lobby tile: the game's artwork, which lifts and offers "Play" on hover, over its details. */
export function GameCard({ game }: { game: GameSummary }) {
  const { t } = useTranslation()

  return (
    <Link
      to={`/games/${game.slug}`}
      className="group flex w-full flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-lg shadow-black/30 transition duration-200 hover:-translate-y-1 hover:border-gold/50 hover:shadow-gold/10"
    >
      <div className="relative overflow-hidden">
        <GameArtwork
          game={game}
          className="aspect-[16/9] w-full transition-transform duration-300 group-hover:scale-105"
        />
        <div
          aria-hidden="true"
          className="absolute inset-0 flex items-center justify-center bg-black/35 opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
        >
          {game.is_playable ? (
            <span className="rounded-full bg-linear-to-b from-gold-light via-gold to-gold-dark px-6 py-2 text-sm font-bold text-black shadow-lg">
              {t('catalog.play')}
            </span>
          ) : null}
        </div>
        {game.is_playable ? null : (
          <span className="absolute top-3 left-3">
            <Badge tone="warning">{t('catalog.comingSoon')}</Badge>
          </span>
        )}
      </div>
      <div className="flex flex-1 flex-col gap-2 p-4">
        <div className="flex items-center justify-between gap-2">
          <h3 className="text-lg font-semibold text-fg">{game.name}</h3>
          <span className="shrink-0 rounded-full bg-surface-raised px-2.5 py-0.5 text-xs font-medium text-muted">
            <PlayerCount game={game} />
          </span>
        </div>
        <p className="line-clamp-2 text-sm text-muted">{game.description}</p>
      </div>
    </Link>
  )
}
