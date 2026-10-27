import { useEffect, useRef } from 'react'
import { getEcho } from './echo'

type Handlers = Record<string, (payload: never) => void>

/**
 * Listens to events on the signed-in user's private channel ("user.{id}"): room updates and
 * matchmaking results. Event names are the server's broadcastAs() names, e.g. "room.updated".
 */
export function useUserChannel(userId: number | undefined, handlers: Handlers) {
  const latest = useRef(handlers)

  useEffect(() => {
    latest.current = handlers
  })

  useEffect(() => {
    const echo = getEcho()
    if (!echo || userId === undefined) return

    const channel = echo.private(`user.${userId}`)
    const names = Object.keys(latest.current)
    for (const name of names) {
      channel.listen(`.${name}`, (payload: never) => latest.current[name]?.(payload))
    }

    return () => {
      for (const name of names) channel.stopListening(`.${name}`)
    }
  }, [userId])
}
