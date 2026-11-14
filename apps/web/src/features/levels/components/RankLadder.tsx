import { useTranslation } from 'react-i18next'
import { useCurrentUser } from '@/features/auth/hooks'
import { tierOf } from '../ranks'
import { RankEmblem } from './RankEmblem'

const tiers = [
  { level: 1, to: 9 },
  { level: 10, to: 19 },
  { level: 20, to: 29 },
  { level: 30, to: 49 },
  { level: 50, to: null },
] as const

/**
 * The five club ranks with their emblems and the levels each covers. Signed in, your rank is lit and a bar shows
 * how many levels are left to the next one. Ranks are earned by playing only (levels): nothing to buy.
 */
export function RankLadder() {
  const { t } = useTranslation()
  const { data: user } = useCurrentUser()
  const mine = user ? tierOf(user.level).key : null
  const next = user ? tiers.find((tier) => tier.level > user.level) : undefined
  const current = user ? [...tiers].reverse().find((tier) => tier.level <= user.level) : undefined
  const share =
    user && next && current ? (user.level - current.level) / (next.level - current.level) : null

  return (
    <div className="space-y-4">
      <ol className="grid grid-cols-5 gap-2 text-center">
        {tiers.map(({ level, to }) => {
          const key = tierOf(level).key
          const here = key === mine
          return (
            <li
              key={level}
              aria-current={here ? 'true' : undefined}
              className={`flex flex-col items-center gap-1 rounded-2xl p-2 transition ${here ? 'bg-gold/10 shadow-[0_0_0_2px_rgb(226_184_79/0.6),0_0_30px_rgb(226_184_79/0.25)]' : ''}`}
            >
              <RankEmblem
                level={level}
                size={44}
                className={`drop-shadow-[0_6px_12px_rgb(0_0_0/0.5)] ${mine && !here && level > (user?.level ?? 0) ? 'opacity-50 grayscale-[0.4]' : ''}`}
              />
              <span className="text-[0.8125rem] font-semibold text-fg sm:text-base">
                {t(`ranks.${key}`)}
              </span>
              <span className="text-xs text-muted">
                {to === null
                  ? t('ranks.levelsUp', { from: level })
                  : t('ranks.levels', { from: level, to })}
              </span>
            </li>
          )
        })}
      </ol>
      {user && next && share !== null ? (
        <div>
          <div className="mb-1 flex justify-between text-xs text-muted">
            <span>
              {t('ranks.yours', { rank: t(`ranks.${tierOf(user.level).key}`), level: user.level })}
            </span>
            <span>
              {t('ranks.toNext', {
                count: next.level - user.level,
                rank: t(`ranks.${tierOf(next.level).key}`),
              })}
            </span>
          </div>
          <div
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={Math.round(share * 100)}
            aria-label={t('ranks.progress')}
            className="h-2 overflow-hidden rounded-full bg-black/40"
          >
            <div
              className="h-full rounded-full bg-linear-to-r from-[#b8862e] to-[#fbe7a6] transition-[width] duration-700"
              style={{ width: `${Math.max(4, share * 100)}%` }}
            />
          </div>
        </div>
      ) : null}
    </div>
  )
}
