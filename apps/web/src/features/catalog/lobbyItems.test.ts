import { describe, expect, it } from 'vitest'
import { gameFixture } from '@/test/handlers'
import { arrange, favoriteItems, itemName, type LobbyItem } from './lobbyItems'

const game = (
  slug: string,
  min: number,
  max: number,
  playing_now: number | null = null,
): LobbyItem => ({
  kind: 'game',
  game: gameFixture({
    slug,
    name: slug[0]!.toUpperCase() + slug.slice(1),
    min_players: min,
    max_players: max,
    playing_now,
  }),
})
const slot = (slug: string): LobbyItem => ({
  kind: 'slot',
  slot: {
    slug,
    name: slug,
    provider: 'APEXSLOTS',
    studio: null,
    kind: 'house',
    image_url: null,
    min_bet: 10,
    max_bet: 1000,
    play_path: `/play/${slug}/`,
  },
})

const items = [
  game('poker', 2, 6, 3),
  game('chess', 2, 2, 7),
  slot('sevens'),
  game('blot', 4, 4, null),
]
const names = (list: LobbyItem[]) => list.map(itemName)

describe('arrange', () => {
  it('keeps the admin order by default and searches by name', () => {
    expect(names(arrange(items, {}))).toEqual(['Poker', 'Chess', 'sevens', 'Blot'])
    expect(names(arrange(items, { query: ' PO ' }))).toEqual(['Poker'])
    expect(names(arrange(items, { query: 'seve' }))).toEqual(['sevens'])
  })

  it('filters by seats; slots are one-player', () => {
    expect(names(arrange(items, { players: 'two' }))).toEqual(['Poker', 'Chess'])
    expect(names(arrange(items, { players: 'many' }))).toEqual(['Poker', 'Blot'])
  })

  it('sorts by name or by people playing now, unknown counts last', () => {
    expect(names(arrange(items, { sort: 'name' }))).toEqual(['Blot', 'Chess', 'Poker', 'sevens'])
    expect(names(arrange(items, { sort: 'playing' }))).toEqual(['Chess', 'Poker', 'sevens', 'Blot'])
  })
})

describe('favoriteItems', () => {
  it('follows the saved order and skips what the catalogue no longer has', () => {
    const games = [items[0], items[1]].map((i) => (i as Extract<LobbyItem, { kind: 'game' }>).game)
    const slots = [(items[2] as Extract<LobbyItem, { kind: 'slot' }>).slot]
    const list = favoriteItems(
      [
        { kind: 'slot', slug: 'sevens' },
        { kind: 'game', slug: 'gone' },
        { kind: 'game', slug: 'chess' },
      ],
      games,
      slots,
    )
    expect(names(list)).toEqual(['sevens', 'Chess'])
  })
})
