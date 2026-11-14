import { useTranslation } from 'react-i18next'
import { tierOf, type RankKey } from '../ranks'

/** Each rank's metal and gem: lighter top, darker bottom, the rim, and the jewel. */
const METAL: Record<RankKey, { top: string; bottom: string; rim: string; gem: string }> = {
  bronze: { top: '#d79a62', bottom: '#7a4520', rim: '#f2c79a', gem: '#b8743c' },
  silver: { top: '#f4f6f9', bottom: '#8790a0', rim: '#ffffff', gem: '#a9b7c9' },
  gold: { top: '#fbe7a6', bottom: '#b8862e', rim: '#fff1c7', gem: '#f0c462' },
  ruby: { top: '#e2606d', bottom: '#7d1422', rim: '#ffd0d4', gem: '#ff3b4f' },
  amethyst: { top: '#b48cf0', bottom: '#46207f', rim: '#e6d2ff', gem: '#c79bff' },
}
const ORDER: RankKey[] = ['bronze', 'silver', 'gold', 'ruby', 'amethyst']

/**
 * A rank's emblem: a shield in the rank's metal, richer the higher it is — laurels from Silver, a crown from Gold,
 * a ruby from Ruby, stars for Amethyst — with the rank's own jewel. The ranks are earned only by playing (levels).
 */
export function RankEmblem({
  level,
  size = 56,
  className = '',
}: {
  level: number
  size?: number
  className?: string
}) {
  const { t } = useTranslation()
  const key = tierOf(level).key
  const m = METAL[key]
  const tier = ORDER.indexOf(key)
  const id = `rank-${key}`

  return (
    <svg
      viewBox="0 0 100 110"
      width={size}
      height={size * 1.1}
      className={className}
      role="img"
      aria-label={t(`ranks.${key}`)}
    >
      <defs>
        <linearGradient id={`${id}-metal`} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor={m.top} />
          <stop offset="1" stopColor={m.bottom} />
        </linearGradient>
        <radialGradient id={`${id}-gem`} cx="0.35" cy="0.3" r="0.8">
          <stop offset="0" stopColor="#fff" stopOpacity="0.9" />
          <stop offset="0.35" stopColor={m.gem} />
          <stop offset="1" stopColor={m.bottom} />
        </radialGradient>
      </defs>
      {tier >= 1 ? (
        // Laurels from Silver up.
        <g fill={m.rim} opacity="0.85">
          {[0, 1, 2, 3, 4].map((i) => (
            <g key={i}>
              <ellipse
                cx={14 + i * 2}
                cy={78 - i * 12}
                rx="4"
                ry="8"
                transform={`rotate(${-30 + i * 6} ${14 + i * 2} ${78 - i * 12})`}
              />
              <ellipse
                cx={86 - i * 2}
                cy={78 - i * 12}
                rx="4"
                ry="8"
                transform={`rotate(${30 - i * 6} ${86 - i * 2} ${78 - i * 12})`}
              />
            </g>
          ))}
        </g>
      ) : null}
      {/* The shield. */}
      <path
        d="M50 14 82 26v30c0 22-15 38-32 46C33 94 18 78 18 56V26Z"
        fill={`url(#${id}-metal)`}
        stroke={m.rim}
        strokeWidth="3"
      />
      <path
        d="M50 22 75 31v25c0 17-11 30-25 37C36 86 25 73 25 56V31Z"
        fill="none"
        stroke="#000"
        strokeOpacity="0.2"
        strokeWidth="2"
      />
      {tier >= 2 ? (
        // A crown from Gold up.
        <path
          d="M33 16 38 2l7 9 5-10 5 10 7-9 5 14Z"
          fill={METAL.gold.top}
          stroke={METAL.gold.bottom}
          strokeWidth="1.5"
        />
      ) : null}
      {/* The jewel. */}
      <path
        d="M50 42 62 54 50 70 38 54Z"
        fill={`url(#${id}-gem)`}
        stroke={m.rim}
        strokeWidth="1.5"
      />
      {tier >= 4
        ? ([28, 72] as const).map((x) => (
            <path
              key={x}
              d={`M${x} 34l2 4 4.5.6-3.3 3 .8 4.4-4-2.1-4 2.1.8-4.4-3.3-3 4.5-.6Z`}
              fill="#fff6c9"
            />
          ))
        : null}
    </svg>
  )
}
