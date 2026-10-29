import { useTranslation } from 'react-i18next'
import { GameIcon } from '@/features/catalog/components/GameArt'
import type { GameStat } from '../api'
import { recordCounts, streakTone, winRate } from '../records'

/**
 * One game's record: win rate, the counts that add up to "played", and the win streak. The numbers
 * wrap onto more lines when space is short, so long words never force sideways scrolling.
 */
export function GameRecord({ row, size = 'md' }: { row: GameStat; size?: 'md' | 'lg' }) {
  const { t, i18n } = useTranslation()
  const Name = size === 'lg' ? 'h3' : 'span'

  return (
    <>
      <div className="flex items-start justify-between gap-3">
        <span className="flex items-center gap-2 font-medium">
          <GameIcon slug={row.game.slug} name={row.game.name} className="size-5 text-gold" />
          <Name className="font-medium">{row.game.name}</Name>
        </span>
        <span className="text-right">
          <span
            className={`block leading-tight font-bold tabular-nums ${size === 'lg' ? 'text-2xl' : 'text-lg'}`}
          >
            {winRate(row, i18n.language)}
          </span>
          <span className="block text-xs text-muted">{t('dashboard.winRate')}</span>
        </span>
      </div>
      <dl className="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-sm">
        {recordCounts(row, t).map(({ key, label, value }) => (
          <div key={key} className="flex gap-1 whitespace-nowrap">
            <dt className="text-muted">{label}</dt>
            <dd className="font-semibold tabular-nums">{value}</dd>
          </div>
        ))}
      </dl>
      {row.best_streak > 0 ? (
        <p className={`mt-1 text-sm ${streakTone(row)}`}>
          {t('dashboard.streakLine', { current: row.current_streak, best: row.best_streak })}
        </p>
      ) : null}
    </>
  )
}
