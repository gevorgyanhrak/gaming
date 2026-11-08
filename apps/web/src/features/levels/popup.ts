import { useSyncExternalStore } from 'react'

// Whether the level-up popup is open: it opens by itself once per visit and player while rewards wait,
// and again whenever a new level is reached during the visit.
let open = false
const shownTo = new Set<number>()
const listeners = new Set<() => void>()
const notify = () => listeners.forEach((listener) => listener())

export function closeLevelUp() {
  open = false
  notify()
}

/** Opens it once per visit for each player; `again` reopens it (a level reached just now). */
export function offerLevelUp(userId: number, again = false) {
  if (shownTo.has(userId) && !again) return
  shownTo.add(userId)
  open = true
  notify()
}

export function useLevelUpOpen(): boolean {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    () => open,
  )
}

/** For tests: a fresh visit. */
export function resetLevelUp() {
  open = false
  shownTo.clear()
}
