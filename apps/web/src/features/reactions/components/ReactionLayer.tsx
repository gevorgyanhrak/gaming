import { useLayoutEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import type { Reaction } from '../api'
import { messageText } from '../labels'

type Point = { x: number; y: number; top: number; left: number; right: number; bottom: number }

/** Message bubbles showing per destination seat: at most two (a third replaces the oldest). */
const shown = new Map<string, { id: string; index: number; retire: () => void }[]>()

/**
 * Where a seat is on screen: the first visible element marked `data-seat-anchor` for it (the player
 * list, the waiting table, poker and durak seats). Null when it isn't drawn or is scrolled out of view:
 * then nothing is drawn for it (the table chat still lists the reaction).
 */
function seatPoint(seat: number | null): Point | null {
  if (seat === null) return null
  for (const el of document.querySelectorAll<HTMLElement>(`[data-seat-anchor="${seat}"]`)) {
    const r = el.getBoundingClientRect()
    if (r.width === 0 || r.height === 0) continue
    if (r.bottom < 0 || r.top > innerHeight || r.right < 0 || r.left > innerWidth) return null
    return {
      x: r.left + r.width / 2,
      y: r.top + r.height / 2,
      top: r.top,
      left: r.left,
      right: r.right,
      bottom: r.bottom,
    }
  }
  return null
}

/**
 * Where a message bubble goes for a seat (its top-left corner): beside the seat on the side away from
 * the middle of the screen, else the other side, below, or above — the first place that is on screen
 * and touches no other player's seat (their name, clock, cards). The second bubble at a seat sits
 * next to the first.
 */
function bubbleSpot(dest: Point, seat: number, node: HTMLElement, index: number) {
  const w = node.offsetWidth
  const h = node.offsetHeight
  const step = index * (h + 6)
  const centred = Math.min(Math.max(dest.x - w / 2, 8), innerWidth - w - 8)
  const sides =
    dest.x <= innerWidth / 2 + 40 ? (['right', 'left'] as const) : (['left', 'right'] as const)
  const spots = {
    right: { left: dest.right + 8, top: dest.y - h / 2 - step },
    left: { left: dest.left - 8 - w, top: dest.y - h / 2 - step },
    below: { left: centred, top: dest.bottom + 8 + step },
    above: { left: centred, top: dest.top - 8 - h - step },
  }
  const others = [...document.querySelectorAll<HTMLElement>('[data-seat-anchor]')]
    .filter((el) => el.dataset.seatAnchor !== String(seat))
    .map((el) => el.getBoundingClientRect())
    .filter((r) => r.width > 0)
  const fits = ({ left, top }: { left: number; top: number }) =>
    left >= 8 &&
    top >= 8 &&
    left + w <= innerWidth - 8 &&
    top + h <= innerHeight - 8 &&
    !others.some((r) => left < r.right && left + w > r.left && top < r.bottom && top + h > r.top)
  for (const place of [...sides, 'below', 'above'] as const) {
    if (fits(spots[place])) return spots[place]
  }
  return { left: centred, top: Math.max(8, spots.above.top) }
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
  sender,
  onLanded,
}: {
  reaction: Reaction
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
    const from = seatPoint(reaction.from_seat)
    const target = reaction.to_seat
    const to = target === null ? null : seatPoint(target)
    const dest = target === null ? from : to
    // Where it should end isn't on screen: the chat has it.
    if (!dest) {
      onLanded(reaction.id)
      return
    }
    const still =
      typeof matchMedia === 'function' && matchMedia('(prefers-reduced-motion: reduce)').matches

    let animation: Animation
    let release = () => undefined as unknown
    if (message) {
      // Beside the seat, on the side away from the middle of the table (off the board, the clocks and
      // the cards); above it when neither side has room. Two at most per seat.
      const slot = `${reaction.room}:${target ?? `from-${reaction.from_seat}`}`
      // Keyed by id: an effect that runs twice (React's strict mode) doesn't count itself twice.
      const list = (shown.get(slot) ?? []).filter((b) => b.id !== reaction.id)
      if (list.length >= 2) list.shift()?.retire()
      const used = new Set(list.map((b) => b.index))
      const index = used.has(0) ? 1 : 0

      const { left, top } = bubbleSpot(dest, target ?? reaction.from_seat, node, index)
      const at = (px: number, py: number, s = 1) => `translate(${px}px, ${py}px) scale(${s})`
      const start = from ?? dest
      const w = node.offsetWidth
      const h = node.offsetHeight

      animation = node.animate(
        still || !to || !from
          ? [
              { transform: at(left, top, 0.85), opacity: 0 },
              { transform: at(left, top), opacity: 1, offset: 0.08 },
              { transform: at(left, top), opacity: 1, offset: 0.9 },
              { transform: at(left, top - 10), opacity: 0 },
            ]
          : [
              { transform: at(start.x - w / 2, start.y - h / 2, 0.6), opacity: 0 },
              { transform: at(start.x - w / 2, start.y - h / 2, 1), opacity: 1, offset: 0.06 },
              { transform: at(left, top, 1.04), opacity: 1, offset: 0.2, easing: 'ease-out' },
              { transform: at(left, top), opacity: 1, offset: 0.26 },
              { transform: at(left, top), opacity: 1, offset: 0.9 },
              { transform: at(left, top - 10), opacity: 0 },
            ],
        { duration: 3400, easing: 'ease-in-out', fill: 'forwards' },
      )
      const bubble = animation
      const entry = {
        id: reaction.id,
        index,
        // Replaced by a newer one: fade out from now.
        retire: () => {
          bubble.currentTime = Math.max(Number(bubble.currentTime ?? 0), 3400 * 0.9)
        },
      }
      shown.set(slot, [...list, entry])
      release = () =>
        shown.set(
          slot,
          (shown.get(slot) ?? []).filter((b) => b.id !== reaction.id),
        )
    } else {
      const at = (px: number, py: number, s: number) =>
        `translate(${px}px, ${py}px) translate(-50%, -50%) scale(${s})`
      if (still || !from) {
        // Reduced motion, or the sender is out of view: it just appears beside the receiver.
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
    return () => {
      animation.cancel()
      release()
    }
  }, [reaction, message, onLanded])

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
