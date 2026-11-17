import { useTranslation } from 'react-i18next'
import { useOnlineCount } from './useOnlineCount'

/**
 * The live online counter at the top: people connected right now, as Reverb counts them on the "online" presence
 * channel. Shows nothing while unknown or when the server can't count (never a made-up number).
 */
export function OnlineCounter({ className = '' }: { className?: string }) {
  const { t, i18n } = useTranslation()
  const count = useOnlineCount()
  if (count === null) return null

  return (
    <p
      className={`flex items-center gap-1.5 rounded-full border border-[#3ddc84]/35 bg-black/30 px-2 py-1 text-xs font-semibold whitespace-nowrap text-fg sm:px-2.5 ${className}`}
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
