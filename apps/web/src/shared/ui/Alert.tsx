import type { ReactNode } from 'react'

const tones = {
  danger: 'border-danger/40 bg-danger/10 text-fg',
  success: 'border-success/40 bg-success/10 text-fg',
  info: 'border-border bg-emerald-800 text-fg',
} as const

export function Alert({ tone, children }: { tone: keyof typeof tones; children: ReactNode }) {
  return (
    <div
      role={tone === 'danger' ? 'alert' : 'status'}
      className={`rounded-control border px-4 py-3 text-sm ${tones[tone]}`}
    >
      {children}
    </div>
  )
}
