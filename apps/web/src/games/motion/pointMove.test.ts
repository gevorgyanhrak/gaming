import { describe, expect, it } from 'vitest'
import { pointMove } from './pointMove'

describe('pointMove', () => {
  it('finds a plain step, a hit, and nothing for bar entries or bearing off', () => {
    expect(pointMove([2, 0, 0, -1], [1, 0, 1, -1])).toEqual({ from: 1, to: 3 })
    // White hits Black's blot on point 4.
    expect(pointMove([2, 0, 0, -1], [1, 0, 0, 1])).toEqual({ from: 1, to: 4 })
    // A checker comes in from the bar: nothing lost on the board.
    expect(pointMove([0, 0, -2], [1, 0, -2])).toBeNull()
    // Borne off: nothing gained.
    expect(pointMove([0, 0, 2], [0, 0, 1])).toBeNull()
    expect(pointMove([1, 1], [1, 1])).toBeNull()
  })
})
