import { useEffect, useRef } from 'react'
import { getEcho } from './echo'

type Handlers = Record<string, (payload: never) => void>

/**
 * Listens to a public channel (no sign-in needed), e.g. "wins" for Big Wins live. Event names are the server's
 * broadcastAs() names.
 */
export function usePublicChannel(name: string, handlers: Handlers) {
  const latest = useRef(handlers)

  useEffect(() => {
    latest.current = handlers
  })

  useEffect(() => {
    const echo = getEcho()
    if (!echo) return

    const channel = echo.channel(name)
    const events = Object.keys(latest.current)
    for (const event of events) {
      channel.listen(`.${event}`, (payload: never) => latest.current[event]?.(payload))
    }

    return () => {
      for (const event of events) channel.stopListening(`.${event}`)
    }
  }, [name])
}
