import { renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as api from './api'
import { usePresence } from './usePresence'

describe('usePresence', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('reports every 2 seconds, and says at once when the page closes or the player leaves the table', () => {
    const seen = vi.spyOn(api, 'sendPresence').mockResolvedValue(null)
    const away = vi.spyOn(api, 'sendAway').mockImplementation(() => undefined)

    const { unmount } = renderHook(() => usePresence('room-1', true))
    expect(seen).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(4000)
    expect(seen).toHaveBeenCalledTimes(3)

    window.dispatchEvent(new Event('pagehide'))
    expect(away).toHaveBeenCalledWith('room-1')

    away.mockClear()
    unmount()
    expect(away).toHaveBeenCalledWith('room-1')
  })

  it('says nothing for a game the player is no longer in', () => {
    const seen = vi.spyOn(api, 'sendPresence').mockResolvedValue(null)
    const away = vi.spyOn(api, 'sendAway').mockImplementation(() => undefined)
    const { unmount } = renderHook(() => usePresence('room-1', false))
    unmount()
    expect(seen).not.toHaveBeenCalled()
    expect(away).not.toHaveBeenCalled()
  })
})
