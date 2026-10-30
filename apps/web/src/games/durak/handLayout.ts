export interface HandLayout {
  /** Cards per row, top row first. */
  rows: number[]
  /** Horizontal step between cards in each row (px): how much of each card shows. */
  steps: number[]
}

/**
 * Lays a hand out in straight rows that fit `width`: as many cards per row as can each show at
 * least `strip` px, at most three rows (beyond that the strips get narrower), cards spread evenly
 * over the rows. Within a row cards sit 0.72 × card width apart, closer only when the row needs it.
 */
export function handLayout(count: number, width: number, card: number, strip: number): HandLayout {
  if (count === 0) return { rows: [], steps: [] }
  const capacity = (s: number) => Math.max(1, Math.floor((width - card) / s) + 1)

  const rows = Math.min(3, Math.ceil(count / capacity(strip)))

  const base = Math.floor(count / rows)
  const extra = count % rows
  const perRow = Array.from({ length: rows }, (_, i) => base + (i < extra ? 1 : 0))
  const steps = perRow.map((k) => (k <= 1 ? 0 : Math.min(card * 0.72, (width - card) / (k - 1))))
  return { rows: perRow, steps }
}
