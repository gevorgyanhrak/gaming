import { useLayoutEffect, useRef, useState, type ReactNode } from 'react'

/** Phones held sideways: wide, but too short for a table laid out for desktop height. */
export const SHORT_LANDSCAPE = '(max-height: 520px) and (orientation: landscape)'

/**
 * Draws its child at a fixed design size and scales it down to fit the width it has and the screen height below
 * its top (never up), so a table made for a desktop keeps its proportions on a phone held sideways instead of
 * squeezing its seats, cards and buttons into each other. Taps still land where they look (browsers hit-test
 * through transforms). Off: renders the child as is.
 */
export function FitStage({
  width,
  height,
  enabled,
  children,
}: {
  width: number
  height: number
  enabled: boolean
  children: ReactNode
}) {
  const boxRef = useRef<HTMLDivElement>(null)
  const [scale, setScale] = useState(1)

  useLayoutEffect(() => {
    const box = boxRef.current
    if (!enabled || !box) return
    const fit = () => {
      const top = box.getBoundingClientRect().top + window.scrollY
      const room = Math.max(160, window.innerHeight - top - 8)
      setScale(Math.min(1, box.clientWidth / width, room / height))
    }
    fit()
    const observer = new ResizeObserver(fit)
    observer.observe(box)
    window.addEventListener('resize', fit)
    return () => {
      observer.disconnect()
      window.removeEventListener('resize', fit)
    }
  }, [enabled, width, height])

  if (!enabled) return children

  return (
    <div ref={boxRef} className="relative w-full" style={{ height: height * scale }}>
      <div
        className="absolute top-0 left-1/2"
        style={{
          width,
          height,
          transform: `translateX(-50%) scale(${scale})`,
          transformOrigin: 'top center',
        }}
      >
        {children}
      </div>
    </div>
  )
}
