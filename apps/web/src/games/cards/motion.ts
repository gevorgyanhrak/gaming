import { playUiSound } from '@/shared/lib/sounds'

/**
 * Card table motion shared by the card games, from what the server's view already shows (no rules here): a card
 * glides from the deck to its place — several dealt together leave one after another, with a soft snap each — and
 * a card turned face up flips over. Nothing moves under reduced motion; everything is skipped while the table is
 * settling after the page opens (cards already there don't fly in).
 */

const reduced = () =>
  typeof window === 'undefined' ||
  window.matchMedia?.('(prefers-reduced-motion: reduce)').matches !== false

let quietUntil = 0
/** The board's first paint: cards already on the table stay put. */
export const settleCards = () => {
  quietUntil = performance.now() + 400
}

const able = (el: HTMLElement | null): el is HTMLElement =>
  !!el && !reduced() && performance.now() >= quietUntil && typeof el.animate === 'function'

/** Time between two cards dealt in one go. */
export const DEAL_GAP_MS = 170
const DEAL_MS = 420

/**
 * Deals `card` from `deck` to where it lies, after `order` cards before it. `faceUp`: it turns over as it lands
 * (a card dealt to you, or to the board). Returns when it has landed (ms from now).
 */
export function dealCard(
  card: HTMLElement | null,
  deck: Element | null,
  order: number,
  faceUp = false,
): number {
  if (!able(card) || !deck) return 0
  // Already on its way (an effect run twice, as in development): measuring now would find it at the deck.
  if (card.getAnimations().some((a) => a.id === 'deal')) return 0
  const from = deck.getBoundingClientRect()
  const to = card.getBoundingClientRect()
  const dx = from.left + from.width / 2 - (to.left + to.width / 2)
  const dy = from.top + from.height / 2 - (to.top + to.height / 2)
  const delay = order * DEAL_GAP_MS
  const flight: Keyframe[] = faceUp
    ? [
        {
          transform: `translate(${dx}px, ${dy}px) rotate(-18deg) scale(0.6) rotateY(180deg)`,
          opacity: 0.3,
          filter: 'brightness(0.4)',
        },
        {
          transform: 'translate(0, 0) rotate(0) scale(1.04) rotateY(90deg)',
          opacity: 1,
          filter: 'brightness(0.6)',
          offset: 0.7,
        },
        { transform: 'translate(0, 0) rotate(0) scale(1) rotateY(0)', opacity: 1, filter: 'none' },
      ]
    : [
        { transform: `translate(${dx}px, ${dy}px) rotate(-18deg) scale(0.6)`, opacity: 0.3 },
        { transform: 'translate(0, 0) rotate(0) scale(1)', opacity: 1 },
      ]
  card.animate(flight, {
    id: 'deal',
    duration: faceUp ? DEAL_MS + 160 : DEAL_MS,
    delay,
    easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)',
    fill: 'backwards',
  })
  window.setTimeout(() => playUiSound('deal'), delay)
  // The dealer's hand: the deck gives a little as each card leaves it.
  if (deck instanceof HTMLElement && typeof deck.animate === 'function') {
    deck.animate(
      [
        { transform: 'none' },
        { transform: 'translateY(-2px) rotate(-2deg)' },
        { transform: 'none' },
      ],
      { duration: 160, delay, easing: 'ease-out' },
    )
  }
  return delay + DEAL_MS
}

/** A card already in place turns face up (shown at the showdown). */
export function flipCard(card: HTMLElement | null, order = 0) {
  if (!able(card) || card.getAnimations().some((a) => a.id === 'flip')) return
  card.animate(
    [
      { transform: 'perspective(500px) rotateY(180deg)', filter: 'brightness(0.4)' },
      { transform: 'perspective(500px) rotateY(90deg)', filter: 'brightness(0.6)', offset: 0.5 },
      { transform: 'perspective(500px) rotateY(0)', filter: 'none' },
    ],
    {
      id: 'flip',
      duration: 460,
      delay: order * 120,
      easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)',
      fill: 'backwards',
    },
  )
}
