import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { walletKey } from '@/features/wallet/api'
import { FishkaIcon } from '@/features/wallet/components/FishkaIcon'
import { playUiSound } from '@/shared/lib/sounds'
import { Button } from '@/shared/ui/Button'
import { Skeleton } from '@/shared/ui/Skeleton'
import { claimMission, type Mission, missionsKey, useMissions } from './api'

/**
 * Daily missions and weekly challenges (HayBet item 9): what to play for today and this week, with progress from the
 * player's real games and a Collect button once done. Rewards are Fishkas, paid once by the server.
 */
export function MissionsPanel() {
  const { t } = useTranslation()
  const missions = useMissions()
  const queryClient = useQueryClient()
  const claim = useMutation({
    mutationFn: claimMission,
    onSuccess: (next) => {
      queryClient.setQueryData(missionsKey, next)
      void queryClient.invalidateQueries({ queryKey: walletKey })
      playUiSound('reward')
    },
  })

  if (missions.isLoading) return <Skeleton className="h-56 rounded-panel" />
  if (!missions.data || missions.data.length === 0) return null

  const day = missions.data.filter((m) => m.period === 'day')
  const week = missions.data.filter((m) => m.period === 'week')

  return (
    <section aria-labelledby="missions-title" className="club-panel-glass rounded-panel p-4 sm:p-5">
      <h2 id="missions-title" className="text-lg font-bold">
        {t('missions.title')}
      </h2>
      <div className="mt-3 grid gap-4 lg:grid-cols-2">
        <MissionGroup
          title={t('missions.today')}
          missions={day}
          claiming={claim.isPending ? claim.variables : null}
          onClaim={(key) => claim.mutate(key)}
        />
        <MissionGroup
          title={t('missions.week')}
          missions={week}
          claiming={claim.isPending ? claim.variables : null}
          onClaim={(key) => claim.mutate(key)}
        />
      </div>
    </section>
  )
}

function MissionGroup({
  title,
  missions,
  claiming,
  onClaim,
}: {
  title: string
  missions: Mission[]
  claiming: string | null | undefined
  onClaim: (key: string) => void
}) {
  const { t, i18n } = useTranslation()
  const ends = missions[0]?.ends_at
  return (
    <div>
      <div className="mb-2 flex items-baseline justify-between gap-2">
        <h3 className="text-sm font-semibold tracking-wide text-muted uppercase">{title}</h3>
        {ends ? <span className="text-xs text-muted">{resetsIn(ends, t)}</span> : null}
      </div>
      <ul className="space-y-2">
        {missions.map((mission) => {
          const done = mission.progress >= mission.target
          const share = Math.round((mission.progress / mission.target) * 100)
          return (
            <li
              key={mission.key}
              className={`flex items-center gap-3 rounded-xl border px-3 py-2.5 ${
                mission.claimed
                  ? 'border-white/5 bg-black/20 opacity-70'
                  : done
                    ? 'border-gold/50 bg-gold/8'
                    : 'border-white/8 bg-black/25'
              }`}
            >
              <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">
                  {t(`missions.kinds.${mission.kind}`, { count: mission.target })}
                </p>
                <div
                  role="progressbar"
                  aria-valuemin={0}
                  aria-valuemax={mission.target}
                  aria-valuenow={mission.progress}
                  aria-label={t(`missions.kinds.${mission.kind}`, { count: mission.target })}
                  className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-white/10"
                >
                  <div
                    className="h-full rounded-full bg-linear-to-r from-gold to-gold-light transition-[width] duration-500 motion-reduce:transition-none"
                    style={{ width: `${share}%` }}
                  />
                </div>
                <p className="mt-1 text-xs text-muted tabular-nums">
                  {mission.progress} / {mission.target}
                </p>
              </div>
              {mission.claimed ? (
                <span className="text-xs font-semibold text-muted">{t('missions.collected')}</span>
              ) : (
                <Button
                  size="sm"
                  variant={done ? 'gold' : 'secondary'}
                  disabled={!done}
                  loading={claiming === mission.key}
                  onClick={() => onClaim(mission.key)}
                  aria-label={t('missions.collect', {
                    amount: mission.reward.toLocaleString(i18n.language),
                  })}
                >
                  <FishkaIcon className="size-4" />+{mission.reward.toLocaleString(i18n.language)}
                </Button>
              )}
            </li>
          )
        })}
      </ul>
    </div>
  )
}

/** "Resets in 5 hours" / "in 3 days": when the period's missions start afresh. */
function resetsIn(
  ends: string,
  t: (key: 'missions.resetsHours' | 'missions.resetsDays', options: { count: number }) => string,
) {
  const hours = Math.max(1, Math.ceil((new Date(ends).getTime() - Date.now()) / 3_600_000))
  return hours >= 48
    ? t('missions.resetsDays', { count: Math.ceil(hours / 24) })
    : t('missions.resetsHours', { count: hours })
}
