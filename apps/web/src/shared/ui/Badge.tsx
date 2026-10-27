import type { ReactNode } from 'react'

const tones = {
  neutral: 'border-border bg-surface-raised text-muted',
  warning: 'border-warning/30 bg-warning/10 text-warning',
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
