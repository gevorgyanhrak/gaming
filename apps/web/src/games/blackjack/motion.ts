/**
 * Table motion for blackjack, from what the server's view already shows (no rules here): a newly shown card glides
 * from the shoe to its place — cards appearing together are dealt one after another — and a tip's chip flies from
 * the player to the dealer. Nothing moves under reduced motion; cards on screen when the page opens don't move.
 */

const reduced = () =>
  typeof window === 'undefined' ||
  window.matchMedia?.('(prefers-reduced-motion: reduce)').matches !== false

let queued = 0
let resetTimer: number | undefined

/** The board's first paint: cards already on the table then stay put. */
let quietUntil = 0
export const settleTable = () => {
  quietUntil = performance.now() + 400
}

/** Deal a card that just appeared: from the shoe ([data-blackjack-shoe]) to where it lies. */
export function dealFromShoe(card: HTMLElement | null) {
  if (!card || reduced() || performance.now() < quietUntil || typeof card.animate !== 'function')
    return
  const shoe = document.querySelector<HTMLElement>('[data-blackjack-shoe]')
  if (!shoe) return
  const from = shoe.getBoundingClientRect()
  const to = card.getBoundingClientRect()
  const dx = from.left + from.width / 2 - (to.left + to.width / 2)
  const dy = from.top + from.height / 2 - (to.top + to.height / 2)
  // Cards shown at the same moment leave the shoe in turn.
  const delay = queued * 140
  queued += 1
  window.clearTimeout(resetTimer)
  resetTimer = window.setTimeout(() => {
    queued = 0
  }, 50)
  card.animate(
    [
      { transform: `translate(${dx}px, ${dy}px) rotate(-25deg) scale(0.7)`, opacity: 0.25 },
      { transform: 'translate(0, 0) rotate(0) scale(1)', opacity: 1 },
    ],
    { duration: 430, delay, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)', fill: 'backwards' },
  )
}

/** The dealer turns the hole card over. */
export function flipOver(card: HTMLElement | null) {
  if (!card || reduced() || performance.now() < quietUntil || typeof card.animate !== 'function')
    return
  card.animate(
    [
      { transform: 'perspective(400px) rotateY(90deg)', filter: 'brightness(0.6)' },
      { transform: 'perspective(400px) rotateY(0)', filter: 'none' },
    ],
    { duration: 420, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' },
  )
}

/** A chip flies from the player's seat to the dealer (a tip). */
export function flyTip(from: Element | null, amount: string) {
  const to = document.querySelector('[data-blackjack-dealer]')
  if (!from || !to || reduced()) return
  const a = from.getBoundingClientRect()
  const b = to.getBoundingClientRect()
  const chip = document.createElement('span')
  chip.textContent = amount
  chip.setAttribute('aria-hidden', 'true')
  chip.style.cssText = `position:fixed;left:${a.left + a.width / 2 - 18}px;top:${a.top + a.height / 2 - 18}px;width:36px;height:36px;border-radius:9999px;display:flex;align-items:center;justify-content:center;font:800 10px system-ui;color:#2a1b0c;background:radial-gradient(circle,#fbe7a6 0 45%,#c99a32 46%);border:3px dashed #fff8e7;box-shadow:0 6px 16px rgb(0 0 0/.5);z-index:60;pointer-events:none`
  document.body.append(chip)
  const dx = b.left + b.width / 2 - (a.left + a.width / 2)
  const dy = b.top + b.height / 2 - (a.top + a.height / 2)
  void chip
    .animate(
      [
        { transform: 'translate(0,0) scale(1)', opacity: 1 },
        {
          transform: `translate(${dx / 2}px, ${dy / 2 - 60}px) scale(1.15)`,
          opacity: 1,
          offset: 0.5,
        },
        { transform: `translate(${dx}px, ${dy}px) scale(0.5)`, opacity: 0 },
      ],
      { duration: 900, easing: 'cubic-bezier(0.3, 0.7, 0.3, 1)' },
    )
    .finished.finally(() => chip.remove())
}
