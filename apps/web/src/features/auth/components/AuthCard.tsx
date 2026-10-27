import type { ReactNode } from 'react'
import { Container } from '@/shared/ui/Container'

/** Centered card used by the sign-in, sign-up and recovery pages. */
export function AuthCard({
  title,
  subtitle,
  children,
  footer,
}: {
  title: string
  subtitle?: ReactNode
  children: ReactNode
  footer?: ReactNode
}) {
  return (
    <Container className="flex justify-center py-12 sm:py-16">
      <title>{title}</title>
      <div className="w-full max-w-md">
        <div className="rounded-2xl border border-border bg-surface p-6 sm:p-8">
          <h1 className="text-2xl font-bold">{title}</h1>
          {subtitle ? <p className="mt-2 text-sm text-muted">{subtitle}</p> : null}
          <div className="mt-6">{children}</div>
        </div>
        {footer ? <div className="mt-6 text-center text-sm text-muted">{footer}</div> : null}
      </div>
    </Container>
  )
}
