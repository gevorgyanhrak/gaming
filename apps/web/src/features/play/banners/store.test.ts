import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { clearBanners, DURATION, showBanner, useBanner } from './store'

afterEach(() => {
  clearBanners()
  vi.useRealTimers()
})

describe('table banners', () => {
  it('shows one at a time, puts big moments first and drops small ones when two wait', () => {
    vi.useFakeTimers()
    const { result } = renderHook(() => useBanner())
    act(() => {
      showBanner({ kind: 'joined', title: 'Anna joined' })
      showBanner({ kind: 'left', title: 'Bob left' })
      showBanner({ kind: 'joined', title: 'Cara joined' })
      showBanner({ kind: 'joined', title: 'Dan joined' }) // two already wait: dropped
      showBanner({ kind: 'allIn', title: 'All-in' }) // a big one: ahead of the small ones
    })
    const seen = [result.current?.title]
    for (let i = 0; i < 4; i++) {
      act(() => {
        vi.advanceTimersByTime(DURATION[result.current!.kind])
      })
      seen.push(result.current?.title)
    }
    expect(seen).toEqual(['Anna joined', 'All-in', 'Bob left', 'Cara joined', undefined])
  })
})
