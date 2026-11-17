const sign = (n: number) => Math.sign(n)

/**
 * The step between two backgammon positions (signed counts per point, + for one player, − for the other): one
 * point lost a checker of the mover's colour and one gained one (a hit turns the opponent's blot into the mover's).
 * Points are 1-based like the board's labels. Null for anything else (entering from the bar, bearing off, a new
 * game) — those just appear.
 */
export function pointMove(before: number[], after: number[]): { from: number; to: number } | null {
  const lost: number[] = []
  const gained: number[] = []
  for (let i = 0; i < Math.max(before.length, after.length); i++) {
    const a = before[i] ?? 0
    const b = after[i] ?? 0
    if (a === b) continue
    if (a !== 0 && (b === 0 || sign(b) === sign(a)) && Math.abs(b) < Math.abs(a)) lost.push(i)
    else if (b !== 0 && (sign(a) !== sign(b) || Math.abs(b) > Math.abs(a))) gained.push(i)
    else return null
  }
  if (lost.length !== 1 || gained.length !== 1) return null
  const [from] = lost as [number]
  const [to] = gained as [number]
  return sign(before[from]!) === sign(after[to]!) ? { from: from + 1, to: to + 1 } : null
}
