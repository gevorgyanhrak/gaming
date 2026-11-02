import { useQuery } from '@tanstack/react-query'
import { useSyncExternalStore } from 'react'
import { useCurrentUser } from '@/features/auth/hooks'
import { apiRequest } from '@/shared/api/http'
import type { ApiResponse } from '@/shared/api/types'

/** Today's daily bonus: the streak's day (1–5), every day's amount, and whether it was collected. */
export type DailyBonus = ApiResponse<'v1.me.daily-bonus.show'>['data']
export type DailyBonusCollected = ApiResponse<'v1.me.daily-bonus.store'>['data']

export const dailyBonusKey = ['me', 'daily-bonus'] as const

export const fetchDailyBonus = (signal?: AbortSignal) =>
  apiRequest<ApiResponse<'v1.me.daily-bonus.show'>>('/api/v1/me/daily-bonus', { signal }).then(
    (r) => r.data,
  )

/** Collect today's bonus; the server gives it once a day (`collected` is 0 for a repeat). */
export const collectDailyBonus = () =>
  apiRequest<ApiResponse<'v1.me.daily-bonus.store'>>('/api/v1/me/daily-bonus', {
    method: 'POST',
  }).then((r) => r.data)

export function useDailyBonus() {
  const { data: user } = useCurrentUser()
  return useQuery({
    queryKey: dailyBonusKey,
    queryFn: ({ signal }) => fetchDailyBonus(signal),
    enabled: !!user,
    // A new day can start while the page is open.
    refetchOnWindowFocus: true,
  })
}

// Whether the popup is open: it opens by itself once per visit and player while today's bonus is
// waiting (another account signing in on the same page is offered theirs), and from the wallet page.
let open = false
const shownTo = new Set<number>()
const listeners = new Set<() => void>()
const notify = () => listeners.forEach((listener) => listener())

export function openDailyBonus() {
  open = true
  notify()
}

export function closeDailyBonus() {
  open = false
  notify()
}

/** Opens it by itself, once per visit for each player (closed, it stays closed until the next visit). */
export function offerDailyBonus(userId: number) {
  if (shownTo.has(userId)) return
  shownTo.add(userId)
  openDailyBonus()
}

export function useDailyBonusOpen(): boolean {
  return useSyncExternalStore(
    (listener) => {
      listeners.add(listener)
      return () => listeners.delete(listener)
    },
    () => open,
  )
}

/** For tests: a fresh visit. */
export function resetDailyBonus() {
  open = false
  shownTo.clear()
}
