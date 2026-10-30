import { useLayoutEffect, useRef, useState, type RefObject } from 'react'

export interface Pair {
  attack: string
  defense: string | null
}

export interface Bout {
  table: Pair[]
  handCounts: number[]
  discarded: number
  defender: number
}

/** The cards of a bout that just ended, kept for a moment to leave the table. */
export interface Ghost {
  id: number
  pairs: Pair[]
  /** Where they go: the discard pile (beaten) or the seat that took them. */
  to: 'discard' | number
}

const center = (r: DOMRect) => ({ x: r.left + r.width / 2, y: r.top + r.height / 2 })

/**
 * Movement on the Durak table, all from the views the server sends: a card played to the table
 * flies in from the player who played it (your tapped card, or the seat whose hand shrank), and a
 * finished bout slides to the discard pile or to whoever took it. Elements are found in `stage` by
 * `data-card`, `data-seat`, `data-discard` and `data-ghost-card`. Nothing moves under reduced
 * motion; `ended` still says how the last bout ended (for screen readers).
 */
export function useBoutMotion({
  version,
  bout,
  stageRef,
  tappedRef,
  reduced,
}: {
  version: number
  bout: Bout
  stageRef: RefObject<HTMLElement | null>
  /** The card you just tapped and where it was. */
  tappedRef: RefObject<{ card: string; rect: DOMRect } | null>
  reduced: boolean
}) {
  // Derived while rendering: a bout ended between the last version and this one.
  const [seen, setSeen] = useState<{ version: number; bout: Bout; ended: Ghost | null }>({
    version,
    bout,
    ended: null,
  })
  if (seen.version !== version) {
    const before = seen.bout
    const ended =
      before.table.length > 0 && bout.table.length === 0
        ? {
            id: version,
            pairs: before.table,
            to: bout.discarded > before.discarded ? ('discard' as const) : before.defender,
          }
        : seen.ended
    setSeen({ version, bout, ended })
  }
  const [cleared, setCleared] = useState<number | null>(null)
  const ghost = !reduced && seen.ended && seen.ended.id !== cleared ? seen.ended : null

  // Cards new on the table fly in from where they were played.
  const previousRef = useRef(bout)
  useLayoutEffect(() => {
    const before = previousRef.current
    previousRef.current = bout
    const root = stageRef.current
    const tap = tappedRef.current
    tappedRef.current = null
    if (!root || reduced || before === bout) return

    const played: string[] = []
    bout.table.forEach((pair, i) => {
      const old = before.table[i]
      if (!old) played.push(pair.attack, ...(pair.defense ? [pair.defense] : []))
      else if (!old.defense && pair.defense) played.push(pair.defense)
    })
    const actor = bout.handCounts.findIndex((n, seat) => n < (before.handCounts[seat] ?? 0))
    for (const card of played) {
      const el = root.querySelector<HTMLElement>(`[data-card="${card}"]`)
      const from =
        tap?.card === card
          ? tap.rect
          : root.querySelector(`[data-seat="${actor}"]`)?.getBoundingClientRect()
      if (!el || !from || typeof el.animate !== 'function') continue
      const a = center(from)
      const b = center(el.getBoundingClientRect())
      el.animate(
        [
          { transform: `translate(${a.x - b.x}px, ${a.y - b.y}px) scale(0.7)`, opacity: 0.4 },
          { transform: 'none', opacity: 1 },
        ],
        { duration: 260, easing: 'cubic-bezier(.2,.8,.2,1)' },
      )
    }
    // Only a new version is a new step.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [version])

  // A finished bout slides off, then the copy is removed.
  const ghostId = ghost?.id
  useLayoutEffect(() => {
    if (ghostId === undefined || !ghost) return
    const root = stageRef.current
    const target =
      ghost.to === 'discard'
        ? root?.querySelector('[data-discard]')
        : root?.querySelector(`[data-seat="${ghost.to}"]`)
    const to = target?.getBoundingClientRect()
    root?.querySelectorAll<HTMLElement>('[data-ghost-card]').forEach((el, i) => {
      if (!to || typeof el.animate !== 'function') return
      const a = center(el.getBoundingClientRect())
      const b = center(to)
      el.animate(
        [
          { transform: 'none', opacity: 1 },
          { transform: `translate(${b.x - a.x}px, ${b.y - a.y}px) scale(0.5)`, opacity: 0 },
        ],
        { duration: 360, delay: i * 30, easing: 'ease-in', fill: 'forwards' },
      )
    })
    const timer = window.setTimeout(() => setCleared(ghostId), 420)
    return () => window.clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ghostId])

  return { ghost, ended: seen.ended }
}
