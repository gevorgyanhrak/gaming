/**
 * An AI player's face (owner 2026-11-15: AI opponents with their own avatars): an illustrated portrait drawn from
 * its name — the same name always gets the same face. Clearly a drawing, never a photo: AI stays recognisable as
 * AI (ADR 0022), and its "AI" tag goes next to its name as everywhere.
 */

const SKIN = ['#f6d3b3', '#eebf97', '#d9a173', '#b97f55', '#8d5a3b', '#f1c9a5']
const HAIR = ['#2b1a12', '#4a2e1c', '#6b3f22', '#1c1c1c', '#8a5a2b', '#b8b2a7', '#5a2d2d']
const BACK = ['#14532d', '#7a1f33', '#1e3a5f', '#4b2c7a', '#7a4a14', '#0e6a66', '#3f3f46']
const SHIRT = ['#e2b24f', '#c0392b', '#2e86c1', '#16a085', '#8e44ad', '#ecf0f1', '#34495e']

function hash(text: string): number {
  let h = 2166136261
  for (const ch of text) {
    h ^= ch.codePointAt(0) ?? 0
    h = Math.imul(h, 16777619)
  }
  return h >>> 0
}

/** Pick from a list by a few bits of the hash, so each trait varies on its own. */
const pick = <T,>(list: readonly T[], h: number, shift: number): T =>
  list[(h >>> shift) % list.length] as T

export function AiFace({ name, className = '' }: { name: string; className?: string }) {
  const h = hash(name)
  const skin = pick(SKIN, h, 0)
  const hair = pick(HAIR, h, 3)
  const back = pick(BACK, h, 6)
  const shirt = pick(SHIRT, h, 9)
  const style = (h >>> 12) % 5 // hair shape
  const extra = (h >>> 15) % 6 // 0 glasses, 1 beard, 2 bow tie, 3 earring, else none
  const smile = (h >>> 18) % 3

  return (
    <svg viewBox="0 0 64 64" aria-hidden="true" className={className}>
      <rect width="64" height="64" fill={back} />
      <circle cx="32" cy="22" r="30" fill="#fff" opacity="0.06" />
      {/* Shoulders */}
      <path d="M8 64c2-12 12-18 24-18s22 6 24 18Z" fill={shirt} />
      {extra === 2 ? <path d="M27 49l5 3 5-3v6l-5-3-5 3Z" fill="#1c1c1c" /> : null}
      {/* Neck and head */}
      <rect x="27" y="38" width="10" height="9" rx="3" fill={skin} />
      <ellipse cx="32" cy="29" rx="13" ry="15" fill={skin} />
      {/* Hair */}
      {style === 0 ? (
        <path d="M19 27c0-11 6-16 13-16s13 5 13 16c-3-5-8-8-13-8s-10 3-13 8Z" fill={hair} />
      ) : null}
      {style === 1 ? (
        <path d="M18 31c-2-14 5-21 14-21s16 7 14 21l-3-9c-4 2-15 2-22-3Z" fill={hair} />
      ) : null}
      {style === 2 ? (
        <path
          d="M17 36c-3-17 4-25 15-25s18 8 15 25c-1-6-2-10-4-13-5 1-16 1-22 0-2 3-3 7-4 13Z"
          fill={hair}
        />
      ) : null}
      {style === 3 ? (
        <path d="M20 22c3-8 9-11 15-10 6 1 10 6 10 12-6-4-16-5-25-2Z" fill={hair} />
      ) : null}
      {style === 4 ? (
        <>
          <path d="M18 30c-1-13 6-19 14-19s15 6 14 19c-4-6-9-9-14-9s-10 3-14 9Z" fill={hair} />
          <path d="M17 30c-2 10-1 18 3 22l2-17Z M47 30c2 10 1 18-3 22l-2-17Z" fill={hair} />
        </>
      ) : null}
      {/* A short beard along the jaw, under the mouth (drawn first so the smile shows). */}
      {extra === 1 ? (
        <path
          d="M21 34c1 8 5 12 11 12s10-4 11-12c-2 3-5 5-11 5s-9-2-11-5Z"
          fill={hair}
          opacity="0.9"
        />
      ) : null}
      {/* Eyes, brows, smile */}
      <circle cx="27" cy="29" r="1.6" fill="#1c1c1c" />
      <circle cx="37" cy="29" r="1.6" fill="#1c1c1c" />
      <path d="M24.5 25.5h5M34.5 25.5h5" stroke={hair} strokeWidth="1.4" strokeLinecap="round" />
      <path
        d={
          smile === 0
            ? 'M28 36c2 2 6 2 8 0'
            : smile === 1
              ? 'M28.5 36.5h7'
              : 'M27.5 35.5c2 3 7 3 9 0'
        }
        stroke="#3d1810"
        strokeWidth="1.5"
        fill="none"
        strokeLinecap="round"
      />
      {extra === 0 ? (
        <g fill="none" stroke="#1c1c1c" strokeWidth="1.2">
          <circle cx="27" cy="29" r="3.6" />
          <circle cx="37" cy="29" r="3.6" />
          <path d="M30.6 29h2.8" />
        </g>
      ) : null}
      {extra === 3 ? <circle cx="19.5" cy="33" r="1.4" fill="#e2b24f" /> : null}
    </svg>
  )
}
