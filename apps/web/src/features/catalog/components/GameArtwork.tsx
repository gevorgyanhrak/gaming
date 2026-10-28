import type { CSSProperties } from 'react'
import type { GameSummary } from '../queries'
import { GameScene } from './GameArt'

/** Uploaded artwork, else the built-in scene for the game, else a monogram tile. */
export function GameArtwork({
  game,
  className = '',
  style,
}: {
  game: GameSummary
  className?: string
  style?: CSSProperties
}) {
  if (game.thumbnail_url) {
    return (
      <img src={game.thumbnail_url} alt="" style={style} className={`object-cover ${className}`} />
    )
  }

  return (
    <GameScene
      slug={game.slug}
      className={`block ${className}`}
      style={style}
      fallback={
        <div
          aria-hidden="true"
          style={style}
          className={`flex items-center justify-center bg-linear-to-br from-accent/40 via-surface-raised to-surface ${className}`}
        >
          <span className="text-4xl font-bold text-fg/80">{game.name.charAt(0)}</span>
        </div>
      }
    />
  )
}
