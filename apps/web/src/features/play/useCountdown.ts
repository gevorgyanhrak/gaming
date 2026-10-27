import { useEffect, useRef, useState } from 'react'

/**
 * Seconds left until an ISO deadline, corrected for the difference between server and device
 * clocks (from the server_time sent with the same state).
 */
export function useCountdown(
  deadline: string | null | undefined,
  serverTime: string | null | undefined,
): number | null {
  const offset = useRef(0)
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    if (serverTime) offset.current = new Date(serverTime).getTime() - Date.now()
  }, [serverTime])

  useEffect(() => {
    if (!deadline) return
    const tick = () => setNow(Date.now() + offset.current)
    const timer = setInterval(tick, 500)
    return () => clearInterval(timer)
  }, [deadline])

  if (!deadline) return null
  return Math.max(0, Math.ceil((new Date(deadline).getTime() - now) / 1000))
}
