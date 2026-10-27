import { useEffect } from 'react'
import { sendPresence } from './api'

/** How often the game page reports that the player is still there (well below the allowed absence). */
const EVERY_MS = 5_000

/**
 * While `active`, tells the server every few seconds that the player is on the game page. A player
 * who stops reporting for longer than the platform allows loses the game.
 */
export function usePresence(roomId: string, active: boolean) {
  useEffect(() => {
    if (!active) return
    const report = () => void sendPresence(roomId).catch(() => undefined)
    report()
    const timer = setInterval(report, EVERY_MS)
    return () => clearInterval(timer)
  }, [roomId, active])
}
