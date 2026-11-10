import { useTranslation } from 'react-i18next'
import { Button } from '@/shared/ui/Button'
import { Skeleton } from '@/shared/ui/Skeleton'
import { openDailyBonus, useDailyBonus } from '../dailyBonus'
import { FishkaIcon } from './FishkaIcon'

/** Today's daily bonus, and the way back to the popup after it was closed. */
export function DailyBonusCard({ className = 'club-panel' }: { className?: string }) {
  const { t, i18n } = useTranslation()
  const bonus = useDailyBonus()
  // Holds its place while loading (isLoading: the query is off for guests); a failed bonus just isn't shown (the popup has its own path).
  if (bonus.isLoading) return <Skeleton className="h-24 w-full rounded-panel" />
  if (!bonus.data) return null
  const { day, claimed, amount } = bonus.data

  return (
    <section className={`flex flex-wrap items-center gap-3 rounded-panel p-5 sm:p-6 ${className}`}>
      <FishkaIcon className="size-10" />
      <div className="min-w-0 flex-1">
        <h2 className="font-bold">{t('daily.title')}</h2>
        <p className="text-sm text-muted">
          {t('daily.day', { day })} ·{' '}
          {claimed ? t('daily.collected') : amount.toLocaleString(i18n.language)}
        </p>
      </div>
      <Button variant={claimed ? 'secondary' : 'gold'} onClick={openDailyBonus}>
        {claimed ? t('daily.open') : t('daily.collect')}
      </Button>
    </section>
  )
}
