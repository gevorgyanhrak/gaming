import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { useCountUp } from './useCountUp'

function motion(reduced: boolean) {
  vi.stubGlobal('matchMedia', (query: string) => ({ matches: reduced, media: query }))
}

describe('useCountUp', () => {
  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('rolls to a new value, passing through the numbers between', () => {
    motion(false)
    vi.useFakeTimers({ toFake: ['requestAnimationFrame', 'cancelAnimationFrame', 'performance'] })
    const { result, rerender } = renderHook(({ n }) => useCountUp(n, 700), {
      initialProps: { n: 1000 },
    })
    expect(result.current).toBe(1000)

    rerender({ n: 2000 })
    act(() => {
      vi.advanceTimersByTime(300)
    })
    expect(result.current).toBeGreaterThan(1000)
    expect(result.current).toBeLessThan(2000)
    act(() => {
      vi.advanceTimersByTime(800)
    })
    expect(result.current).toBe(2000)
  })

  it('jumps straight there under reduced motion', () => {
    motion(true)
    const { result, rerender } = renderHook(({ n }) => useCountUp(n), { initialProps: { n: 5 } })
    rerender({ n: 50 })
    expect(result.current).toBe(50)
  })
})
