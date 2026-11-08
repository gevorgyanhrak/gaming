import { useTranslation } from 'react-i18next'

/** Colour tiers: bronze, silver, gold, ruby (from 30, where levels get harder), amethyst (from 50). */
function tier(level: number) {
  if (level >= 50)
    return 'border-[#e6d2ff]/70 bg-linear-to-b from-[#9266d6] to-[#4f2489] text-white shadow-[0_0_8px_rgb(185_140_243/0.6)]'
  if (level >= 30)
    return 'border-[#ffd0d4]/70 bg-linear-to-b from-[#cc4957] to-[#8a1c2a] text-white shadow-[0_0_6px_rgb(232_96_108/0.5)]'
  if (level >= 20)
    return 'border-[#fff1c7]/80 bg-linear-to-b from-gold-light to-gold-dark text-black'
  if (level >= 10) return 'border-white/80 bg-linear-to-b from-[#eef1f5] to-[#8d96a3] text-black'
  return 'border-[#f2c79a]/70 bg-linear-to-b from-[#a56c38] to-[#6e421c] text-white'
}

const sizes = {
  sm: 'h-4 min-w-4 rounded-md px-0.5 text-[0.65rem]',
  md: 'h-5 min-w-5 rounded-md px-1 text-[0.7rem]',
  lg: 'h-7 min-w-7 rounded-md px-1.5 text-sm',
  xl: 'h-14 min-w-14 rounded-xl px-2 text-2xl',
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
      className={`inline-flex items-center justify-center border leading-none font-black tabular-nums ${sizes[size]} ${tier(level)} ${className}`}
      title={t('level.badge', { level })}
    >
      <span aria-hidden="true">{level}</span>
      <span className="sr-only">{t('level.badge', { level })}</span>
    </span>
  )
}
