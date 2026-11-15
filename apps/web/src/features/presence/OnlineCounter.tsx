import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { apiRequest } from '@/shared/api/http'

type Online = { online: number | null }

/** How often the counter asks; the server asks Reverb at most every 10 s. */
const EVERY_MS = 15_000

/**
 * The live online counter at the top: people connected right now, as Reverb counts them on the "online" presence
 * channel. Shows nothing while unknown or when the server can't count (never a made-up number).
 */
export function OnlineCounter({ className = '' }: { className?: string }) {
  const { t, i18n } = useTranslation()
  const online = useQuery({
    queryKey: ['online'],
    queryFn: ({ signal }) =>
      apiRequest<{ data: Online }>('/api/v1/stats/online', { signal }).then((r) => r.data),
    refetchInterval: EVERY_MS,
  })
  const count = online.data?.online
  if (count === null || count === undefined) return null

  return (
    <p
      className={`flex items-center gap-1.5 rounded-full border border-[#3ddc84]/35 bg-black/30 px-2.5 py-1 text-xs font-semibold whitespace-nowrap text-fg ${className}`}
      title={t('online.title')}
    >
      <span aria-hidden="true" className="relative flex size-2">
        <span className="absolute inline-flex size-full rounded-full bg-[#3ddc84] opacity-75 motion-safe:animate-ping" />
        <span className="relative inline-flex size-2 rounded-full bg-[#3ddc84] shadow-[0_0_6px_#3ddc84]" />
      </span>
      {/* The number changes with a short pop. */}
      <span key={count} className="tabular-nums motion-safe:animate-turn-pop">
        {count.toLocaleString(i18n.language)}
      </span>
      <span className="sr-only sm:not-sr-only">{t('online.label', { count })}</span>
    </p>
  )
}
