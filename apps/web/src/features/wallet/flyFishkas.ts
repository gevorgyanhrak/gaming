/**
 * Fishka chips flying between a place on the page and the header balance: into it for a gain, out of
 * it for a loss. Purely visual — the balance itself only changes from the server. Skipped under reduced
 * motion or when either end isn't on screen. Calls `done` when the chips land (or at once if skipped).
 */
export function flyFishkas(from: Element | null, direction: 'in' | 'out', done?: () => void) {
  const target = document.querySelector('[data-fishka-balance]')
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  if (!from || !target || reduced || typeof document.body.animate !== 'function') {
    done?.()
    return
  }

  const center = (el: Element) => {
    const r = el.getBoundingClientRect()
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 }
  }
  const [a, b] =
    direction === 'in' ? [center(from), center(target)] : [center(target), center(from)]
  const chips = 6
  let landed = 0

  for (let i = 0; i < chips; i++) {
    const chip = document.createElement('span')
    chip.setAttribute('aria-hidden', 'true')
    chip.style.cssText = `position:fixed;left:${a.x - 10}px;top:${a.y - 10}px;width:20px;height:20px;border-radius:9999px;z-index:60;pointer-events:none;background:radial-gradient(circle at 38% 32%,#f7dc8f,#d6ab4e 55%,#9a6a22);box-shadow:0 2px 6px rgb(0 0 0/.45),inset 0 0 0 2px rgb(255 244 214/.7)`
    document.body.appendChild(chip)
    const lift = -60 - i * 8
    chip
      .animate(
        [
          { transform: 'translate(0,0) scale(.6)', opacity: 0 },
          {
            transform: `translate(${(b.x - a.x) / 2}px, ${(b.y - a.y) / 2 + lift}px) scale(1)`,
            opacity: 1,
            offset: 0.5,
          },
          {
            transform: `translate(${b.x - a.x}px, ${b.y - a.y}px) scale(.5)`,
            opacity: direction === 'in' ? 1 : 0,
          },
        ],
        { duration: 800, delay: i * 70, easing: 'cubic-bezier(.3,.7,.2,1)', fill: 'forwards' },
      )
      .finished.catch(() => undefined)
      .finally(() => {
        chip.remove()
        if (++landed === chips) done?.()
      })
  }
}
