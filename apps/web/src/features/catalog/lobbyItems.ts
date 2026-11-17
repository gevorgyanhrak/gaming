import type { Slot } from '@/features/casino/slots'
import type { Favorite } from '@/features/favorites/favorites'
import type { GameSummary } from './queries'

/** One card in the lobby: a table game or a slot (a one-player game of the Casino category). */
export type LobbyItem = { kind: 'game'; game: GameSummary } | { kind: 'slot'; slot: Slot }

export const SORTS = ['recommended', 'name', 'playing'] as const
export type Sort = (typeof SORTS)[number]
export const PLAYERS = ['two', 'many'] as const
export type Players = (typeof PLAYERS)[number]

export const itemName = (item: LobbyItem) =>
  item.kind === 'game' ? item.game.name : item.slot.name
export const itemKey = (item: LobbyItem) =>
  item.kind === 'game' ? item.game.slug : `slot-${item.slot.slug}`

const seats = (item: LobbyItem) =>
  item.kind === 'game' ? [item.game.min_players, item.game.max_players] : [1, 1]

/** People at its tables now; a game that doesn't show the count (and a slot) sorts after the rest. */
const playing = (item: LobbyItem) => (item.kind === 'game' ? (item.game.playing_now ?? -1) : -1)

/** Favourites in the order they were saved (newest first), skipping any the catalogue no longer has. */
export function favoriteItems(
  favorites: Favorite[],
  games: GameSummary[],
  slots: Slot[],
): LobbyItem[] {
  return favorites.flatMap((favorite): LobbyItem[] => {
    if (favorite.kind === 'game') {
      const game = games.find((g) => g.slug === favorite.slug)
      return game ? [{ kind: 'game', game }] : []
    }
    const slot = slots.find((s) => s.slug === favorite.slug)
    return slot ? [{ kind: 'slot', slot }] : []
  })
}

/**
 * Narrows and orders the lobby's cards: a name search, a seat filter (2 players: tables a pair can play; 3 or
 * more: tables for groups — slots are one-player, so either leaves them out) and a sort. "Recommended" keeps the
 * admin's order; sorting never drops a card.
 */
export function arrange(
  items: LobbyItem[],
  { query = '', players, sort = 'recommended' }: { query?: string; players?: Players; sort?: Sort },
): LobbyItem[] {
  const needle = query.trim().toLocaleLowerCase()
  const kept = items.filter((item) => {
    // The Latin slug too, so "durak" finds Դուրակ in any language.
    const slug = item.kind === 'game' ? item.game.slug : item.slot.slug
    if (needle && !itemName(item).toLocaleLowerCase().includes(needle) && !slug.includes(needle))
      return false
    const [min, max] = seats(item)
    if (players === 'two' && !(min! <= 2 && max! >= 2)) return false
    if (players === 'many' && max! < 3) return false
    return true
  })
  if (sort === 'name') return kept.toSorted((a, b) => itemName(a).localeCompare(itemName(b)))
  if (sort === 'playing') return kept.toSorted((a, b) => playing(b) - playing(a))
  return kept
}
