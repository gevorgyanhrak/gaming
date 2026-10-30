import { useLayoutEffect, useState, type RefObject } from 'react'

/** The space a table can take: from its top to the bottom of the screen, above the phone tab bar. */
export function fitHeight({
  viewport,
  top,
  tabbar,
  min,
}: {
  viewport: number
  top: number
  tabbar: number
  min: number
}) {
  return Math.max(min, Math.floor(viewport - top - tabbar - 12))
}

/**
 * Sizes a game table to the screen so the whole of it (the player's own hand included) is visible
 * without scrolling when the page is at the top. Measured on mount, whenever the viewport changes
 * (rotation, browser bars, the on-screen keyboard) and whenever the page above it changes height
 * (a header line wrapping, an alert). Returns the height, null until measured.
 */
export function useFitHeight(ref: RefObject<HTMLElement | null>, { min }: { min: number }) {
  const [height, setHeight] = useState<number | null>(null)

  useLayoutEffect(() => {
    const measure = () => {
      const el = ref.current
      if (!el) return
      const bar = document.querySelector<HTMLElement>('[data-tabbar]')
      const tabbar = bar && getComputedStyle(bar).display !== 'none' ? bar.offsetHeight : 0
      setHeight(
        fitHeight({
          viewport: window.visualViewport?.height ?? window.innerHeight,
          top: el.getBoundingClientRect().top + window.scrollY,
          tabbar,
          min,
        }),
      )
    }
    measure()
    let frame = 0
    const later = () => {
      cancelAnimationFrame(frame)
      frame = requestAnimationFrame(measure)
    }
    window.addEventListener('resize', measure)
    window.visualViewport?.addEventListener('resize', measure)
    // Content above the table moving it down or up changes the body's size; the measured height
    // settles after one pass (the same top gives the same height).
    const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(later)
    observer?.observe(document.body)
    return () => {
      cancelAnimationFrame(frame)
      observer?.disconnect()
      window.removeEventListener('resize', measure)
      window.visualViewport?.removeEventListener('resize', measure)
    }
  }, [ref, min])

  return height
}
