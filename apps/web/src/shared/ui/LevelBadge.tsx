import { useTranslation } from 'react-i18next'
import { tierOf } from '@/features/levels/ranks'

const sizes = {
  sm: 'h-[18px] min-w-[18px] rounded-md px-0.5 text-[0.6875rem]',
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
      className={`inline-flex items-center justify-center border leading-none font-black tabular-nums ${sizes[size]} ${tierOf(level).classes} ${className}`}
      title={t('level.badge', { level })}
    >
      <span aria-hidden="true">{level}</span>
      <span className="sr-only">{t('level.badge', { level })}</span>
    </span>
  )
}
