import { describe, expect, it } from 'vitest'
import { fitHeight } from '@/features/play/useFitHeight'
import { handLayout } from './handLayout'

describe('handLayout', () => {
  it('keeps a normal hand in one row, cards 0.72 of a card apart', () => {
    expect(handLayout(6, 346, 64, 40)).toEqual({ rows: [6], steps: [64 * 0.72] })
  })

  it('splits a big hand evenly into rows that each show at least the strip', () => {
    // 346 px wide, 64 px cards, 40 px strips: 8 fit in a row.
    const { rows, steps } = handLayout(18, 346, 64, 40)
    expect(rows).toEqual([6, 6, 6])
    for (const step of steps) expect(step).toBeGreaterThanOrEqual(40)

    expect(handLayout(9, 346, 64, 40).rows).toEqual([5, 4])
  })

  it('shrinks the strips rather than use a fourth row', () => {
    const { rows, steps } = handLayout(30, 346, 64, 40)
    expect(rows).toHaveLength(3)
    for (const step of steps) expect(step).toBeGreaterThanOrEqual(28)
  })

  it('handles an empty hand', () => {
    expect(handLayout(0, 346, 64, 40)).toEqual({ rows: [], steps: [] })
  })
})

describe('fitHeight', () => {
  it('fills the screen below the table top, above the tab bar', () => {
    expect(fitHeight({ viewport: 844, top: 184, tabbar: 58, min: 420 })).toBe(590)
    expect(fitHeight({ viewport: 900, top: 248, tabbar: 0, min: 420 })).toBe(640)
  })

  it('never goes below the minimum', () => {
    expect(fitHeight({ viewport: 500, top: 300, tabbar: 58, min: 420 })).toBe(420)
  })
})
