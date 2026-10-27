import type { ReactNode } from 'react'

export function Card({
  title,
  description,
  children,
  className = '',
}: {
  title?: string
  description?: ReactNode
  children: ReactNode
  className?: string
}) {
  return (
    <section className={`rounded-2xl border border-border bg-surface p-5 sm:p-6 ${className}`}>
      {title ? <h2 className="text-lg font-semibold">{title}</h2> : null}
      {description ? <p className="mt-1 text-sm text-muted">{description}</p> : null}
      <div className={title || description ? 'mt-5' : ''}>{children}</div>
    </section>
  )
}
