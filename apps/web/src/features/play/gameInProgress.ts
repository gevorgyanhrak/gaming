import { useEffect, useSyncExternalStore } from 'react'

// Whether the page shows a game the player is still playing: popups (level-up, daily bonus) wait.
let playing = 0
const listeners = new Set<() => void>()
const notify = () => listeners.forEach((listener) => listener())

/** RoomPage marks a game in progress while it is on screen. */
export function useMarkGameInProgress(active: boolean) {
  useEffect(() => {
    if (!active) return
    playing++
    notify()
    return () => {
      playing--
      notify()
    }
  }, [active])
}

export function useGameInProgress(): boolean {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    () => playing > 0,
  )
}
