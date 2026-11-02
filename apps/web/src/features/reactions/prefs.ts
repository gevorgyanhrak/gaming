import { useSyncExternalStore } from 'react'

/**
 * Per-viewer conveniences, kept in this browser only: recently sent emojis and whether other players'
 * reactions are muted. Storage can be unavailable (private mode); then nothing is remembered.
 */
const RECENT_KEY = 'reactions.recent'
const MUTED_KEY = 'reactions.muted'
const RECENT_MAX = 24

function read(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

function write(key: string, value: string) {
  try {
    localStorage.setItem(key, value)
  } catch {
    // Not remembering is acceptable.
  }
}

export function recentEmojis(): string[] {
  try {
    const list: unknown = JSON.parse(read(RECENT_KEY) ?? '[]')
    return Array.isArray(list) ? list.filter((e): e is string => typeof e === 'string') : []
  } catch {
    return []
  }
}

export function rememberEmoji(emoji: string) {
  write(
    RECENT_KEY,
    JSON.stringify([emoji, ...recentEmojis().filter((e) => e !== emoji)].slice(0, RECENT_MAX)),
  )
}

const listeners = new Set<() => void>()

export function setMuted(muted: boolean) {
  write(MUTED_KEY, muted ? '1' : '0')
  for (const listener of listeners) listener()
}

/** Whether other players' reactions are muted (no flights; the table chat still lists them). */
export function useReactionsMuted(): boolean {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    () => read(MUTED_KEY) === '1',
  )
}
