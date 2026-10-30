import { useLayoutEffect, useRef, useState, type Ref } from 'react'
import { PlayingCard } from '../cards/PlayingCard'
import { handLayout } from './handLayout'

/** Room above the cards for a playable card to lift into, with a gap to the line above (px). */
const LIFT = 18

/**
 * Your hand in straight rows that always fit (see handLayout). Every visible strip of a card is
 * that card's own button: later cards sit above earlier ones and a lift never changes that, so a
 * tap never reaches a card underneath. One tap plays a playable card.
 */
export function DurakHand({
  cards,
  playable,
  onPlay,
  myTurn,
  pending,
  finished,
  card,
  strip,
  label,
  cardLabel,
  listRef,
}: {
  cards: string[]
  playable: (card: string) => boolean
  /** Called with the card and where it was on screen (for the move to the table). */
  onPlay: (card: string, from: DOMRect) => void
  myTurn: boolean
  pending: boolean
  finished: boolean
  /** Card width (px). */
  card: number
  /** Least visible width of each card (px). */
  strip: number
  label: string
  cardLabel: (card: string) => string
  listRef?: Ref<HTMLUListElement>
}) {
  const box = useRef<HTMLDivElement>(null)
  const [width, setWidth] = useState(0)

  useLayoutEffect(() => {
    const el = box.current
    if (!el) return
    const update = () => setWidth(el.clientWidth)
    update()
    if (typeof ResizeObserver === 'undefined') return
    const observer = new ResizeObserver(update)
    observer.observe(el)
    return () => observer.disconnect()
  }, [])

  // Unmeasured (tests, first paint): lay out for a wide row.
  const space = width || 1000
  const height = card * 1.43
  const { rows, steps } = handLayout(cards.length, space, card, strip)
  const rowStep = height * 0.45

  let index = 0
  const placed = rows.flatMap((k, r) => {
    const step = steps[r] ?? 0
    const left = (space - (card + (k - 1) * step)) / 2
    return Array.from({ length: k }, (_, j) => ({
      card: cards[index++]!,
      left: left + j * step,
      top: LIFT + r * rowStep,
    }))
  })

  return (
    <div ref={box} className="w-full min-w-0">
      <ul
        ref={listRef}
        aria-label={label}
        tabIndex={-1}
        className="relative outline-none"
        style={{
          height: rows.length ? LIFT + height + (rows.length - 1) * rowStep : LIFT + height,
          ['--card-w' as string]: `${card}px`,
        }}
      >
        {placed.map((spot, i) => {
          const canPlay = !finished && playable(spot.card)
          return (
            <li
              key={spot.card}
              className="absolute"
              style={{ left: spot.left, top: spot.top, zIndex: i + 1 }}
            >
              <button
                type="button"
                disabled={pending || !canPlay}
                onClick={(e) => onPlay(spot.card, e.currentTarget.getBoundingClientRect())}
                aria-label={cardLabel(spot.card)}
                className={`block rounded-[calc(var(--card-w)*0.1)] transition-transform duration-150 disabled:cursor-default ${
                  canPlay
                    ? '-translate-y-2.5 lg:hover:-translate-y-4 lg:focus-visible:-translate-y-4 [&>span]:shadow-[0_0_0_2px_var(--color-turn),0_6px_14px_rgb(0_0_0/0.45)]'
                    : myTurn && !finished
                      ? 'brightness-75'
                      : ''
                }`}
              >
                <PlayingCard card={spot.card} label={cardLabel(spot.card)} size="fluid" />
              </button>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
