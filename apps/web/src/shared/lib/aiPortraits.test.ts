import { describe, expect, it } from 'vitest'
import { AI_PORTRAITS, portraitOf, portraitUrl, tableFaces } from './aiPortraits'

describe('AI portraits', () => {
  it('gives a name the same portrait every time', () => {
    expect(portraitUrl('Armine')).toBe(portraitUrl('Armine'))
    expect(portraitUrl('Armine')).toMatch(/\/ai\/\d{3}\.svg$/)
    expect(portraitOf('Armine')).toBeLessThan(AI_PORTRAITS)
  })

  it('never shows two AI players at a table with the same portrait', () => {
    // Find two names that land on the same portrait.
    const first = 'Bot 0'
    let second = ''
    for (let i = 1; !second; i++)
      if (portraitOf(`Bot ${i}`) === portraitOf(first)) second = `Bot ${i}`
    const faces = tableFaces([
      { seat: 3, name: second },
      { seat: 1, name: first },
      { seat: 2, name: 'Lilit' },
    ])
    expect(faces[1]).toBe(first)
    expect(faces[3]).not.toBe(second)
    expect(new Set(Object.values(faces).map((key) => portraitOf(key ?? ''))).size).toBe(3)
    // The same table gives the same faces again.
    expect(
      tableFaces([
        { seat: 1, name: first },
        { seat: 3, name: second },
        { seat: 2, name: 'Lilit' },
      ]),
    ).toEqual(faces)
  })
})
