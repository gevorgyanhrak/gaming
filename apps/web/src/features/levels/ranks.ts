/** The five rank tiers by level (TASK-048). Names come from i18n (`ranks.<key>`). */
export type RankKey = 'bronze' | 'silver' | 'gold' | 'ruby' | 'amethyst'

const shine = 'shadow-[inset_0_1px_0_rgb(255_255_255/0.3)]'

export function tierOf(level: number): { key: RankKey; classes: string } {
  if (level >= 50)
    return {
      key: 'amethyst',
      classes: `border-[#e6d2ff]/70 bg-linear-to-b from-[#7d52c4] to-[#4f2489] text-white ${shine}`,
    }
  if (level >= 30)
    return {
      key: 'ruby',
      classes: `border-[#ffd0d4]/70 bg-linear-to-b from-[#b83a49] to-[#8a1c2a] text-white ${shine}`,
    }
  if (level >= 20)
    return {
      key: 'gold',
      classes: `border-[#fff1c7]/80 bg-linear-to-b from-gold-light to-gold-dark text-ink ${shine}`,
    }
  if (level >= 10)
    return {
      key: 'silver',
      classes: `border-white/80 bg-linear-to-b from-[#eef1f5] to-[#8d96a3] text-black ${shine}`,
    }
  return {
    key: 'bronze',
    classes: `border-[#f2c79a]/70 bg-linear-to-b from-[#8f5a2c] to-[#6e421c] text-white ${shine}`,
  }
}
