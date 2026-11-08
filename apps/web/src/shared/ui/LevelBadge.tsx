import { useTranslation } from 'react-i18next'

/** Colour tiers: bronze, silver, gold, ruby (from 30, where levels get harder), amethyst (from 50). */
function tier(level: number) {
  if (level >= 50)
    return 'border-[#e6d2ff]/70 bg-linear-to-b from-[#b98cf3] to-[#5a2b9a] text-white shadow-[0_0_8px_rgb(185_140_243/0.6)]'
  if (level >= 30)
    return 'border-[#ffd0d4]/70 bg-linear-to-b from-[#e8606c] to-[#8a1c2a] text-white shadow-[0_0_6px_rgb(232_96_108/0.5)]'
  if (level >= 20)
    return 'border-[#fff1c7]/80 bg-linear-to-b from-gold-light to-gold-dark text-black'
  if (level >= 10) return 'border-white/80 bg-linear-to-b from-[#eef1f5] to-[#8d96a3] text-black'
  return 'border-[#f2c79a]/70 bg-linear-to-b from-[#c98a4e] to-[#7a4a20] text-white'
}

const sizes = {
  sm: 'h-4 min-w-4 px-0.5 text-[0.6rem]',
  md: 'h-5 min-w-5 px-1 text-[0.7rem]',
  lg: 'h-7 min-w-7 px-1.5 text-sm',
} as const

/** A player's level: the same compact badge next to every avatar (people and bots). */
export function LevelBadge({
  level,
  size = 'md',
  className = '',
}: {
  level: number
  size?: keyof typeof sizes
  className?: string
}) {
  const { t } = useTranslation()
  return (
    <span
      className={`inline-flex items-center justify-center rounded-md border leading-none font-black tabular-nums ${sizes[size]} ${tier(level)} ${className}`}
      title={t('level.badge', { level })}
    >
      <span aria-hidden="true">{level}</span>
      <span className="sr-only">{t('level.badge', { level })}</span>
    </span>
  )
}
