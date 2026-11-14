import { themeOf } from '@/games/themes'

/**
 * The game's own room behind its screens (games/themes.ts): a fixed layer under the page, so the board,
 * cards, avatars and buttons sit on it unchanged. Nothing for a game without a theme.
 */
export function GameBackdrop({ slug }: { slug: string }) {
  const theme = themeOf(slug)
  if (!theme) return null

  return (
    <div
      aria-hidden="true"
      data-game-theme={slug}
      className="pointer-events-none fixed inset-0 -z-10 bg-no-repeat motion-safe:animate-[fade-in_400ms_ease-out_both]"
      style={{
        backgroundImage: `${theme.overlay}, url(${JSON.stringify(theme.image)})`,
        backgroundSize: `100% 100%, 100% 100%, ${theme.size}`,
        backgroundPosition: `center, center, ${theme.position}`,
        boxShadow: `inset 0 0 140px rgb(0 0 0 / 0.5), inset 0 1px 0 color-mix(in srgb, ${theme.accent} 35%, transparent)`,
      }}
    />
  )
}
