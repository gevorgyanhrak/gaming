import { GameIcon } from '@/features/catalog/components/GameArt'
import { useAchievementName, type Achievement } from './names'
import { BoltIcon, HistoryIcon, StarIcon, TrophyIcon } from '@/shared/ui/icons'

/** Each kind's look: icon and colours (gold titles, ruby streaks, emerald experience). */
const LOOK: Record<string, { Icon: typeof TrophyIcon; tone: string }> = {
  big_win: { Icon: TrophyIcon, tone: 'from-[#f7d774] to-[#b8862e] text-[#2a1b0c]' },
  streak: { Icon: BoltIcon, tone: 'from-[#e5584b] to-[#8f1d16] text-white' },
  daily_best: { Icon: StarIcon, tone: 'from-[#f7d774] to-[#b8862e] text-[#2a1b0c]' },
  weekly_champion: { Icon: TrophyIcon, tone: 'from-[#c9a2ff] to-[#5d34a8] text-white' },
}
const VETERAN = { Icon: HistoryIcon, tone: 'from-[#5fd39a] to-[#1d6b3e] text-white' }

/** One achievement as a medal: icon (the game's own for game achievements), name, and its game or day. */
export function AchievementBadge({
  achievement,
  size = 'md',
}: {
  achievement: Achievement
  size?: 'sm' | 'md'
}) {
  const name = useAchievementName()
  const look = LOOK[achievement.key] ?? VETERAN
  const label = name(achievement)
  return (
    <span
      className={`flex items-center gap-2 ${size === 'sm' ? '' : 'rounded-2xl border border-gold/20 bg-black/30 p-2 pr-3'}`}
      title={label}
    >
      <span
        className={`relative flex shrink-0 items-center justify-center rounded-full bg-linear-to-b shadow-[0_4px_12px_rgb(0_0_0/0.45),inset_0_1px_0_rgb(255_255_255/0.4)] ${look.tone} ${size === 'sm' ? 'size-7' : 'size-10'}`}
      >
        <look.Icon className={size === 'sm' ? 'size-3.5' : 'size-5'} aria-hidden="true" />
        {achievement.game ? (
          <span className="absolute -right-1 -bottom-1 flex size-5 items-center justify-center rounded-full bg-black/80 ring-1 ring-gold/50">
            <GameIcon
              slug={achievement.game.slug}
              name={achievement.game.name}
              className="size-3 text-gold"
            />
          </span>
        ) : null}
      </span>
      {size === 'sm' ? (
        <span className="sr-only">{label}</span>
      ) : (
        <span className="text-sm font-semibold">{label}</span>
      )}
    </span>
  )
}
