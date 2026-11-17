import { describe, expect, it } from 'vitest'
import { GAME_ALIASES, search, skeleton, termsOf } from './gameSearch'

// The real catalogue's slugs, with their names as the hy and en interfaces show them.
const catalogue = [
  { slug: 'chess', en: 'Chess', hy: 'Շախմատ' },
  { slug: 'nardi', en: 'Backgammon', hy: 'Նարդի' },
  { slug: 'checkers', en: 'Checkers', hy: 'Շաշկի' },
  { slug: 'blot', en: 'Blot', hy: 'Բլոտ' },
  { slug: 'durak', en: 'Durak', hy: 'Դուրակ' },
  { slug: 'poker', en: 'Poker', hy: 'Պոկեր' },
  { slug: 'blackjack', en: 'Blackjack', hy: 'Բլեքջեք' },
  { slug: 'domino', en: 'Domino', hy: 'Դոմինո' },
  { slug: 'ludo', en: 'Ludo', hy: 'Լուդո' },
  { slug: 'pool', en: '8-ball pool', hy: 'Բիլիարդ' },
  { slug: 'rummy', en: 'Rummy', hy: 'Ռամի' },
]
const find = (query: string, lang: 'en' | 'hy' = 'en') =>
  search(catalogue, query, (game) => termsOf(game.slug, game[lang])).map((m) => m.item.slug)

describe('game search', () => {
  it.each([
    ['shaxmat', 'chess'],
    ['shax', 'chess'],
    ['shahmat', 'chess'],
    ['shakhmaty', 'chess'],
    ['nardi', 'nardi'],
    ['nard', 'nardi'],
    ['shashki', 'checkers'],
    ['shash', 'checkers'],
    ['blot', 'blot'],
    ['durak', 'durak'],
    ['poker', 'poker'],
  ])('"%s" finds %s first, in English and in Armenian', (query, slug) => {
    expect(find(query)[0]).toBe(slug)
    expect(find(query, 'hy')[0]).toBe(slug)
  })

  it('ignores case and spaces around the text', () => {
    expect(find('  SHAXMAT ')[0]).toBe('chess')
    expect(find('Nardi')[0]).toBe('nardi')
  })

  it('understands the names in Armenian and Russian letters and English names', () => {
    expect(find('շախ')[0]).toBe('chess')
    expect(find('шахматы')[0]).toBe('chess')
    expect(find('нарды')[0]).toBe('nardi')
    expect(find('backgammon', 'hy')[0]).toBe('nardi')
    expect(find('draughts')[0]).toBe('checkers')
    expect(find('holdem')[0]).toBe('poker')
    expect(find('belot')[0]).toBe('blot')
  })

  it('forgives small typing slips but not unrelated words', () => {
    expect(find('shaxmta')[0]).toBe('chess')
    expect(find('pokre')[0]).toBe('poker')
    expect(find('durka')[0]).toBe('durak')
    expect(find('nrdi')[0]).toBe('nardi')
    expect(find('zzzz')).toEqual([])
    expect(find('roulette')).toEqual([])
  })

  it('lists each game once, however many of its names match', () => {
    const slugs = find('sha')
    expect(new Set(slugs).size).toBe(slugs.length)
    expect(slugs.slice(0, 2).toSorted()).toEqual(['checkers', 'chess'])
  })

  it('folds spellings that sound the same', () => {
    expect(skeleton('shahmat')).toBe(skeleton('shakhmat'))
    expect(skeleton('shakhmat')).toBe(skeleton('Շախմատ'))
    expect(skeleton('nardy')).toBe(skeleton('nardi'))
  })

  it('has aliases only for games that exist, and a game without any still matches by name and slug', () => {
    for (const slug of Object.keys(GAME_ALIASES))
      expect(catalogue.map((g) => g.slug)).toContain(slug)
    expect(
      search([{ slug: 'hearts', name: 'Hearts' }], 'hear', (g) => termsOf(g.slug, g.name)),
    ).toHaveLength(1)
  })
})
