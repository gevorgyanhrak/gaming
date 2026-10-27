import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Badge } from '@/shared/ui/Badge'
import type { GameSummary } from '../queries'
import { GameArtwork } from './GameArtwork'
import { PlayerCount } from './PlayerCount'

export function GameCard({ game }: { game: GameSummary }) {
  const { t } = useTranslation()

  return (
    <Link
      to={`/games/${game.slug}`}
      className="group flex w-full flex-col overflow-hidden rounded-2xl border border-border bg-surface transition-colors hover:border-accent/60 hover:bg-surface-raised"
    >
      <GameArtwork game={game} className="aspect-[16/9] w-full" />
      <div className="flex flex-1 flex-col gap-2 p-4">
        <div className="flex items-center justify-between gap-2">
          <h3 className="text-lg font-semibold text-fg">{game.name}</h3>
          {game.is_playable ? null : <Badge tone="warning">{t('catalog.comingSoon')}</Badge>}
        </div>
        <p className="line-clamp-2 text-sm text-muted">{game.description}</p>
        <p className="mt-auto pt-2 text-xs font-medium tracking-wide text-muted uppercase">
          <PlayerCount game={game} />
        </p>
      </div>
    </Link>
  )
}
