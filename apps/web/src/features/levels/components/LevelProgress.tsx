import { useTranslation } from 'react-i18next'
import { LevelBadge } from '@/shared/ui/LevelBadge'
import type { LevelStatus } from '../api'

/**
 * Progress to the next level: the level, a bar, "2,400 / 3,000 XP" (within this level) and what's left.
 * `compact` is the one-line version for menus.
 */
export function LevelProgress({
  status,
  compact = false,
}: {
  status: Pick<LevelStatus, 'level' | 'xp' | 'level_xp' | 'next_level_xp'>
  compact?: boolean
}) {
  const { t, i18n } = useTranslation()
  const format = (n: number) => n.toLocaleString(i18n.language)
  const { level, xp, level_xp: start, next_level_xp: next } = status
  const span = next === null ? 0 : next - start
  const into = next === null ? 0 : Math.min(span, Math.max(0, xp - start))
  const percent = next === null ? 100 : Math.floor((into / span) * 100)

  return (
    <div className={compact ? 'space-y-1' : 'space-y-2'}>
      <div className="flex items-center justify-between gap-3">
        <span className="flex items-center gap-2">
          <LevelBadge level={level} size={compact ? 'sm' : 'md'} />
          <span
            className={
              compact
                ? 'text-xs font-bold tracking-wide uppercase'
                : 'text-sm font-extrabold tracking-wider uppercase'
            }
          >
            {t('level.title', { level })}
          </span>
        </span>
        <span className="text-xs text-muted tabular-nums">
          {next === null
            ? t('level.top')
            : t('level.xpOf', { xp: format(into), total: format(span) })}
        </span>
      </div>
      <div
        role="progressbar"
        aria-label={t('level.progressLabel', { level: level + 1 })}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={percent}
        aria-valuetext={
          next === null
            ? t('level.top')
            : t('level.toNext', { xp: format(span - into), level: level + 1 })
        }
        className={`overflow-hidden rounded-full bg-black/40 shadow-[inset_0_1px_2px_rgb(0_0_0/0.6)] ${compact ? 'h-1.5' : 'h-3'}`}
      >
        <div
          className="h-full rounded-full bg-linear-to-r from-gold-dark via-gold to-gold-light transition-[width] duration-700 ease-out motion-reduce:transition-none"
          style={{ width: `${percent}%` }}
        />
      </div>
      {compact || next === null ? null : (
        <p className="text-xs text-muted">
          <span className="font-semibold text-fg tabular-nums">{percent}%</span>
          {' · '}
          {t('level.toNext', { xp: format(span - into), level: level + 1 })}
        </p>
      )}
    </div>
  )
}
