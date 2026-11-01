import { useSyncExternalStore } from 'react'
import { getEcho, isRealtimeConnected, onRealtimeStatus } from './echo'

/**
 * Whether live updates are arriving now. False when realtime isn't configured, while connecting, and
 * when the socket can't be reached (e.g. a wrong host): callers then poll the API instead.
 */
export function useRealtimeConnected(): boolean {
  getEcho()
  return useSyncExternalStore(onRealtimeStatus, isRealtimeConnected, () => false)
}
