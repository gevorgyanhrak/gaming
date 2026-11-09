import type { ReactNode } from 'react'

const tones = {
  neutral: 'border-border-strong/50 bg-emerald-800 text-fg',
  warning: 'border-warning/40 bg-warning/15 text-warning',
  // Tint + light text like Alert: coloured text on a tint would miss AA.
  success: 'border-success/50 bg-success/15 text-fg',
  danger: 'border-danger/50 bg-danger/15 text-fg',
} as const

export function Badge({
  children,
  tone = 'neutral',
}: {
  children: ReactNode
  tone?: keyof typeof tones
}) {
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${tones[tone]}`}
    >
      {children}
    </span>
  )
}
