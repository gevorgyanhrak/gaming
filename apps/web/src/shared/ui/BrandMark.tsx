import { useId } from 'react'

/**
 * The HayBet mark (HayBet item 7, 2026-11-20): a gold casino chip with eight notches; inside, an "H" whose crossbar
 * is a diamond — the H of HayBet and a card suit at once. Drawn in code so it stays sharp at any size; the same
 * drawing is the favicon (public/favicon.svg) and the loading screen (index.html).
 */
export function BrandMark({ className = 'size-8' }: { className?: string }) {
  const id = useId().replace(/:/g, '')
  return (
    <svg viewBox="0 0 64 64" aria-hidden="true" className={`shrink-0 ${className}`}>
      <defs>
        <linearGradient id={`${id}-gold`} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stopColor="#fbe7a1" />
          <stop offset="0.45" stopColor="#f2c14e" />
          <stop offset="1" stopColor="#b8862b" />
        </linearGradient>
        <radialGradient id={`${id}-night`} cx="0.35" cy="0.3" r="0.8">
          <stop offset="0" stopColor="#24305a" />
          <stop offset="1" stopColor="#0b0f1d" />
        </radialGradient>
      </defs>
      <circle cx="32" cy="32" r="30" fill={`url(#${id}-gold)`} />
      {[0, 45, 90, 135, 180, 225, 270, 315].map((angle) => (
        <rect
          key={angle}
          x="29"
          y="2"
          width="6"
          height="7"
          rx="1.5"
          fill="#0b0f1d"
          transform={`rotate(${angle} 32 32)`}
        />
      ))}
      <circle
        cx="32"
        cy="32"
        r="21.5"
        fill={`url(#${id}-night)`}
        stroke="#f2c14e"
        strokeOpacity="0.7"
        strokeWidth="1.2"
      />
      <g fill={`url(#${id}-gold)`}>
        <rect x="20.5" y="20" width="5.5" height="24" rx="1.6" />
        <rect x="38" y="20" width="5.5" height="24" rx="1.6" />
        <path d="M32 25.5 L38.5 32 L32 38.5 L25.5 32 Z" />
      </g>
    </svg>
  )
}

/**
 * The logo: an uploaded one if the administrator set it, else the mark and the name — "HayBet" in two tones.
 */
export function BrandLogo({
  name,
  logoUrl,
  markClassName = 'size-8',
  wordClassName = '',
}: {
  name: string | undefined
  logoUrl?: string | null
  markClassName?: string
  /** Classes for the name (e.g. sr-only on small phones). */
  wordClassName?: string
}) {
  const split = name?.match(/^(Hay)(Bet)$/i)
  return (
    <>
      {logoUrl ? (
        <img src={logoUrl} alt="" className={`shrink-0 object-contain ${markClassName}`} />
      ) : (
        <BrandMark className={markClassName} />
      )}
      {name ? (
        <span className={`truncate font-display font-bold tracking-tight ${wordClassName}`}>
          {split ? (
            <>
              <span className="text-fg">{split[1]}</span>
              <span className="text-gold-gradient">{split[2]}</span>
            </>
          ) : (
            name
          )}
        </span>
      ) : null}
    </>
  )
}
