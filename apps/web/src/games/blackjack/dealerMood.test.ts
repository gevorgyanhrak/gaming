import { describe, expect, it } from 'vitest'
import { roundMood, tipMood } from './dealerMood'

describe('dealer lines', () => {
  it('never says the same line twice in a row', () => {
    let previous = ''
    for (let i = 0; i < 40; i++) {
      const { line } = roundMood(100, false, false)
      expect(line.key).toMatch(/^blackjack\.say\.youWin\.[0-2]$/)
      expect(line.key).not.toBe(previous)
      previous = line.key
    }
  })

  it('reacts to what happened: blackjack, the dealer busting, your bust, a push, a tip', () => {
    expect(roundMood(150, true, false).line.key).toMatch(/\.blackjack\./)
    expect(roundMood(100, false, true).line.key).toMatch(/\.dealerBust\./)
    expect(roundMood(-100, false, false, true)).toMatchObject({ mood: 'sorry' })
    expect(roundMood(-100, false, false, true).line.key).toMatch(/\.youBust\./)
    expect(roundMood(-100, false, false).line.key).toMatch(/\.youLose\./)
    expect(roundMood(0, false, false).line.key).toMatch(/\.push\./)
    expect(tipMood(60, 100, 'Ani')).toMatchObject({
      mood: 'delighted',
      line: { values: { name: 'Ani' } },
    })
    expect(tipMood(60, 100, 'Ani').line.key).toMatch(/\.tipBigNamed\./)
  })
})
