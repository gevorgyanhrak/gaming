/** The ring of a level's avatar frame (ADR 0018), for seats that draw their own avatars. */
export function frameRing(frame: string | null | undefined): string | undefined {
  if (frame === 'gold') return 'var(--color-gold)'
  if (frame === 'legend') return 'conic-gradient(from 0deg,#e8606c,#f7dc8f,#b98cf3,#5fd4c4,#e8606c)'
  return undefined
}
