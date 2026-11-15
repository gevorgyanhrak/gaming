import { useTranslation } from 'react-i18next'

/**
 * A small speech bubble with three pulsing dots: an AI player is thinking over its move. Placed by its parent
 * (absolute) next to the avatar; still dots under reduced motion.
 */
export function ThinkingDots({ name, className = '' }: { name: string; className?: string }) {
  const { t } = useTranslation()

  return (
    <span
      role="img"
      aria-label={t('room.thinking', { name })}
      className={`pointer-events-none flex items-center gap-1 rounded-full border border-[#e8c66a] bg-[#fdf3d0] px-2 py-1.5 shadow-[0_4px_10px_rgb(0_0_0/0.4)] motion-safe:animate-card-in ${className}`}
    >
      {[0, 1, 2].map((i) => (
        <span
          key={i}
          className="size-1.5 rounded-full bg-[#2a1b0c] motion-safe:animate-thinking"
          style={{ animationDelay: `${i * 160}ms` }}
        />
      ))}
    </span>
  )
}
