import { LevelBadge } from './LevelBadge'

const sizes = { sm: 'size-8 text-sm', md: 'size-12 text-lg', lg: 'size-24 text-3xl' } as const

/** Frames unlocked by levels (ADR 0018): a ring drawn around the avatar. */
const frames: Record<string, string> = {
  gold: 'p-[2px] bg-linear-to-br from-gold-light via-gold to-gold-dark shadow-[0_0_8px_rgb(214_171_78/0.55)]',
  legend:
    'p-[2px] bg-[conic-gradient(from_0deg,#e8606c,#f7dc8f,#b98cf3,#5fd4c4,#e8606c)] shadow-[0_0_10px_rgb(232_96_108/0.6)]',
}

/**
 * A user's avatar image, or their initial on a neutral tile. With a level, the level badge sits on its
 * corner (and a frame, if the level unlocked one, goes round it) — the same everywhere.
 */
export function Avatar({
  url,
  name,
  size = 'md',
  level,
  frame,
}: {
  url: string | null
  name: string
  size?: keyof typeof sizes
  level?: number | null
  frame?: string | null
}) {
  const face = url ? (
    <img src={url} alt="" className={`${sizes[size]} rounded-full object-cover`} />
  ) : (
    <span
      aria-hidden="true"
      className={`${sizes[size]} inline-flex shrink-0 items-center justify-center rounded-full bg-[#23301b] font-semibold text-fg uppercase`}
    >
      {name.charAt(0)}
    </span>
  )

  const ring = frame ? frames[frame] : undefined
  const framed = ring ? <span className={`inline-flex rounded-full ${ring}`}>{face}</span> : face
  if (level == null) return framed

  return (
    <span className="relative inline-flex w-fit shrink-0">
      {framed}
      <LevelBadge
        level={level}
        size={size === 'lg' ? 'lg' : size === 'md' ? 'md' : 'sm'}
        className={`absolute -bottom-1 ${size === 'lg' ? '-right-1' : level >= 100 ? '-right-2' : '-right-1.5'}`}
      />
    </span>
  )
}
