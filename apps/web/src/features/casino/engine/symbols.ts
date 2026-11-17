/**
 * How engine slot symbols are drawn (ADR 0026: the engine brings no art). Every symbol number gets its own glyph and
 * colours, the same in every game; they're labels for the maths, not the original games' pictures.
 */
export const SYMBOL_LOOKS = [
  { glyph: '★', from: '#f7d774', to: '#b8860b', ink: '#3b2600' },
  { glyph: '◆', from: '#7fd3ff', to: '#1d6fb8', ink: '#ffffff' },
  { glyph: '7', from: '#ff6b6b', to: '#a4161a', ink: '#ffffff' },
  { glyph: '♛', from: '#c9a3ff', to: '#6a32b8', ink: '#ffffff' },
  { glyph: '☘', from: '#7be495', to: '#1f7a3a', ink: '#ffffff' },
  { glyph: '♥', from: '#ff9ac1', to: '#c2185b', ink: '#ffffff' },
  { glyph: '●', from: '#ffb35c', to: '#c25e00', ink: '#ffffff' },
  { glyph: '▲', from: '#6ee7d8', to: '#0f766e', ink: '#ffffff' },
  { glyph: 'A', from: '#fde68a', to: '#d97706', ink: '#3b2600' },
  { glyph: 'K', from: '#bfdbfe', to: '#2563eb', ink: '#ffffff' },
  { glyph: 'Q', from: '#fbcfe8', to: '#db2777', ink: '#ffffff' },
  { glyph: 'J', from: '#bbf7d0', to: '#16a34a', ink: '#ffffff' },
  { glyph: '10', from: '#e9d5ff', to: '#9333ea', ink: '#ffffff' },
  { glyph: '9', from: '#fed7aa', to: '#ea580c', ink: '#ffffff' },
  { glyph: '♣', from: '#d1d5db', to: '#4b5563', ink: '#ffffff' },
  { glyph: '✦', from: '#a5f3fc', to: '#0891b2', ink: '#ffffff' },
  { glyph: '■', from: '#fca5a5', to: '#b91c1c', ink: '#ffffff' },
  { glyph: '☀', from: '#fef08a', to: '#ca8a04', ink: '#3b2600' },
] as const

export const symbolLook = (sym: number) =>
  SYMBOL_LOOKS[(sym - 1 + SYMBOL_LOOKS.length * 4) % SYMBOL_LOOKS.length]!
