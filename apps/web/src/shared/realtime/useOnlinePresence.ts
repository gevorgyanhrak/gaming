import { useEffect } from 'react'
import { getEcho } from './echo'

/**
 * While a player is signed in, their page is "online": it joins the "online" presence channel, which the server
 * counts (unique people, several tabs are one) for the counter at the top. Nothing is listened to.
 */
export function useOnlinePresence(userId: number | undefined) {
  useEffect(() => {
    const echo = getEcho()
    if (!echo || userId === undefined) return
    echo.join('online')
    return () => echo.leave('online')
  }, [userId])
}
