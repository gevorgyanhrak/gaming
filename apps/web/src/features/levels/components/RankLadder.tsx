import { useTranslation } from 'react-i18next'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import { tierOf } from '../ranks'

const tiers = [
  { level: 1, to: 9 },
  { level: 10, to: 19 },
  { level: 20, to: 29 },
  { level: 30, to: 49 },
  { level: 50, to: null },
] as const

/** The five club ranks and the levels each covers, on a walnut band. */
export function RankLadder() {
  const { t } = useTranslation()
  return (
    <ol className="grid grid-cols-5 gap-2 text-center">
      {tiers.map(({ level, to }) => (
        <li key={level} className="flex flex-col items-center gap-1.5">
          <LevelBadge level={level} size="lg" />
          <span className="text-[0.8125rem] font-semibold text-fg sm:text-base">
            {t(`ranks.${tierOf(level).key}`)}
          </span>
          <span className="text-xs text-muted">
            {to === null
              ? t('ranks.levelsUp', { from: level })
              : t('ranks.levels', { from: level, to })}
          </span>
        </li>
      ))}
    </ol>
  )
}
