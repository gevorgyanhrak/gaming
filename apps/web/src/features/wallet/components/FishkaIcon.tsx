/** A Fishka: a gold play chip with an inlaid edge. Decorative. */
export function FishkaIcon({ className = 'size-5' }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" className={className}>
      <defs>
        <radialGradient id="fishka-face" cx="38%" cy="32%" r="75%">
          <stop offset="0" stopColor="#f7dc8f" />
          <stop offset="0.55" stopColor="#d6ab4e" />
          <stop offset="1" stopColor="#9a6a22" />
        </radialGradient>
      </defs>
      <circle cx="12" cy="12" r="11" fill="url(#fishka-face)" stroke="#6f4a16" strokeWidth="0.8" />
      {[0, 45, 90, 135, 180, 225, 270, 315].map((angle) => (
        <rect
          key={angle}
          x="10.6"
          y="1.2"
          width="2.8"
          height="3"
          rx="0.6"
          fill="#fff4d6"
          opacity="0.9"
          transform={`rotate(${angle} 12 12)`}
        />
      ))}
      <circle
        cx="12"
        cy="12"
        r="6.4"
        fill="none"
        stroke="#7a5218"
        strokeWidth="0.9"
        strokeDasharray="1.6 1.2"
      />
      <path
        d="M10.2 8.6h4.2M10.2 8.6v6.8M10.2 11.8h3.2"
        stroke="#5a3a0e"
        strokeWidth="1.6"
        strokeLinecap="round"
        fill="none"
      />
    </svg>
  )
}
