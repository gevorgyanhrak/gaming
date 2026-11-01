import type { Schemas } from '@/shared/api/types'

export type PlayGroup = Schemas['PlayGroupResource']

/**
 * Each group's colour, by its position (1 = lowest): bronze, silver, gold, emerald, amethyst. Used for
 * the medal on the group tiles and the badge on a table.
 */
export const groupColors = ['#b9824a', '#c3ccd6', '#e2b553', '#47c08e', '#b98cf0'] as const

export const groupColor = (group: Pick<PlayGroup, 'position'>) =>
  groupColors[Math.min(groupColors.length, Math.max(1, group.position)) - 1]!

/** Whether a balance may sit at the group's tables (the minimum is only checked, never charged). */
export const canEnter = (group: PlayGroup, balance: number | undefined) =>
  balance !== undefined &&
  balance >= Math.max(group.min_balance, group.kind === 'chips' ? 1 : group.min_bet)
