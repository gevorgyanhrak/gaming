import { useTranslation } from 'react-i18next'

/** The mark every AI player carries next to its name (ADR 0022): a small "AI" tag. */
export function AiTag({ className = '' }: { className?: string }) {
  const { t } = useTranslation()
  return (
    <span
      title={t('play.aiTagTitle')}
      className={`inline-flex shrink-0 items-center rounded border border-sky-300/50 bg-sky-400/15 px-1 text-[0.6rem] leading-tight font-extrabold tracking-wide text-sky-200 ${className}`}
    >
      {t('play.aiTag')}
    </span>
  )
}
