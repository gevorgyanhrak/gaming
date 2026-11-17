/**
 * An AI player's face (owner 2026-11-17: natural, diverse illustrated people instead of the drawn generic faces):
 * one of the illustrated portraits in `public/ai` (Avataaars, see its CREDITS.md), picked from the player's name —
 * the same name always gets the same portrait. Drawings, never photos: AI stays recognisable as AI (ADR 0022), and
 * its "AI" tag goes next to its name as everywhere.
 */

/** How many portraits `public/ai` holds (000.svg …). */
export const AI_PORTRAITS = 200

function hash(text: string): number {
  let h = 2166136261
  for (const ch of text) {
    h ^= ch.codePointAt(0) ?? 0
    h = Math.imul(h, 16777619)
  }
  return h >>> 0
}

/** The portrait a face key (an AI's name, or a table's variant of it) shows. */
export const portraitOf = (key: string) => hash(key) % AI_PORTRAITS

export const portraitUrl = (key: string) =>
  `${import.meta.env.BASE_URL}ai/${String(portraitOf(key)).padStart(3, '0')}.svg`

/**
 * Face keys for the AI players at one table, by seat: each AI's own name, unless an earlier seat already shows
 * that portrait — then a variant of its name that lands on a free one. So no two AI players at a table look alike,
 * and each keeps its face for the whole game (the keys depend only on who sits where).
 */
export function tableFaces(
  bots: { seat: number; name: string }[],
): Partial<Record<number, string>> {
  const taken = new Set<number>()
  const faces: Partial<Record<number, string>> = {}
  for (const { seat, name } of [...bots].sort((a, b) => a.seat - b.seat)) {
    let key = name
    for (let n = 2; taken.has(portraitOf(key)) && n < AI_PORTRAITS + 2; n++) key = `${name}#${n}`
    taken.add(portraitOf(key))
    faces[seat] = key
  }
  return faces
}
