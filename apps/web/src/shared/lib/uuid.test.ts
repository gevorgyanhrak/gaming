import { afterEach, describe, expect, it, vi } from 'vitest'
import { randomId } from './uuid'

const V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/

describe('randomId', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('uses crypto.randomUUID when the page is on a secure origin', () => {
    expect(randomId()).toMatch(V4)
  })

  it('builds a v4 UUID when randomUUID is missing (plain HTTP on the local network)', () => {
    const real = crypto
    vi.stubGlobal('crypto', {
      getRandomValues: <T extends Uint8Array<ArrayBuffer>>(array: T) => real.getRandomValues(array),
    })

    const ids = new Set(Array.from({ length: 50 }, randomId))
    expect(ids.size).toBe(50)
    for (const id of ids) expect(id).toMatch(V4)
  })
})
