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

  it('says when someone disconnected, came back, or lost their seat to AI (ADR 0028)', () => {
    vi.useFakeTimers()
    const banner = renderHook(() => useBanner())
    const away = { ...seat(1, 'Anna'), return_until: '2026-11-20T12:00:05.000Z', replaced: false }
    const { rerender } = renderHook(({ list }) => useSeatBanners(list, 0), {
      initialProps: {
        list: [seat(0, 'You'), seat(1, 'Anna')] as Parameters<typeof useSeatBanners>[0],
      },
    })

    rerender({ list: [seat(0, 'You'), away] })
    expect(banner.result.current?.title).toBe('Anna disconnected — a few seconds to return')

    act(() => {
      vi.advanceTimersByTime(DURATION.left)
    })
    rerender({
      list: [seat(0, 'You'), { ...seat(1, 'Anna'), return_until: null, replaced: false }],
    })
    expect(banner.result.current?.title).toBe('Anna is back')

    act(() => {
      vi.advanceTimersByTime(DURATION.joined)
    })
    rerender({ list: [seat(0, 'You'), away] })
    act(() => {
      vi.advanceTimersByTime(DURATION.left)
    })
    rerender({
      list: [
        seat(0, 'You'),
        { ...seat(1, 'Anna', 'abandoned'), return_until: null, replaced: true },
      ],
    })
    // One banner for the takeover, not a second "left" as well.
    expect(banner.result.current?.title).toBe('AI took Anna’s seat')
  })
})
