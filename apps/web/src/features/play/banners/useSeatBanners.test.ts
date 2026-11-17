import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { clearBanners, DURATION, useBanner } from './store'
import { useSeatBanners } from './useSeatBanners'

afterEach(() => {
  clearBanners()
  vi.useRealTimers()
})

const seat = (n: number, name: string, result: string | null = null) => ({
  seat: n,
  name,
  username: null,
  result,
})

describe('useSeatBanners', () => {
  it('says who joined and who left, never for the room as it opened nor for you', () => {
    vi.useFakeTimers()
    const banner = renderHook(() => useBanner())
    const { rerender } = renderHook(({ list }) => useSeatBanners(list, 0), {
      initialProps: { list: [seat(0, 'You'), seat(1, 'Anna')] },
    })
    expect(banner.result.current).toBeNull()

    rerender({ list: [seat(0, 'You'), seat(1, 'Anna'), seat(2, 'Bob')] })
    expect(banner.result.current?.title).toBe('Bob joined')

    rerender({ list: [seat(0, 'You', 'abandoned'), seat(1, 'Anna', 'abandoned'), seat(2, 'Bob')] })
    act(() => {
      vi.advanceTimersByTime(DURATION.joined)
    })
    expect(banner.result.current?.title).toBe('Anna left')
    act(() => {
      vi.advanceTimersByTime(DURATION.left)
    })
    expect(banner.result.current).toBeNull()
  })
})
