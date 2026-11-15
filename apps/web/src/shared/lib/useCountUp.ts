import { useEffect, useRef, useState } from 'react'

/**
 * A number that rolls to its new value (about 0.7 s, easing out) instead of jumping — for balances. The first value
 * shows at once; under reduced motion every change does.
 */
export function useCountUp(target: number, ms = 700): number {
  const [shown, setShown] = useState(target)
  const fromRef = useRef(target)
  useEffect(() => {
    const from = fromRef.current
    fromRef.current = target
    const still =
      typeof window === 'undefined' ||
      window.matchMedia?.('(prefers-reduced-motion: reduce)').matches !== false
    if (from === target || still) {
      setShown(target)
      return
    }
    const start = performance.now()
    let frame = 0
    const step = (now: number) => {
      const t = Math.min(1, (now - start) / ms)
      const eased = 1 - (1 - t) ** 3
      setShown(Math.round(from + (target - from) * eased))
      if (t < 1) frame = requestAnimationFrame(step)
    }
    frame = requestAnimationFrame(step)
    return () => cancelAnimationFrame(frame)
  }, [target, ms])
  return shown
}
