import type { TFunction } from 'i18next'
import type { LevelItem } from './api'

/** A level reward item's name ("Avatar frame: Gold"); unknown ones fall back to their key. */
export function itemLabel(t: TFunction, item: LevelItem): string {
  const known: Record<string, Record<string, string>> = {
    frame: { gold: t('level.items.frameGold'), legend: t('level.items.frameLegend') },
    badge: {
      rising: t('level.items.badgeRising'),
      premium: t('level.items.badgePremium'),
      champion: t('level.items.badgeChampion'),
    },
    emoji_pack: { royal: t('level.items.packRoyal'), legend: t('level.items.packLegend') },
  }
  return known[item.kind]?.[item.key] ?? item.key
}

/** A profile badge's name. */
export function badgeLabel(t: TFunction, key: string): string {
  return itemLabel(t, { kind: 'badge', key })
}
