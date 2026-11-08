import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { Button } from '@/shared/ui/Button'
import { useLevel } from '../hooks'
import { itemLabel } from '../items'
import { offerLevelUp } from '../popup'
import { LevelProgress } from './LevelProgress'

/** The signed-in player's level card: the progress bar and what the next level gives. */
export function LevelCard({ className = '' }: { className?: string }) {
  const { t, i18n } = useTranslation()
  const level = useLevel()
  const { data: user } = useCurrentUser()
  if (!level.data) return null
  const next = level.data.next_reward

  return (
    <section
      aria-label={t('level.card')}
      className={`club-panel rounded-2xl border border-gold/25 p-4 sm:p-5 ${className}`}
    >
      {level.data.pending.length > 0 && user ? (
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gold/40 bg-gold/10 px-3 py-2">
          <p className="text-sm font-semibold text-gold-light">{t('level.pendingReward')}</p>
          <Button
            variant="gold"
            className="min-h-11 px-4 text-sm"
            onClick={() => offerLevelUp(user.id, true)}
          >
            {t('level.collect')}
          </Button>
        </div>
      ) : null}
      <LevelProgress status={level.data} />
      {next ? (
        <p className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted">
          <span>{t('level.nextReward', { level: next.level })}:</span>
          <span className="inline-flex items-center gap-1 font-semibold text-gold-light tabular-nums">
            <FishkaIcon className="size-4" />
            {next.fishkas.toLocaleString(i18n.language)}
          </span>
          {next.items.map((item) => (
            <span key={`${item.kind}-${item.key}`} className="font-semibold text-fg">
              + {itemLabel(t, item)}
            </span>
          ))}
        </p>
      ) : null}
    </section>
  )
}
