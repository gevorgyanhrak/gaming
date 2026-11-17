/**
 * Best matches first: a name that starts with the text, then one with a word that does, then any match; the
 * catalogue's order within. `alt` is a second name to match (the Latin slug, so "durak" finds Դուրակ).
 */
export function rank<T extends { name: string; alt?: string }>(items: T[], needle: string): T[] {
  const score = (text: string) => {
    const lower = text.toLocaleLowerCase()
    if (lower.startsWith(needle)) return 0
    if (lower.split(/[\s-]+/).some((word) => word.startsWith(needle))) return 1
    return lower.includes(needle) ? 2 : -1
  }
  const best = (item: T) => {
    const scores = [score(item.name), item.alt ? score(item.alt) : -1].filter((s) => s >= 0)
    return scores.length > 0 ? Math.min(...scores) : -1
  }
  return items
    .map((item) => ({ item, score: best(item) }))
    .filter((entry) => entry.score >= 0)
    .toSorted((a, b) => a.score - b.score)
    .map((entry) => entry.item)
}
