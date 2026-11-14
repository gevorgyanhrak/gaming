import { useTranslation } from 'react-i18next'
import { AchievementBadge } from './AchievementBadge'
import type { Achievement } from './names'

/** A player's achievements on their profile, newest first; says so when there are none yet. */
export function PlayerAchievements({ achievements }: { achievements: Achievement[] }) {
  const { t } = useTranslation()
  return (
    <section aria-labelledby="achievements" className="mt-6">
      <h2 id="achievements" className="mb-2 text-lg font-bold">
        {t('achievements.title')}
      </h2>
      {achievements.length === 0 ? (
        <p className="text-sm text-muted">{t('achievements.none')}</p>
      ) : (
        <ul className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          {achievements.map((a, i) => (
            <li key={`${a.key}-${a.game?.slug ?? ''}-${a.period ?? ''}-${i}`}>
              <AchievementBadge achievement={a} />
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
