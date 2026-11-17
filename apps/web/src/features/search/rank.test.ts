import { describe, expect, it } from 'vitest'
import { rank } from './rank'

const names = (list: { name: string }[]) => list.map((item) => item.name)

describe('rank', () => {
  it('puts names that start with the text first, then word starts, then any match', () => {
    const items = [
      { name: 'Stellar Sevens' },
      { name: 'Seven Seas' },
      { name: 'Nardi' },
      { name: 'Fives' },
    ]
    expect(names(rank(items, 'se'))).toEqual(['Seven Seas', 'Stellar Sevens'])
    expect(names(rank(items, 'ves'))).toEqual(['Fives'])
    expect(names(rank(items, 'zz'))).toEqual([])
  })

  it('matches the Latin slug too', () => {
    const items = [
      { name: 'Դուրակ', alt: 'durak' },
      { name: 'Նարդի', alt: 'nardi' },
    ]
    expect(names(rank(items, 'dur'))).toEqual(['Դուրակ'])
  })
})
