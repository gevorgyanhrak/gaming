import { renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as sounds from '@/shared/lib/sounds'
import { useCasinoAmbience } from './useCasinoAmbience'

let soundOn = true
vi.mock('./useSoundSetting', () => ({
  useSoundSetting: () => ({ on: soundOn, set: vi.fn(), pending: false }),
}))

describe('Casino ambience', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.spyOn(sounds, 'unlockSounds').mockImplementation(() => undefined)
    sessionStorage.clear()
  })
  afterEach(() => {
    vi.useRealTimers()
    soundOn = true
  })

  it('starts quietly with sounds on, greets once a session, and stops when the hook goes', () => {
    const ambience = vi.spyOn(sounds, 'setAmbience').mockImplementation(() => undefined)
    const play = vi.spyOn(sounds, 'playUiSound').mockImplementation(() => undefined)

    const first = renderHook(() => useCasinoAmbience(true))
    vi.advanceTimersByTime(200)
    expect(ambience).toHaveBeenLastCalledWith(true)
    expect(play).toHaveBeenCalledWith('welcome')
    first.unmount()
    expect(ambience).toHaveBeenLastCalledWith(false)

    play.mockClear()
    renderHook(() => useCasinoAmbience(true))
    vi.advanceTimersByTime(200)
    expect(play).not.toHaveBeenCalledWith('welcome')
  })

  it('stays silent at a table and with sounds off', () => {
    const ambience = vi.spyOn(sounds, 'setAmbience').mockImplementation(() => undefined)
    renderHook(() => useCasinoAmbience(false))
    vi.advanceTimersByTime(200)
    expect(ambience).not.toHaveBeenCalledWith(true)

    soundOn = false
    renderHook(() => useCasinoAmbience(true))
    vi.advanceTimersByTime(200)
    expect(ambience).not.toHaveBeenCalledWith(true)
  })
})
