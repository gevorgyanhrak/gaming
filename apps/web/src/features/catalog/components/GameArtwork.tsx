import type { GameSummary } from '../queries'

/** Thumbnail, or a neutral monogram tile until an admin uploads artwork. */
export function GameArtwork({ game, className = '' }: { game: GameSummary; className?: string }) {
  if (game.thumbnail_url) {
    return <img src={game.thumbnail_url} alt="" className={`object-cover ${className}`} />
  }

  return (
    <div
      aria-hidden="true"
      className={`flex items-center justify-center bg-linear-to-br from-accent/40 via-surface-raised to-surface ${className}`}
    >
      <span className="text-4xl font-bold text-fg/80">{game.name.charAt(0)}</span>
    </div>
  )
}
