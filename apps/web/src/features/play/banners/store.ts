import { useSyncExternalStore } from 'react'

/**
 * Table banners: the big moments of a game (ALL-IN, WINNER) and the small ones (someone joined or left), one at a
 * time. Major banners wait their turn; a minor one is dropped when two are already waiting, so the table never
 * fills with messages. Nothing here blocks a move: banners take no clicks and don't hold the game back.
 */
export type BannerKind = 'allIn' | 'winner' | 'lost' | 'joined' | 'left'

export interface Banner {
  id: number
  kind: BannerKind
  title: string
  detail?: string
}

const MAJOR: BannerKind[] = ['allIn', 'winner']
/** How long each stays: long enough to read, short enough not to hold up the next hand. */
export const DURATION: Record<BannerKind, number> = {
  allIn: 1900,
  winner: 2300,
  lost: 1600,
  joined: 1500,
  left: 1500,
}
const MAX_WAITING = 2

let shown: Banner | null = null
let waiting: Banner[] = []
let nextId = 1
let timer: number | undefined
const listeners = new Set<() => void>()
const emit = () => listeners.forEach((listener) => listener())

function advance() {
  shown = waiting.shift() ?? null
  window.clearTimeout(timer)
  if (shown) timer = window.setTimeout(advance, DURATION[shown.kind])
  emit()
}

export function showBanner(banner: Omit<Banner, 'id'>) {
  const next = { ...banner, id: nextId++ }
  if (!MAJOR.includes(next.kind) && waiting.length >= MAX_WAITING) return
  // A major moment goes ahead of the small ones still waiting.
  if (MAJOR.includes(next.kind)) {
    const at = waiting.findIndex((b) => !MAJOR.includes(b.kind))
    if (at >= 0) waiting.splice(at, 0, next)
    else waiting.push(next)
  } else waiting.push(next)
  if (!shown) advance()
  else emit()
}

/** Leaving a table forgets its banners. */
export function clearBanners() {
  window.clearTimeout(timer)
  shown = null
  waiting = []
  emit()
}

const subscribe = (listener: () => void) => {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export const useBanner = () =>
  useSyncExternalStore(
    subscribe,
    () => shown,
    () => null,
  )
