import { portraitUrl } from '@/shared/lib/aiPortraits'

/**
 * An AI player's face: its illustrated portrait (see `shared/lib/aiPortraits`). `name` is its face key — its name,
 * or the table's variant of it (`tableFaces`).
 */
export function AiFace({ name, className = '' }: { name: string; className?: string }) {
  return (
    <img
      src={portraitUrl(name)}
      alt=""
      aria-hidden="true"
      draggable={false}
      decoding="async"
      className={`object-cover select-none ${className}`}
    />
  )
}
