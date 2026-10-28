import type { ButtonHTMLAttributes } from 'react'
import { Link, type LinkProps } from 'react-router'

const variants = {
  primary:
    'border border-white/15 bg-linear-to-b from-accent-light to-accent text-white shadow-lg shadow-black/40 hover:brightness-110',
  /** The main "play" calls to action. */
  gold: 'bg-linear-to-b from-gold-light via-gold to-gold-dark text-black shadow-lg shadow-gold/20 hover:brightness-110',
  secondary:
    'border border-border bg-surface text-fg hover:border-accent/50 hover:bg-surface-raised',
} as const

type Variant = keyof typeof variants

const base =
  'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50'

export function Button({
  variant = 'primary',
  className = '',
  type = 'button',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: Variant }) {
  return <button type={type} className={`${base} ${variants[variant]} ${className}`} {...props} />
}

export function ButtonLink({
  variant = 'primary',
  className = '',
  ...props
}: LinkProps & { variant?: Variant }) {
  return <Link className={`${base} ${variants[variant]} ${className}`} {...props} />
}
