import type { ReactNode } from 'react'
import { Link } from 'react-router'

/**
 * A section's title on the platform's pages: a small lit eyebrow, the title in the display face, and an optional
 * link to the rest ("See all →") on the right.
 */
export function SectionHeading({
  id,
  eyebrow,
  title,
  action,
  as: Tag = 'h2',
}: {
  id?: string
  eyebrow?: string
  title: ReactNode
  action?: { to: string; label: string }
  as?: 'h1' | 'h2'
}) {
  return (
    <div className="mb-5 flex items-end justify-between gap-4">
      <div className="min-w-0">
        {eyebrow ? (
          <p className="mb-1.5 flex items-center gap-2 text-[0.7rem] font-bold tracking-[0.18em] text-violet-400 uppercase">
            <span
              aria-hidden="true"
              className="h-px w-6 bg-linear-to-r from-violet-400 to-transparent"
            />
            {eyebrow}
          </p>
        ) : null}
        <Tag id={id} className="text-2xl font-bold text-fg sm:text-[1.75rem]">
          {title}
        </Tag>
      </div>
      {action ? (
        <Link
          to={action.to}
          className="group inline-flex shrink-0 items-center gap-1.5 rounded-full border border-white/12 px-3.5 py-1.5 text-sm font-semibold text-fg transition-colors hover:border-gold/60 hover:text-gold-light"
        >
          {action.label}
          <span aria-hidden="true" className="transition-transform group-hover:translate-x-0.5">
            →
          </span>
        </Link>
      ) : null}
    </div>
  )
}
