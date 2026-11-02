import { useLayoutEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import type { Reaction } from '../api'
import { messageText } from '../labels'

type Point = { x: number; y: number; top: number }

/** Bubbles currently shown per destination seat, so a second one stacks above the first. */
const stacked = new Map<string, number>()

/**
 * Where a seat is on screen: the first visible element marked `data-seat-anchor` for it (the player
 * list, the waiting table, poker and durak seats). Off-screen seats are pulled to the screen's edge;
 * a seat that isn't drawn at all falls back to the bottom (you) or the top (the others).
 */
function seatPoint(seat: number | null, mine: boolean): Point {
  const margin = 28
  if (seat !== null) {
    for (const el of document.querySelectorAll<HTMLElement>(`[data-seat-anchor="${seat}"]`)) {
      const r = el.getBoundingClientRect()
      if (r.width > 0 && r.height > 0) {
        const clamp = (v: number, max: number) => Math.min(Math.max(v, margin), max - margin)
        return {
          x: clamp(r.left + r.width / 2, innerWidth),
          y: clamp(r.top + r.height / 2, innerHeight),
          top: clamp(r.top, innerHeight),
        }
      }
    }
  }
  const y = mine ? innerHeight * 0.78 : innerHeight * 0.22
  return { x: innerWidth / 2, y, top: y - 20 }
}

/**
 * Plays the reactions: an emoji flies from the sender's seat to the receiver's along an arc, lands
 * beside their avatar with a bounce and fades; to everyone it pops up over the sender. A message shows
 * as a bubble with the sender's name that travels to the receiver and stays a moment. Transforms and
 * opacity only (no layout work), nothing to click, and still pictures with reduced motion.
 */
export function ReactionLayer({
  flights,
  mySeat,
  names,
  muted,
  onLanded,
}: {
  flights: Reaction[]
  mySeat: number | null
  names: Partial<Record<number, string>>
  /** Other players' reactions aren't animated (yours still are). */
  muted: boolean
  onLanded: (id: string) => void
}) {
  return createPortal(
    <div aria-hidden="true" className="pointer-events-none fixed inset-0 z-40 overflow-hidden">
      {flights.map((reaction) =>
        muted && reaction.from_seat !== mySeat ? (
          <Skip key={reaction.id} id={reaction.id} onLanded={onLanded} />
        ) : (
          <Flight
            key={reaction.id}
            reaction={reaction}
            mySeat={mySeat}
            sender={names[reaction.from_seat] ?? ''}
            onLanded={onLanded}
          />
        ),
      )}
    </div>,
    document.body,
  )
}

function Skip({ id, onLanded }: { id: string; onLanded: (id: string) => void }) {
  useLayoutEffect(() => onLanded(id), [id, onLanded])
  return null
}

function Flight({
  reaction,
  mySeat,
  sender,
  onLanded,
}: {
  reaction: Reaction
  mySeat: number | null
  sender: string
  onLanded: (id: string) => void
}) {
  const { t } = useTranslation()
  const el = useRef<HTMLDivElement>(null)
  const message = reaction.kind === 'message'

  useLayoutEffect(() => {
    const node = el.current
    if (!node) return
    // No Web Animations (very old browsers): nothing flies; the table chat still lists it.
    if (typeof node.animate !== 'function') {
      onLanded(reaction.id)
      return
    }
    const from = seatPoint(reaction.from_seat, reaction.from_seat === mySeat)
    const target = reaction.to_seat
    const to = target === null ? null : seatPoint(target, target === mySeat)
    const still =
      typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches

    let animation: Animation
    if (message) {
      // Above the receiver's seat (or the sender's, for everyone), stacked if several are up there.
      const dest = to ?? from
      const slot = `${reaction.room}:${target ?? `from-${reaction.from_seat}`}`
      const index = stacked.get(slot) ?? 0
      stacked.set(slot, index + 1)
      const half = node.offsetWidth / 2
      const x = Math.min(Math.max(dest.x, half + 8), innerWidth - half - 8)
      const y = Math.max(dest.top - 8 - index * (node.offsetHeight + 6), node.offsetHeight + 8)
      const at = (px: number, py: number, s = 1) =>
        `translate(${px}px, ${py}px) translate(-50%, -100%) scale(${s})`
      const fromX = Math.min(Math.max(from.x, half + 8), innerWidth - half - 8)
      animation = node.animate(
        still || !to
          ? [
              { transform: at(x, y, 0.85), opacity: 0 },
              { transform: at(x, y), opacity: 1, offset: 0.08 },
              { transform: at(x, y), opacity: 1, offset: 0.9 },
              { transform: at(x, y - 10), opacity: 0 },
            ]
          : [
              { transform: at(fromX, from.top - 8, 0.6), opacity: 0 },
              { transform: at(fromX, from.top - 8, 1), opacity: 1, offset: 0.06 },
              { transform: at(x, y, 1.04), opacity: 1, offset: 0.2, easing: 'ease-out' },
              { transform: at(x, y), opacity: 1, offset: 0.26 },
              { transform: at(x, y), opacity: 1, offset: 0.9 },
              { transform: at(x, y - 10), opacity: 0 },
            ],
        { duration: 3400, easing: 'ease-in-out', fill: 'forwards' },
      )
      animation.finished
        .catch(() => undefined)
        .finally(() => stacked.set(slot, Math.max(0, (stacked.get(slot) ?? 1) - 1)))
    } else {
      const at = (px: number, py: number, s: number) =>
        `translate(${px}px, ${py}px) translate(-50%, -50%) scale(${s})`
      if (still) {
        const dest = to ?? from
        animation = node.animate(
          [
            { transform: at(dest.x, dest.top, 1), opacity: 0 },
            { transform: at(dest.x, dest.top, 1), opacity: 1, offset: 0.15 },
            { transform: at(dest.x, dest.top, 1), opacity: 1, offset: 0.8 },
            { transform: at(dest.x, dest.top, 1), opacity: 0 },
          ],
          { duration: 1800, fill: 'forwards' },
        )
      } else if (!to) {
        // To everyone: pops up over the sender and rises.
        animation = node.animate(
          [
            { transform: at(from.x, from.top, 0.4), opacity: 0 },
            { transform: at(from.x, from.top - 10, 1.5), opacity: 1, offset: 0.15 },
            { transform: at(from.x, from.top - 16, 1.15), opacity: 1, offset: 0.3 },
            { transform: at(from.x, from.top - 70, 1.1), opacity: 1, offset: 0.8 },
            { transform: at(from.x, from.top - 100, 0.9), opacity: 0 },
          ],
          { duration: 1900, easing: 'ease-out', fill: 'forwards' },
        )
      } else {
        // An arc from the sender to the receiver, a bounce on arrival beside their avatar, then away.
        const land = { x: to.x, y: to.top }
        const distance = Math.hypot(land.x - from.x, land.y - from.y)
        const mid = {
          x: (from.x + land.x) / 2,
          y: (from.y + land.y) / 2 - Math.min(140, 40 + distance * 0.3),
        }
        animation = node.animate(
          [
            { transform: at(from.x, from.y, 0.4), opacity: 0 },
            { transform: at(from.x, from.y - 6, 1.2), opacity: 1, offset: 0.08, easing: 'ease-in' },
            { transform: at(mid.x, mid.y, 1.6), opacity: 1, offset: 0.27, easing: 'ease-out' },
            { transform: at(land.x, land.y, 1.3), opacity: 1, offset: 0.44 },
            { transform: at(land.x, land.y + 4, 0.95), opacity: 1, offset: 0.5 },
            { transform: at(land.x, land.y, 1.12), opacity: 1, offset: 0.56 },
            { transform: at(land.x, land.y, 1.05), opacity: 1, offset: 0.84 },
            { transform: at(land.x, land.y - 26, 0.9), opacity: 0 },
          ],
          { duration: 2300, fill: 'forwards' },
        )
      }
    }
    animation.finished.then(() => onLanded(reaction.id)).catch(() => undefined)
    return () => animation.cancel()
  }, [reaction, mySeat, message, onLanded])

  return (
    <div
      ref={el}
      className={
        message
          ? 'absolute top-0 left-0 max-w-56 rounded-2xl rounded-bl-sm border border-gold/40 bg-black/85 px-3 py-1.5 text-sm font-semibold text-white opacity-0 shadow-lg shadow-black/50 will-change-transform'
          : 'absolute top-0 left-0 text-4xl leading-none opacity-0 drop-shadow-[0_4px_10px_rgb(0_0_0/0.55)] will-change-transform sm:text-5xl'
      }
    >
      {message ? (
        <>
          <span className="block truncate text-xs font-bold text-gold-light">{sender}</span>
          {messageText(t, reaction.value)}
        </>
      ) : (
        reaction.value
      )}
    </div>
  )
}
