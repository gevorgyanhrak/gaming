import type { ComponentProps, ReactNode } from 'react'
import { Link, type LinkProps } from 'react-router'

const gold =
  'bg-linear-to-b from-gold-light to-gold text-ink shadow-gold hover:brightness-105 active:brightness-95'

const variants = {
  /** The one main action of a view: gold. */
  primary: gold,
  /** Same as primary: kept so existing callers don't change. */
  gold,
  /** A second way to play. */
  outline:
    'border border-gold/60 bg-transparent text-accent-text hover:border-gold-light hover:bg-gold/12 active:bg-gold/20',
  /** Everything else. */
  secondary:
    'border border-border-strong/70 bg-emerald-800 text-fg hover:bg-surface-raised active:bg-emerald-900',
  ghost: 'text-fg hover:bg-white/8 active:bg-white/12',
  /** Resign and other destructive confirmations. */
  danger: 'border border-danger/60 bg-danger/14 text-fg hover:bg-danger/24',
} as const

const sizes = {
  sm: 'min-h-11 px-3.5 py-1.5 text-[0.8125rem] sm:min-h-9',
  md: 'min-h-11 px-5 py-2.5 text-sm',
  lg: 'min-h-12 px-7 py-3 text-base',
} as const

type Variant = keyof typeof variants
type Size = keyof typeof sizes

const base =
  'inline-flex items-center justify-center gap-2 rounded-control font-semibold transition-[filter,transform,background-color,border-color,color,box-shadow] duration-150 ease-soft motion-safe:active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-55 disabled:shadow-none aria-busy:pointer-events-none'

function Spinner() {
  return (
    <span
      aria-hidden="true"
      className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none"
    />
  )
}

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  className = '',
  type = 'button',
  disabled,
  children,
  ...props
}: ComponentProps<'button'> & {
  variant?: Variant
  size?: Size
  /** Disabled with a spinner; the label stays. */
  loading?: boolean
  children?: ReactNode
}) {
  return (
    <button
      type={type}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      className={`${base} ${sizes[size]} ${variants[variant]} ${className}`}
      {...props}
    >
      {loading ? <Spinner /> : null}
      {children}
    </button>
  )
}

export function ButtonLink({
  variant = 'primary',
  size = 'md',
  className = '',
  ...props
}: LinkProps & { variant?: Variant; size?: Size }) {
  return <Link className={`${base} ${sizes[size]} ${variants[variant]} ${className}`} {...props} />
}
