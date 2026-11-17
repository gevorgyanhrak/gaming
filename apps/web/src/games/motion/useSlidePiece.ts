import { useLayoutEffect, useRef, type RefObject } from 'react'

const reducedMotion = () =>
  typeof window.matchMedia === 'function' &&
  window.matchMedia('(prefers-reduced-motion: reduce)').matches

/**
 * Slides the piece that just moved from its old square to its new one, so a move reads as a move rather than a
 * jump (chess, checkers). Only the view's own `last` move is drawn — the rules stay on the server. Squares carry
 * `data-cell`, pieces `data-piece`; nothing moves on the first view or under reduced motion. Short on purpose: it
 * starts after the move is already on the board, so it never holds up the next one.
 */
export function useSlidePiece(
  rootRef: RefObject<HTMLElement | null>,
  last: { from: number; to: number } | null,
  /** Changes with every new position (the board itself), so the same move twice still slides. */
  position: unknown,
) {
  const seenRef = useRef<unknown>(undefined)

  useLayoutEffect(() => {
    const first = seenRef.current === undefined
    seenRef.current = position
    const root = rootRef.current
    if (first || !root || !last || reducedMotion()) return
    const from = root.querySelector<HTMLElement>(`[data-cell="${last.from}"]`)
    const piece = root.querySelector<HTMLElement>(`[data-cell="${last.to}"] [data-piece]`)
    if (!from || !piece || typeof piece.animate !== 'function') return
    const a = from.getBoundingClientRect()
    const b = piece.parentElement!.getBoundingClientRect()
    const dx = a.left + a.width / 2 - (b.left + b.width / 2)
    const dy = a.top + a.height / 2 - (b.top + b.height / 2)
    const distance = Math.hypot(dx, dy)
    piece.style.zIndex = '20'
    const slide = piece.animate(
      [
        { transform: `translate(${dx}px, ${dy}px) scale(1.08)` },
        { transform: 'translate(0, 0) scale(1.08)', offset: 0.85 },
        { transform: 'none' },
      ],
      { duration: Math.min(420, 200 + distance * 0.45), easing: 'cubic-bezier(.2,.8,.2,1)' },
    )
    const done = () => {
      piece.style.zIndex = ''
    }
    slide.onfinish = done
    return () => {
      slide.cancel?.()
      done()
    }
  }, [position, rootRef, last])
}
