import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { useUserChannel } from '@/shared/realtime/useUserChannel'
import { CloseIcon } from '@/shared/ui/icons'
import { AchievementBadge } from './AchievementBadge'
import { useAchievementName, type Achievement } from './names'

/**
 * Congratulates the signed-in player the moment they earn an achievement (achievement.unlocked on their channel):
 * a small card at the bottom that goes by itself after 6 s; several queue up.
 */
export function AchievementToast() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const name = useAchievementName()
  const [queue, setQueue] = useState<Achievement[]>([])
  const current = queue[0]

  useUserChannel(user?.id, {
    'achievement.unlocked': (a: Achievement) => setQueue((q) => [...q, a]),
  })

  useEffect(() => {
    if (!current) return
    const timer = window.setTimeout(() => setQueue((q) => q.slice(1)), 6000)
    return () => window.clearTimeout(timer)
  }, [current])

  if (!current) return null
  return (
    <div className="pointer-events-none fixed inset-x-0 bottom-20 z-50 flex justify-center px-3 sm:bottom-6">
      <div
        role="status"
        aria-live="polite"
        className="pointer-events-auto flex w-full max-w-sm items-center gap-3 rounded-2xl border border-gold bg-[linear-gradient(180deg,#2a1c0b,#120c06)] px-3 py-2.5 shadow-[0_16px_40px_rgb(0_0_0/0.6),0_0_30px_rgb(240_196_98/0.3)] motion-safe:animate-rise"
      >
        <AchievementBadge achievement={current} size="sm" />
        <span className="min-w-0 flex-1">
          <span className="casino-gold block text-xs font-bold tracking-widest uppercase">
            {t('achievements.unlocked')}
          </span>
          <span className="block truncate text-sm font-bold">{name(current)}</span>
        </span>
        <button
          type="button"
          onClick={() => setQueue((q) => q.slice(1))}
          aria-label={t('wins.close')}
          className="flex size-8 shrink-0 items-center justify-center rounded-full border border-white/20 text-white/70 hover:text-white"
        >
          <CloseIcon className="size-4" />
        </button>
      </div>
    </div>
  )
}
