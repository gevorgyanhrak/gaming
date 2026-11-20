import { useSyncExternalStore } from 'react'

/**
 * Holding back a slot win in the header (HayBet item 17). The server credits a win with the spin, at once and for
 * good; only the number shown waits: while the game presents the win, the header shows the balance without it, then
 * counts up when the game says it's done (`slot:settled`). A safety timer lets go if a game never says so.
 */
let held: number | null = null
let timer: ReturnType<typeof setTimeout> | undefined
const listeners = new Set<() => void>()
const notify = () => listeners.forEach((listener) => listener())

/** The longest a win waits in the header, whatever the game does. */
export const HOLD_MS = 10_000

/** Show `balance` (the real balance minus the win being presented) until released. */
export function holdBalance(balance: number) {
  held = balance
  clearTimeout(timer)
  timer = setTimeout(releaseBalance, HOLD_MS)
  notify()
}

export function releaseBalance() {
  clearTimeout(timer)
  if (held === null) return
  held = null
  notify()
}

/** The balance to show instead of the wallet's while a win is being presented, or null. */
export function useHeldBalance(): number | null {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    () => held,
    () => null,
  )
}
