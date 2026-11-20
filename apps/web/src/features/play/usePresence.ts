import { useEffect } from 'react'
import { sendAway, sendPresence } from './api'

/** How often the game page reports that the player is still there (ADR 0028: quiet for 6 s = disconnected). */
const EVERY_MS = 2_000

/**
 * While `active`, tells the server every 2 s that the player is on the game page, and when the page closes or the
 * player navigates away — then their return window opens at once (a few seconds before AI takes the seat).
 */
export function usePresence(roomId: string, active: boolean) {
  useEffect(() => {
    if (!active) return
    const report = () => void sendPresence(roomId).catch(() => undefined)
    report()
    const timer = setInterval(report, EVERY_MS)
    const closing = () => sendAway(roomId)
    window.addEventListener('pagehide', closing)
    return () => {
      clearInterval(timer)
      window.removeEventListener('pagehide', closing)
      // Leaving the table inside the app counts the same as closing the page.
      sendAway(roomId)
    }
  }, [roomId, active])
}
