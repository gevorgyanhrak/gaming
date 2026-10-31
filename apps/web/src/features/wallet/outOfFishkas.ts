import { useSyncExternalStore } from 'react'

// Whether the "out of Fishkas" popup is open. Opened from anywhere a game fails to start for lack
// of Fishkas (see the mutation cache in app/queryClient.ts); one popup for the whole app.
let open = false
const listeners = new Set<() => void>()

function set(next: boolean) {
  if (open === next) return
  open = next
  for (const listener of listeners) listener()
}

export const openOutOfFishkas = () => set(true)
export const closeOutOfFishkas = () => set(false)

export function useOutOfFishkasOpen(): boolean {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    () => open,
    () => false,
  )
}
