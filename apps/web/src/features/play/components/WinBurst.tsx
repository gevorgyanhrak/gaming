/**
 * A win, celebrated behind the trophy: gold coins and confetti burst out and fall (CSS only, once). Nothing under
 * reduced motion.
 */
const PIECES = Array.from({ length: 22 }, (_, i) => {
  const angle = (i / 22) * Math.PI * 2 + (i % 3) * 0.2
  const reach = 70 + (i % 5) * 18
  return {
    x: Math.round(Math.cos(angle) * reach),
    y: Math.round(Math.sin(angle) * reach * 0.7 - 30),
    coin: i % 3 === 0,
    color: ['#f7d774', '#e8606c', '#5fd4c4', '#b98cf3', '#fff8e7'][i % 5],
    delay: (i % 6) * 40,
    spin: (i % 2 ? 1 : -1) * (180 + i * 25),
  }
})

export function WinBurst() {
  return (
    <span
      aria-hidden="true"
      className="pointer-events-none absolute inset-0 overflow-visible motion-reduce:hidden"
    >
      <span className="absolute top-1/2 left-1/2 size-40 -translate-1/2 rounded-full bg-[radial-gradient(circle,rgb(247_215_116/0.55),transparent_65%)] motion-safe:animate-[win-glow_1600ms_ease-out_both]" />
      {PIECES.map((p, i) => (
        <span
          key={i}
          className={`absolute top-1/2 left-1/2 motion-safe:animate-[win-piece_1500ms_cubic-bezier(0.2,0.8,0.3,1)_both] ${
            p.coin
              ? 'size-3.5 rounded-full border border-[#9a6a1c] bg-radial from-[#fbe7a6] to-[#c99a32] shadow'
              : 'h-1.5 w-2.5 rounded-[1px]'
          }`}
          style={
            {
              background: p.coin ? undefined : p.color,
              animationDelay: `${p.delay}ms`,
              '--wx': `${p.x}px`,
              '--wy': `${p.y}px`,
              '--wr': `${p.spin}deg`,
            } as React.CSSProperties
          }
        />
      ))}
    </span>
  )
}
